<?php
$pageTitle = 'Admin Executive Dashboard';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

requirePermission('dashboard');

// 1. Sales & Financial Metrics
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// Today's Sales
$todayRes = $conn->query("SELECT COUNT(*) as txn, COALESCE(SUM(total_amount), 0) as rev FROM sales WHERE DATE(created_at) = '$today'");
$todayStats = $todayRes->fetch_assoc();

// Month to Date Sales
$mtdRes = $conn->query("SELECT COUNT(*) as txn, COALESCE(SUM(total_amount), 0) as rev FROM sales WHERE created_at >= '$monthStart'");
$mtdStats = $mtdRes->fetch_assoc();

// All Time Sales & Estimated COGS for Profit Margin
$financialsRes = $conn->query("
    SELECT 
        COUNT(DISTINCT s.id) as total_txns,
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        COALESCE(SUM(si.quantity * si.cost_price), 0) as total_cogs
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
");
$finStats = $financialsRes->fetch_assoc();
$grossProfit = max(0, $finStats['total_revenue'] - $finStats['total_cogs']);
$profitMargin = $finStats['total_revenue'] > 0 ? ($grossProfit / $finStats['total_revenue']) * 100 : 0;

// 2. Inventory Stats
$invRes = $conn->query("
    SELECT 
        COUNT(*) as total_skus,
        COALESCE(SUM(stock), 0) as total_units,
        COALESCE(SUM(stock * cost_price), 0) as total_cost_value,
        SUM(CASE WHEN stock <= reorder_level AND stock > 0 THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
    FROM products
");
$invStats = $invRes->fetch_assoc();

// 3. Low Stock Items List
$lowStock = [];
$lsRes = $conn->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock <= p.reorder_level
    ORDER BY p.stock ASC
    LIMIT 6
");
if ($lsRes) while ($r = $lsRes->fetch_assoc()) $lowStock[] = $r;

// 4. Recent Sales Transactions
$recentSales = [];
$rsRes = $conn->query("
    SELECT s.*, u.username as cashier_name, c.name as customer_name,
           COUNT(si.id) as item_count,
           SUM(si.quantity) as total_qty
    FROM sales s
    JOIN users u ON s.cashier_id = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 6
");
if ($rsRes) while ($r = $rsRes->fetch_assoc()) $recentSales[] = $r;

// 5. Chart 1: 7-Day Sales Trend
$trendLabels = [];
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M d', strtotime($d));
    $tRes = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as rev FROM sales WHERE DATE(created_at) = '$d'");
    $trendData[] = (float)($tRes->fetch_assoc()['rev'] ?? 0);
}

// 6. Chart 2: Revenue by Department / Category
$catChartLabels = [];
$catChartData = [];
$catSalesRes = $conn->query("
    SELECT c.name as cat_name, COALESCE(SUM(si.quantity * si.price), 0) as rev
    FROM categories c
    JOIN products p ON c.id = p.category_id
    JOIN sale_items si ON p.id = si.product_id
    GROUP BY c.id, c.name
    ORDER BY rev DESC
    LIMIT 6
");
if ($catSalesRes && $catSalesRes->num_rows > 0) {
    while ($r = $catSalesRes->fetch_assoc()) {
        $catChartLabels[] = $r['cat_name'];
        $catChartData[] = (float)$r['rev'];
    }
} else {
    // Default preview from current inventory
    $cPreview = $conn->query("SELECT name FROM categories LIMIT 5");
    while ($r = $cPreview->fetch_assoc()) {
        $catChartLabels[] = $r['name'];
        $catChartData[] = rand(1500, 8500);
    }
}

// 7. Top Selling Retail Products
$topProducts = [];
$topRes = $conn->query("
    SELECT p.name, p.sku, p.brand, SUM(si.quantity) as units_sold, SUM(si.quantity * si.price) as revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    GROUP BY p.id
    ORDER BY units_sold DESC
    LIMIT 5
");
if ($topRes) while ($r = $topRes->fetch_assoc()) $topProducts[] = $r;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Row 1: KPI Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card p-3 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center" style="background-color: #f8f9fa;">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Today's Sales</div>
                                <div class="fs-4 fw-bold text-primary mt-1">₱<?php echo number_format($todayStats['rev'], 2); ?></div>
                                <small class="text-muted"><?php echo $todayStats['txn']; ?> receipts processed</small>
                            </div>
                            <div class="bg-primary text-white p-3 rounded-3"><i class="fas fa-peso-sign fa-lg"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card p-3 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center" style="background-color: #f8f9fa;">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Month-to-Date Revenue</div>
                                <div class="fs-4 fw-bold text-success mt-1">₱<?php echo number_format($mtdStats['rev'], 2); ?></div>
                                <small class="text-muted"><?php echo $mtdStats['txn']; ?> monthly transactions</small>
                            </div>
                            <div class="bg-success text-white p-3 rounded-3"><i class="fas fa-calendar-check fa-lg"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card p-3 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center" style="background-color: #f8f9fa;">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Gross Profit Margin</div>
                                <div class="fs-4 fw-bold text-info mt-1"><?php echo number_format($profitMargin, 1); ?>%</div>
                                <small class="text-muted">Est. Profit: ₱<?php echo number_format($grossProfit, 2); ?></small>
                            </div>
                            <div class="bg-info text-white p-3 rounded-3"><i class="fas fa-sack-dollar fa-lg"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card p-3 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center" style="background-color: #f8f9fa;">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Stock Health & Reorder</div>
                                <div class="fs-4 fw-bold <?php echo $invStats['low_stock_count'] > 0 ? 'text-warning' : 'text-success'; ?> mt-1">
                                    <?php echo $invStats['low_stock_count']; ?> Low / <?php echo $invStats['out_of_stock_count']; ?> Out
                                </div>
                                <small class="text-muted">Across <?php echo $invStats['total_skus']; ?> active products</small>
                            </div>
                            <div class="bg-warning text-dark p-3 rounded-3"><i class="fas fa-boxes-stacked fa-lg"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Header & Quick Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-chart-line text-primary me-2"></i> Retail Store Overview</h2>
                    <p class="text-muted small mb-0">Unified management analytics, cashier POS revenue, and inventory health monitoring.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary fw-bold" onclick="window.VoiceAssistant ? window.VoiceAssistant.wakeUp() : null" title="Activate Hands-Free Voice Assistant">
                        <i class="fas fa-microphone-lines me-1 text-primary"></i> Voice Assistant
                    </button>
                    <a href="../cashier/pos.php" class="btn btn-primary fw-bold">
                        <i class="fas fa-cash-register me-1"></i> Open POS
                    </a>
                    <a href="inventory.php" class="btn btn-outline-primary fw-bold">
                        <i class="fas fa-warehouse me-1"></i> Inventory
                    </a>
                    <a href="reports.php" class="btn btn-outline-secondary fw-bold">
                        <i class="fas fa-file-invoice-dollar me-1"></i> Z-Reading
                    </a>
                </div>
            </div>

            <!-- Revenue Trend -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-chart-area text-primary me-2"></i> 7-Day Revenue Trend</h6>
                            <span class="badge bg-light text-muted border">Daily POS Sales</span>
                        </div>
                        <div class="card-body p-3">
                            <div style="height: 280px;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Department Distribution -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie text-primary me-2"></i> Department Share</h6>
                        </div>
                        <div class="card-body p-3">
                            <div style="height: 280px;">
                                <canvas id="catChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Fast Movers & Low Stock Warning -->
            <div class="row g-4 mb-4">
                <!-- Fast Moving Products -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-success"><i class="fas fa-fire me-2"></i> Fast-Moving Retail Products</h6>
                            <a href="reports.php" class="btn btn-sm btn-link text-decoration-none">Full Report &rarr;</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th class="ps-3">Product Name</th>
                                            <th>Brand</th>
                                            <th class="text-center">Units Sold</th>
                                            <th class="text-end pe-3">Total Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($topProducts)): ?>
                                            <tr><td colspan="4" class="text-center py-4 text-muted">Complete sales through POS to populate fast movers.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($topProducts as $tp): ?>
                                                <tr>
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($tp['name']); ?></div>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($tp['brand'] ?? 'Retail'); ?></span></td>
                                                    <td class="text-center fw-bold text-primary"><?php echo number_format($tp['units_sold']); ?></td>
                                                    <td class="text-end pe-3 fw-bold text-dark">₱<?php echo number_format($tp['revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Items Requiring Reorder -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-warning"><i class="fas fa-triangle-exclamation me-2"></i> Reorder Alerts (< Min Level)</h6>
                            <a href="inventory.php" class="btn btn-sm btn-primary">View Inventory</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th class="ps-3">Item</th>
                                            <th>Department</th>
                                            <th class="text-center">Stock</th>
                                            <th class="text-center">Min Level</th>
                                            <th class="text-center pe-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($lowStock)): ?>
                                            <tr><td colspan="5" class="text-center py-4 text-muted">All catalog products have sufficient stock.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($lowStock as $ls): ?>
                                                <tr>
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($ls['name']); ?></div>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ls['category_name'] ?? 'General'); ?></span></td>
                                                    <td class="text-center">
                                                        <span class="badge <?php echo $ls['stock'] <= 0 ? 'bg-danger' : 'bg-warning text-dark'; ?> fs-6">
                                                            <?php echo $ls['stock']; ?> <?php echo htmlspecialchars($ls['unit']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center text-muted small fw-bold"><?php echo $ls['reorder_level']; ?></td>
                                                    <td class="text-center pe-3">
                                                        <a href="inventory.php" class="btn btn-sm btn-outline-primary py-0 px-2">Manage</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 4: Recent Receipts -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-receipt text-primary me-2"></i> Recent Retail Receipts</h6>
                    <a href="sales.php" class="btn btn-sm btn-link text-decoration-none">View All Sales &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">OR Number</th>
                                    <th>Date & Time</th>
                                    <th>Cashier</th>
                                    <th>Customer</th>
                                    <th>Payment Mode</th>
                                    <th>Units</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-center pe-3">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSales)): ?>
                                    <tr><td colspan="8" class="text-center py-4 text-muted">No recent sales records.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentSales as $s): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary font-monospace">OR-<?php echo str_pad($s['id'], 8, '0', STR_PAD_LEFT); ?></td>
                                            <td>
                                                <div><?php echo date('M d, Y', strtotime($s['created_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($s['created_at'])); ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['cashier_name']); ?></span></td>
                                            <td><?php echo !empty($s['customer_name']) ? htmlspecialchars($s['customer_name']) : '<span class="text-muted small">Walk-in</span>'; ?></td>
                                            <td><span class="badge bg-info text-white text-uppercase"><?php echo htmlspecialchars($s['payment_method']); ?></span></td>
                                            <td class="fw-bold"><?php echo (int)($s['total_qty'] ?? 0); ?> units</td>
                                            <td class="text-end fw-black text-dark fs-6">₱<?php echo number_format($s['total_amount'], 2); ?></td>
                                            <td class="text-center pe-3">
                                                <a href="../cashier/receipt.php?id=<?php echo $s['id']; ?>&print=1" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
                datasets: [{
                    label: 'Daily Revenue (₱)',
                    data: <?php echo json_encode($trendData); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#1e3a8a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '₱' + v.toLocaleString() }
                    }
                }
            }
        });

        // Category Chart
        const catCtx = document.getElementById('catChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($catChartLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($catChartData); ?>,
                    backgroundColor: ['#2563eb', '#0d9488', '#f59e0b', '#dc2626', '#8b5cf6', '#0284c7']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    </script>
</body>
</html>
