<?php
/**
 * Context-Aware Smart Barcode Management API Service
 * 
 * Centralized endpoint for barcode lookups, duplicate detection,
 * physical stock count variance logging, incoming delivery receiving,
 * and session scan history.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is authenticated
if (!isLoggedIn() && php_sapi_name() !== 'cli') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$currentUserRole = getUserRole();
$currentUserId = getUserId();
$currentUsername = getUsername();

$action = trim($_GET['action'] ?? $_POST['action'] ?? 'lookup');

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?: $_POST;

/**
 * 1. Barcode Lookup & Identification
 */
if ($action === 'lookup') {
    $barcode = trim($inputData['barcode'] ?? $_GET['barcode'] ?? '');
    $context = trim($inputData['context'] ?? $_GET['context'] ?? 'general');

    if ($barcode === '') {
        echo json_encode(['success' => false, 'error' => 'Barcode string is required.']);
        exit();
    }

    // Search by exact barcode, then fallback to SKU
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, s.name as supplier_name,
               IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.barcode = ? OR p.sku = ?
        LIMIT 1
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database query failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("ss", $barcode, $barcode);
    $stmt->execute();
    $res = $stmt->get_result();
    $product = $res->fetch_assoc();
    $stmt->close();

    if (!$product) {
        // Record scan history as unregistered attempt
        if (!isset($_SESSION['barcode_scan_history'])) {
            $_SESSION['barcode_scan_history'] = [];
        }
        array_unshift($_SESSION['barcode_scan_history'], [
            'barcode' => $barcode,
            'status' => 'unregistered',
            'product_name' => 'Unregistered Barcode',
            'timestamp' => date('H:i:s')
        ]);
        $_SESSION['barcode_scan_history'] = array_slice($_SESSION['barcode_scan_history'], 0, 20);

        echo json_encode([
            'success' => false,
            'error_code' => 'UNREGISTERED_BARCODE',
            'error' => "Barcode '$barcode' is not associated with any product in the catalog.",
            'scanned_barcode' => $barcode,
            'can_register' => in_array($currentUserRole, ['admin', 'manager']),
            'suggested_action' => 'register_prompt'
        ]);
        exit();
    }

    $stock = (int)$product['stock'];
    $isOutOfStock = ($stock <= 0);
    $isLowStock = ($stock > 0 && $stock <= (float)$product['reorder_level']);

    // Check sales velocity (30d ADS)
    $adsStmt = $conn->prepare("
        SELECT IFNULL(SUM(si.quantity), 0) as total_sold
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE si.product_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $ads = 0.0;
    if ($adsStmt) {
        $adsStmt->bind_param("i", $product['id']);
        $adsStmt->execute();
        $adsRow = $adsStmt->get_result()->fetch_assoc();
        $adsStmt->close();
        $ads = round((float)($adsRow['total_sold'] ?? 0) / 30, 2);
    }

    $dos = $ads > 0 ? round($stock / $ads, 1) : ($stock == 0 ? 0 : 999);

    // Save to session scan history
    if (!isset($_SESSION['barcode_scan_history'])) {
        $_SESSION['barcode_scan_history'] = [];
    }
    array_unshift($_SESSION['barcode_scan_history'], [
        'product_id' => (int)$product['id'],
        'product_name' => $product['name'],
        'barcode' => $product['barcode'] ?: $barcode,
        'sku' => $product['sku'],
        'price' => (float)$product['price'],
        'stock' => $stock,
        'context' => $context,
        'timestamp' => date('H:i:s')
    ]);
    $_SESSION['barcode_scan_history'] = array_slice($_SESSION['barcode_scan_history'], 0, 20);

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'barcode' => $product['barcode'] ?: $barcode,
            'brand' => $product['brand'] ?? '',
            'category_id' => (int)$product['category_id'],
            'category_name' => $product['category_name'] ?? 'Uncategorized',
            'supplier_id' => (int)$product['supplier_id'],
            'supplier_name' => $product['supplier_name'] ?? 'General Supplier',
            'cost_price' => (float)$product['cost_price'],
            'price' => (float)$product['price'],
            'stock' => $stock,
            'unit' => $product['unit'] ?: 'pc',
            'reorder_level' => (float)$product['reorder_level'],
            'image' => (!empty($product['image_path']) && file_exists(__DIR__ . '/../' . $product['image_path'])) ? $product['image_path'] : ((!empty($product['image']) && file_exists(__DIR__ . '/../' . $product['image'])) ? $product['image'] : 'assets/images/triple8_logo.png'),
            'is_active' => (bool)($product['is_active'] ?? 1),
            'ads_30d' => $ads,
            'days_of_supply' => $dos,
            'is_out_of_stock' => $isOutOfStock,
            'is_low_stock' => $isLowStock
        ],
        'context' => $context,
        'workflow_hint' => match ($context) {
            'pos' => $isOutOfStock ? 'Item is out of stock.' : 'Ready to add to POS cart.',
            'inventory' => 'Inventory diagnostic loaded.',
            'stock_counting' => 'Enter physical counted units.',
            'receiving' => 'Specify incoming delivery quantity.',
            'products' => 'Ready for editing.',
            'transactions' => 'Filtering transaction history.',
            default => 'Product identified.'
        }
    ]);
    exit();
}

/**
 * 2. Barcode Duplicate Protection Check
 */
if ($action === 'validate_unique') {
    $barcode = trim($inputData['barcode'] ?? '');
    $excludeId = (int)($inputData['exclude_id'] ?? 0);

    if ($barcode === '') {
        echo json_encode(['success' => true, 'is_unique' => true]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, name, sku, barcode 
        FROM products 
        WHERE barcode = ? AND id != ?
        LIMIT 1
    ");
    $stmt->bind_param("si", $barcode, $excludeId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'is_unique' => false,
            'conflict' => [
                'id' => (int)$existing['id'],
                'name' => $existing['name'],
                'sku' => $existing['sku'],
                'barcode' => $existing['barcode']
            ],
            'message' => "⚠ Duplicate Barcode: '$barcode' is already assigned to {$existing['name']}."
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'is_unique' => true,
            'message' => "✓ Barcode '$barcode' is available."
        ]);
    }
    exit();
}

/**
 * 3. Physical Stock Counting & Variance Audit
 */
if ($action === 'stock_count_log') {
    if (!in_array($currentUserRole, ['admin', 'manager', 'inventory_staff', 'cashier'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit();
    }

    $productId = (int)($inputData['product_id'] ?? 0);
    $physicalCount = (int)($inputData['physical_count'] ?? 0);
    $applyCorrection = !empty($inputData['apply_correction']);
    $notes = trim($inputData['notes'] ?? 'Physical Stocktake Audit via Barcode Scan');

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Valid product ID is required.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, name, sku, stock, unit FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prod) {
        echo json_encode(['success' => false, 'error' => 'Product not found.']);
        exit();
    }

    $expectedStock = (int)$prod['stock'];
    $variance = $physicalCount - $expectedStock;

    if ($applyCorrection && $variance !== 0) {
        // Record adjustment
        $adjStmt = $conn->prepare("
            INSERT INTO stock_adjustments (product_id, user_id, adjustment_type, quantity_change, previous_stock, new_stock, reason, created_at)
            VALUES (?, ?, 'stocktake', ?, ?, ?, ?, NOW())
        ");
        if ($adjStmt) {
            $adjStmt->bind_param("iiiiis", $productId, $currentUserId, $variance, $expectedStock, $physicalCount, $notes);
            $adjStmt->execute();
            $adjStmt->close();
        }

        // Update product stock
        $upd = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $upd->bind_param("ii", $physicalCount, $productId);
        $upd->execute();
        $upd->close();
    }

    echo json_encode([
        'success' => true,
        'product_id' => $productId,
        'product_name' => $prod['name'],
        'expected_stock' => $expectedStock,
        'physical_count' => $physicalCount,
        'variance' => $variance,
        'is_matched' => ($variance === 0),
        'corrected' => $applyCorrection,
        'unit' => $prod['unit'] ?: 'pc',
        'message' => $variance === 0 
            ? "✓ Perfect Match: Physical count matches system stock ({$expectedStock} {$prod['unit']})."
            : ($variance > 0 
                ? "⚠️ Positive Discrepancy: +{$variance} {$prod['unit']} over expected stock." 
                : "🚨 Negative Discrepancy: {$variance} {$prod['unit']} (Shrinkage/Loss).")
    ]);
    exit();
}

/**
 * 4. Delivery / Receiving Workflow
 */
if ($action === 'receive_delivery') {
    if (!in_array($currentUserRole, ['admin', 'manager', 'inventory_staff'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied for stock receiving.']);
        exit();
    }

    $productId = (int)($inputData['product_id'] ?? 0);
    $receiveQty = (int)($inputData['received_quantity'] ?? 0);
    $invoiceRef = trim($inputData['reference_invoice'] ?? 'Inward Delivery');
    $supplierId = (int)($inputData['supplier_id'] ?? 0);

    if ($productId <= 0 || $receiveQty <= 0) {
        echo json_encode(['success' => false, 'error' => 'Valid product ID and positive receive quantity are required.']);
        exit();
    }

    $pStmt = $conn->prepare("SELECT id, name, sku, stock, cost_price, unit FROM products WHERE id = ?");
    $pStmt->bind_param("i", $productId);
    $pStmt->execute();
    $prod = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();

    if (!$prod) {
        echo json_encode(['success' => false, 'error' => 'Product not found.']);
        exit();
    }

    $prevStock = (int)$prod['stock'];
    $newStock = $prevStock + $receiveQty;

    // Log adjustment
    $adjStmt = $conn->prepare("
        INSERT INTO stock_adjustments (product_id, user_id, adjustment_type, quantity_change, previous_stock, new_stock, reason, created_at)
        VALUES (?, ?, 'correction', ?, ?, ?, ?, NOW())
    ");
    $reason = "Inward Delivery Received (Ref: $invoiceRef) via Smart Barcode";
    if ($adjStmt) {
        $adjStmt->bind_param("iiiiis", $productId, $currentUserId, $receiveQty, $prevStock, $newStock, $reason);
        $adjStmt->execute();
        $adjStmt->close();
    }

    // Update stock
    $upStmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $upStmt->bind_param("ii", $newStock, $productId);
    $upStmt->execute();
    $upStmt->close();

    echo json_encode([
        'success' => true,
        'product_id' => $productId,
        'product_name' => $prod['name'],
        'received_quantity' => $receiveQty,
        'previous_stock' => $prevStock,
        'new_stock' => $newStock,
        'unit' => $prod['unit'] ?: 'pc',
        'message' => "✓ Received +{$receiveQty} {$prod['unit']} for {$prod['name']}. New stock: {$newStock} {$prod['unit']}."
    ]);
    exit();
}

/**
 * 5. Session Scan History
 */
if ($action === 'scan_history') {
    echo json_encode([
        'success' => true,
        'history' => $_SESSION['barcode_scan_history'] ?? []
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid barcode service action.']);
exit();
