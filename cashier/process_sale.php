<?php
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

requirePermission('process_sale');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty. Please add items to checkout.']);
    exit();
}

try {
    $conn->begin_transaction();

    $cashierId = getUserId();
    $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    $subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : 0.00;
    $totalAmount = (float)$data['total'];
    $payment = (float)$data['payment'];
    $changeAmount = (float)($data['change'] ?? max(0, $payment - $totalAmount));
    
    // Tax breakdown
    $vatableSales = isset($data['vatable_sales']) ? (float)$data['vatable_sales'] : round($totalAmount / 1.12, 2);
    $vatAmount = isset($data['vat_amount']) ? (float)$data['vat_amount'] : round($totalAmount - $vatableSales, 2);
    $nonVatableSales = isset($data['non_vatable_sales']) ? (float)$data['non_vatable_sales'] : 0.00;
    
    // Cash payments only validation
    if (isset($data['payment_method']) && $data['payment_method'] !== 'cash') {
        echo json_encode(['success' => false, 'message' => 'Cash payments only. GCash, Maya, and electronic payments are not accepted.']);
        exit();
    }

    if ($payment < $totalAmount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient cash tendered. Total payable: ₱' . number_format($totalAmount, 2) . ', Tendered: ₱' . number_format($payment, 2)]);
        exit();
    }

    $paymentMethod = 'cash';
    $paymentRef = '';

    // Discount details
    $discountType = in_array($data['discount_type'] ?? 'none', ['none', 'senior_pwd', 'member', 'promo', 'custom']) ? $data['discount_type'] : 'none';
    $discountAmount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0.00;
    $discountReason = trim($data['discount_reason'] ?? '');

    // Insert sale record
    $stmt = $conn->prepare("INSERT INTO sales (
        cashier_id, customer_id, subtotal, total_amount, vatable_sales, vat_amount, non_vatable_sales,
        payment, change_amount, payment_method, payment_reference, discount_type, discount_amount, discount_reason
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("Failed to prepare sales insert: " . $conn->error);
    }

    $stmt->bind_param(
        "iidddddddsssds",
        $cashierId, $customerId, $subtotal, $totalAmount, $vatableSales, $vatAmount, $nonVatableSales,
        $payment, $changeAmount, $paymentMethod, $paymentRef, $discountType, $discountAmount, $discountReason
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to record sale: " . $stmt->error);
    }

    $saleId = $conn->insert_id;
    $stmt->close();

    // Insert sale items, update stock & log inventory
    $itemStmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, cost_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $logStmt = $conn->prepare("INSERT INTO inventory_logs (product_id, user_id, stock_change, operation, notes) VALUES (?, ?, ?, 'subtract', ?)");

    foreach ($data['items'] as $item) {
        $productId = (int)$item['id'];
        $qty = (int)$item['quantity'];
        $price = (float)$item['price'];
        $itemSubtotal = $price * $qty;

        // Fetch cost price for profit margin analysis
        $costPrice = 0.00;
        $costRes = $conn->query("SELECT cost_price FROM products WHERE id = $productId");
        if ($costRes && $cRow = $costRes->fetch_assoc()) {
            $costPrice = (float)$cRow['cost_price'];
        }

        if ($itemStmt) {
            $itemStmt->bind_param("iiiddd", $saleId, $productId, $qty, $price, $costPrice, $itemSubtotal);
            $itemStmt->execute();
        }

        // Deduct inventory
        if ($stockStmt) {
            $stockStmt->bind_param("ii", $qty, $productId);
            $stockStmt->execute();
        }

        // Log inventory movement
        if ($logStmt) {
            $logNote = "POS Checkout Sale #" . $saleId;
            $logStmt->bind_param("iiis", $productId, $cashierId, $qty, $logNote);
            $logStmt->execute();
        }
    }

    if ($itemStmt) $itemStmt->close();
    if ($stockStmt) $stockStmt->close();
    if ($logStmt) $logStmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sale completed successfully',
        'sale_id' => $saleId,
        'total' => $totalAmount,
        'change' => $changeAmount,
        'payment_method' => $paymentMethod
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
