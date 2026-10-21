<?php
/**
 * Protected Inventory & Premix API Endpoint
 * Accepts Session Auth OR X-API-Key Header
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    $authRole = getUserRole();
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 401, 'message' => 'Unauthorized. Active session required.']);
    exit();
}

if (!in_array($authRole, ['admin', 'manager', 'inventory_clerk'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 403, 'message' => 'Forbidden. Insufficient endpoint permissions.']);
    exit();
}

// Return inventory stock levels
$premixes = [];
$pRes = $conn->query("SELECT id, name, sku, stock_kg, reorder_level_kg FROM premixes WHERE active = 1 ORDER BY name ASC");
if ($pRes) {
    while ($row = $pRes->fetch_assoc()) {
        $premixes[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'stock_kg' => (float)$row['stock_kg'],
            'reorder_level_kg' => (float)$row['reorder_level_kg']
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'auth_role' => $authRole,
    'premix_count' => count($premixes),
    'premixes' => $premixes,
    'timestamp' => date('c')
]);
