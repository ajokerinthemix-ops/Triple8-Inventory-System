<?php
require_once __DIR__ . '/auth.php';

if (!isset($baseUrl)) {
    $currentUri = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    if (strpos($currentUri, '/admin/') !== false || strpos($currentUri, '/cashier/') !== false) {
        $baseUrl = '../';
    } else {
        $baseUrl = './';
    }
}

$headerUsername = function_exists('getUsername') ? getUsername() : ($_SESSION['username'] ?? 'User');
$headerUserRole = function_exists('getUserRole') ? getUserRole() : ($_SESSION['role'] ?? 'guest');
$currentScript = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Triple 8 Inventory System'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>assets/images/triple8_logo.png?v=<?php echo time(); ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4a157e;
            --primary-hover: #370d61;
            --secondary-color: #65b318;
            --secondary-hover: #539610;
            --accent-red: #c1121f;
            --accent-gold: #f59e0b;
            --success-color: #65b318;
            --danger-color: #c1121f;
            --warning-color: #d97706;
            --info-color: #6d28d9;
            --light-bg: #f8faf5;
            --sidebar-width: 250px;
            --surface: #ffffff;
            --surface-muted: #fbfdf9;
            --border-color: #e2e8f0;
            --text-color: #1a0f2e;
            --muted-color: #64748b;
            --shadow-sm: 0 1px 3px rgba(35, 11, 58, 0.08);
            --shadow-md: 0 10px 25px rgba(35, 11, 58, 0.10);
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            background: #f8faf5;
            color: var(--text-color);
            overflow-x: hidden;
        }

        .navbar {
            background: #230b3a;
            border-bottom: 1px solid rgba(101, 179, 24, 0.25);
            box-shadow: 0 4px 15px rgba(35, 11, 58, 0.25);
            padding: 0.72rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: 0;
            color: white !important;
        }

        .navbar-brand i {
            margin-right: 8px;
            color: #84cc16;
        }

        .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info .badge {
            background-color: var(--warning-color);
            padding: 5px 10px;
            border-radius: 6px;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1b0730 0%, #290e47 100%);
            padding-top: 74px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 1px 0 0 rgba(255,255,255,0.06), 8px 0 24px rgba(27, 7, 48, 0.30);
            border-right: 1px solid rgba(101, 179, 24, 0.15);
        }

        .sidebar .nav-link {
            color: #cbd5e1;
            padding: 10px 16px;
            margin: 3px 10px;
            border-radius: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(101, 179, 24, 0.15);
            color: #ffffff;
            transform: none;
        }

        .sidebar .nav-link.active {
            border-left: 3px solid #84cc16;
            background: linear-gradient(90deg, rgba(101, 179, 24, 0.25) 0%, rgba(74, 21, 126, 0.20) 100%);
            color: #84cc16;
            font-weight: 700;
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .sidebar .nav-link:hover i,
        .sidebar .nav-link.active i {
            color: #84cc16;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 92px 24px 28px 24px; /* Account for fixed navbar */
            min-height: 100vh;
            transition: all 0.3s;
            background-color: var(--light-bg);
            width: calc(100% - var(--sidebar-width));
        }
        
        .main-content .container-fluid {
            padding: 0;
            max-width: 100%;
        }
        
        .main-content h2 {
            color: var(--text-color);
            font-weight: 700;
            margin-bottom: 1.25rem;
            font-size: 1.35rem;
        }

        .main-content h2 i {
            color: var(--secondary-color);
            margin-right: 8px;
        }
        
        .main-content .card {
            margin-bottom: 1.5rem;
        }
        
        .main-content .card-header {
            background-color: var(--surface);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .chart-container canvas {
            max-height: 300px;
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: none;
            box-shadow: var(--shadow-md);
            border-color: #c7d2d8;
        }

        .stat-card {
            background: var(--surface);
            color: var(--text-color);
            border-left: 4px solid var(--secondary-color);
        }

        .stat-card.success {
            background: var(--surface);
            border-left-color: var(--success-color);
        }

        .stat-card.warning {
            background: var(--surface);
            border-left-color: var(--warning-color);
        }

        .stat-card.danger {
            background: var(--surface);
            border-left-color: var(--danger-color);
        }

        .stat-card .card-body {
            text-align: left !important;
        }

        .stat-card h3,
        .stat-card h4 {
            color: var(--text-color);
            font-weight: 700;
        }

        .stat-card p {
            color: var(--muted-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.76rem;
            letter-spacing: 0.04em;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            border-radius: 6px;
            padding: 8px 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #255f63;
            border-color: #255f63;
            transform: none;
            box-shadow: none;
        }

        .table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table thead {
            background: #eef3f4;
            color: #314149;
        }

        .table thead th {
            border-bottom: 1px solid var(--border-color);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .table tbody td {
            color: #2f3f47;
            vertical-align: middle;
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid var(--border-color);
            padding: 9px 12px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.18rem rgba(47, 111, 115, 0.16);
        }

        .modal-content {
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            background: var(--surface);
            color: var(--text-color);
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid var(--border-color);
        }

        .alert {
            border-radius: 6px;
            border: 1px solid transparent;
            padding: 12px 16px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 100px 15px 20px 15px;
            }
            
            .main-content .container-fluid {
                padding: 0 5px;
            }
            
            /* Responsive form styling for reports */
            .main-content .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start !important;
            }
            
            .main-content form.d-flex {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            
            .main-content select.form-select,
            .main-content input.form-control {
                width: 100%;
                max-width: none !important;
            }
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--secondary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Profile Dropdown Styles */
        .profile-dropdown {
            position: relative;
        }

        .profile-toggle {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 8px 15px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .profile-toggle:hover {
            background: rgba(255, 255, 255, 0.16);
            transform: none;
        }

        .profile-avatar {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: linear-gradient(135deg, #2563eb, #0d9488);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }

        .profile-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            min-width: 250px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            margin-top: 10px;
        }

        .profile-dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: #eef3f4;
            color: var(--text-color);
            border-radius: 8px 8px 0 0;
        }

        .profile-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .profile-role {
            font-size: 12px;
            opacity: 0.9;
        }

        .profile-body {
            padding: 10px 0;
        }

        .profile-item {
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .profile-item:hover {
            background: #f8f9fa;
            color: var(--secondary-color);
        }

        .profile-item i {
            width: 20px;
            text-align: center;
        }

        .profile-divider {
            height: 1px;
            background: #eee;
            margin: 5px 0;
        }

        .profile-footer {
            padding: 10px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }

        .badge {
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .bg-success {
            background-color: #2f855a !important;
        }

        .bg-warning {
            background-color: #d69e2e !important;
            color: #1f2933 !important;
        }

        .bg-danger {
            background-color: #c2410c !important;
        }

        .bg-info {
            background-color: #2f6f73 !important;
        }

        .btn {
            border-radius: 6px;
            font-weight: 600;
        }

        .btn-outline-primary {
            color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-sm {
            padding: 0.35rem 0.6rem;
        }

        .restock-item,
        .low-stock-item,
        .product-card,
        .cart-section,
        .pos-header {
            border-radius: 8px !important;
            box-shadow: var(--shadow-sm) !important;
        }

        .restock-item {
            background: var(--surface) !important;
            border: 1px solid var(--border-color);
            border-left-width: 4px;
        }

        .restock-item:hover,
        .product-card:hover {
            transform: none !important;
        }

        .metric {
            border-radius: 6px !important;
            background: #f8fafb !important;
        }

        .metric-value {
            color: var(--text-color) !important;
        }

        .metric-label {
            color: var(--muted-color) !important;
            letter-spacing: 0.04em;
        }

        .stat-card.manager-primary,
        .stat-card.manager-success,
        .stat-card.manager-warning,
        .stat-card.manager-info {
            background: var(--surface) !important;
            color: var(--text-color) !important;
        }

        .stat-card.manager-primary { border-left-color: var(--secondary-color); }
        .stat-card.manager-success { border-left-color: var(--success-color); }
        .stat-card.manager-warning { border-left-color: var(--warning-color); }
        .stat-card.manager-info { border-left-color: #4a6f8a; }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo $baseUrl; ?>dashboard.php">
                <img src="<?php echo $baseUrl; ?>assets/images/triple8_logo.png?v=<?php echo time(); ?>" alt="Triple 8 Logo" style="width: 34px; height: 34px; border-radius: 50%; object-fit: contain; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
                <span>Triple 8 <small class="d-none d-md-inline ms-1 text-info" style="font-size:.72rem;font-weight:600;letter-spacing:.05em;">INVENTORY SYSTEM</small></span>
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <!-- Hands-Free Voice Assistant Navbar Status -->
                <button type="button" class="btn btn-sm btn-outline-light d-flex align-items-center gap-2 rounded-pill px-3 py-1 fw-semibold shadow-sm" onclick="window.VoiceAssistant && window.VoiceAssistant.toggleHUD()" title="Hands-Free Voice Assistant (Say 'Turn On' or Click to Open Control)" style="font-size: 0.82rem; border-color: rgba(132, 204, 22, 0.4); background: rgba(101, 179, 24, 0.15);">
                    <i class="fas fa-microphone-lines text-warning"></i>
                    <span id="navVoiceStatusPill" class="badge bg-warning text-dark">VOICE: STANDBY</span>
                </button>
            </div>
        </div>
    </nav>
    
    <script>
        window.currentUserRole = '<?php echo htmlspecialchars(getUserRole() ?? "guest", ENT_QUOTES, "UTF-8"); ?>';
        window.currentUserId = <?php echo (int)(getUserId() ?? 0); ?>;
        window.currentUsername = '<?php echo htmlspecialchars(getUsername() ?? "", ENT_QUOTES, "UTF-8"); ?>';

        // Universal Voice Target Highlighting
        function highlightVoiceTarget(element) {
            if (!element) return;
            try {
                element.classList.add('voice-target-highlight');
                element.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                setTimeout(() => {
                    element.classList.remove('voice-target-highlight');
                }, 900);
            } catch (e) {}
        }

        // Logout confirmation
        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = '<?php echo $baseUrl; ?>logout.php';
            }
        }
    </script>
    
    <!-- Hands-Free Voice Assistant Engine (Universal Element Control & Proximity Focus) -->
    <script src="<?php echo $baseUrl; ?>assets/js/voice_assistant.js?v=<?php echo time(); ?>"></script>
</head>
