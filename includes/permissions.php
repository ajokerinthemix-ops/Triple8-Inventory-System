<?php
/**
 * Static RBAC permission map and definitions (fallback when database is unavailable).
 * System is configured for two standard roles: Admin and Cashier.
 */
return [
    'categories' => [
        'Core Access' => [
            'dashboard' => ['name' => 'View Dashboard', 'desc' => 'Access dashboard and operational summaries'],
            'profile'   => ['name' => 'Manage Profile', 'desc' => 'View and update personal profile and security credentials'],
        ],
        'Users & Security' => [
            'users'            => ['name' => 'User Management', 'desc' => 'View, create, edit, and deactivate user accounts'],
            'manage_roles'     => ['name' => 'Manage Roles & Permissions', 'desc' => 'Configure access control matrix and user overrides'],
            'view_audit_logs'  => ['name' => 'View Audit Trail', 'desc' => 'View security events, access logs, and modification history'],
        ],
        'Products & Catalog' => [
            'products'          => ['name' => 'Product Catalog', 'desc' => 'View retail product catalog, SKU, and pricing'],
            'add_edit_products' => ['name' => 'Add & Edit Products', 'desc' => 'Create and update products and retail prices'],
            'categories'        => ['name' => 'Category Management', 'desc' => 'Manage retail departments and merchandise categories'],
        ],
        'Inventory Operations' => [
            'inventory'           => ['name' => 'Inventory Overview', 'desc' => 'View stock levels, alerts, and valuations'],
            'adjust_inventory'    => ['name' => 'Adjust Inventory', 'desc' => 'Perform stock adjustments'],
        ],
        'POS & Sales' => [
            'pos'               => ['name' => 'Point of Sale (POS)', 'desc' => 'Access the retail POS cash register terminal'],
            'process_sale'      => ['name' => 'Process Sales & Checkout', 'desc' => 'Submit payment, apply discounts, and record revenue'],
            'transactions'      => ['name' => 'Transaction History', 'desc' => 'View historical sales receipts and transactions'],
            'receipt'           => ['name' => 'Print / View Receipts', 'desc' => 'Reprint official retail sales receipts'],
            'void_transactions' => ['name' => 'Void Transactions', 'desc' => 'Void completed transactions'],
            'apply_discounts'   => ['name' => 'Apply Discounts', 'desc' => 'Apply Senior/PWD and promotional discounts'],
            'approve_returns'   => ['name' => 'Approve Product Returns', 'desc' => 'Authorize customer product returns and exchange vouchers'],
        ],
        'Reports & Finance' => [
            'sales'   => ['name' => 'Sales Overview', 'desc' => 'View aggregate sales analytics and operational metrics'],
            'reports' => ['name' => 'Business Reports', 'desc' => 'Generate and export financial, inventory, and sales reports'],
        ],
        'System Settings' => [
            'settings' => ['name' => 'System Settings', 'desc' => 'Configure store metadata, tax rates, currency, and receipt headers'],
            'backup'   => ['name' => 'Backup & Restore', 'desc' => 'Create, download, and restore system database backups'],
        ],
    ],
    'permissions' => [
        'dashboard'         => ['admin', 'manager', 'cashier'],
        'profile'           => ['admin', 'manager', 'cashier'],

        // Users & Security (Admin only)
        'users'             => ['admin'],
        'manage_roles'      => ['admin'],
        'view_audit_logs'   => ['admin'],

        // Products & Catalog (Admin & Manager)
        'products'          => ['admin', 'manager'],
        'add_edit_products' => ['admin', 'manager'],
        'categories'        => ['admin', 'manager'],

        // Inventory Operations (Admin & Manager)
        'inventory'           => ['admin', 'manager'],
        'adjust_inventory'    => ['admin', 'manager'],

        // POS & Sales (Admin, Manager, Cashier)
        'pos'               => ['admin', 'manager', 'cashier'],
        'process_sale'      => ['admin', 'manager', 'cashier'],
        'transactions'      => ['admin', 'manager', 'cashier'],
        'receipt'           => ['admin', 'manager', 'cashier'],
        'void_transactions' => ['admin', 'manager'],
        'apply_discounts'   => ['admin', 'manager'],
        'approve_returns'   => ['admin', 'manager'],

        // Reports & Finance (Admin & Manager)
        'sales'             => ['admin', 'manager'],
        'reports'           => ['admin', 'manager'],

        // Settings (Admin only)
        'settings'          => ['admin'],
        'backup'            => ['admin'],
    ],
];
