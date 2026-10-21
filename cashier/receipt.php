<?php
$pageTitle = 'Official Retail Receipt';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

requirePermission('receipt');

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0);

if ($saleId <= 0) {
    header("Location: pos.php");
    exit();
}

// Fetch sale details
$stmt = $conn->prepare("
    SELECT s.*, u.username as cashier_name
    FROM sales s 
    JOIN users u ON s.cashier_id = u.id 
    WHERE s.id = ?
");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    echo "<div class='p-4 text-center text-danger'>Sale transaction not found.</div>";
    exit();
}

// Fetch sale items
$itemStmt = $conn->prepare("
    SELECT si.*, p.name as product_name, p.sku, p.unit, p.brand
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$itemStmt->bind_param("i", $saleId);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Fetch system settings with default overrides
$settings = [
    'store_name' => 'Triple 8 Inventory System',
    'store_tagline' => 'Retail Management & Point of Sale',
    'store_address' => 'Balingasag, Misamis Oriental',
    'store_phone' => '',
    'store_tin' => '',
    'currency_symbol' => '₱',
    'receipt_header' => 'WELCOME TO TRIPLE 8 INVENTORY SYSTEM',
    'receipt_footer' => 'Thank you for shopping with us! Please present receipt for exchanges within 7 days.'
];

$setRes = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($setRes) {
    while ($r = $setRes->fetch_assoc()) {
        if (!empty($r['setting_value'])) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    }
}

$conn->close();

$isModal = isset($_GET['format']) && $_GET['format'] === 'modal';
$autoPrint = isset($_GET['print']) && $_GET['print'] == 1;
?>
<?php if (!$isModal): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo str_pad($saleId, 8, '0', STR_PAD_LEFT); ?> | Triple 8 Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php endif; ?>

<style>
    <?php if (!$isModal): ?>
    body {
        background: #f1f5f9;
        font-family: 'Courier New', Courier, monospace;
        padding: 20px;
    }
    <?php endif; ?>

    .receipt-card {
        max-width: 380px;
        margin: 0 auto;
        background: #ffffff;
        padding: 25px 20px;
        font-family: 'Courier New', Courier, monospace;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.4;
        border-radius: 4px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
    }

    .receipt-header {
        text-align: center;
        border-bottom: 1px dashed #334155;
        padding-bottom: 12px;
        margin-bottom: 12px;
    }

    .receipt-title {
        font-weight: 900;
        font-size: 16px;
        letter-spacing: 0.05em;
        margin-bottom: 2px;
        text-transform: uppercase;
    }

    .receipt-sub {
        font-size: 11px;
        color: #475569;
    }

    .receipt-info-table, .receipt-calc-table {
        width: 100%;
        margin-bottom: 10px;
        font-size: 12px;
    }

    .receipt-info-table td {
        padding: 1px 0;
    }

    .receipt-items-table {
        width: 100%;
        border-top: 1px dashed #334155;
        border-bottom: 1px dashed #334155;
        padding: 8px 0;
        margin: 10px 0;
        font-size: 12px;
    }

    .receipt-items-table th {
        text-align: left;
        padding: 4px 0;
        font-weight: bold;
    }

    .receipt-items-table td {
        padding: 3px 0;
        vertical-align: top;
    }

    .receipt-totals {
        border-bottom: 1px dashed #334155;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }

    .receipt-grand-total {
        font-size: 16px;
        font-weight: 900;
    }

    @media print {
        body {
            background: #ffffff;
            padding: 0;
            margin: 0;
        }
        .receipt-card {
            box-shadow: none;
            border: none;
            max-width: 100%;
            width: 100%;
            padding: 10px;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<?php if (!$isModal): ?>
</head>
<body <?php echo $autoPrint ? 'onload="window.print()"' : ''; ?>>
<?php endif; ?>

    <div class="receipt-card">
        <div class="receipt-header">
            <img src="../assets/images/triple8_logo.png?v=<?php echo time(); ?>" alt="888 Triple Eight Logo" style="width: 52px; height: 52px; border-radius: 50%; object-fit: contain; margin-bottom: 6px;" onerror="this.style.display='none'">
            <div class="receipt-title"><?php echo htmlspecialchars($settings['store_name']); ?></div>
            <?php if (!empty($settings['store_tagline'])): ?>
                <div class="receipt-sub"><?php echo htmlspecialchars($settings['store_tagline']); ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['store_address']) && !str_contains($settings['store_address'], 'Commonwealth')): ?>
                <div class="receipt-sub"><?php echo htmlspecialchars($settings['store_address']); ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['store_tin']) && $settings['store_tin'] !== '248-888-888-000-VAT'): ?>
                <div class="receipt-sub">TIN: <?php echo htmlspecialchars($settings['store_tin']); ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['store_phone'])): ?>
                <div class="receipt-sub">Tel: <?php echo htmlspecialchars($settings['store_phone']); ?></div>
            <?php endif; ?>
            <div class="mt-2 fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 1px;">
                *** OFFICIAL SALES RECEIPT ***
            </div>
        </div>

        <table class="receipt-info-table">
            <tr>
                <td><strong>OR #:</strong> <?php echo str_pad($sale['id'], 8, '0', STR_PAD_LEFT); ?></td>
                <td class="text-end"><strong>Date:</strong> <?php echo date('m/d/Y', strtotime($sale['created_at'])); ?></td>
            </tr>
            <tr>
                <td><strong>Cashier:</strong> <?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                <td class="text-end"><strong>Time:</strong> <?php echo date('h:i A', strtotime($sale['created_at'])); ?></td>
            </tr>
        </table>

        <table class="receipt-items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">ITEM</th>
                    <th class="text-center" style="width: 15%;">QTY</th>
                    <th class="text-end" style="width: 15%;">PRICE</th>
                    <th class="text-end" style="width: 20%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div><?php echo htmlspecialchars($it['product_name']); ?></div>
                        </td>
                        <td class="text-center"><?php echo $it['quantity']; ?></td>
                        <td class="text-end"><?php echo number_format($it['price'], 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($it['price'] * $it['quantity'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-totals">
            <table class="receipt-calc-table">
                <tr>
                    <td>Gross Subtotal:</td>
                    <td class="text-end">₱<?php echo number_format($sale['subtotal'] > 0 ? $sale['subtotal'] : $sale['total_amount'], 2); ?></td>
                </tr>
                <?php if ($sale['discount_amount'] > 0): ?>
                    <tr class="text-danger">
                        <td>Discount (<?php echo strtoupper($sale['discount_type']); ?>):</td>
                        <td class="text-end">-₱<?php echo number_format($sale['discount_amount'], 2); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>VATable Sales (12%):</td>
                    <td class="text-end">₱<?php echo number_format($sale['vatable_sales'], 2); ?></td>
                </tr>
                <tr>
                    <td>VAT Amount:</td>
                    <td class="text-end">₱<?php echo number_format($sale['vat_amount'], 2); ?></td>
                </tr>
                <tr class="receipt-grand-total">
                    <td class="pt-2">TOTAL AMOUNT DUE:</td>
                    <td class="text-end pt-2">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td class="pt-2">Payment Mode:</td>
                    <td class="text-end pt-2 text-uppercase fw-bold"><?php echo htmlspecialchars($sale['payment_method']); ?></td>
                </tr>
                <tr>
                    <td>Amount Tendered:</td>
                    <td class="text-end">₱<?php echo number_format($sale['payment'], 2); ?></td>
                </tr>
                <tr>
                    <td>Change Given:</td>
                    <td class="text-end fw-bold">₱<?php echo number_format($sale['change_amount'], 2); ?></td>
                </tr>
            </table>
        </div>

        <div class="text-center my-3 py-2 border-top border-bottom">
            <span class="font-monospace fw-bold" style="letter-spacing: 2px;">OR-<?php echo str_pad($sale['id'], 8, '0', STR_PAD_LEFT); ?></span>
        </div>

        <div class="text-center" style="font-size: 11px; color: #64748b;">
            <p class="mb-1"><?php echo nl2br(htmlspecialchars($settings['receipt_footer'])); ?></p>
            <p class="mb-0">POS Terminal System: Triple 8 Inventory System</p>
        </div>
    </div>

<?php if (!$isModal): ?>
    <div class="text-center mt-3 no-print">
        <button class="btn btn-primary btn-sm me-2" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print Receipt
        </button>
        <a href="pos.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to POS
        </a>
    </div>
</body>
</html>
<?php endif; ?>
