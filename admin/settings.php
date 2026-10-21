<?php
$pageTitle = 'Store Settings';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

requirePermission('settings');

$message = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settingsToSave = [
        'store_name'          => trim($_POST['store_name'] ?? 'Triple8 Retail Superstore'),
        'store_address'       => trim($_POST['store_address'] ?? ''),
        'tax_rate'            => (float)($_POST['tax_rate'] ?? 12),
        'currency_symbol'     => trim($_POST['currency_symbol'] ?? '₱'),
        'receipt_header'      => trim($_POST['receipt_header'] ?? ''),
        'receipt_footer'      => trim($_POST['receipt_footer'] ?? '')
    ];

    $hasError = false;
    foreach ($settingsToSave as $key => $val) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $stmt->bind_param("ss", $key, $val);
            if (!$stmt->execute()) {
                $hasError = true;
            }
            $stmt->close();
        }
    }

    if (!$hasError) {
        $message = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i> Store settings saved successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i> Error saving settings: ' . htmlspecialchars($conn->error) . ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Fetch current settings with sensible defaults
$settings = [
    'store_name'          => 'Triple8 Retail Store',
    'store_address'       => 'Balingasag, Misamis Oriental',
    'tax_rate'            => '12',
    'currency_symbol'     => '₱',
    'receipt_header'      => "WELCOME TO TRIPLE8 RETAIL STORE\nOfficial Sales Invoice / POS Receipt",
    'receipt_footer'      => "Thank you for shopping at Triple8 Superstore!\nPlease present receipt for exchanges within 7 days."
];

$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('store_name', 'store_address', 'tax_rate', 'currency_symbol', 'receipt_header', 'receipt_footer')");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
}

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
                    <h2 class="fw-bold mb-1"><i class="fas fa-sliders text-primary me-2"></i> Store Settings</h2>
                    <p class="text-muted small mb-0">Configure store identity, pricing rules, and official POS receipt layout.</p>
                </div>
            </div>

            <?php if (!empty($message)) echo $message; ?>

            <form method="POST" action="">
                <div class="row g-4">
                    
                    <!-- Store Identity & Tax Settings -->
                    <div class="col-lg-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-store text-primary me-2"></i> Store Information & Currency</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">Store Name *</label>
                                        <input type="text" name="store_name" class="form-control" value="<?php echo htmlspecialchars($settings['store_name']); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small">Standard VAT Rate (%)</label>
                                        <input type="number" name="tax_rate" class="form-control" step="0.1" value="<?php echo htmlspecialchars($settings['tax_rate']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small">Currency Symbol</label>
                                        <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small">Store Address</label>
                                        <textarea name="store_address" class="form-control" rows="2"><?php echo htmlspecialchars($settings['store_address']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- POS Receipt Layout -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-receipt text-primary me-2"></i> POS Receipt Header & Footer</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">Receipt Header Message</label>
                                        <textarea name="receipt_header" class="form-control font-monospace" rows="3"><?php echo htmlspecialchars($settings['receipt_header']); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">Receipt Footer / Return Policy</label>
                                        <textarea name="receipt_footer" class="form-control font-monospace" rows="3"><?php echo htmlspecialchars($settings['receipt_footer']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light py-3 d-flex justify-content-end">
                                <button type="submit" name="save_settings" class="btn btn-primary fw-bold px-4 py-2">
                                    <i class="fas fa-save me-1"></i> Save Store Settings
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
