<?php
/**
 * AI Retail Intelligence Assistant API Engine
 * 
 * Provides natural language intent understanding, real-time analytics retrieval,
 * anomaly audits, proactive daily briefings, context-aware diagnostics,
 * and automated What-If simulation integration.
 * 
 * Safe Architecture: Zero direct or silent database modifications.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is logged in
if (!isLoggedIn() && php_sapi_name() !== 'cli') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$currentUserRole = getUserRole();
$currentUsername = getUsername();
$currentUserId = getUserId();

$action = trim($_GET['action'] ?? 'chat');

// ==========================================
// 1. CORE ANALYTICAL BACKEND TOOLS (GROUNDED)
// ==========================================

class RetailAnalyticsTools {
    private mysqli $db;
    private string $userRole;

    public function __construct(mysqli $db, string $userRole = 'admin') {
        $this->db = $db;
        $this->userRole = $userRole;
    }

    /**
     * Get stock overview & critical items
     */
    public function getLowStockProducts(int $limit = 10): array {
        $query = "
            SELECT p.id, p.sku, p.name, p.stock as stock_quantity, p.reorder_level, p.unit, p.price, p.cost_price,
                   c.name as category_name, s.name as supplier_name,
                   IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.stock <= p.reorder_level
            ORDER BY (p.stock - p.reorder_level) ASC, p.stock ASC
            LIMIT $limit
        ";
        $res = $this->db->query($query);
        $items = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['ads'] = $this->getProductAds((int)$row['id'], 30);
                $row['days_of_supply'] = $row['ads'] > 0 ? round($row['stock_quantity'] / $row['ads'], 1) : ($row['stock_quantity'] == 0 ? 0 : 999);
                $items[] = $row;
            }
        }
        return $items;
    }

    /**
     * Get Average Daily Sales (ADS) over $days
     */
    public function getProductAds(int $productId, int $days = 30): float {
        $stmt = $this->db->prepare("
            SELECT IFNULL(SUM(si.quantity), 0) as total_sold
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE si.product_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $productId, $days);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $sold = (float)($row['total_sold'] ?? 0);
            return round($sold / max(1, $days), 2);
        }
        return 0.5;
    }

    /**
     * Find product by fuzzy name, sku, or ID
     */
    public function findProduct(string $query, ?int $contextProductId = null): ?array {
        $clean = trim($query);
        
        // If pronoun/context requested or valid ID given
        if ($contextProductId && ($clean === '' || preg_match('/(this|it|current|the product|this product|selected|selling well|doing well)/i', $clean))) {
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name, s.name as supplier_name,
                       IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.id = ?
            ");
            $stmt->bind_param("i", $contextProductId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res) return $this->enrichProductMetrics($res);
        }

        if (is_numeric($clean)) {
            $pid = (int)$clean;
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name, s.name as supplier_name,
                       IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.id = ? OR p.barcode = ?
            ");
            $stmt->bind_param("is", $pid, $clean);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res) return $this->enrichProductMetrics($res);
        }

        // Search by exact SKU or name match
        $like = "%" . $clean . "%";
        $stmt = $this->db->prepare("
            SELECT p.*, c.name as category_name, s.name as supplier_name,
                   IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?
            ORDER BY CASE WHEN p.name LIKE ? THEN 1 ELSE 2 END, p.name ASC
            LIMIT 1
        ");
        $exactLike = $clean . "%";
        $stmt->bind_param("ssss", $like, $like, $like, $exactLike);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($res) {
            return $this->enrichProductMetrics($res);
        }

        // Context fallback
        if ($contextProductId) {
            $cStmt = $this->db->prepare("
                SELECT p.*, c.name as category_name, s.name as supplier_name,
                       IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.id = ?
            ");
            $cStmt->bind_param("i", $contextProductId);
            $cStmt->execute();
            $cRes = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
            if ($cRes) return $this->enrichProductMetrics($cRes);
        }

        return null;
    }

    private function enrichProductMetrics(array $p): array {
        $pid = (int)$p['id'];
        $stock = (int)($p['stock_quantity'] ?? $p['stock'] ?? 0);
        $p['stock_quantity'] = $stock;
        $p['stock'] = $stock;
        $p['unit'] = $p['unit'] ?? 'units';
        $p['reorder_level'] = (float)($p['reorder_level'] ?? 10);
        $p['supplier_name'] = $p['supplier_name'] ?? 'Primary Supplier';
        $p['lead_time_days'] = (int)($p['lead_time_days'] ?? 3);

        $p['ads_7d'] = $this->getProductAds($pid, 7);
        $p['ads_30d'] = $this->getProductAds($pid, 30);
        
        // Sales trend: 7d vs 30d baseline
        if ($p['ads_30d'] > 0) {
            $p['trend_pct'] = round((($p['ads_7d'] - $p['ads_30d']) / $p['ads_30d']) * 100, 1);
        } else {
            $p['trend_pct'] = $p['ads_7d'] > 0 ? 100 : 0;
        }

        $p['days_of_supply'] = $p['ads_30d'] > 0 ? round($stock / $p['ads_30d'], 1) : ($stock == 0 ? 0 : 999);
        $p['stockout_day'] = ($p['ads_30d'] > 0 && $stock > 0) ? (int)floor($stock / $p['ads_30d']) : ($stock == 0 ? 0 : null);
        
        // Suggested replenishment
        $leadTime = (int)$p['lead_time_days'];
        $safetyStock = max(5, (int)ceil($p['reorder_level'] * 0.5));
        $targetStock = (int)ceil(($p['ads_30d'] * ($leadTime + 14)) + $safetyStock);
        $p['suggested_restock_qty'] = max(0, $targetStock - $stock);

        return $p;
    }

    /**
     * Detect inventory anomalies and unusual adjustments
     */
    public function getInventoryAnomalies(int $limit = 6): array {
        $hasAudit = $this->db->query("SHOW TABLES LIKE 'inventory_adjustments'")->num_rows > 0;
        if (!$hasAudit) {
            $hasAudit = $this->db->query("SHOW TABLES LIKE 'inventory_logs'")->num_rows > 0;
            if (!$hasAudit) return [];
        }

        // Try inventory_adjustments or inventory_logs
        $table = $this->db->query("SHOW TABLES LIKE 'inventory_adjustments'")->num_rows > 0 ? 'inventory_adjustments' : 'inventory_logs';
        
        $anomalies = [];
        $res = $this->db->query("
            SELECT a.*, p.name as product_name, p.sku, p.stock as current_stock,
                   u.username as adjusted_by_user
            FROM $table a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 15
        ");

        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $qtyChange = abs((float)($r['quantity_change'] ?? $r['quantity'] ?? 0));
                // If single adjustment exceeds 20 units or reason contains loss/damage/error
                $reason = strtolower($r['reason'] ?? $r['notes'] ?? '');
                $isAnomaly = $qtyChange >= 15 || strpos($reason, 'damage') !== false || strpos($reason, 'loss') !== false || strpos($reason, 'discrepancy') !== false || strpos($reason, 'theft') !== false;

                if ($isAnomaly && count($anomalies) < $limit) {
                    $anomalies[] = [
                        'product_id' => $r['product_id'],
                        'product_name' => $r['product_name'] ?? 'Product #' . $r['product_id'],
                        'sku' => $r['sku'] ?? 'N/A',
                        'qty_change' => $r['quantity_change'] ?? $r['quantity'] ?? 0,
                        'reason' => $r['reason'] ?? $r['notes'] ?? 'Manual Adjustment',
                        'user' => $r['adjusted_by_user'] ?? 'System',
                        'created_at' => $r['created_at']
                    ];
                }
            }
        }
        return $anomalies;
    }

    /**
     * Get slow-moving & dead stock
     */
    public function getSlowMovingProducts(int $limit = 8): array {
        $res = $this->db->query("
            SELECT p.id, p.sku, p.name, p.stock as stock_quantity, p.price, p.cost_price, c.name as category_name,
                   p.updated_at,
                   IFNULL((
                       SELECT SUM(si.quantity) 
                       FROM sale_items si 
                       JOIN sales s ON si.sale_id = s.id 
                       WHERE si.product_id = p.id AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   ), 0) as units_sold_30d
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock > 10
            HAVING units_sold_30d <= 2
            ORDER BY (p.stock * p.cost_price) DESC, p.stock DESC
            LIMIT $limit
        ");

        $items = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['tied_up_capital'] = round($r['stock_quantity'] * (float)$r['cost_price'], 2);
                $r['status'] = $r['units_sold_30d'] == 0 ? 'Dead Stock' : 'Slow Moving';
                $items[] = $r;
            }
        }
        return $items;
    }

    /**
     * Get products by category / department
     */
    public function getProductsByCategory(string $catKeyword): array {
        $like = "%" . $catKeyword . "%";
        $stmt = $this->db->prepare("
            SELECT p.id, p.sku, p.name, p.stock as stock_quantity, p.reorder_level, p.price, p.cost_price, p.unit, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE c.name LIKE ? OR p.name LIKE ?
            ORDER BY p.stock ASC
            LIMIT 15
        ");
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($r = $res->fetch_assoc()) {
            $r['ads'] = $this->getProductAds((int)$r['id'], 30);
            $r['days_of_supply'] = $r['ads'] > 0 ? round($r['stock_quantity'] / $r['ads'], 1) : 999;
            $items[] = $r;
        }
        $stmt->close();
        return $items;
    }

    /**
     * Overall inventory health KPIs
     */
    public function getInventoryHealth(): array {
        $totalSkus = 0;
        $totalUnits = 0;
        $totalValuation = 0.0;
        $outOfStock = 0;
        $lowStock = 0;
        $healthyStock = 0;

        $res = $this->db->query("
            SELECT 
                COUNT(*) as total_skus,
                IFNULL(SUM(stock), 0) as total_units,
                IFNULL(SUM(stock * cost_price), 0) as total_valuation,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN stock > 0 AND stock <= reorder_level THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock > reorder_level THEN 1 ELSE 0 END) as healthy_stock
            FROM products
            WHERE is_active = 1 OR is_active IS NULL
        ");

        if ($res && $row = $res->fetch_assoc()) {
            $totalSkus = (int)$row['total_skus'];
            $totalUnits = (int)$row['total_units'];
            $totalValuation = (float)$row['total_valuation'];
            $outOfStock = (int)$row['out_of_stock'];
            $lowStock = (int)$row['low_stock'];
            $healthyStock = (int)$row['healthy_stock'];
        }

        // Sales today
        $today = date('Y-m-d');
        $sRes = $this->db->query("SELECT IFNULL(SUM(total_amount), 0) as rev, COUNT(*) as txn FROM sales WHERE DATE(created_at) = '$today' AND (is_voided = 0 OR is_voided IS NULL)");
        $sRow = $sRes ? $sRes->fetch_assoc() : ['rev' => 0, 'txn' => 0];

        $mRes = $this->db->query("SELECT IFNULL(SUM(total_amount), 0) as rev FROM sales WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND (is_voided = 0 OR is_voided IS NULL)");
        $mRow = $mRes ? $mRes->fetch_assoc() : ['rev' => 0];

        return [
            'total_skus' => $totalSkus,
            'total_units' => $totalUnits,
            'total_valuation' => round($totalValuation, 2),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'healthy_stock' => $healthyStock,
            'today_revenue' => (float)($sRow['rev'] ?? 0),
            'today_transactions' => (int)($sRow['txn'] ?? 0),
            'mtd_revenue' => (float)($mRow['rev'] ?? 0)
        ];
    }

    /**
     * Sales & Financial Overview
     */
    public function getSalesMetrics(): array {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Today
        $tRes = $this->db->query("SELECT IFNULL(SUM(total_amount), 0) as rev, COUNT(*) as txn FROM sales WHERE DATE(created_at) = '$today' AND (is_voided = 0 OR is_voided IS NULL)");
        $todayStats = $tRes ? $tRes->fetch_assoc() : ['rev' => 0, 'txn' => 0];

        // Yesterday
        $yRes = $this->db->query("SELECT IFNULL(SUM(total_amount), 0) as rev, COUNT(*) as txn FROM sales WHERE DATE(created_at) = '$yesterday' AND (is_voided = 0 OR is_voided IS NULL)");
        $yestStats = $yRes ? $yRes->fetch_assoc() : ['rev' => 0, 'txn' => 0];

        // MTD
        $mRes = $this->db->query("SELECT IFNULL(SUM(total_amount), 0) as rev, COUNT(*) as txn FROM sales WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND (is_voided = 0 OR is_voided IS NULL)");
        $mtdStats = $mRes ? $mRes->fetch_assoc() : ['rev' => 0, 'txn' => 0];

        // All Time & Profit Margin
        $allRes = $this->db->query("
            SELECT 
                COUNT(DISTINCT s.id) as total_txns,
                IFNULL(SUM(s.total_amount), 0) as total_revenue,
                IFNULL(SUM(si.quantity * si.cost_price), 0) as total_cogs
            FROM sales s
            LEFT JOIN sale_items si ON s.id = si.sale_id
            WHERE s.is_voided = 0 OR s.is_voided IS NULL
        ");
        $allStats = $allRes ? $allRes->fetch_assoc() : ['total_txns' => 0, 'total_revenue' => 0, 'total_cogs' => 0];
        
        $grossProfit = max(0, (float)$allStats['total_revenue'] - (float)$allStats['total_cogs']);
        $marginPct = $allStats['total_revenue'] > 0 ? round(($grossProfit / (float)$allStats['total_revenue']) * 100, 1) : 0;
        $avgBasket = $todayStats['txn'] > 0 ? round((float)$todayStats['rev'] / (int)$todayStats['txn'], 2) : 0;

        return [
            'today_rev' => (float)$todayStats['rev'],
            'today_txn' => (int)$todayStats['txn'],
            'avg_basket' => $avgBasket,
            'yesterday_rev' => (float)$yestStats['rev'],
            'yesterday_txn' => (int)$yestStats['txn'],
            'mtd_rev' => (float)$mtdStats['rev'],
            'mtd_txn' => (int)$mtdStats['txn'],
            'all_time_rev' => (float)$allStats['total_revenue'],
            'all_time_txn' => (int)$allStats['total_txns'],
            'gross_profit' => $grossProfit,
            'profit_margin_pct' => $marginPct
        ];
    }

    /**
     * Top Selling Products
     */
    public function getTopProducts(int $limit = 5): array {
        $res = $this->db->query("
            SELECT p.id, p.name, p.sku, p.price, p.stock,
                   SUM(si.quantity) as units_sold,
                   SUM(si.quantity * si.price) as revenue
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE (s.is_voided = 0 OR s.is_voided IS NULL)
            GROUP BY p.id
            ORDER BY units_sold DESC
            LIMIT $limit
        ");
        $items = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $items[] = $r;
        }
        return $items;
    }

    /**
     * Payment Methods Breakdown
     */
    public function getPaymentMethodBreakdown(): array {
        $res = $this->db->query("
            SELECT payment_method, COUNT(*) as txn_count, IFNULL(SUM(total_amount), 0) as total_amount
            FROM sales
            WHERE (is_voided = 0 OR is_voided IS NULL)
            GROUP BY payment_method
            ORDER BY total_amount DESC
        ");
        $methods = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $methods[] = $r;
        }
        return $methods;
    }

    /**
     * Cashier Performance
     */
    public function getCashierPerformance(): array {
        $res = $this->db->query("
            SELECT u.id, u.username, COUNT(s.id) as txn_count, IFNULL(SUM(s.total_amount), 0) as total_revenue
            FROM users u
            JOIN sales s ON u.id = s.cashier_id
            WHERE (s.is_voided = 0 OR s.is_voided IS NULL)
            GROUP BY u.id, u.username
            ORDER BY total_revenue DESC
            LIMIT 5
        ");
        $cashiers = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $cashiers[] = $r;
        }
        return $cashiers;
    }

    /**
     * Supplier Directory & Purchase Orders
     */
    public function getSuppliers(int $limit = 8): array {
        $res = $this->db->query("
            SELECT s.*, COUNT(p.id) as product_count
            FROM suppliers s
            LEFT JOIN products p ON s.id = p.supplier_id
            GROUP BY s.id
            ORDER BY s.name ASC
            LIMIT $limit
        ");
        $suppliers = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $suppliers[] = $r;
        }
        return $suppliers;
    }

    /**
     * Customer & Loyalty Stats
     */
    public function getCustomerMetrics(): array {
        $res = $this->db->query("
            SELECT 
                COUNT(*) as total_customers,
                IFNULL(SUM(total_spent), 0) as total_spent,
                SUM(CASE WHEN membership_tier = 'Gold' THEN 1 ELSE 0 END) as gold_count,
                SUM(CASE WHEN membership_tier = 'Silver' THEN 1 ELSE 0 END) as silver_count,
                SUM(CASE WHEN membership_tier = 'Bronze' THEN 1 ELSE 0 END) as bronze_count
            FROM customers
        ");
        $stats = $res ? $res->fetch_assoc() : ['total_customers' => 0];

        $topRes = $this->db->query("SELECT name, phone, membership_tier, loyalty_points, total_spent FROM customers ORDER BY total_spent DESC LIMIT 3");
        $topCustomers = [];
        if ($topRes) while ($r = $topRes->fetch_assoc()) $topCustomers[] = $r;

        return [
            'stats' => $stats,
            'top_customers' => $topCustomers
        ];
    }

    /**
     * Execute What-If simulation inline
     */
    public function simulateWhatIf(float $demandChangePct, int $periodDays = 30, int $supplierDelayDays = 0): array {
        $res = $this->db->query("
            SELECT p.id, p.sku, p.name, p.stock as stock_quantity, p.reorder_level, p.unit, p.price, p.cost_price,
                   IFNULL(p.lead_time_days, IFNULL(s.lead_time_days, 3)) as lead_time_days,
                   c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
        ");

        $totalAnalyzed = 0;
        $stockouts = [];
        $atRisk = [];

        if ($res) {
            while ($p = $res->fetch_assoc()) {
                $totalAnalyzed++;
                $pid = (int)$p['id'];
                $baseAds = $this->getProductAds($pid, 30);
                if ($baseAds <= 0) $baseAds = max(0.5, round($p['reorder_level'] / 10, 1));
                
                $projectedDaily = round($baseAds * (1 + ($demandChangePct / 100)), 2);
                $projectedTotal = round($projectedDaily * $periodDays, 1);
                $endingStock = round($p['stock_quantity'] - $projectedTotal, 1);

                if ($endingStock <= 0) {
                    $stockoutDay = $projectedDaily > 0 ? max(1, (int)floor($p['stock_quantity'] / $projectedDaily)) : 1;
                    $p['projected_daily'] = $projectedDaily;
                    $p['stockout_day'] = $stockoutDay;
                    $p['ending_stock'] = $endingStock;
                    $stockouts[] = $p;
                } elseif ($endingStock < $p['reorder_level']) {
                    $p['projected_daily'] = $projectedDaily;
                    $p['ending_stock'] = $endingStock;
                    $atRisk[] = $p;
                }
            }
        }

        return [
            'analyzed_count' => $totalAnalyzed,
            'stockouts' => $stockouts,
            'stockout_count' => count($stockouts),
            'at_risk' => $atRisk,
            'at_risk_count' => count($atRisk),
            'demand_shift_pct' => $demandChangePct,
            'period_days' => $periodDays
        ];
    }
}

$tools = new RetailAnalyticsTools($conn, $currentUserRole ?? 'admin');

if (php_sapi_name() === 'cli' && realpath(__FILE__) !== realpath($_SERVER['SCRIPT_FILENAME'] ?? '') && empty($_GET['action'])) {
    return;
}

/**
 * Proactive Daily Briefing
 */
if ($action === 'daily_briefing') {
    $health = $tools->getInventoryHealth();
    $lowStock = $tools->getLowStockProducts(5);
    $slowMovers = $tools->getSlowMovingProducts(4);
    $anomalies = $tools->getInventoryAnomalies(3);

    $criticalItems = [];
    $warningItems = [];
    $infoItems = [];

    // Critical: Out of stock or stockout within 2 days
    foreach ($lowStock as $p) {
        if ($p['stock_quantity'] == 0) {
            $criticalItems[] = [
                'type' => 'out_of_stock',
                'title' => "{$p['name']} is completely OUT OF STOCK (0 {$p['unit']}).",
                'action_label' => "Restock {$p['name']}",
                'product_id' => $p['id'],
                'suggested_qty' => $p['suggested_restock_qty']
            ];
        } elseif ($p['days_of_supply'] <= 2 && $p['ads'] > 0) {
            $criticalItems[] = [
                'type' => 'imminent_stockout',
                'title' => "{$p['name']} has only {$p['stock_quantity']} {$p['unit']} left (~{$p['days_of_supply']} days of supply at current ADS).",
                'action_label' => "Review Replenishment",
                'product_id' => $p['id'],
                'suggested_qty' => $p['suggested_restock_qty']
            ];
        } else {
            $warningItems[] = [
                'type' => 'low_stock',
                'title' => "{$p['name']} is below reorder threshold ({$p['stock_quantity']}/{$p['reorder_level']} {$p['unit']}).",
                'product_id' => $p['id']
            ];
        }
    }

    // Warnings: Anomalies or dead capital
    foreach ($anomalies as $a) {
        $warningItems[] = [
            'type' => 'anomaly',
            'title' => "Unusual adjustment detected: {$a['product_name']} ({$a['qty_change']} units) — Reason: {$a['reason']}.",
            'product_id' => $a['product_id']
        ];
    }

    foreach ($slowMovers as $sm) {
        $warningItems[] = [
            'type' => 'slow_moving',
            'title' => "Slow-moving inventory: {$sm['name']} ({$sm['stock_quantity']} units, ₱" . number_format($sm['tied_up_capital'], 2) . " tied up).",
            'product_id' => $sm['id']
        ];
    }

    // Info items: Revenue & healthy status
    $infoItems[] = [
        'type' => 'general_health',
        'title' => "Total catalog valuation: ₱" . number_format($health['total_valuation'], 2) . " across {$health['total_skus']} active items. {$health['healthy_stock']} items are in a healthy stock range."
    ];
    if ($health['today_revenue'] > 0) {
        $infoItems[] = [
            'type' => 'sales_pulse',
            'title' => "Today's POS volume: ₱" . number_format($health['today_revenue'], 2) . " across {$health['today_transactions']} customer transactions."
        ];
    }

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'health' => $health,
        'critical' => $criticalItems,
        'warning' => $warningItems,
        'info' => $infoItems,
        'summary_headline' => count($criticalItems) > 0 
            ? "I identified " . count($criticalItems) . " critical inventory situation(s) requiring your immediate attention today."
            : "Inventory operations are stable. " . count($warningItems) . " item(s) are flagged for proactive review."
    ]);
    exit();
}

/**
 * Natural Language Chat & Reasoning
 */
if ($action === 'chat') {
    $rawInput = !empty($GLOBALS['MOCK_AI_INPUT']) ? $GLOBALS['MOCK_AI_INPUT'] : file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;
    
    $query = trim($data['message'] ?? $data['query'] ?? '');
    $pageContext = trim($data['page_context'] ?? '');
    $selectedProductId = isset($data['selected_product_id']) && (int)$data['selected_product_id'] > 0 ? (int)$data['selected_product_id'] : null;
    $selectedProductName = trim($data['selected_product_name'] ?? '');

    if ($query === '') {
        echo json_encode(['success' => false, 'error' => 'Message query is required.']);
        exit();
    }

    $lower = strtolower($query);

    // ========================================================
    // INTENT RECOGNITION & MULTI-STRATEGY REASONING
    // ========================================================

    $reply = "";
    $priority = "normal";
    $intent = "general";
    $actionCard = null;
    $dataPoints = [];

    // Strategy 1: Simulation Inquiry (e.g. "What happens if sales increase by 20% next week?")
    if (preg_match('/what if|what happens if|simulate|demand increase|sales drop|sales increase|surge/i', $lower)) {
        $intent = "simulation";
        
        // Extract percentage
        $pct = 20.0; // default
        if (preg_match('/([+-]?\d+(?:\.\d+)?)\s*%/i', $lower, $m)) {
            $pct = (float)$m[1];
            if (strpos($lower, 'drop') !== false || strpos($lower, 'decrease') !== false) {
                $pct = -abs($pct);
            }
        } elseif (strpos($lower, 'decrease') !== false || strpos($lower, 'drop') !== false) {
            $pct = -15.0;
        }

        // Extract period
        $period = 30;
        if (preg_match('/(\d+)\s*(days?|d)/i', $lower, $m)) {
            $period = (int)$m[1];
        } elseif (strpos($lower, 'next week') !== false || strpos($lower, '1 week') !== false || strpos($lower, '7 days') !== false) {
            $period = 7;
        } elseif (strpos($lower, 'month') !== false) {
            $period = 30;
        }

        $simRes = $tools->simulateWhatIf($pct, $period);
        $priority = $simRes['stockout_count'] > 0 ? 'critical' : ($simRes['at_risk_count'] > 0 ? 'warning' : 'info');

        $shiftSign = $pct >= 0 ? "+{$pct}%" : "{$pct}%";
        $reply = "### 📊 What-If Projection ($shiftSign Demand Shift over $period Days)\n\n";
        $reply .= "I ran a live simulation across all **{$simRes['analyzed_count']} catalog products**:\n\n";
        
        if ($simRes['stockout_count'] > 0) {
            $reply .= "🚨 **{$simRes['stockout_count']} Product(s) Projected to Stock Out:**\n";
            foreach (array_slice($simRes['stockouts'], 0, 4) as $s) {
                $reply .= "- **{$s['name']}**: Stockout projected by **Day {$s['stockout_day']}** (Current stock: {$s['stock_quantity']} {$s['unit']}, Projected daily demand: {$s['projected_daily']}).\n";
            }
            $reply .= "\n";
        }

        if ($simRes['at_risk_count'] > 0) {
            $reply .= "⚠️ **{$simRes['at_risk_count']} Product(s) Falling Below Safety Reorder Thresholds.**\n\n";
        }

        if ($simRes['stockout_count'] === 0 && $simRes['at_risk_count'] === 0) {
            $reply .= "✅ **All items remain well-stocked.** No stockouts or safety breaches projected under this demand scenario.\n\n";
        }

        $reply .= "Would you like me to prepare restock drafts for the at-risk items?";
        
        $actionCard = [
            'type' => 'navigation',
            'title' => 'Open Inventory Overview',
            'url' => 'inventory.php',
            'button_text' => 'View Inventory'
        ];
    }

    // Strategy 2: Restock Recommendations (e.g. "What should I restock today?", "Can you prepare what we need to restock?")
    elseif (preg_match('/what should i restock|what to restock|restock today|need to restock|reorder list|who needs restock|replenish|order recommendations/i', $lower)) {
        $intent = "restock_recommendation";
        $low = $tools->getLowStockProducts(6);

        if (empty($low)) {
            $reply = "### ✅ Inventory Restock Status\n\nAll products currently have stock levels safely above their reorder thresholds. No urgent replenishment orders are required today.";
            $priority = "info";
        } else {
            $priority = "critical";
            $reply = "### 📦 Recommended Restock Replenishments\n\n";
            $reply .= "Based on current stock levels, verified 30-day Average Daily Sales (ADS), and supplier lead times, I recommend reviewing replenishment for the following items:\n\n";

            $topItem = $low[0];
            foreach ($low as $idx => $p) {
                $stockoutNote = $p['stock_quantity'] == 0 ? "**OUT OF STOCK**" : "{$p['days_of_supply']} days of supply left";
                $reply .= ($idx + 1) . ". **{$p['name']}** ({$p['sku']})\n";
                $reply .= "   - Current Stock: **{$p['stock_quantity']} {$p['unit']}** (Reorder point: {$p['reorder_level']})\n";
                $reply .= "   - Velocity: {$p['ads']} {$p['unit']}/day • $stockoutNote\n";
                $reply .= "   - **Recommended Restock Quantity:** **{$p['suggested_restock_qty']} {$p['unit']}** (Supplier: {$p['supplier_name']}, Lead time: {$p['lead_time_days']}d)\n\n";
            }

            $reply .= "You can prepare a restock draft below. *Remember, drafts will only prefill the stock adjustment form for your review and will NOT modify stock until you explicitly confirm.*";

            $actionCard = [
                'type' => 'restock_draft',
                'title' => "Prepare Restock for {$topItem['name']}",
                'product_id' => $topItem['id'],
                'product_name' => $topItem['name'],
                'suggested_qty' => $topItem['suggested_restock_qty'],
                'unit' => $topItem['unit'],
                'current_stock' => $topItem['stock_quantity'],
                'reorder_level' => $topItem['reorder_level'],
                'reason' => "AI Restock Recommendation: Low stock ({$topItem['stock_quantity']} {$topItem['unit']}) vs ADS {$topItem['ads']}/day.",
                'review_url' => "inventory.php?action=restock_draft&draft_product_id={$topItem['id']}&draft_qty={$topItem['suggested_restock_qty']}&draft_reason=" . urlencode("AI Restock Recommendation ({$topItem['suggested_restock_qty']} {$topItem['unit']})")
            ];
        }
    }

    // Strategy 3: Specific Product / Context Reference ("Is this okay?", "Should I order more Coca-Cola?", "Why did this product run out?")
    elseif (
        $selectedProductId !== null ||
        preg_match('/(is this okay|about this|coca|coke|san miguel|bread|coffee|noodles|soap|water|t-shirt|headphone|this product|order more of this)/i', $lower)
    ) {
        $extractedName = $query;
        // Strip common prompt prefixes
        $extractedName = preg_replace('/^(should i order more of|tell me about|how is|is this okay|check|analyze|what happened to|why is|why did)\s+/i', '', $extractedName);
        $extractedName = preg_replace('/\s+(selling slowly|run out|doing|okay)\??$/i', '', $extractedName);

        $product = $tools->findProduct($extractedName, $selectedProductId);

        if ($product) {
            $intent = "product_diagnostic";
            $pid = (int)$product['id'];
            $stock = $product['stock_quantity'];
            $reorder = $product['reorder_level'];
            $unit = $product['unit'];
            $ads = $product['ads_30d'];
            $trend = $product['trend_pct'];
            $dos = $product['days_of_supply'];
            $lead = $product['lead_time_days'];

            $trendText = $trend > 0 ? "+{$trend}% (surging)" : ($trend < 0 ? "{$trend}% (cooling)" : "steady");
            
            $reply = "### 🔍 Diagnostic: **{$product['name']}**\n\n";
            $reply .= "- **Current Stock:** **{$stock} {$unit}** (Safety Reorder Level: {$reorder} {$unit})\n";
            $reply .= "- **Sales Velocity:** **{$ads} {$unit}/day** (7-day trend: {$trendText})\n";
            $reply .= "- **Supplier Lead Time:** **{$lead} days** (Supplier: {$product['supplier_name']})\n";
            $reply .= "- **Est. Days of Supply:** **{$dos} days**\n\n";

            if ($stock == 0) {
                $priority = "critical";
                $reply .= "🚨 **Critical Stockout Alert:** This product is completely out of stock. At normal velocity ({$ads} {$unit}/day), you are losing potential sales daily.\n\n";
                $reply .= "**Recommendation:** Replenish at least **{$product['suggested_restock_qty']} {$unit}** immediately.";
            } elseif ($stock <= $reorder) {
                $priority = "warning";
                $reply .= "⚠️ **Low Stock Warning:** Stock is at or below the reorder point. With a {$lead}-day supplier lead time, ordering now avoids supply interruption.\n\n";
                $reply .= "**Recommendation:** Prepare a replenishment of **{$product['suggested_restock_qty']} {$unit}**.";
            } elseif ($dos > 90 && $ads < 0.2) {
                $priority = "warning";
                $reply .= "⚠️ **Capital Inefficiency:** This product is moving very slowly. At current rates, existing inventory represents over 90 days of supply.\n\n";
                $reply .= "**Recommendation:** Avoid reordering. Consider bundling or running a promotional discount.";
            } else {
                $priority = "info";
                $reply .= "✅ **Healthy Inventory:** Current inventory is in a stable buffer and comfortably covers the expected lead time demand.";
            }

            if ($product['suggested_restock_qty'] > 0 && ($stock <= $reorder || $stock == 0)) {
                $actionCard = [
                    'type' => 'restock_draft',
                    'title' => "Prepare Restock Draft ({$product['suggested_restock_qty']} {$unit})",
                    'product_id' => $pid,
                    'product_name' => $product['name'],
                    'suggested_qty' => $product['suggested_restock_qty'],
                    'unit' => $unit,
                    'current_stock' => $stock,
                    'reorder_level' => $reorder,
                    'reason' => "AI Restock Draft for {$product['name']} based on {$lead}-day lead time.",
                    'review_url' => "inventory.php?action=restock_draft&draft_product_id={$pid}&draft_qty={$product['suggested_restock_qty']}&draft_reason=" . urlencode("Restock draft: {$product['suggested_restock_qty']} {$unit} for {$product['name']}")
                ];
            }
        } else {
            $reply = "I couldn't locate a specific product matching that inquiry in your catalog. Could you specify the product name, SKU, or click an item on the inventory table?";
            $priority = "normal";
        }
    }

    // Strategy 4: Anomalies & Irregular Movements ("Something feels wrong with inventory", "Unusual stock", "What happened yesterday?")
    elseif (preg_match('/unusual|something feels wrong|wrong with the inventory|anomaly|anomalies|discrepancy|theft|damage|adjustments/i', $lower)) {
        $intent = "anomaly_audit";
        $anomalies = $tools->getInventoryAnomalies(5);

        if (empty($anomalies)) {
            $reply = "### 🛡️ Inventory Movement Audit\n\nI reviewed recent stock adjustments across all catalog items. No abnormal variance, high-magnitude loss adjustments, or suspicious pattern shifts were detected.";
            $priority = "info";
        } else {
            $priority = "warning";
            $reply = "### ⚠️ Recent Inventory Anomalies & Adjustments\n\n";
            $reply .= "I scanned recent stock adjustments and identified **" . count($anomalies) . " unusual event(s)**:\n\n";

            foreach ($anomalies as $idx => $a) {
                $qtySign = $a['qty_change'] > 0 ? "+{$a['qty_change']}" : "{$a['qty_change']}";
                $reply .= ($idx + 1) . ". **{$a['product_name']}** ({$a['sku']})\n";
                $reply .= "   - Adjustment: **$qtySign units** by **{$a['user']}** on " . substr($a['created_at'], 0, 16) . "\n";
                $reply .= "   - Reason Logged: *\"{$a['reason']}\"*\n\n";
            }

            $reply .= "**Recommendation:** Review these adjustments with store personnel to verify physical shelf count vs system receipts.";
        }
    }

    // Strategy 5: Slow Moving & Dead Stock ("Which products are tying up too much money?", "Slow selling", "Dead stock")
    elseif (preg_match('/slow|tying up too much money|dead stock|slowest|not selling|inactive products/i', $lower)) {
        $intent = "slow_moving_audit";
        $slow = $tools->getSlowMovingProducts(6);

        if (empty($slow)) {
            $reply = "### 📈 Inventory Velocity Review\n\nAll items in stock have registered healthy sales activity over the past 30 days. No significant capital stagnation was detected.";
            $priority = "info";
        } else {
            $priority = "warning";
            $totalTiedCapital = array_sum(array_column($slow, 'tied_up_capital'));
            $reply = "### ⏳ Slow-Moving & Stagnant Inventory\n\n";
            $reply .= "I analyzed unit velocity over the past 30 days and found **" . count($slow) . " product(s)** tying up approximately **₱" . number_format($totalTiedCapital, 2) . "** in holding capital:\n\n";

            foreach ($slow as $idx => $s) {
                $soldText = $s['units_sold_30d'] == 0 ? "0 units sold in 30 days (**Dead Stock**)" : "{$s['units_sold_30d']} units sold in 30 days";
                $reply .= ($idx + 1) . ". **{$s['name']}** ({$s['category_name']})\n";
                $reply .= "   - On Hand: **{$s['stock_quantity']} units** (Capital tied up: ₱" . number_format($s['tied_up_capital'], 2) . ")\n";
                $reply .= "   - Velocity: $soldText\n\n";
            }

            $reply .= "**Actionable Strategies:**\n- Halt replenishment orders for these items.\n- Bundle with high-velocity companion items.\n- Consider promotional discounts to liberate working capital.";
        }
    }

    // Strategy 6: Sales & Financial Performance ("How much did we sell today?", "What is our revenue?", "Top selling products", "Profit margin")
    elseif (preg_match('/(how much did we sell|sales today|today sales|sales yesterday|revenue|gross profit|profit margin|top selling|best sellers|payment method|cashier|who sold|average basket)/i', $lower)) {
        $intent = "sales_analytics";
        $salesMetrics = $tools->getSalesMetrics();
        $topProds = $tools->getTopProducts(4);
        $payMethods = $tools->getPaymentMethodBreakdown();
        $cashiers = $tools->getCashierPerformance();

        if (strpos($lower, 'top') !== false || strpos($lower, 'best seller') !== false || strpos($lower, 'most sold') !== false) {
            $reply = "### 🏆 Top-Selling Products\n\n";
            if (empty($topProds)) {
                $reply .= "No completed sales recorded in the system yet.";
            } else {
                foreach ($topProds as $idx => $tp) {
                    $reply .= ($idx + 1) . ". **{$tp['name']}** ({$tp['sku']})\n";
                    $reply .= "   - Volume: **{$tp['units_sold']} units** sold • Revenue: **₱" . number_format((float)$tp['revenue'], 2) . "**\n";
                    $reply .= "   - Current Stock: **{$tp['stock']} remaining**\n\n";
                }
            }
        } elseif (strpos($lower, 'cashier') !== false || strpos($lower, 'who sold') !== false) {
            $reply = "### 👤 Cashier Sales Performance\n\n";
            if (empty($cashiers)) {
                $reply .= "No cashier transaction history recorded yet.";
            } else {
                foreach ($cashiers as $idx => $c) {
                    $reply .= ($idx + 1) . ". **{$c['username']}**: **₱" . number_format((float)$c['total_revenue'], 2) . "** across **{$c['txn_count']} transactions**\n";
                }
            }
        } elseif (strpos($lower, 'payment') !== false || strpos($lower, 'gcash') !== false || strpos($lower, 'cash') !== false) {
            $reply = "### 💳 Payment Methods Breakdown\n\n";
            if (empty($payMethods)) {
                $reply .= "No payment records found.";
            } else {
                foreach ($payMethods as $pm) {
                    $methodName = strtoupper($pm['payment_method'] ?: 'CASH');
                    $reply .= "- **$methodName**: ₱" . number_format((float)$pm['total_amount'], 2) . " ({$pm['txn_count']} transactions)\n";
                }
            }
        } else {
            // General Sales & Revenue
            $reply = "### 💰 Sales & Revenue Summary\n\n";
            $reply .= "- **Today's POS Sales:** **₱" . number_format($salesMetrics['today_rev'], 2) . "** across **{$salesMetrics['today_txn']} receipts** (Avg Basket: ₱" . number_format($salesMetrics['avg_basket'], 2) . ")\n";
            $reply .= "- **Yesterday's Sales:** ₱" . number_format($salesMetrics['yesterday_rev'], 2) . " ({$salesMetrics['yesterday_txn']} receipts)\n";
            $reply .= "- **Month-to-Date Revenue:** **₱" . number_format($salesMetrics['mtd_rev'], 2) . "** ({$salesMetrics['mtd_txn']} receipts)\n";
            $reply .= "- **All-Time Revenue:** ₱" . number_format($salesMetrics['all_time_rev'], 2) . " across {$salesMetrics['all_time_txn']} transactions\n";
            $reply .= "- **Overall Gross Profit Margin:** **{$salesMetrics['profit_margin_pct']}%** (Est. Gross Profit: ₱" . number_format($salesMetrics['gross_profit'], 2) . ")\n\n";
        }

        $priority = "info";
        $actionCard = [
            'type' => 'reports_link',
            'title' => 'Open Sales & Financial Reports',
            'url' => 'reports.php',
            'button_text' => 'View Detailed Analytics'
        ];
    }

    // Strategy 7: Suppliers & Delivery Queries ("Who are our suppliers?", "Supplier lead times", "Deliveries")
    elseif (preg_match('/supplier|vendor|delivery|lead time|purchase order/i', $lower)) {
        $intent = "supplier_inquiry";
        $suppliers = $tools->getSuppliers(6);

        $reply = "### 🚚 Supplier & Procurement Directory\n\n";
        if (empty($suppliers)) {
            $reply .= "No suppliers registered in the database.";
        } else {
            $reply .= "Here are your active suppliers and lead time benchmarks:\n\n";
            foreach ($suppliers as $idx => $s) {
                $lead = $s['lead_time_days'] ?? 3;
                $phone = $s['phone'] ? " • Tel: " . $s['phone'] : "";
                $reply .= ($idx + 1) . ". **{$s['name']}** (Lead Time: **$lead days**$phone)\n";
                $reply .= "   - Products Supplied: **{$s['product_count']} items**\n\n";
            }
            $reply .= "You can manage supplier records or log deliveries in the Inventory & Supplier modules.";
        }
        $priority = "info";
    }

    // Strategy 8: Customers & Loyalty Tier Queries ("Who are our top customers?", "Loyalty points", "Members")
    elseif (preg_match('/customer|loyalty|membership|member|top spender|gold tier/i', $lower)) {
        $intent = "customer_inquiry";
        $custData = $tools->getCustomerMetrics();
        $stats = $custData['stats'];
        $topCust = $custData['top_customers'];

        $reply = "### 👥 Customer & Loyalty Overview\n\n";
        $reply .= "- **Total Registered Customers:** **{$stats['total_customers']} members**\n";
        $reply .= "- **Tier Breakdown:** {$stats['gold_count']} Gold • {$stats['silver_count']} Silver • {$stats['bronze_count']} Bronze\n";
        $reply .= "- **Total Customer Spending:** ₱" . number_format((float)$stats['total_spent'], 2) . "\n\n";

        if (!empty($topCust)) {
            $reply .= "👑 **Top Customers by Lifetime Spend:**\n";
            foreach ($topCust as $idx => $tc) {
                $reply .= ($idx + 1) . ". **{$tc['name']}** ({$tc['membership_tier']}) — ₱" . number_format((float)$tc['total_spent'], 2) . " spent ({$tc['loyalty_points']} pts)\n";
            }
        }
        $priority = "info";
    }

    // Strategy 9: Navigation & How-To POS / Workflow Assistance
    elseif (preg_match('/(how to|how do i|take me to|go to|open|navigate|shortcut|help with)/i', $lower)) {
        $intent = "navigation_help";

        if (preg_match('/(pos|checkout|cashier|sell|sale counter|register)/i', $lower)) {
            $reply = "### 🛒 Point of Sale (POS) Guidance\n\n";
            $reply .= "To process a transaction:\n";
            $reply .= "1. Click items from catalog or search by name/SKU.\n";
            $reply .= "2. Select discounts if applicable (Senior/PWD, Member).\n";
            $reply .= "3. Choose payment method (Cash, GCash, Maya, Card) and enter tendered amount.\n";
            $reply .= "4. Click **Complete Sale & Print Receipt**.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Point of Sale (POS)',
                'url' => '../cashier/pos.php',
                'button_text' => 'Launch POS Terminal'
            ];
        } elseif (preg_match('/(transaction|sales history|sales log|receipt)/i', $lower)) {
            $reply = "### 🧾 Sales & Transactions\n\n";
            $reply .= "View complete transaction history, cashier sales logs, audit receipts, and payment method breakdowns.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Sales & Transactions',
                'url' => 'sales.php',
                'button_text' => 'View Transactions'
            ];
        } elseif (preg_match('/(inventory|stock|reorder|adjust)/i', $lower)) {
            $reply = "### 📦 Inventory & Stock Management\n\n";
            $reply .= "In the Inventory module, you can:\n";
            $reply .= "- Audit on-hand stock and view beginning vs ending quantities.\n";
            $reply .= "- Perform quick stock adjustments (damages, stocktakes, corrections).\n";
            $reply .= "- Export catalog stock reports to CSV.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Inventory Management',
                'url' => 'inventory.php',
                'button_text' => 'Go to Inventory'
            ];
        } elseif (preg_match('/(product|add product|edit product|catalog)/i', $lower)) {
            $reply = "### 🏷️ Product Catalog Management\n\n";
            $reply .= "To manage your products:\n";
            $reply .= "- Go to **Products** to add new items, update prices, upload product photos, and set safety reorder thresholds.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Product Management',
                'url' => 'products.php',
                'button_text' => 'Manage Products'
            ];
        } elseif (preg_match('/(category|categories|department)/i', $lower)) {
            $reply = "### 🗂️ Departments & Categories\n\n";
            $reply .= "Organize catalog items into hierarchical departments and categories with parent-child rollups.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Departments & Categories',
                'url' => 'categories.php',
                'button_text' => 'Manage Categories'
            ];
        } elseif (preg_match('/(report|analytics|chart|export)/i', $lower)) {
            $reply = "### 📈 Sales & Financial Reports\n\n";
            $reply .= "Access detailed daily, monthly, and yearly revenue breakdowns, payment distribution charts, and profit margins.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Reports & Analytics',
                'url' => 'reports.php',
                'button_text' => 'View Reports'
            ];
        } elseif (preg_match('/(user|staff|employee|account)/i', $lower)) {
            $reply = "### 👥 User Management\n\n";
            $reply .= "Manage administrative, manager, and cashier staff accounts, reset credentials, and oversee user activity.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open User Management',
                'url' => 'users.php',
                'button_text' => 'Manage Users'
            ];
        } elseif (preg_match('/(rbac|access control|permission|role)/i', $lower)) {
            $reply = "### 🛡️ Access Control & RBAC\n\n";
            $reply .= "Configure granular role-based access permissions, feature locks, and security boundaries.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Access Control',
                'url' => 'rbac.php',
                'button_text' => 'Access Control'
            ];
        } elseif (preg_match('/(backup|restore|database|recovery)/i', $lower)) {
            $reply = "### 💾 Database Backup & Restore\n\n";
            $reply .= "Generate one-click SQL dumps, download automated compressed archives, or restore previous system states.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Backup & Restore',
                'url' => 'backup.php',
                'button_text' => 'Backup Engine'
            ];
        } elseif (preg_match('/(setting|configuration|preference)/i', $lower)) {
            $reply = "### ⚙️ Store Settings\n\n";
            $reply .= "Configure store business details, tax rates, currency formatting, receipt headers, and security options.\n";

            $actionCard = [
                'type' => 'navigation',
                'title' => 'Open Store Settings',
                'url' => 'settings.php',
                'button_text' => 'Store Settings'
            ];
        } else {
            $reply = "### 🧭 System Navigation Shortcuts\n\n";
            $reply .= "I can help you jump straight to any section of the system:\n";
            $reply .= "- **POS Terminal**: Quick checkout & item search\n";
            $reply .= "- **Sales & Transactions**: Transaction receipts & cashier logs\n";
            $reply .= "- **Inventory**: Stock audits, reconciliations, and adjustments\n";
            $reply .= "- **Products & Categories**: Catalog, pricing, SKU and category hierarchy\n";
            $reply .= "- **Business Reports**: Revenue analytics, margins, and trends\n";
            $reply .= "- **User Management & RBAC**: Accounts and permission policies\n";
            $reply .= "- **Backup & Restore**: Database exports & recovery snapshots\n";
            $reply .= "- **Store Settings**: System preferences and store configuration\n";
        }
        $priority = "info";
    }

    // Strategy 10: Department / Category Specific (e.g. "Do we have enough drinks for this weekend?")
    elseif (preg_match('/(drinks?|beverage|food|bakery|canned|personal care|snack|weekend|supplies)/i', $lower)) {
        $intent = "category_health";
        preg_match('/(drink|beverage|food|bakery|canned|personal care|snack)/i', $lower, $m);
        $cat = $m[1] ?? 'Beverages';
        $items = $tools->getProductsByCategory($cat);

        if (empty($items)) {
            $reply = "I checked your catalog for **$cat**, but could not find matching active items.";
            $priority = "normal";
        } else {
            $lowCount = 0;
            $reply = "### 🥤 Category Status: **$cat**\n\n";
            $reply .= "Here is the stock health evaluation for upcoming demand:\n\n";

            foreach ($items as $idx => $p) {
                $status = $p['stock_quantity'] <= $p['reorder_level'] ? "⚠️ Low Stock" : "✅ Good";
                if ($p['stock_quantity'] <= $p['reorder_level']) $lowCount++;
                $reply .= "- **{$p['name']}**: **{$p['stock_quantity']} {$p['unit']}** in stock ($status • {$p['days_of_supply']} days of supply)\n";
            }

            $reply .= "\n";
            if ($lowCount > 0) {
                $priority = "warning";
                $reply .= "**Summary:** You have **$lowCount item(s)** in this category near or below safety reorder levels. Restocking before high-traffic periods (like weekends) is recommended.";
            } else {
                $priority = "info";
                $reply .= "**Summary:** Category inventory is fully adequate to sustain normal weekend customer volume.";
            }
        }
    }

    // Strategy 7: General Store Overview / "What needs attention today?"
    else {
        $intent = "overview_summary";
        $health = $tools->getInventoryHealth();
        $lowStock = $tools->getLowStockProducts(3);
        $anomalies = $tools->getInventoryAnomalies(2);

        $reply = "### 🤖 Retail Operations Overview\n\n";
        $reply .= "Here is what is happening across your retail system right now:\n\n";
        $reply .= "- **Catalog Valuation:** ₱" . number_format($health['total_valuation'], 2) . " across **{$health['total_skus']} items**.\n";
        $reply .= "- **Stock Health:** **{$health['healthy_stock']} Healthy**, **{$health['low_stock']} Low Stock**, and **{$health['out_of_stock']} Out of Stock**.\n";
        if ($health['today_revenue'] > 0) {
            $reply .= "- **Today's POS Sales:** ₱" . number_format($health['today_revenue'], 2) . " ({$health['today_transactions']} receipts).\n\n";
        } else {
            $reply .= "- **Month-to-Date POS Sales:** ₱" . number_format($health['mtd_revenue'], 2) . ".\n\n";
        }

        if (!empty($lowStock)) {
            $reply .= "📌 **Top Priority Items:**\n";
            foreach ($lowStock as $p) {
                $reply .= "- **{$p['name']}**: {$p['stock_quantity']} {$p['unit']} remaining (Recommended restock: {$p['suggested_restock_qty']} {$p['unit']}).\n";
            }
            $reply .= "\n";
        }

        $reply .= "Feel free to ask me questions like:\n";
        $reply .= "- *\"What should I restock today?\"*\n";
        $reply .= "- *\"Which products are tying up too much money?\"*\n";
        $reply .= "- *\"Is there anything unusual in the stock?\"*\n";
        $reply .= "- *\"What happens if sales increase by 25% next week?\"*";
    }

    echo json_encode([
        'success' => true,
        'reply' => $reply,
        'intent' => $intent,
        'priority' => $priority,
        'action_card' => $actionCard,
        'user' => $currentUsername,
        'timestamp' => date('H:i')
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
exit();
