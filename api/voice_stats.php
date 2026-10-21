<?php
/**
 * Protected Voice Assistant Stats & Live Updates API Endpoint
 * Provides real-time stock levels, sales summaries, and voice adjustments
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 401, 'message' => 'Unauthorized. Active session required.']);
    exit();
}

$authRole = getUserRole();
$authUserId = getUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? 'overview');

// 1. SALES SUMMARY / TODAY'S SALES
if ($action === 'sales_summary' || $action === 'today_sales') {
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $todayStmt = $conn->prepare("SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as total_revenue FROM sales WHERE DATE(created_at) = ?");
    $todayStmt->bind_param("s", $today);
    $todayStmt->execute();
    $todayRes = $todayStmt->get_result()->fetch_assoc();
    $todayStmt->close();

    $mtdStmt = $conn->prepare("SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as total_revenue FROM sales WHERE created_at >= ?");
    $mtdStmt->bind_param("s", $monthStart);
    $mtdStmt->execute();
    $mtdRes = $mtdStmt->get_result()->fetch_assoc();
    $mtdStmt->close();

    $txnCount = (int)($todayRes['txn_count'] ?? 0);
    $totalRev = (float)($todayRes['total_revenue'] ?? 0);
    $avgSale = $txnCount > 0 ? ($totalRev / $txnCount) : 0;

    echo json_encode([
        'status' => 'success',
        'action' => 'sales_summary',
        'today' => [
            'transactions' => $txnCount,
            'revenue' => $totalRev,
            'formatted_revenue' => '₱' . number_format($totalRev, 2),
            'average_sale' => $avgSale,
            'formatted_average' => '₱' . number_format($avgSale, 2),
            'date' => $today
        ],
        'month_to_date' => [
            'transactions' => (int)($mtdRes['txn_count'] ?? 0),
            'revenue' => (float)($mtdRes['total_revenue'] ?? 0),
            'formatted_revenue' => '₱' . number_format((float)($mtdRes['total_revenue'] ?? 0), 2)
        ]
    ]);
    exit();
}

// 2. STOCK SUMMARY / INVENTORY STATUS / LOW STOCK
if ($action === 'stock_summary' || $action === 'inventory_status' || $action === 'low_stock') {
    $invRes = $conn->query("
        SELECT 
            COUNT(*) as total_skus,
            COALESCE(SUM(stock), 0) as total_units,
            SUM(CASE WHEN stock <= reorder_level AND stock > 0 THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
        FROM products
        WHERE is_active = 1
    ");
    $invStats = $invRes ? $invRes->fetch_assoc() : ['total_skus' => 0, 'total_units' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0];

    $lowStockItems = [];
    $lsRes = $conn->query("
        SELECT p.id, p.name, p.sku, p.stock, p.reorder_level, p.price, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.stock <= p.reorder_level
        ORDER BY p.stock ASC
        LIMIT 10
    ");
    if ($lsRes) {
        while ($r = $lsRes->fetch_assoc()) {
            $lowStockItems[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'sku' => $r['sku'],
                'stock' => (int)$r['stock'],
                'reorder_level' => (int)$r['reorder_level'],
                'price' => (float)$r['price'],
                'category' => $r['category_name'] ?? 'General'
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'action' => 'stock_summary',
        'total_skus' => (int)($invStats['total_skus'] ?? 0),
        'total_units' => (int)($invStats['total_units'] ?? 0),
        'low_stock_count' => (int)($invStats['low_stock_count'] ?? 0),
        'out_of_stock_count' => (int)($invStats['out_of_stock_count'] ?? 0),
        'low_stock_items' => $lowStockItems
    ]);
    exit();
}

// 3. CHECK STOCK FOR SPECIFIC PRODUCT
if ($action === 'check_stock') {
    $query = trim($_GET['query'] ?? ($_POST['query'] ?? ''));
    if ($query === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Product query is required']);
        exit();
    }

    $searchTerm = "%$query%";
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.sku, p.brand, p.price, p.cost_price, p.stock, p.reorder_level, p.unit, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ? OR p.barcode LIKE ?)
        ORDER BY CASE WHEN p.name = ? THEN 1 WHEN p.name LIKE ? THEN 2 ELSE 3 END, p.name ASC
        LIMIT 5
    ");
    $exactMatch = $query;
    $prefixMatch = "$query%";
    $stmt->bind_param("ssssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $exactMatch, $prefixMatch);
    $stmt->execute();
    $res = $stmt->get_result();
    $matches = [];
    while ($r = $res->fetch_assoc()) {
        $matches[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'sku' => $r['sku'],
            'brand' => $r['brand'],
            'price' => (float)$r['price'],
            'formatted_price' => '₱' . number_format((float)$r['price'], 2),
            'stock' => (int)$r['stock'],
            'unit' => $r['unit'] ?: 'piece',
            'reorder_level' => (int)$r['reorder_level'],
            'category' => $r['category_name'] ?? 'General',
            'is_low_stock' => (int)$r['stock'] <= (int)$r['reorder_level']
        ];
    }
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'action' => 'check_stock',
        'query' => $query,
        'count' => count($matches),
        'best_match' => $matches[0] ?? null,
        'products' => $matches
    ]);
    exit();
}

// 4. TOP SELLING PRODUCTS
if ($action === 'top_selling' || $action === 'best_sellers') {
    $topRes = $conn->query("
        SELECT p.id, p.name, p.price, SUM(si.quantity) as total_sold, SUM(si.subtotal) as total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        GROUP BY p.id, p.name, p.price
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProducts = [];
    if ($topRes) {
        while ($r = $topRes->fetch_assoc()) {
            $topProducts[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'price' => (float)$r['price'],
                'total_sold' => (int)$r['total_sold'],
                'total_revenue' => (float)$r['total_revenue'],
                'formatted_revenue' => '₱' . number_format((float)$r['total_revenue'], 2)
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'action' => 'top_selling',
        'products' => $topProducts
    ]);
    exit();
}

// 5. ADJUST STOCK VIA VOICE
if ($action === 'adjust_stock') {
    if (!in_array($authRole, ['admin', 'manager', 'inventory_clerk'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Insufficient permissions to adjust stock.']);
        exit();
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $query = trim($_POST['product_name'] ?? '');
    $mode = $_POST['mode'] ?? 'delta'; // 'set' or 'delta'
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Voice command stock adjustment');

    if ($productId === 0 && $query !== '') {
        $pStmt = $conn->prepare("SELECT id, name, stock FROM products WHERE is_active = 1 AND (name LIKE ? OR sku LIKE ?) LIMIT 1");
        $qLike = "%$query%";
        $pStmt->bind_param("ss", $qLike, $qLike);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();
        if ($pRow) {
            $productId = (int)$pRow['id'];
        }
    }

    if ($productId === 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Product could not be identified for adjustment.']);
        exit();
    }

    // Get current stock
    $stmt = $conn->prepare("SELECT id, name, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        exit();
    }

    $previousStock = (int)$product['stock'];
    if ($mode === 'set') {
        $newStock = max(0, $quantity);
        $quantityChange = $newStock - $previousStock;
    } else {
        $quantityChange = $quantity;
        $newStock = max(0, $previousStock + $quantityChange);
    }

    $upStmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $upStmt->bind_param("ii", $newStock, $productId);
    $success = $upStmt->execute();
    $upStmt->close();

    if ($success) {
        // Record adjustment
        $adjType = $quantityChange >= 0 ? 'correction' : 'damage';
        $adjStmt = $conn->prepare("INSERT INTO stock_adjustments (product_id, user_id, adjustment_type, quantity_change, previous_stock, new_stock, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($adjStmt) {
            $adjStmt->bind_param("iisiiss", $productId, $authUserId, $adjType, $quantityChange, $previousStock, $newStock, $reason);
            $adjStmt->execute();
            $adjStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'action' => 'adjust_stock',
            'product_id' => $productId,
            'product_name' => $product['name'],
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'quantity_change' => $quantityChange,
            'message' => "Stock for {$product['name']} updated from $previousStock to $newStock."
        ]);
        exit();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update stock in database.']);
        exit();
    }
}

// 6. DEFAULT COMBINED OVERVIEW / BRIEFING
$today = date('Y-m-d');
$todayStmt = $conn->prepare("SELECT COUNT(*) as txn_count, COALESCE(SUM(total_amount), 0) as total_revenue FROM sales WHERE DATE(created_at) = ?");
$todayStmt->bind_param("s", $today);
$todayStmt->execute();
$todayRes = $todayStmt->get_result()->fetch_assoc();
$todayStmt->close();

$invRes = $conn->query("
    SELECT 
        COUNT(*) as total_skus,
        COALESCE(SUM(stock), 0) as total_units,
        SUM(CASE WHEN stock <= reorder_level AND stock > 0 THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
    FROM products
    WHERE is_active = 1
");
$invStats = $invRes ? $invRes->fetch_assoc() : ['total_skus' => 0, 'total_units' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0];

$todayRev = (float)($todayRes['total_revenue'] ?? 0);
$todayTxn = (int)($todayRes['txn_count'] ?? 0);

echo json_encode([
    'status' => 'success',
    'action' => 'overview',
    'sales_today' => [
        'revenue' => $todayRev,
        'formatted' => '₱' . number_format($todayRev, 2),
        'transactions' => $todayTxn
    ],
    'inventory' => [
        'total_skus' => (int)($invStats['total_skus'] ?? 0),
        'total_units' => (int)($invStats['total_units'] ?? 0),
        'low_stock' => (int)($invStats['low_stock_count'] ?? 0),
        'out_of_stock' => (int)($invStats['out_of_stock_count'] ?? 0)
    ],
    'timestamp' => date('c')
]);
