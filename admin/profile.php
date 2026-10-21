<?php
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !hasPermission('profile')) {
    header("Location: " . getBasePath() . "login.php");
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';

// Get user information
$userId = getUserId();
$userQuery = "SELECT id, username, email, role, created_at, last_login 
             FROM users 
             WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $error_message = "Security validation failed (CSRF token invalid). Please refresh and try again.";
        } else {
            $email = trim((string)($_POST['email'] ?? ''));
            $current_password = (string)($_POST['current_password'] ?? '');
            $new_password = (string)($_POST['new_password'] ?? '');
            $confirm_password = (string)($_POST['confirm_password'] ?? '');
            
            // Validate email
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Invalid email format";
            } else {
                // Update email if provided
                if (!empty($email) && $email !== ($user['email'] ?? '')) {
                    $updateEmailQuery = "UPDATE users SET email = ?, email_verified = 0 WHERE id = ?";
                    $stmt = $conn->prepare($updateEmailQuery);
                    $stmt->bind_param("si", $email, $userId);
                    if ($stmt->execute()) {
                        require_once '../includes/otp.php';
                        require_once '../includes/mailer.php';
                        $token = storeEmailVerification($userId, $email);
                        if ($token) {
                            SimpleMailer::sendVerificationEmail($email, $token);
                        }
                        $_SESSION['email_verified'] = 0;
                        $success_message = "Email updated successfully! A verification link has been sent to the new email address.";
                    }
                    $stmt->close();
                }
                
                // Update password if provided
                if (!empty($current_password)) {
                    if (empty($new_password) || empty($confirm_password)) {
                        $error_message = "Please fill in all password fields";
                    } elseif ($new_password !== $confirm_password) {
                        $error_message = "New passwords do not match";
                    } else {
                        $passwordErrors = validatePasswordStrength($new_password);
                        if (!empty($passwordErrors)) {
                            $error_message = "Password must contain: " . implode(', ', $passwordErrors);
                        } else {
                            // Verify current password
                            $passwordQuery = "SELECT password FROM users WHERE id = ?";
                            $stmt = $conn->prepare($passwordQuery);
                            $stmt->bind_param("i", $userId);
                            $stmt->execute();
                            $result = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            
                            if (password_verify($current_password, $result['password'])) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $updatePasswordQuery = "UPDATE users SET password = ? WHERE id = ?";
                                $stmt = $conn->prepare($updatePasswordQuery);
                                $stmt->bind_param("si", $hashed_password, $userId);
                                $stmt->execute();
                                $stmt->close();
                                session_regenerate_id(true);
                                $success_message .= (empty($success_message) ? '' : ' ') . "Password updated successfully!";
                            } else {
                                $error_message = "Current password is incorrect";
                            }
                        }
                    }
                }
            }
            
            // Refresh user data
            $userQuery = "SELECT id, username, email, role, created_at, last_login 
                         FROM users 
                         WHERE id = ?";
            $stmt = $conn->prepare($userQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

// Get admin statistics
$statsQuery = "SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT p.id) as total_products,
                COUNT(DISTINCT s.id) as total_sales,
                COALESCE(SUM(s.total_amount), 0) as total_revenue,
                COUNT(DISTINCT c.id) as total_categories
              FROM users u
              LEFT JOIN products p ON 1=1
              LEFT JOIN sales s ON 1=1
              LEFT JOIN categories c ON 1=1";
$result = $conn->query($statsQuery);
$stats = $result->fetch_assoc();

// Get recent activity
$recentActivityQuery = "SELECT 
                          s.id as sale_id,
                          s.total_amount,
                          s.created_at,
                          u.username as cashier_name
                        FROM sales s
                        JOIN users u ON s.cashier_id = u.id
                        ORDER BY s.created_at DESC
                        LIMIT 5";
$recent_sales = $conn->query($recentActivityQuery);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Profile - Philippine Retail System</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .profile-header {
            background: linear-gradient(135deg, #300f54 0%, #4a157e 50%, #539610 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 0 0 30px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 50%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(35deg);
        }
        
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a157e 0%, #65b318 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 20px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }
        
        .profile-info {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .profile-name {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .profile-role {
            font-size: 20px;
            opacity: 0.9;
            margin-bottom: 5px;
            background: rgba(255, 255, 255, 0.2);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        .profile-meta {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .admin-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            backdrop-filter: blur(10px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.1;
            border-radius: 0 15px 0 100px;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }
        
        .stats-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stats-icon.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .stats-icon.warning { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); color: white; }
        .stats-icon.info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stats-icon.danger { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; }
        
        .stats-number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
        }
        
        .stats-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            position: relative;
            z-index: 2;
        }
        
        .card-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .card-section h4 {
            color: #667eea;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 3px solid #667eea;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .activity-meta {
            font-size: 12px;
            color: #666;
        }
        
        .activity-amount {
            font-weight: bold;
            color: #28a745;
            font-size: 18px;
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 30px 0;
            }
            
            .profile-avatar-large {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }
            
            .profile-name {
                font-size: 24px;
            }
            
            .admin-badge {
                position: static;
                margin-bottom: 15px;
                display: inline-block;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="admin-badge">
                    <i class="fas fa-crown"></i> SYSTEM ADMINISTRATOR
                </div>
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="profile-role">Administrator</div>
                    <div class="profile-meta">
                        <i class="fas fa-calendar"></i> Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                        <?php if ($user['last_login']): ?>
                            • <i class="fas fa-clock"></i> Last login <?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Administrator Statistics -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stats-label">Total Users</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stats-label">Total Products</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_categories']); ?></div>
                    <div class="stats-label">Categories</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon info">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_sales']); ?></div>
                    <div class="stats-label">Total Sales</div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon danger">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stats-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="stats-label">Total Revenue</div>
                </div>
            </div>
            
            <div class="row">
                <!-- Account Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card-section">
                        <h4><i class="fas fa-user-circle"></i> Account Information</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-user"></i> Username
                                </span>
                                <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-envelope"></i> Email
                                </span>
                                <span class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-shield-alt"></i> Role
                                </span>
                                <span class="info-value">Administrator</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-id-badge"></i> User ID
                                </span>
                                <span class="info-value">#<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-calendar-plus"></i> Account Created
                                </span>
                                <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></span>
                            </div>
                            <?php if ($user['last_login']): ?>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-clock"></i> Last Login
                                </span>
                                <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($user['last_login'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card-section">
                        <h4><i class="fas fa-cog"></i> Update Profile Settings</h4>
                        <form method="POST" id="profileSettingsForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       placeholder="Enter your email address">
                            </div>
                            
                            <div class="form-group">
                                <label for="current_password">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <input type="password" class="form-control" id="current_password" name="current_password" 
                                       placeholder="Enter current password to change">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">
                                    <i class="fas fa-key"></i> New Password
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Enter new password (optional)">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="fas fa-check"></i> Confirm New Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm new password">
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card-section">
                <h4><i class="fas fa-history"></i> Recent Sales Activity</h4>
                <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-details">
                                <div class="activity-title">Sale #<?php echo str_pad($sale['sale_id'], 6, '0', STR_PAD_LEFT); ?></div>
                                <div class="activity-meta">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($sale['cashier_name']); ?> • 
                                    <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?>
                                </div>
                            </div>
                            <div class="activity-amount">₱<?php echo number_format($sale['total_amount'], 2); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No recent sales activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Reset Form
        function resetForm() {
            document.getElementById('profileSettingsForm').reset();
            document.getElementById('email').value = '<?php echo htmlspecialchars($user['email'] ?? ''); ?>';
        }
        
        // Password validation
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.length > 0 && password.length < 6) {
                this.setCustomValidity('Password must be at least 6 characters long');
            } else {
                this.setCustomValidity('');
            }
            
            if (confirmPassword.value.length > 0) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validation before submit
        document.getElementById('profileSettingsForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length > 0 && currentPassword.length === 0) {
                e.preventDefault();
                alert('Please enter your current password to change your password');
                document.getElementById('current_password').focus();
            }
        });
    </script>
</body>
</html>
