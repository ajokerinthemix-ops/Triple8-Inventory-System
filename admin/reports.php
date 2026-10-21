<?php
$pageTitle = 'Retail Business Reports';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

requirePermission('reports');

// Fetch system settings
$settings = [
    'store_name' => 'Triple8 Retail Store',
    'store_address' => 'Balingasag, Misamis Oriental',
    'store_phone' => '',
    'store_tin' => ''
];
$setRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('store_name', 'store_address', 'store_phone', 'store_tin')");
if ($setRes) {
    while ($r = $setRes->fetch_assoc()) {
        if (!empty($r['setting_value'])) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    }
}

$activeTab = $_GET['tab'] ?? 'z_reading';
$reportType = $_GET['type'] ?? 'daily';
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Build date condition
switch ($reportType) {
    case 'weekly':
        $dateCondition = "s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $periodLabel = "Past 7 Days";
        break;
    case 'monthly':
        $dateCondition = "s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $periodLabel = "Past 30 Days";
        break;
    case 'custom':
        $dateCondition = "DATE(s.created_at) BETWEEN '$startDate' AND '$endDate'";
        $periodLabel = "$startDate to $endDate";
        break;
    case 'daily':
    default:
        $dateCondition = "DATE(s.created_at) = '$startDate'";
        $periodLabel = date('F d, Y', strtotime($startDate));
        break;
}

// 1. Z-Reading / Financial Summary
$zRes = $conn->query("
    SELECT 
        COUNT(*) as total_transactions,
        MIN(id) as start_or,
        MAX(id) as end_or,
        COALESCE(SUM(total_amount), 0) as gross_sales,
        COALESCE(SUM(vatable_sales), 0) as vatable_sales,
        COALESCE(SUM(vat_amount), 0) as vat_amount,
        COALESCE(SUM(non_vatable_sales), 0) as non_vatable_sales,
        COALESCE(SUM(discount_amount), 0) as total_discounts,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
        COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END), 0) as card_sales,
        COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN total_amount ELSE 0 END), 0) as gcash_sales,
        COALESCE(SUM(CASE WHEN payment_method = 'maya' THEN total_amount ELSE 0 END), 0) as maya_sales,
        COALESCE(AVG(total_amount), 0) as avg_basket
    FROM sales s
    WHERE $dateCondition
");
$zData = $zRes->fetch_assoc();

// 2. Product Profitability Report
$prodProfRes = $conn->query("
    SELECT 
        p.name as product_name,
        p.sku,
        p.brand,
        c.name as category_name,
        SUM(si.quantity) as units_sold,
        SUM(si.quantity * si.cost_price) as total_cogs,
        SUM(si.quantity * si.price) as total_revenue,
        SUM(si.quantity * (si.price - si.cost_price)) as gross_profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $dateCondition
    GROUP BY p.id
    ORDER BY total_revenue DESC
");
$productProfits = $prodProfRes ? $prodProfRes->fetch_all(MYSQLI_ASSOC) : [];

// 3. Department Sales
$deptRes = $conn->query("
    SELECT 
        c.name as category_name,
        COUNT(DISTINCT p.id) as sku_count,
        SUM(si.quantity) as units_sold,
        SUM(si.quantity * si.price) as category_revenue
    FROM categories c
    JOIN products p ON c.id = p.category_id
    JOIN sale_items si ON p.id = si.product_id
    JOIN sales s ON si.sale_id = s.id
    WHERE $dateCondition
    GROUP BY c.id
    ORDER BY category_revenue DESC
");
$deptSales = $deptRes ? $deptRes->fetch_all(MYSQLI_ASSOC) : [];

// 4. Cashier Performance
$cashierRes = $conn->query("
    SELECT 
        u.username as cashier_name,
        COUNT(s.id) as receipt_count,
        COALESCE(SUM(s.total_amount), 0) as total_sales,
        COALESCE(AVG(s.total_amount), 0) as avg_ticket
    FROM users u
    JOIN sales s ON u.id = s.cashier_id
    WHERE $dateCondition
    GROUP BY u.id
    ORDER BY total_sales DESC
");
$cashierSales = $cashierRes ? $cashierRes->fetch_all(MYSQLI_ASSOC) : [];

// 5. Inventory Valuation Snapshot
$invValRes = $conn->query("
    SELECT 
        COUNT(*) as total_skus,
        COALESCE(SUM(stock), 0) as total_units,
        COALESCE(SUM(stock * cost_price), 0) as wholesale_val,
        COALESCE(SUM(stock * price), 0) as retail_val
    FROM products
");
$invVal = $invValRes->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .report-nav-pill {
            padding: 10px 18px;
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 8px;
            text-decoration: none;
            color: #475569;
            background: #ffffff;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        .report-nav-pill.active {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
            box-shadow: var(--shadow-sm);
        }
        .z-reading-paper {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 30px;
            font-family: 'Courier New', Courier, monospace;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-radius: 4px;
        }
        @media print {
            .no-print { display: none !important; }
            .z-reading-paper { border: none; box-shadow: none; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 no-print">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-chart-pie text-primary me-2"></i> Retail Reports & Financial Analytics</h2>
                    <p class="text-muted small mb-0">Generate official End-of-Day Z-Readings, departmental revenue, and gross profitability reports.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Date Filter Bar -->
            <div class="card border-0 shadow-sm mb-4 no-print">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Report Period</label>
                            <select name="type" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                                <option value="daily" <?php echo $reportType === 'daily' ? 'selected' : ''; ?>>Specific Day (Daily)</option>
                                <option value="weekly" <?php echo $reportType === 'weekly' ? 'selected' : ''; ?>>Weekly (Last 7 Days)</option>
                                <option value="monthly" <?php echo $reportType === 'monthly' ? 'selected' : ''; ?>>Monthly (Last 30 Days)</option>
                                <option value="custom" <?php echo $reportType === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="singleDateWrapper" style="<?php echo $reportType === 'custom' ? 'display:none;' : ''; ?>">
                            <label class="form-label small fw-bold mb-1">Select Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-md-3" id="endDateWrapper" style="<?php echo $reportType !== 'custom' ? 'display:none;' : ''; ?>">
                            <label class="form-label small fw-bold mb-1">End Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                <i class="fas fa-arrows-rotate me-1"></i> Generate
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Navigation Tabs -->
            <div class="d-flex flex-wrap gap-2 mb-4 no-print">
                <a href="?tab=z_reading&type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                   class="report-nav-pill <?php echo $activeTab === 'z_reading' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice me-1"></i> End-of-Day Z-Reading
                </a>
                <a href="?tab=profitability&type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                   class="report-nav-pill <?php echo $activeTab === 'profitability' ? 'active' : ''; ?>">
                    <i class="fas fa-sack-dollar me-1"></i> Product Profitability
                </a>
                <a href="?tab=department&type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                   class="report-nav-pill <?php echo $activeTab === 'department' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group me-1"></i> Department Sales
                </a>
                <a href="?tab=cashiers&type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                   class="report-nav-pill <?php echo $activeTab === 'cashiers' ? 'active' : ''; ?>">
                    <i class="fas fa-cash-register me-1"></i> Cashier Breakdown
                </a>
                <a href="?tab=inventory_val&type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                   class="report-nav-pill <?php echo $activeTab === 'inventory_val' ? 'active' : ''; ?>">
                    <i class="fas fa-vault me-1"></i> Inventory Valuation
                </a>
            </div>

            <!-- TAB 1: Z-READING / END OF DAY REPORT -->
            <?php if ($activeTab === 'z_reading'): ?>
                <div class="z-reading-paper">
                    <div class="text-center mb-3 border-bottom pb-2">
                        <div class="fw-bold fs-6"><?php echo htmlspecialchars($settings['store_name'] ?? 'TRIPLE8 RETAIL STORE'); ?></div>
                        <?php if (!empty($settings['store_address']) && !str_contains($settings['store_address'], 'Commonwealth')): ?>
                            <div class="small"><?php echo htmlspecialchars($settings['store_address']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($settings['store_tin']) && $settings['store_tin'] !== '248-888-888-000-VAT'): ?>
                            <div class="small">TIN: <?php echo htmlspecialchars($settings['store_tin']); ?></div>
                        <?php endif; ?>
                        <div class="fw-bold mt-2 text-uppercase">*** OFFICIAL Z-READING REPORT ***</div>
                        <div class="small text-muted">Period: <?php echo $periodLabel; ?></div>
                    </div>

                    <table class="w-100 small mb-3">
                        <tr>
                            <td>Report Date:</td>
                            <td class="text-end fw-bold"><?php echo date('m/d/Y h:i A'); ?></td>
                        </tr>
                        <tr>
                            <td>Beginning OR #:</td>
                            <td class="text-end font-monospace">OR-<?php echo str_pad($zData['start_or'] ?? 0, 8, '0', STR_PAD_LEFT); ?></td>
                        </tr>
                        <tr>
                            <td>Ending OR #:</td>
                            <td class="text-end font-monospace">OR-<?php echo str_pad($zData['end_or'] ?? 0, 8, '0', STR_PAD_LEFT); ?></td>
                        </tr>
                        <tr>
                            <td>Total Transactions:</td>
                            <td class="text-end fw-bold"><?php echo number_format($zData['total_transactions'] ?? 0); ?></td>
                        </tr>
                    </table>

                    <div class="border-top border-bottom py-2 my-2 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Gross Registered Sales:</span>
                            <span class="fw-bold">₱<?php echo number_format($zData['gross_sales'] ?? 0, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 text-danger">
                            <span>Less: Customer Discounts:</span>
                            <span>-₱<?php echo number_format($zData['total_discounts'] ?? 0, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>VATable Sales (12%):</span>
                            <span>₱<?php echo number_format($zData['vatable_sales'] ?? 0, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>VAT Amount Collected:</span>
                            <span>₱<?php echo number_format($zData['vat_amount'] ?? 0, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Non-VAT / Exempt:</span>
                            <span>₱<?php echo number_format($zData['non_vatable_sales'] ?? 0, 2); ?></span>
                        </div>
                    </div>

                    <div class="py-2 small border-bottom mb-3">
                        <div class="fw-bold text-uppercase mb-2">Tender Breakdown:</div>
                        <div class="d-flex justify-content-between">
                            <span>Cash in Drawer:</span>
                            <span class="fw-bold">₱<?php echo number_format($zData['cash_sales'] ?? 0, 2); ?></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center fs-6 fw-bold">
                        <span>NET REGISTER TOTAL:</span>
                        <span class="text-primary">₱<?php echo number_format($zData['gross_sales'] ?? 0, 2); ?></span>
                    </div>

                    <div class="text-center mt-4 pt-3 border-top small text-muted">
                        Generated by: <?php echo htmlspecialchars(getUsername()); ?><br>
                        Triple8 POS Management Terminal
                    </div>
                </div>

            <!-- TAB 2: PRODUCT PROFITABILITY -->
            <?php elseif ($activeTab === 'profitability'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-sack-dollar text-primary me-2"></i> Merchandise Profitability & Margin Analysis (<?php echo $periodLabel; ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Product Name</th>
                                        <th>Department</th>
                                        <th>Brand</th>
                                        <th class="text-center">Units Sold</th>
                                        <th class="text-end">Cost of Goods (COGS)</th>
                                        <th class="text-end">Sales Revenue</th>
                                        <th class="text-end">Gross Profit</th>
                                        <th class="text-center pe-3">Margin %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($productProfits)): ?>
                                        <tr><td colspan="8" class="text-center py-5 text-muted">No merchandise sales recorded in this period.</td></tr>
                                    <?php else: ?>
                                        <?php 
                                            $totUnits = 0; $totCogs = 0; $totRev = 0; $totProf = 0;
                                            foreach ($productProfits as $p): 
                                                $totUnits += $p['units_sold'];
                                                $totCogs += $p['total_cogs'];
                                                $totRev += $p['total_revenue'];
                                                $totProf += $p['gross_profit'];
                                                $margin = $p['total_revenue'] > 0 ? ($p['gross_profit'] / $p['total_revenue']) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($p['category_name'] ?? 'General'); ?></span></td>
                                                <td><?php echo htmlspecialchars($p['brand'] ?? '—'); ?></td>
                                                <td class="text-center fw-bold"><?php echo number_format($p['units_sold']); ?></td>
                                                <td class="text-end text-muted">₱<?php echo number_format($p['total_cogs'], 2); ?></td>
                                                <td class="text-end fw-bold text-dark">₱<?php echo number_format($p['total_revenue'], 2); ?></td>
                                                <td class="text-end fw-bold text-success">₱<?php echo number_format($p['gross_profit'], 2); ?></td>
                                                <td class="text-center pe-3">
                                                    <span class="badge <?php echo $margin >= 20 ? 'bg-success' : 'bg-info'; ?>">
                                                        <?php echo number_format($margin, 1); ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light fw-bold">
                                            <td colspan="3" class="ps-3 text-uppercase">Summary Totals:</td>
                                            <td class="text-center"><?php echo number_format($totUnits); ?></td>
                                            <td class="text-end text-muted">₱<?php echo number_format($totCogs, 2); ?></td>
                                            <td class="text-end text-dark">₱<?php echo number_format($totRev, 2); ?></td>
                                            <td class="text-end text-success">₱<?php echo number_format($totProf, 2); ?></td>
                                            <td class="text-center pe-3"><?php echo $totRev > 0 ? number_format(($totProf / $totRev) * 100, 1) : '0.0'; ?>%</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB 3: DEPARTMENT SALES -->
            <?php elseif ($activeTab === 'department'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-layer-group text-primary me-2"></i> Department Revenue & Volume Breakdown (<?php echo $periodLabel; ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Department Name</th>
                                        <th class="text-center">Products Linked</th>
                                        <th class="text-center">Quantity Sold</th>
                                        <th class="text-end">Total Department Revenue</th>
                                        <th class="text-center pe-3">Store Share %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($deptSales)): ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">No departmental sales data recorded.</td></tr>
                                    <?php else: ?>
                                        <?php 
                                            $totDeptRev = array_sum(array_column($deptSales, 'category_revenue'));
                                            foreach ($deptSales as $d): 
                                                $share = $totDeptRev > 0 ? ($d['category_revenue'] / $totDeptRev) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark fs-6"><?php echo htmlspecialchars($d['category_name']); ?></td>
                                                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $d['sku_count']; ?> Products</span></td>
                                                <td class="text-center fw-bold"><?php echo number_format($d['units_sold']); ?> units</td>
                                                <td class="text-end fw-black text-primary font-monospace fs-6">₱<?php echo number_format($d['category_revenue'], 2); ?></td>
                                                <td class="text-center pe-3">
                                                    <span class="badge bg-primary px-3 py-2"><?php echo number_format($share, 1); ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB 4: CASHIERS -->
            <?php elseif ($activeTab === 'cashiers'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-cash-register text-primary me-2"></i> Cashier Terminal Performance (<?php echo $periodLabel; ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Cashier Operator</th>
                                        <th class="text-center">Receipts Checked Out</th>
                                        <th class="text-end">Total Tendered Sales</th>
                                        <th class="text-end pe-3">Average Basket Size</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cashierSales)): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No cashier shift records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($cashierSales as $cs): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark fs-6">
                                                    <i class="fas fa-user-circle text-primary me-2"></i><?php echo htmlspecialchars($cs['cashier_name']); ?>
                                                </td>
                                                <td class="text-center fw-bold"><?php echo number_format($cs['receipt_count']); ?> txns</td>
                                                <td class="text-end fw-black text-success fs-6 font-monospace">₱<?php echo number_format($cs['total_sales'], 2); ?></td>
                                                <td class="text-end pe-3 fw-semibold text-muted font-monospace">₱<?php echo number_format($cs['avg_ticket'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- TAB 5: INVENTORY VALUATION -->
            <?php elseif ($activeTab === 'inventory_val'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-vault text-primary me-2"></i> Store Inventory Valuation & Asset Health</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center">
                                    <div class="small text-muted fw-bold text-uppercase">Total Catalog Products</div>
                                    <div class="fs-3 fw-bold text-dark mt-1"><?php echo number_format($invVal['total_skus']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center">
                                    <div class="small text-muted fw-bold text-uppercase">Total Stock Units</div>
                                    <div class="fs-3 fw-bold text-primary mt-1"><?php echo number_format($invVal['total_units']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center">
                                    <div class="small text-muted fw-bold text-uppercase">Total Cost Valuation</div>
                                    <div class="fs-3 fw-bold text-dark mt-1">₱<?php echo number_format($invVal['wholesale_val'], 2); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center">
                                    <div class="small text-muted fw-bold text-uppercase">Expected Retail Value</div>
                                    <div class="fs-3 fw-bold text-success mt-1">₱<?php echo number_format($invVal['retail_val'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info py-3">
                            <i class="fas fa-circle-info me-2"></i>
                            <strong>Potential Store Gross Margin:</strong> 
                            ₱<?php echo number_format(max(0, $invVal['retail_val'] - $invVal['wholesale_val']), 2); ?> 
                            (<?php echo $invVal['retail_val'] > 0 ? number_format((($invVal['retail_val'] - $invVal['wholesale_val']) / $invVal['retail_val']) * 100, 1) : 0; ?>% potential ROI on current stock holdings).
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCustomDates(type) {
            const single = document.getElementById('singleDateWrapper');
            const end = document.getElementById('endDateWrapper');
            if (type === 'custom') {
                single.style.display = 'block';
                end.style.display = 'block';
            } else {
                single.style.display = 'block';
                end.style.display = 'none';
            }
        }
    </script>
</body>
</html>
