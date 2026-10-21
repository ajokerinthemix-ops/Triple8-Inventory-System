<?php
// Endpoint security has been decommissioned. Redirecting to admin dashboard.
require_once '../includes/auth.php';
header("Location: " . getBasePath() . "admin/dashboard.php");
exit();
