<?php
$pageTitle = 'Roles & Security Audit Trail';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';
$conn = $GLOBALS['conn'] ?? $conn;

// Require permission to manage settings/access control
requirePermission('settings');

$message = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'roles';

// =========================================================================
// 1. FORM ACTIONS HANDLING
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i>Invalid CSRF security token. Please refresh and try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } else {
        // ACTION: Edit Role
        if (isset($_POST['edit_role'])) {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $displayName = trim($_POST['display_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $defaultDashboard = trim($_POST['default_dashboard'] ?? 'dashboard.php');

            if ($roleId > 0 && !empty($displayName)) {
                $stmt = $conn->prepare("UPDATE roles SET display_name = ?, description = ?, default_dashboard = ? WHERE id = ?");
                $stmt->bind_param("sssi", $displayName, $description, $defaultDashboard, $roleId);
                if ($stmt->execute()) {
                    logAccessEvent('ROLE_UPDATE', 'role', $roleId, "Updated role details for ID: $roleId", 'success');
                    $message = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Role details updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i>Error updating role: ' . htmlspecialchars($conn->error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
                $stmt->close();
            }
            $activeTab = 'roles';
        }
    }
}

// =========================================================================
// 2. QUERY DATA FOR DISPLAY
// =========================================================================

// Query Roles
$rolesResult = $conn->query("
    SELECT r.id, r.role_name, r.display_name, r.description, r.is_system, r.default_dashboard, r.created_at,
           COUNT(u.id) as user_count
    FROM roles r
    LEFT JOIN users u ON r.role_name = u.role
    GROUP BY r.id, r.role_name, r.display_name, r.description, r.is_system, r.default_dashboard, r.created_at
    ORDER BY r.is_system DESC, r.display_name ASC
");
$allRoles = [];
while ($r = $rolesResult->fetch_assoc()) {
    $allRoles[] = $r;
}

// Query Security Audit Trail
$auditLogs = [];
$auditResult = $conn->query("SELECT id, user_id, username, action, target_type, target_id, details, ip_address, status, created_at FROM access_audit_logs ORDER BY created_at DESC LIMIT 100");
if ($auditResult) {
    while ($row = $auditResult->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Triple 8 Inventory System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3c72;
            --primary-dark: #142850;
            --secondary: #2a5298;
            --bg-light: #f8fafc;
            --card-border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: #1e293b;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .page-header {
            margin-bottom: 25px;
        }

        .nav-pills-custom .nav-link {
            color: #64748b;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .nav-pills-custom .nav-link.active {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.25);
        }

        .nav-pills-custom .nav-link:hover:not(.active) {
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
        }

        .badge-role-admin { background-color: #ef4444; color: white; }
        .badge-role-manager { background-color: #3b82f6; color: white; }
        .badge-role-cashier { background-color: #10b981; color: white; }
        .badge-role-inventory_clerk { background-color: #f59e0b; color: white; }
        .badge-role-custom { background-color: #8b5cf6; color: white; }

        .log-action-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-shield-halved text-primary me-2"></i> Access Control</h3>
                    <p class="text-muted small mb-0">Manage store roles and monitor security audit trail logs</p>
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Navigation Tabs: Roles Management and Security Audit Trail -->
            <ul class="nav nav-pills nav-pills-custom mb-4 bg-white p-2 rounded border shadow-sm" id="accessTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link <?php echo $activeTab === 'roles' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-roles">
                        <i class="fas fa-user-tag me-2"></i> Roles Management (<?php echo count($allRoles); ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $activeTab === 'audit' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-audit">
                        <i class="fas fa-history me-2"></i> Security Audit Trail (<?php echo count($auditLogs); ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                
                <!-- ========================================================================= -->
                <!-- TAB 1: ROLES MANAGEMENT -->
                <!-- ========================================================================= -->
                <div class="tab-pane fade <?php echo $activeTab === 'roles' ? 'show active' : ''; ?>" id="tab-roles">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Configured Store Roles</h5>
                            <small class="text-muted">Manage system roles, landing portals, and staff assignments</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Role Key</th>
                                            <th>Display Name</th>
                                            <th>Description</th>
                                            <th>Assigned Users</th>
                                            <th>Default Portal</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allRoles as $role): 
                                            $rKey = $role['role_name'];
                                            $badgeClass = 'badge-role-' . ($role['is_system'] ? $rKey : 'custom');
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?php echo $badgeClass; ?> px-2 py-1">
                                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                                    </span>
                                                    <?php if ($role['is_system']): ?>
                                                        <span class="badge bg-light text-secondary border ms-1" title="System Role">System</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($role['display_name']); ?></strong></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($role['description'] ?: 'No description provided'); ?></small></td>
                                                <td>
                                                    <span class="badge bg-secondary rounded-pill">
                                                        <i class="fas fa-users me-1"></i> <?php echo (int)$role['user_count']; ?> user(s)
                                                    </span>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($role['default_dashboard']); ?></code></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditRoleModal(<?php echo json_encode($role); ?>)'>
                                                        <i class="fas fa-edit"></i> Edit Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- TAB 2: SECURITY AUDIT TRAIL -->
                <!-- ========================================================================= -->
                <div class="tab-pane fade <?php echo $activeTab === 'audit' ? 'show active' : ''; ?>" id="tab-audit">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark">Access & Security Audit Trail</h5>
                                <small class="text-muted">Real-time log of user access events, role updates, and security activities</small>
                            </div>
                            <div class="input-group input-group-sm" style="width: 260px;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="auditSearch" class="form-control" placeholder="Filter audit logs..." onkeyup="filterAudit()">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="auditTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action Type</th>
                                            <th>Target Type</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($auditLogs)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-muted">No audit log entries recorded yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($auditLogs as $log): 
                                                $statusClass = ($log['status'] === 'success') ? 'bg-success' : (($log['status'] === 'denied' || $log['status'] === 'danger') ? 'bg-danger' : 'bg-warning text-dark');
                                            ?>
                                                <tr>
                                                    <td><small class="text-muted"><?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?></small></td>
                                                    <td>
                                                        <i class="fas fa-user-circle me-1 text-primary"></i>
                                                        <strong><?php echo htmlspecialchars($log['username'] ?: 'System'); ?></strong>
                                                        <?php if (!empty($log['user_id'])): ?>
                                                            <small class="text-muted">(#<?php echo (int)$log['user_id']; ?>)</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border font-monospace log-action-badge">
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td><code><?php echo htmlspecialchars($log['target_type'] ?: 'system'); ?></code></td>
                                                    <td><small><?php echo htmlspecialchars($log['details']); ?></small></td>
                                                    <td><small class="text-muted font-monospace"><?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?></small></td>
                                                    <td class="text-center">
                                                        <span class="badge <?php echo $statusClass; ?>">
                                                            <?php echo strtoupper(htmlspecialchars($log['status'] ?: 'INFO')); ?>
                                                        </span>
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
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="role_id" id="edit_role_id">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Role Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Role Key</label>
                            <input type="text" id="edit_role_name" class="form-control font-monospace" readonly disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Display Name *</label>
                            <input type="text" name="display_name" id="edit_display_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Default Landing Portal</label>
                            <input type="text" name="default_dashboard" id="edit_default_dashboard" class="form-control font-monospace" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_role" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Filter audit logs
        function filterAudit() {
            const query = document.getElementById('auditSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#auditTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        // Open edit role modal
        function openEditRoleModal(role) {
            document.getElementById('edit_role_id').value = role.id;
            document.getElementById('edit_role_name').value = role.role_name;
            document.getElementById('edit_display_name').value = role.display_name;
            document.getElementById('edit_description').value = role.description || '';
            document.getElementById('edit_default_dashboard').value = role.default_dashboard;
            new bootstrap.Modal(document.getElementById('editRoleModal')).show();
        }
    </script>
</body>
</html>
