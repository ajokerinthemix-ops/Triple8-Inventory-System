<?php
/**
 * Triple8 Category & Department Management Helper
 * Provides hierarchical category tree generation, SVG icons, path breadcrumbs,
 * and descendant lookup for filtering.
 */

require_once __DIR__ . '/db.php';

/**
 * Get category SVG icon markup
 * @param string|null $iconKey
 * @param string $className
 * @param int $size
 * @return string
 */
function getCategorySvgIcon($iconKey, $className = '', $size = 20) {
    $classAttr = 'class="category-svg-icon ' . htmlspecialchars($className) . '"';
    $sizeAttr = 'width="' . (int)$size . '" height="' . (int)$size . '"';
    $common = $classAttr . ' ' . $sizeAttr . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

    switch (strtolower(trim($iconKey ?? ''))) {
        // Hardware & Tools
        case 'hardware':
            return '<svg ' . $common . '><path d="m14 12-8.5 8.5a2.12 2.12 0 1 1-3-3L11 9"/><path d="M15 13 9 7l4-4 6 6h3l-4 4h-3z"/></svg>';
        case 'locks-security':
        case 'none':
        case '':
            return '';
        case 'latches':
            return '<svg ' . $common . '><rect width="18" height="12" x="3" y="6" rx="2"/><path d="M7 12h10"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/></svg>';
        case 'padlocks':
            return '<svg ' . $common . '><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16.5" r="1.5"/></svg>';

        // Electrical
        case 'electrical':
            return '<svg ' . $common . '><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
        case 'batteries':
            return '<svg ' . $common . '><rect width="16" height="10" x="2" y="7" rx="2" ry="2"/><line x1="22" x2="22" y1="11" y2="13"/><line x1="6" x2="6" y1="10" y2="14"/><line x1="10" x2="10" y1="10" y2="14"/></svg>';
        case 'electrical-accessories':
            return '<svg ' . $common . '><path d="M12 2v6"/><path d="M6 8h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2z"/><circle cx="9" cy="14" r="1"/><circle cx="15" cy="14" r="1"/></svg>';
        case 'lighting':
            return '<svg ' . $common . '><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>';

        // Household
        case 'household':
            return '<svg ' . $common . '><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
        case 'furnishings':
            return '<svg ' . $common . '><path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"/><path d="M3 16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1H7v-1a2 2 0 0 0-4 0z"/><path d="M5 18v3"/><path d="M19 18v3"/></svg>';
        case 'home-care':
            return '<svg ' . $common . '><path d="M15 4V2a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v2"/><path d="M8 4h8l2 6v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V10l2-6z"/><path d="m11 11 2 2 4-4"/><path d="M21 7l1 2"/><path d="M19 3l1 2"/></svg>';
        case 'home-improvement':
            return '<svg ' . $common . '><path d="M2 14.5A2.5 2.5 0 0 0 4.5 17H6v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2h1.5a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 19.5 3H4.5A2.5 2.5 0 0 0 2 5.5z"/><path d="M6 7h12"/><path d="M6 11h12"/></svg>';
        case 'kitchen-dining':
            return '<svg ' . $common . '><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>';
        case 'living-bath':
            return '<svg ' . $common . '><path d="M9 6h6a2 2 0 0 1 2 2v1H7V8a2 2 0 0 1 2-2z"/><path d="M2 12h20v4a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5v-4z"/><path d="M4 21v2"/><path d="M20 21v2"/></svg>';

        // Stationery
        case 'stationery':
            return '<svg ' . $common . '><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>';
        case 'writing-instruments':
            return '<svg ' . $common . '><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
        case 'paper-notebooks':
            return '<svg ' . $common . '><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>';
        case 'stationery-tools':
            return '<svg ' . $common . '><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" x2="8.12" y1="4" y2="15.88"/><line x1="14.47" x2="20" y1="14.48" y2="20"/><line x1="8.12" x2="12" y1="8.12" y2="12"/></svg>';
        case 'stationery-organisers':
            return '<svg ' . $common . '><rect width="20" height="8" x="2" y="3" rx="2"/><rect width="20" height="8" x="2" y="13" rx="2"/><line x1="10" x2="14" y1="7" y2="7"/><line x1="10" x2="14" y1="17" y2="17"/></svg>';

        // Toys
        case 'toys':
            return '<svg ' . $common . '><rect width="8" height="8" x="2" y="2" rx="1.5"/><rect width="8" height="8" x="14" y="2" rx="1.5"/><rect width="8" height="8" x="2" y="14" rx="1.5"/><rect width="8" height="8" x="14" y="14" rx="1.5"/></svg>';
        case 'baby-toddlers':
            return '<svg ' . $common . '><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v7h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>';
        case 'kids-toys':
            return '<svg ' . $common . '><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>';

        // Generic fallback
        default:
            return '<svg ' . $common . '><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
    }
}

/**
 * Get Caret / Chevron SVG Icon
 * @param string $direction 'down', 'up', 'right', 'left'
 * @param string $className
 * @param int $size
 * @return string
 */
function getChevronSvg($direction = 'down', $className = '', $size = 14) {
    $classAttr = 'class="category-caret ' . htmlspecialchars($className) . '"';
    $sizeAttr = 'width="' . (int)$size . '" height="' . (int)$size . '"';
    $common = $classAttr . ' ' . $sizeAttr . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"';

    switch ($direction) {
        case 'up':
            return '<svg ' . $common . '><polyline points="18 15 12 9 6 15"/></svg>';
        case 'right':
            return '<svg ' . $common . '><polyline points="9 18 15 12 9 6"/></svg>';
        case 'left':
            return '<svg ' . $common . '><polyline points="15 18 9 12 15 6"/></svg>';
        case 'down':
        default:
            return '<svg ' . $common . '><polyline points="6 9 12 15 18 9"/></svg>';
    }
}

/**
 * Build hierarchical nested tree of categories
 * @param array $categories
 * @param int|null $parentId
 * @param int $depth
 * @return array
 */
function buildCategoryHierarchy($categories, $parentId = null, $depth = 0) {
    $tree = [];
    foreach ($categories as $cat) {
        $catParent = !empty($cat['parent_id']) ? (int)$cat['parent_id'] : null;
        if ($catParent === $parentId) {
            $cat['depth'] = $depth;
            $children = buildCategoryHierarchy($categories, (int)$cat['id'], $depth + 1);
            $cat['children'] = $children;

            $directCount = (int)($cat['product_count'] ?? 0);
            $directUnits = (int)($cat['total_units'] ?? 0);
            $totalCount = $directCount;
            $totalUnits = $directUnits;

            foreach ($children as $child) {
                $totalCount += (int)($child['total_product_count'] ?? $child['product_count'] ?? 0);
                $totalUnits += (int)($child['total_stock_units'] ?? $child['total_units'] ?? 0);
            }

            $cat['total_product_count'] = $totalCount;
            $cat['total_stock_units'] = $totalUnits;

            $tree[] = $cat;
        }
    }
    return $tree;
}

/**
 * Flatten tree into ordered list with depth & indentation info
 * @param array $tree
 * @param array &$flat
 * @return array
 */
function flattenCategoryTree($tree, &$flat = []) {
    foreach ($tree as $item) {
        $children = $item['children'] ?? [];
        $copy = $item;
        unset($copy['children']);
        $copy['has_children'] = !empty($children);
        $flat[] = $copy;
        if (!empty($children)) {
            flattenCategoryTree($children, $flat);
        }
    }
    return $flat;
}

/**
 * Fetch all descendant category IDs (including self)
 * Useful for filtering products by parent category
 * @param mysqli $conn
 * @param int $categoryId
 * @return array
 */
function getCategoryAndDescendantIds($conn, $categoryId) {
    $categoryId = (int)$categoryId;
    if ($categoryId <= 0) return [];

    $ids = [$categoryId];
    $toCheck = [$categoryId];

    while (!empty($toCheck)) {
        $idList = implode(',', array_map('intval', $toCheck));
        $res = $conn->query("SELECT id FROM categories WHERE parent_id IN ($idList)");
        $toCheck = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $childId = (int)$row['id'];
                if (!in_array($childId, $ids)) {
                    $ids[] = $childId;
                    $toCheck[] = $childId;
                }
            }
        }
    }

    return $ids;
}

/**
 * Recalculate and update `path` for all categories in database
 * @param mysqli $conn
 * @return void
 */
function syncAllCategoryPaths($conn) {
    $res = $conn->query("SELECT id, parent_id, name FROM categories");
    $catsById = [];
    while ($row = $res->fetch_assoc()) {
        $catsById[(int)$row['id']] = $row;
    }

    $computePath = function($id) use (&$computePath, &$catsById) {
        if (!isset($catsById[$id])) return '';
        $item = $catsById[$id];
        $parentId = !empty($item['parent_id']) ? (int)$item['parent_id'] : null;
        if ($parentId && isset($catsById[$parentId])) {
            return $computePath($parentId) . ' / ' . $item['name'];
        }
        return $item['name'];
    };

    foreach ($catsById as $id => $item) {
        $fullPath = $computePath($id);
        $stmt = $conn->prepare("UPDATE categories SET path = ? WHERE id = ?");
        $stmt->bind_param("si", $fullPath, $id);
        $stmt->execute();
        $stmt->close();
    }
}
