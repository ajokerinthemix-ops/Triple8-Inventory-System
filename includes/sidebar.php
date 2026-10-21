<?php
if (!isset($baseUrl)) {
    $currentUri = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    if (strpos($currentUri, '/admin/') !== false || strpos($currentUri, '/cashier/') !== false) {
        $baseUrl = '../';
    } else {
        $baseUrl = './';
    }
}
$role = getUserRole();
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="d-flex flex-column h-100 justify-content-between pb-3">
        <nav class="nav flex-column">
            <?php if (hasPermission('dashboard')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            <?php endif; ?>

            <?php if (hasPermission('pos')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>cashier/pos.php">
                    <i class="fas fa-cash-register"></i>
                    Point of Sale (POS)
                </a>
            <?php endif; ?>

            <?php if (hasPermission('products')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>admin/products.php">
                    <i class="fas fa-boxes-stacked"></i>
                    Product Catalog
                </a>
            <?php endif; ?>

            <?php if (hasPermission('categories')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>admin/categories.php">
                    <i class="fas fa-tags"></i>
                    Departments / Categories
                </a>
            <?php endif; ?>

            <?php if (hasPermission('inventory')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>admin/inventory.php">
                    <i class="fas fa-warehouse"></i>
                    Inventory Overview
                </a>
            <?php endif; ?>

            <?php if (hasPermission('transactions') || hasPermission('sales')): ?>
                <a class="nav-link" href="<?php echo $baseUrl . ($role === 'cashier' ? 'cashier/transactions.php' : 'admin/sales.php'); ?>">
                    <i class="fas fa-receipt"></i>
                    Sales & Transactions
                </a>
            <?php endif; ?>

            <?php if (hasPermission('reports')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>admin/reports.php">
                    <i class="fas fa-chart-line"></i>
                    Business Reports
                </a>
            <?php endif; ?>

            <?php if (hasPermission('users')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>admin/users.php">
                    <i class="fas fa-users-gear"></i>
                    User Management
                </a>
            <?php endif; ?>

            <?php if (hasPermission('manage_roles') || hasPermission('settings') || hasPermission('backup')): ?>
                <a class="nav-link" href="<?php echo $baseUrl; ?>admin/rbac.php">
                    <i class="fas fa-shield-halved"></i>
                    Access Control
                </a>
                <?php if (hasPermission('backup')): ?>
                    <a class="nav-link" href="<?php echo $baseUrl; ?>admin/backup.php">
                        <i class="fas fa-database"></i>
                        Backup & Restore
                    </a>
                <?php endif; ?>
                <?php if (hasPermission('settings')): ?>
                    <a class="nav-link" href="<?php echo $baseUrl; ?>admin/settings.php">
                        <i class="fas fa-sliders"></i>
                        Store Settings
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- Direct Logout Action in Sidebar -->
        <div class="px-2 pt-3 mt-3 border-top border-secondary border-opacity-25">
            <a class="nav-link text-danger fw-semibold d-flex align-items-center gap-2" href="javascript:void(0)" onclick="confirmLogout()" title="Log out of system">
                <i class="fas fa-sign-out-alt text-danger"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>
</div>

<!-- Mobile Menu Toggle -->
<button class="btn btn-primary d-md-none position-fixed" style="top: 80px; left: 10px; z-index: 1001;" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

function confirmLogout() {
    if (confirm('Are you sure you want to log out?')) {
        window.location.href = '<?php echo $baseUrl; ?>logout.php';
    }
}

// Set active menu item based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.endsWith(href.replace('../', '').replace('./', ''))) {
            link.classList.add('active');
        }
    });
});
</script>
