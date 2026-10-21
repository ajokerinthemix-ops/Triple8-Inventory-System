<?php
$pageTitle = 'Sales & Transactions';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

requirePermission('transactions');

$userRole = getUserRole();
$userId = getUserId();

$search = trim($_GET['search'] ?? '');
$filterMethod = trim($_GET['payment_method'] ?? '');
$filterDate = trim($_GET['date'] ?? '');

$where = [];
$params = [];
$types = '';

// Cashier sees own sales; Manager/Admin sees all
if ($userRole === 'cashier') {
    $where[] = "s.cashier_id = ?";
    $params[] = $userId;
    $types .= "i";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT s.*, u.username as cashier_name, c.name as customer_name, c.customer_code,
           COUNT(si.id) as total_items,
           SUM(si.quantity) as total_qty
    FROM sales s
    JOIN users u ON s.cashier_id = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    $whereSql
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Metrics
$metricsQuery = "SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as revenue FROM sales s " . ($userRole === 'cashier' ? "WHERE s.cashier_id = $userId" : "");
$metrics = $conn->query($metricsQuery)->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-receipt text-primary me-2"></i> Sales & Transaction History</h2>
                    <p class="text-muted small mb-0">Review past retail orders, reprint receipts, and inspect payment tender.</p>
                </div>
            </div>

            <!-- Metric Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card p-3 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Total Transactions</div>
                                <div class="fs-4 fw-bold text-dark mt-1"><?php echo number_format($metrics['cnt']); ?></div>
                            </div>
                            <div class="bg-primary text-white p-3 rounded-3"><i class="fas fa-receipt fa-lg"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card p-3 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Gross Revenue Recorded</div>
                                <div class="fs-4 fw-bold text-success mt-1">₱<?php echo number_format($metrics['revenue'], 2); ?></div>
                            </div>
                            <div class="bg-success text-white p-3 rounded-3"><i class="fas fa-peso-sign fa-lg"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transactions Table Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-list text-primary me-2"></i> Recent Retail Receipts (<?php echo count($transactions); ?>)</h6>
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
                                    <th>Items / Qty</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-center pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-receipt fa-2x mb-2 text-secondary opacity-50"></i><br>
                                            No sales transactions recorded yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $t): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary font-monospace">
                                                OR-<?php echo str_pad($t['id'], 8, '0', STR_PAD_LEFT); ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($t['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['cashier_name']); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($t['customer_name'])): ?>
                                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($t['customer_name']); ?></span>
                                                    <div class="small text-muted font-monospace"><?php echo htmlspecialchars($t['customer_code']); ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted small">Walk-in</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-white text-uppercase px-2 py-1">
                                                    <?php echo htmlspecialchars($t['payment_method']); ?>
                                                </span>
                                                <?php if (!empty($t['payment_reference'])): ?>
                                                    <div class="small text-muted font-monospace mt-1"><?php echo htmlspecialchars($t['payment_reference']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?php echo (int)($t['total_qty'] ?? 0); ?> units</span>
                                                <span class="text-muted small">(<?php echo (int)($t['total_items'] ?? 0); ?> items)</span>
                                            </td>
                                            <td class="text-end fw-black text-dark fs-6">
                                                ₱<?php echo number_format($t['total_amount'], 2); ?>
                                                <?php if ($t['discount_amount'] > 0): ?>
                                                    <div class="small text-danger">-₱<?php echo number_format($t['discount_amount'], 2); ?> disc</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center pe-3">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewReceipt(<?php echo $t['id']; ?>)">
                                                    <i class="fas fa-eye me-1"></i> View Receipt
                                                </button>
                                                <a href="receipt.php?id=<?php echo $t['id']; ?>&print=1" target="_blank" class="btn btn-sm btn-outline-secondary">
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

    <!-- Receipt Preview Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-receipt me-2"></i> Official Receipt</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="receiptModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnModalPrint" onclick="printCurrentReceipt()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentModalSaleId = null;

        async function viewReceipt(saleId) {
            currentModalSaleId = saleId;
            const modalBody = document.getElementById('receiptModalBody');
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading receipt...</p>
                </div>`;

            const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
            modal.show();

            try {
                const res = await fetch(`receipt.php?id=${saleId}&format=modal`);
                const html = await res.text();
                modalBody.innerHTML = html;
            } catch (err) {
                modalBody.innerHTML = `<div class="p-4 text-danger">Failed to load receipt: ${err.message}</div>`;
            }
        }

        function printCurrentReceipt() {
            if (currentModalSaleId) {
                window.open(`receipt.php?id=${currentModalSaleId}&print=1`, '_blank', 'width=450,height=650');
            }
        }
    </script>
</body>
</html>
