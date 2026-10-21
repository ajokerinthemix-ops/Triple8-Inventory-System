<?php
/**
 * Protected POS API Endpoint
 * Accepts Session Auth OR X-API-Key Header
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Method must be GET or POST
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Authenticate Request (Active Session)
if (isLoggedIn()) {
    $authRole = getUserRole();
    $authUserId = getUserId();
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 401, 'message' => 'Unauthorized. Active session required.']);
    exit();
}

// 2. Permission Check for POS Operations
if (!in_array($authRole, ['admin', 'manager', 'cashier'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 403, 'message' => 'Forbidden. Insufficient endpoint permissions.']);
    exit();
}

// 3. Handle GET / POST operations
if ($method === 'GET') {
    // Return catalogue for POS terminal
    $products = [];
    $res = $conn->query("SELECT id, name, price, stock, barcode, item_type FROM products WHERE stock > 0 ORDER BY name ASC LIMIT 100");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['stock'],
                'barcode' => $row['barcode'],
                'item_type' => $row['item_type']
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'auth_role' => $authRole,
        'count' => count($products),
        'products' => $products,
        'timestamp' => date('c')
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['status' => 'error', 'code' => 405, 'message' => 'Method Not Allowed']);
