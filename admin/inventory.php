<?php
$pageTitle = 'Inventory Overview & Stock Control';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';
require_once '../includes/categories_helper.php';

requirePermission('inventory');

$message = '';
$currentUserId = $_SESSION['user_id'] ?? 1;

// Handle Stock Adjustments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adjust_stock'])) {
        $productId = (int)($_POST['product_id'] ?? 0);
        $mode = $_POST['mode'] ?? 'delta'; // 'delta' (+/-) or 'set' (exact new stock)
        $qtyInput = (int)($_POST['quantity'] ?? 0);
        $adjustmentType = $_POST['adjustment_type'] ?? 'correction';
        $reason = trim($_POST['reason'] ?? '');

        // Allowed adjustment types matching ENUM in database
        $allowedTypes = ['damage', 'expired', 'shrinkage', 'stocktake', 'correction'];
        if (!in_array($adjustmentType, $allowedTypes)) {
            $adjustmentType = 'correction';
        }

        // Fetch current product stock
        $stmt = $conn->prepare("SELECT id, name, stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        $product = $res->fetch_assoc();
        $stmt->close();

        if (!$product) {
            $message = '<div class="alert alert-danger py-2 small"><i class="fas fa-exclamation-circle me-1"></i> Product not found.</div>';
        } else {
            $previousStock = (int)$product['stock'];
            if ($mode === 'set') {
                $newStock = max(0, $qtyInput);
                $quantityChange = $newStock - $previousStock;
            } else {
                $quantityChange = $qtyInput;
                $newStock = max(0, $previousStock + $quantityChange);
            }

            // Update product stock
            $upStmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $upStmt->bind_param("ii", $newStock, $productId);
            
            if ($upStmt->execute()) {
                // Record into stock_adjustments table
                $adjStmt = $conn->prepare("INSERT INTO stock_adjustments (product_id, user_id, adjustment_type, quantity_change, previous_stock, new_stock, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($adjStmt) {
                    $adjStmt->bind_param("iisiiss", $productId, $currentUserId, $adjustmentType, $quantityChange, $previousStock, $newStock, $reason);
                    $adjStmt->execute();
                    $adjStmt->close();
                }

                // Also record to inventory_logs if table exists
                $logOp = $quantityChange >= 0 ? 'add' : 'subtract';
                $absChange = abs($quantityChange);
                $logStmt = $conn->prepare("INSERT INTO inventory_logs (product_id, user_id, stock_change, operation, notes) VALUES (?, ?, ?, ?, ?)");
                if ($logStmt) {
                    $note = "Adjustment ($adjustmentType): " . ($reason ?: 'No remarks');
                    $logStmt->bind_param("iiiss", $productId, $currentUserId, $absChange, $logOp, $note);
                    @$logStmt->execute();
                    $logStmt->close();
                }

                $message = '<div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-1"></i> Stock for <strong>' . htmlspecialchars($product['name']) . '</strong> updated from ' . $previousStock . ' to ' . $newStock . '.</div>';
            } else {
                $message = '<div class="alert alert-danger py-2 small">Error updating stock: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $upStmt->close();
        }
    } elseif (isset($_POST['update_thresholds'])) {
        $productId = (int)($_POST['product_id'] ?? 0);
        $reorderLevel = (float)($_POST['reorder_level'] ?? 10);
        $costPrice = (float)($_POST['cost_price'] ?? 0.00);

        if ($productId > 0) {
            $stmt = $conn->prepare("UPDATE products SET reorder_level = ?, cost_price = ? WHERE id = ?");
            $stmt->bind_param("ddi", $reorderLevel, $costPrice, $productId);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-1"></i> Reorder level & cost price updated successfully.</div>';
            } else {
                $message = '<div class="alert alert-danger py-2 small">Error: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $stmt->close();
        }
    }
}

// Export CSV handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Product Name', 'Category', 'Cost Price', 'Retail Price', 'Stock Level', 'Unit', 'Reorder Level', 'Stock Status', 'Total Cost Valuation']);

    $expSql = "
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC
    ";
    $expRes = $conn->query($expSql);
    if ($expRes) {
        while ($row = $expRes->fetch_assoc()) {
            $status = 'In Stock';
            if ($row['stock'] <= 0) {
                $status = 'Out of Stock';
            } elseif ($row['stock'] <= $row['reorder_level']) {
                $status = 'Low Stock';
            }
            $costVal = $row['stock'] * ($row['cost_price'] ?? 0);
            fputcsv($output, [
                $row['name'],
                $row['category_name'] ?? 'Uncategorized',
                number_format((float)$row['cost_price'], 2, '.', ''),
                number_format((float)$row['price'], 2, '.', ''),
                $row['stock'],
                $row['unit'] ?? 'pc',
                $row['reorder_level'],
                $status,
                number_format($costVal, 2, '.', '')
            ]);
        }
    }
    fclose($output);
    exit();
}

// Fetch categories for dropdown filter
$categoriesRaw = [];
$cRes = $conn->query("SELECT * FROM categories ORDER BY parent_id IS NOT NULL, display_order ASC, name ASC");
if ($cRes) {
    while ($r = $cRes->fetch_assoc()) $categoriesRaw[] = $r;
}
$categoryTree = buildCategoryHierarchy($categoriesRaw);
$flatCategories = flattenCategoryTree($categoryTree);

// Filters & Query builder
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stockFilter = $_GET['status'] ?? 'all'; // 'all', 'low', 'out', 'in'
$search = trim($_GET['search'] ?? '');
$currentTab = $_GET['tab'] ?? 'inventory'; // 'inventory' or 'adjustments'

$where = [];
if ($categoryFilter > 0) {
    $descendantIds = getCategoryAndDescendantIds($conn, $categoryFilter);
    if (!empty($descendantIds)) {
        $where[] = "p.category_id IN (" . implode(',', $descendantIds) . ")";
    } else {
        $where[] = "p.category_id = $categoryFilter";
    }
}
if (!empty($search)) {
    $escaped = $conn->real_escape_string($search);
    $where[] = "(p.name LIKE '%$escaped%' OR p.barcode LIKE '%$escaped%' OR p.sku LIKE '%$escaped%')";
}
if ($stockFilter === 'low') {
    $where[] = "p.stock > 0 AND p.stock <= p.reorder_level";
} elseif ($stockFilter === 'out') {
    $where[] = "p.stock <= 0";
} elseif ($stockFilter === 'in') {
    $where[] = "p.stock > p.reorder_level";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Summary Metrics
$totalProducts = 0;
$totalUnits = 0;
$lowStockCount = 0;
$outOfStockCount = 0;
$totalCostValuation = 0.00;
$totalRetailValuation = 0.00;

$summaryRes = $conn->query("
    SELECT 
        COUNT(*) as total_items,
        COALESCE(SUM(stock), 0) as total_units,
        COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
        COALESCE(SUM(CASE WHEN stock > 0 AND stock <= reorder_level THEN 1 ELSE 0 END), 0) as low_stock,
        COALESCE(SUM(stock * cost_price), 0) as total_cost_val,
        COALESCE(SUM(stock * price), 0) as total_retail_val
    FROM products
");
if ($summaryRes) {
    $sRow = $summaryRes->fetch_assoc();
    $totalProducts = (int)$sRow['total_items'];
    $totalUnits = (int)$sRow['total_units'];
    $outOfStockCount = (int)$sRow['out_of_stock'];
    $lowStockCount = (int)$sRow['low_stock'];
    $totalCostValuation = (float)$sRow['total_cost_val'];
    $totalRetailValuation = (float)$sRow['total_retail_val'];
}

// Fetch Products
$products = [];
$prodRes = $conn->query("
    SELECT p.*, c.name as category_name, c.icon as category_icon, c.path as category_path
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereSql
    ORDER BY 
        CASE 
            WHEN p.stock <= 0 THEN 1
            WHEN p.stock <= p.reorder_level THEN 2
            ELSE 3
        END,
        p.name ASC
");
if ($prodRes) {
    while ($r = $prodRes->fetch_assoc()) $products[] = $r;
}

// Fetch Recent Adjustments
$adjustments = [];
$adjRes = $conn->query("
    SELECT sa.*, p.name as product_name, p.sku as product_sku, p.unit as product_unit, u.username as adjusted_by_user
    FROM stock_adjustments sa
    LEFT JOIN products p ON sa.product_id = p.id
    LEFT JOIN users u ON sa.user_id = u.id
    ORDER BY sa.created_at DESC
    LIMIT 100
");
if ($adjRes) {
    while ($r = $adjRes->fetch_assoc()) $adjustments[] = $r;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .badge-status-in { background-color: #d1e7dd; color: #0f5132; }
        .badge-status-low { background-color: #fff3cd; color: #664d03; }
        .badge-status-out { background-color: #f8d7da; color: #842029; }
        .type-badge-damage { background-color: #dc3545; color: #fff; }
        .type-badge-expired { background-color: #fd7e14; color: #fff; }
        .type-badge-shrinkage { background-color: #6f42c1; color: #fff; }
        .type-badge-stocktake { background-color: #0d6efd; color: #fff; }
        .type-badge-correction { background-color: #198754; color: #fff; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <!-- Page Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-warehouse text-primary me-2"></i> Inventory Overview & Stock Control</h2>
                    <p class="text-muted small mb-0">Monitor stock levels, reorder thresholds, inventory valuation, and perform stock adjustments.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#quickAdjustModal">
                        <i class="fas fa-sliders me-1"></i> Stock Adjustment
                    </button>
                    <a href="inventory.php?export=csv" class="btn btn-outline-success fw-bold">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </a>
                </div>
            </div>

            <?php if (!empty($message)) echo $message; ?>

            <!-- KPI Metric Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Total Catalog Items</span>
                                <h3 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($totalProducts); ?></h3>
                                <div class="small text-muted mt-1"><?php echo number_format($totalUnits); ?> total units in stock</div>
                            </div>
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                                <i class="fas fa-boxes-stacked fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Cost Valuation</span>
                                <h3 class="fw-bold mb-0 text-primary mt-1">₱<?php echo number_format($totalCostValuation, 2); ?></h3>
                                <div class="small text-muted mt-1">Retail Value: ₱<?php echo number_format($totalRetailValuation, 2); ?></div>
                            </div>
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                                <i class="fas fa-coins fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Low Stock Warnings</span>
                                <h3 class="fw-bold mb-0 text-warning mt-1"><?php echo number_format($lowStockCount); ?></h3>
                                <div class="small text-muted mt-1">Items at/below reorder point</div>
                            </div>
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                                <i class="fas fa-triangle-exclamation fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Out of Stock</span>
                                <h3 class="fw-bold mb-0 text-danger mt-1"><?php echo number_format($outOfStockCount); ?></h3>
                                <div class="small text-muted mt-1">Critical stockout items</div>
                            </div>
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                                <i class="fas fa-box-open fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4 border-bottom">
                <li class="nav-item">
                    <a class="nav-link fw-bold <?php echo $currentTab === 'inventory' ? 'active' : ''; ?>" href="inventory.php?tab=inventory">
                        <i class="fas fa-list-check me-1"></i> Stock Levels & Valuation (<?php echo count($products); ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold <?php echo $currentTab === 'adjustments' ? 'active' : ''; ?>" href="inventory.php?tab=adjustments">
                        <i class="fas fa-clock-rotate-left me-1"></i> Stock Movement & Adjustment Log (<?php echo count($adjustments); ?>)
                    </a>
                </li>
            </ul>

            <?php if ($currentTab === 'inventory'): ?>
                <!-- Filter & Search Toolbar -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-center">
                            <input type="hidden" name="tab" value="inventory">
                            
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search product name or SKU..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <select name="category" class="form-select form-select-sm">
                                    <option value="0">All Departments & Categories</option>
                                    <?php foreach ($flatCategories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo str_repeat('&nbsp;&nbsp;', (int)$cat['depth']); ?>
                                            <?php echo ((int)$cat['depth'] > 0 ? '└─ ' : ''); ?>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all" <?php echo $stockFilter === 'all' ? 'selected' : ''; ?>>All Stock Statuses</option>
                                    <option value="low" <?php echo $stockFilter === 'low' ? 'selected' : ''; ?>>⚠️ Low Stock Only (<= Reorder)</option>
                                    <option value="out" <?php echo $stockFilter === 'out' ? 'selected' : ''; ?>>❌ Out of Stock Only (0)</option>
                                    <option value="in" <?php echo $stockFilter === 'in' ? 'selected' : ''; ?>>✅ Healthy Stock Only</option>
                                </select>
                            </div>

                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                                <?php if (!empty($search) || $categoryFilter > 0 || $stockFilter !== 'all'): ?>
                                    <a href="inventory.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                                        <i class="fas fa-undo"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Inventory Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes-stacked text-primary me-2"></i> Inventory Items</h6>
                        <span class="badge bg-light text-dark border"><?php echo count($products); ?> Records Displayed</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Product Name & Department</th>
                                        <th class="text-end">Cost Price</th>
                                        <th class="text-end">Retail Price</th>
                                        <th class="text-center">Current Stock</th>
                                        <th class="text-center">Reorder Pt</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end">Cost Valuation</th>
                                        <th class="text-center pe-3" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fas fa-box-open fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                                No inventory items match your selected filters.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $p): 
                                            $isOut = (int)$p['stock'] <= 0;
                                            $isLow = !$isOut && (int)$p['stock'] <= (float)$p['reorder_level'];
                                            $costVal = (float)$p['stock'] * (float)($p['cost_price'] ?? 0);
                                        ?>
                                            <tr class="<?php echo $isOut ? 'table-danger bg-opacity-25' : ($isLow ? 'table-warning bg-opacity-25' : ''); ?>"
                                                data-product-id="<?php echo (int)$p['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($p['name']); ?>"
                                                style="cursor: pointer;">
                                                <td class="ps-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="../<?php echo htmlspecialchars((!empty($p['image_path']) && file_exists(__DIR__ . '/../' . $p['image_path'])) ? $p['image_path'] : (!empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image']) ? $p['image'] : 'assets/images/triple8_logo.png')); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" style="width: 38px; height: 38px; border-radius: 6px; object-fit: contain; background: #f8fafc; border: 1px solid #e2e8f0; flex-shrink: 0;" onerror="this.onerror=null; this.src='../assets/images/triple8_logo.png';">
                                                        <div>
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></div>
                                                            <div class="d-flex align-items-center gap-1 mt-1">
                                                                <?php 
                                                                $pIconHtml = ($p['category_name'] !== 'Locks & Security' && !empty($p['category_icon'])) ? getCategorySvgIcon($p['category_icon'], '', 14) : '';
                                                                if (!empty($pIconHtml)): ?>
                                                                    <span class="text-primary" title="<?php echo htmlspecialchars($p['category_name'] ?? ''); ?>">
                                                                        <?php echo $pIconHtml; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size: 0.72rem;">
                                                                    <?php echo htmlspecialchars($p['category_name'] ?? 'General'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end text-muted small">₱<?php echo number_format((float)($p['cost_price'] ?? 0), 2); ?></td>
                                                <td class="text-end fw-semibold">₱<?php echo number_format((float)$p['price'], 2); ?></td>
                                                <td class="text-center">
                                                    <span class="fs-6 fw-bold <?php echo $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success'); ?>">
                                                        <?php echo $p['stock']; ?>
                                                    </span>
                                                    <span class="text-muted small"><?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?></span>
                                                </td>
                                                <td class="text-center text-muted small fw-bold"><?php echo $p['reorder_level']; ?></td>
                                                <td class="text-center">
                                                    <?php if ($isOut): ?>
                                                        <span class="badge badge-status-out border border-danger">Out of Stock</span>
                                                    <?php elseif ($isLow): ?>
                                                        <span class="badge badge-status-low border border-warning">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-status-in border border-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold text-dark font-monospace">₱<?php echo number_format($costVal, 2); ?></td>
                                                <td class="text-center pe-3">
                                                    <button type="button" class="btn btn-sm btn-primary py-1 px-2" 
                                                            onclick="openAdjustModal(<?php echo htmlspecialchars(json_encode($p)); ?>)"
                                                            title="Adjust Stock">
                                                        <i class="fas fa-sliders"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" 
                                                            onclick="openThresholdModal(<?php echo htmlspecialchars(json_encode($p)); ?>)"
                                                            title="Edit Threshold / Cost">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <!-- Stock Movement & Adjustment Audit Log Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-clock-rotate-left text-primary me-2"></i> Recent Stock Adjustments (Last 100 entries)</h6>
                        <span class="badge bg-light text-dark border"><?php echo count($adjustments); ?> Logs</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Date & Time</th>
                                        <th>Product Name</th>
                                        <th>Type</th>
                                        <th class="text-center">Change</th>
                                        <th class="text-center">Prior Stock</th>
                                        <th class="text-center">New Stock</th>
                                        <th>Adjusted By</th>
                                        <th class="pe-3">Reason / Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($adjustments)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                                No stock adjustments have been recorded yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($adjustments as $adj): 
                                            $typeClass = 'type-badge-' . ($adj['adjustment_type'] ?? 'correction');
                                            $change = (int)$adj['quantity_change'];
                                        ?>
                                            <tr>
                                                <td class="ps-3 text-muted small"><?php echo date('M d, Y h:i A', strtotime($adj['created_at'])); ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($adj['product_name'] ?? 'Unknown Item'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $typeClass; ?>">
                                                        <?php echo strtoupper($adj['adjustment_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center fw-bold <?php echo $change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo ($change >= 0 ? '+' : '') . $change; ?>
                                                </td>
                                                <td class="text-center text-muted"><?php echo $adj['previous_stock']; ?></td>
                                                <td class="text-center fw-bold text-dark"><?php echo $adj['new_stock']; ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($adj['adjusted_by_user'] ?? 'System'); ?>
                                                    </span>
                                                </td>
                                                <td class="pe-3 text-muted small"><?php echo htmlspecialchars($adj['reason'] ?: 'None provided'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="quickAdjustModal" tabindex="-1" aria-labelledby="quickAdjustModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="inventory.php">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="quickAdjustModalLabel"><i class="fas fa-sliders text-primary me-2"></i> Stock Adjustment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Select Product <span class="text-danger">*</span></label>
                            <select name="product_id" id="adjust_product_id" class="form-select" required onchange="updateProductStockPreview()">
                                <option value="">-- Choose Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" data-stock="<?php echo $p['stock']; ?>" data-unit="<?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?>">
                                        <?php echo htmlspecialchars($p['name']); ?> (Current: <?php echo $p['stock']; ?> <?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="alert alert-light border py-2 mb-3 small d-flex justify-content-between align-items-center">
                            <span>Current Stock on Hand:</span>
                            <strong id="preview_current_stock" class="fs-6 text-primary">0 pc</strong>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Adjustment Method</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="mode" id="mode_delta" value="delta" checked onchange="toggleMode()">
                                <label class="btn btn-outline-primary btn-sm fw-semibold" for="mode_delta">+/- Relative Change</label>

                                <input type="radio" class="btn-check" name="mode" id="mode_set" value="set" onchange="toggleMode()">
                                <label class="btn btn-outline-primary btn-sm fw-semibold" for="mode_set">Set Exact Count</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small" id="lbl_quantity">Quantity to Adjust (+ / -) <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="adjust_quantity" class="form-control" placeholder="e.g. +50 or -5" required>
                            <div class="form-text small" id="help_quantity">Positive number adds stock, negative subtracts stock.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Reason / Adjustment Type <span class="text-danger">*</span></label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="correction">Stock Correction / Manual Restock</option>
                                <option value="stocktake">Physical Stocktake / Audit Reconciliation</option>
                                <option value="damage">Damaged Goods / Broken</option>
                                <option value="expired">Expired Goods</option>
                                <option value="shrinkage">Shrinkage / Lost / Theft</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Remarks / Notes</label>
                            <textarea name="reason" class="form-control" rows="2" placeholder="Optional explanation for this adjustment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="adjust_stock" class="btn btn-primary fw-bold">Save Stock Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Threshold & Cost Modal -->
    <div class="modal fade" id="thresholdModal" tabindex="-1" aria-labelledby="thresholdModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="inventory.php">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="thresholdModalLabel"><i class="fas fa-cog text-primary me-2"></i> Edit Reorder Point & Cost Price</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="thresh_product_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Product</label>
                            <input type="text" id="thresh_product_name" class="form-control bg-light" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Reorder Threshold Level <span class="text-danger">*</span></label>
                            <input type="number" step="1" name="reorder_level" id="thresh_reorder_level" class="form-control" required>
                            <div class="form-text small">Alert is triggered when stock falls to or below this level.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Unit Cost Price (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="cost_price" id="thresh_cost_price" class="form-control" required>
                            <div class="form-text small">Used for calculating total inventory cost valuation.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_thresholds" class="btn btn-primary fw-bold">Update Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    function updateProductStockPreview() {
        const sel = document.getElementById('adjust_product_id');
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.value) {
            const stock = opt.getAttribute('data-stock') || '0';
            const unit = opt.getAttribute('data-unit') || 'pc';
            document.getElementById('preview_current_stock').textContent = stock + ' ' + unit;
        } else {
            document.getElementById('preview_current_stock').textContent = '0 pc';
        }
    }

    function toggleMode() {
        const isSet = document.getElementById('mode_set').checked;
        const lbl = document.getElementById('lbl_quantity');
        const help = document.getElementById('help_quantity');
        const input = document.getElementById('adjust_quantity');

        if (isSet) {
            lbl.innerHTML = 'New Exact Stock Count <span class="text-danger">*</span>';
            help.textContent = 'Enter the verified physical quantity counted.';
            input.placeholder = 'e.g. 150';
            input.min = '0';
        } else {
            lbl.innerHTML = 'Quantity to Adjust (+ / -) <span class="text-danger">*</span>';
            help.textContent = 'Positive number adds stock, negative subtracts stock.';
            input.placeholder = 'e.g. +50 or -5';
            input.removeAttribute('min');
        }
    }

    function openAdjustModal(product) {
        const sel = document.getElementById('adjust_product_id');
        sel.value = product.id;
        updateProductStockPreview();
        const modal = new bootstrap.Modal(document.getElementById('quickAdjustModal'));
        modal.show();
    }

    function openThresholdModal(product) {
        document.getElementById('thresh_product_id').value = product.id;
        document.getElementById('thresh_product_name').value = product.name;
        document.getElementById('thresh_reorder_level').value = product.reorder_level || 10;
        document.getElementById('thresh_cost_price').value = product.cost_price || 0.00;
        
        const modal = new bootstrap.Modal(document.getElementById('thresholdModal'));
        modal.show();
    }

    // Auto-open adjustment modal if transferred from Restock Draft
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'restock_draft' || urlParams.has('draft_product_id')) {
            const prodId = urlParams.get('draft_product_id') || urlParams.get('product_id');
            const qty = urlParams.get('draft_qty') || urlParams.get('qty');
            const reason = urlParams.get('draft_reason') || urlParams.get('reason') || 'Recommended Restock Replenishment';

            if (prodId) {
                const sel = document.getElementById('adjust_product_id');
                sel.value = prodId;
                updateProductStockPreview();

                if (qty) {
                    const qtyInput = document.getElementById('adjust_quantity');
                    qtyInput.value = qty;
                }

                const reasonInput = document.querySelector('#quickAdjustModal textarea[name="reason"]');
                if (reasonInput) {
                    reasonInput.value = reason;
                }

                const adjType = document.querySelector('#quickAdjustModal select[name="adjustment_type"]');
                if (adjType) {
                    adjType.value = 'correction';
                }

                const modal = new bootstrap.Modal(document.getElementById('quickAdjustModal'));
                modal.show();
            }
        }
    });
    </script>
</body>
</html>
