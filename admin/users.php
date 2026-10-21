<?php
$pageTitle = 'User Management';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';
$conn = $GLOBALS['conn'] ?? $conn;

// Check if user is authorized to manage users
requirePermission('users');

$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$csrfToken = generateCsrfToken();

if (!function_exists('isStrongPassword')) {
    function isStrongPassword($password) {
        return validatePasswordStrength($password);
    }
}

// Fetch all available roles from roles table
$rolesList = [];
$rolesQuery = $conn->query("SELECT role_name, display_name FROM roles ORDER BY is_system DESC, display_name ASC");
if ($rolesQuery) {
    while ($r = $rolesQuery->fetch_assoc()) {
        $rolesList[] = $r;
    }
}
if (empty($rolesList)) {
    $rolesList = [
        ['role_name' => 'admin', 'display_name' => 'Administrator'],
        ['role_name' => 'manager', 'display_name' => 'Store Manager'],
        ['role_name' => 'cashier', 'display_name' => 'Cashier']
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $message = '<div class="alert alert-danger alert-dismissible fade show">Invalid request security token. Please refresh and try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $role = trim($_POST['role']);
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'suspended']) ? $_POST['status'] : 'active';
            $email = trim($_POST['email'] ?? '');
            
            if (empty($username) || empty($password) || empty($role)) {
                $message = '<div class="alert alert-danger alert-dismissible fade show">Please fill all required fields.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                $passwordErrors = isStrongPassword($password);
                if (!empty($passwordErrors)) {
                    $message = '<div class="alert alert-danger alert-dismissible fade show">Password must contain: ' . implode(', ', $passwordErrors) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $message = '<div class="alert alert-danger alert-dismissible fade show">Username already exists!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, email) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $username, $hashedPassword, $role, $status, $email);
                        
                        if ($stmt->execute()) {
                            $newUserId = $conn->insert_id;
                            logAccessEvent('USER_CREATE', 'user', $newUserId, "Created user '$username' with role '$role' and status '$status'", 'success');

                            if (!empty($email)) {
                                require_once '../includes/otp.php';
                                require_once '../includes/mailer.php';
                                $token = storeEmailVerification($newUserId, $email);
                                if ($token) {
                                    SimpleMailer::sendVerificationEmail($email, $token);
                                }
                            }
                            $message = '<div class="alert alert-success alert-dismissible fade show">User <strong>' . htmlspecialchars($username) . '</strong> added successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                        } else {
                            $message = '<div class="alert alert-danger alert-dismissible fade show">Error adding user: ' . htmlspecialchars($conn->error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                        }
                    }
                    $stmt->close();
                }
            }
        }
    } elseif (isset($_POST['edit_user'])) {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $message = '<div class="alert alert-danger alert-dismissible fade show">Invalid request security token. Please refresh and try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $id = (int)$_POST['id'];
            $username = trim($_POST['username']);
            $role = trim($_POST['role']);
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'suspended']) ? $_POST['status'] : 'active';
            $password = $_POST['password'];
            $email = trim($_POST['email'] ?? '');
            
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = '<div class="alert alert-danger alert-dismissible fade show">Username already exists!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                if (!empty($password)) {
                    $passwordErrors = isStrongPassword($password);
                    if (!empty($passwordErrors)) {
                        $message = '<div class="alert alert-danger alert-dismissible fade show">Password must contain: ' . implode(', ', $passwordErrors) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ?, password = ?, email = ? WHERE id = ?");
                        $stmt->bind_param("sssssi", $username, $role, $status, $hashedPassword, $email, $id);
                        
                        if ($stmt->execute()) {
                            logAccessEvent('USER_UPDATE', 'user', $id, "Updated user '$username' (role: $role, status: $status, password reset)", 'success');
                            $message = '<div class="alert alert-success alert-dismissible fade show">User updated successfully with new password!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                        } else {
                            $message = '<div class="alert alert-danger alert-dismissible fade show">Error updating user: ' . htmlspecialchars($conn->error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                        }
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ?, email = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $username, $role, $status, $email, $id);
                    
                    if ($stmt->execute()) {
                        logAccessEvent('USER_UPDATE', 'user', $id, "Updated user '$username' (role: $role, status: $status)", 'success');
                        $message = '<div class="alert alert-success alert-dismissible fade show">User updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    } else {
                        $message = '<div class="alert alert-danger alert-dismissible fade show">Error updating user: ' . htmlspecialchars($conn->error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                    $stmt->close();
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $message = '<div class="alert alert-danger alert-dismissible fade show">Invalid request security token. Please refresh and try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $id = (int)$_POST['id'];
            
            // Prevent deletion of self
            if ($id == getUserId()) {
                $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-ban me-2"></i>You cannot delete your own account!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    logAccessEvent('USER_DELETE', 'user', $id, "Deleted user ID: $id", 'warning');
                    $message = '<div class="alert alert-success alert-dismissible fade show">User deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    $message = '<div class="alert alert-danger alert-dismissible fade show">Error deleting user: ' . htmlspecialchars($conn->error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
                $stmt->close();
            }
        }
    }
}

// Get users with voice authentication status
$users = [];
$result = $conn->query("
    SELECT u.id, u.username, u.role, u.status, u.email, u.email_verified, u.created_at, u.last_login,
           v.id as voiceprint_id, v.pitch_mean, v.auth_level, v.enrolled_at as voice_enrolled_at
    FROM users u
    LEFT JOIN user_voiceprints v ON u.id = v.user_id AND v.is_active = 1
    ORDER BY u.username ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .badge-status-active { background-color: #198754; color: white; }
        .badge-status-inactive { background-color: #6c757d; color: white; }
        .badge-status-suspended { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-users me-2 text-primary"></i> User Management</h2>
                    <p class="text-muted mb-0">Create accounts, assign roles, manage access status, and configure permissions</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="rbac.php" class="btn btn-outline-primary">
                        <i class="fas fa-shield-alt me-1"></i> Access Control Center
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-1"></i> Add New User
                    </button>
                </div>
            </div>
            
            <?php echo $message; ?>
            
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Active User Accounts (<?php echo count($users); ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Assigned Role</th>
                                    <th>Status</th>
                                    <th>Voice Auth</th>
                                    <th>Last Login</th>
                                    <th>Created At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): 
                                    $roleBadge = ($user['role'] === 'admin') ? 'bg-danger' : (($user['role'] === 'manager') ? 'bg-success' : 'bg-primary');
                                    $statusClass = 'badge-status-' . ($user['status'] ?? 'active');
                                ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $roleBadge; ?> fs-6">
                                             <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['voiceprint_id'])): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2" title="Enrolled <?php echo date('M d, Y', strtotime($user['voice_enrolled_at'])); ?> • Pitch <?php echo round($user['pitch_mean']); ?>Hz">
                                                <i class="fas fa-fingerprint me-1"></i> Registered
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-muted border border-secondary border-opacity-25 py-1 px-2">
                                                <i class="fas fa-microphone-slash me-1"></i> Unregistered
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo !empty($user['last_login']) ? date('M d, Y H:i', strtotime($user['last_login'])) : '<span class="text-secondary">Never</span>'; ?>
                                    </td>
                                    <td class="small text-muted"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != getUserId()): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username *</label>
                            <input type="text" class="form-control" name="username" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="text-muted">Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special symbol.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($rolesList as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r['role_name']); ?>">
                                            <?php echo htmlspecialchars($r['display_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Account Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-user-pen me-2"></i> Edit User Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="id" id="editUserId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Username *</label>
                            <input type="text" class="form-control" name="username" id="editUsername" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Password (leave empty to keep current)</label>
                            <input type="password" class="form-control" name="password" id="editPassword">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Role *</label>
                                <select class="form-select" name="role" id="editRole" required>
                                    <?php foreach ($rolesList as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r['role_name']); ?>">
                                            <?php echo htmlspecialchars($r['display_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Status *</label>
                                <select class="form-select" name="status" id="editStatus" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash-can me-2"></i> Delete User Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="id" id="deleteUserId">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this user account?</p>
                        <p class="text-danger mb-0"><strong><i class="fas fa-triangle-exclamation me-1"></i> Warning:</strong> All user-specific permissions and preferences will be permanently removed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const users = <?php echo json_encode($users); ?>;
        
        function editUser(id) {
            const user = users.find(u => u.id == id);
            if (user) {
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editUsername').value = user.username;
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editRole').value = user.role;
                document.getElementById('editStatus').value = user.status || 'active';
                document.getElementById('editPassword').value = '';
                
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            }
        }
        
        function deleteUser(id) {
            document.getElementById('deleteUserId').value = id;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html>
