<?php
$pageTitle = 'Department & Category Management';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';
require_once '../includes/categories_helper.php';

requirePermission('categories');

$message = '';

// Handle Category CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = trim($_POST['icon'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if (empty($name)) {
            $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> Please enter a category name.</div>';
        } else {
            // Check for duplicate category name (case-insensitive)
            $checkStmt = $conn->prepare("SELECT id, name FROM categories WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
            $checkStmt->bind_param("s", $name);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();

            if ($checkRes && $checkRes->num_rows > 0) {
                $existing = $checkRes->fetch_assoc();
                $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> A category named <strong>' . htmlspecialchars($existing['name']) . '</strong> already exists. Category names must be unique.</div>';
            } else {
                try {
                    $stmt = $conn->prepare("INSERT INTO categories (parent_id, name, slug, icon, display_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssi", $parentId, $name, $slug, $icon, $displayOrder);
                    if ($stmt->execute()) {
                        syncAllCategoryPaths($conn);
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> Category <strong>' . htmlspecialchars($name) . '</strong> added successfully!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Error adding category: ' . htmlspecialchars($conn->error) . '</div>';
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062) {
                        $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> A category named <strong>' . htmlspecialchars($name) . '</strong> already exists. Category names must be unique.</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            }
            $checkStmt->close();
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = trim($_POST['icon'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if (empty($name)) {
            $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> Category name cannot be empty.</div>';
        } elseif ($parentId === $id) {
            $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> A category cannot be its own parent.</div>';
        } else {
            // Check if selected parent is a descendant of this category (prevent cycles)
            $descendants = getCategoryAndDescendantIds($conn, $id);
            if ($parentId && in_array($parentId, $descendants)) {
                $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> Cannot set a subcategory as parent (circular reference).</div>';
            } else {
                // Check duplicate name
                $checkStmt = $conn->prepare("SELECT id, name FROM categories WHERE LOWER(TRIM(name)) = LOWER(?) AND id != ? LIMIT 1");
                $checkStmt->bind_param("si", $name, $id);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();

                if ($checkRes && $checkRes->num_rows > 0) {
                    $existing = $checkRes->fetch_assoc();
                    $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> Another category named <strong>' . htmlspecialchars($existing['name']) . '</strong> already exists. Category names must be unique.</div>';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE categories SET parent_id = ?, name = ?, slug = ?, icon = ?, display_order = ? WHERE id = ?");
                        $stmt->bind_param("isssii", $parentId, $name, $slug, $icon, $displayOrder, $id);
                        if ($stmt->execute()) {
                            syncAllCategoryPaths($conn);
                            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> Category <strong>' . htmlspecialchars($name) . '</strong> updated successfully.</div>';
                        } else {
                            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($conn->error) . '</div>';
                        }
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        if ($e->getCode() == 1062) {
                            $message = '<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-1"></i> A category named <strong>' . htmlspecialchars($name) . '</strong> already exists. Category names must be unique.</div>';
                        } else {
                            $message = '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
                $checkStmt->close();
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = (int)$_POST['id'];
        
        // Check if category has subcategories
        $childChk = $conn->query("SELECT COUNT(*) as cnt FROM categories WHERE parent_id = $id");
        $childCnt = $childChk ? (int)$childChk->fetch_assoc()['cnt'] : 0;

        // Check if products exist in category
        $chk = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE category_id = $id");
        $cnt = $chk ? (int)$chk->fetch_assoc()['cnt'] : 0;

        if ($childCnt > 0) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i> Cannot delete category: it has ' . $childCnt . ' subcategory/subcategories. Please delete or reassign subcategories first.</div>';
        } elseif ($cnt > 0) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i> Cannot delete category: contains ' . $cnt . ' retail products. Reassign or remove items first.</div>';
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                syncAllCategoryPaths($conn);
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> Category removed.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $stmt->close();
        }
    }
}

// Fetch all categories with product counts & total stock units
$allCategoriesRaw = [];
$res = $conn->query("
    SELECT c.*, 
           p.name as parent_name,
           COUNT(pr.id) as product_count,
           COALESCE(SUM(pr.stock), 0) as total_units
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    LEFT JOIN products pr ON c.id = pr.category_id
    GROUP BY c.id
    ORDER BY c.parent_id IS NOT NULL, c.display_order ASC, c.name ASC
");
if ($res) {
    while ($r = $res->fetch_assoc()) $allCategoriesRaw[] = $r;
}

$tree = buildCategoryHierarchy($allCategoriesRaw);
$flatCategories = flattenCategoryTree($tree);

// Preset list of SVG icon keys with human labels
$availableIcons = [
    'hardware' => ['label' => 'Hardware (Tools)', 'group' => 'Hardware'],
    'locks-security' => ['label' => 'Locks & Security (Shield)', 'group' => 'Hardware'],
    'latches' => ['label' => 'Latches (Bolt)', 'group' => 'Hardware'],
    'padlocks' => ['label' => 'Padlocks (Lock)', 'group' => 'Hardware'],
    'electrical' => ['label' => 'Electrical (Power Zap)', 'group' => 'Electrical'],
    'batteries' => ['label' => 'Batteries (Battery Cell)', 'group' => 'Electrical'],
    'electrical-accessories' => ['label' => 'Electrical Accessories (Socket)', 'group' => 'Electrical'],
    'lighting' => ['label' => 'Lighting (Light Bulb)', 'group' => 'Electrical'],
    'household' => ['label' => 'Household (Home)', 'group' => 'Household'],
    'furnishings' => ['label' => 'Furnishings (Armchair)', 'group' => 'Household'],
    'home-care' => ['label' => 'Home Care (Spray Bottle)', 'group' => 'Household'],
    'home-improvement' => ['label' => 'Home Improvement (Paint/Tools)', 'group' => 'Household'],
    'kitchen-dining' => ['label' => 'Kitchen & Dining (Utensils)', 'group' => 'Household'],
    'living-bath' => ['label' => 'Living & Bath (Tub/Shower)', 'group' => 'Household'],
    'stationery' => ['label' => 'Stationery (Pen & Book)', 'group' => 'Stationery'],
    'writing-instruments' => ['label' => 'Writing Instruments (Fountain Pen)', 'group' => 'Stationery'],
    'paper-notebooks' => ['label' => 'Paper & Notebooks (Sheets)', 'group' => 'Stationery'],
    'stationery-tools' => ['label' => 'Stationery Tools (Scissors)', 'group' => 'Stationery'],
    'stationery-organisers' => ['label' => 'Stationery Organisers (Filing Box)', 'group' => 'Stationery'],
    'toys' => ['label' => 'Toys (Blocks)', 'group' => 'Toys'],
    'baby-toddlers' => ['label' => 'Baby & Toddlers (Stroller)', 'group' => 'Toys'],
    'kids-toys' => ['label' => 'Kids Toys (Rocket/Car)', 'group' => 'Toys'],
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .category-svg-icon {
            display: inline-block;
            vertical-align: middle;
            color: currentColor;
        }
        .cat-icon-bubble {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: #eff6ff;
            color: #2563eb;
            transition: all 0.2s ease;
        }
        .dept-row {
            background-color: #ffffff;
            font-weight: 600;
        }
        .dept-row:hover {
            background-color: #f8fafc;
        }
        .subcat-row {
            background-color: #fafbfc;
            transition: all 0.2s ease;
        }
        .subcat-row:hover {
            background-color: #f1f5f9;
        }
        .btn-dept-toggle {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            cursor: pointer;
            font-size: 0.72rem;
            transition: all 0.2s ease;
            padding: 0;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .btn-dept-toggle:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
        .btn-dept-toggle i {
            transition: transform 0.2s ease;
        }
        .btn-dept-toggle.collapsed i {
            transform: rotate(-90deg);
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-tags text-primary me-2"></i> Departments & Categories</h2>
                    <p class="text-muted small mb-0">Organize store merchandise into departments, subcategories, and merchandise groups.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary fw-bold" id="btnToggleAll" onclick="toggleAllDepartments()">
                        <i class="fas fa-arrows-up-down me-1"></i> <span id="toggleAllText">Collapse All</span>
                    </button>
                    <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus me-1"></i> Add Category
                    </button>
                </div>
            </div>

            <?php if (!empty($message)) echo $message; ?>

            <!-- Categories Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-layer-group text-primary me-2"></i> Active Departments (<?php echo count($flatCategories); ?>)</h6>
                    <div style="width: 280px;">
                        <input type="text" id="categoryTableFilter" class="form-control form-control-sm" placeholder="Search department name...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="categoriesTable">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-3" style="width: 70px;">ID</th>
                                    <th>Department / Category Name</th>
                                    <th class="text-center">Total Products</th>
                                    <th class="text-center">Units In Stock</th>
                                    <th class="text-center pe-3" style="width: 160px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($flatCategories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            No categories found. Click "Add Category" to create store categories.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($flatCategories as $c): 
                                        $depth = (int)($c['depth'] ?? 0);
                                        $iconKey = $c['icon'] ?? '';
                                        $isDepartment = ($depth === 0 || empty($c['parent_id']));
                                        $hasKids = !empty($c['has_children']);
                                        $svgHtml = ($isDepartment && !empty($iconKey)) ? getCategorySvgIcon($iconKey, '', 20) : '';
                                        $totalProdCount = (int)($c['total_product_count'] ?? $c['product_count']);
                                        $totalUnitsCount = (int)($c['total_stock_units'] ?? $c['total_units']);
                                        $rowClass = $isDepartment ? 'cat-row dept-row' : 'cat-row subcat-row';
                                    ?>
                                        <tr class="<?php echo $rowClass; ?>" 
                                            data-id="<?php echo $c['id']; ?>" 
                                            data-parent="<?php echo (int)($c['parent_id'] ?? 0); ?>" 
                                            data-depth="<?php echo $depth; ?>"
                                            data-has-kids="<?php echo $hasKids ? '1' : '0'; ?>"
                                            id="cat-row-<?php echo $c['id']; ?>">
                                            
                                            <td class="ps-3 font-monospace fw-bold text-muted">#<?php echo $c['id']; ?></td>
                                            
                                            <td>
                                                <div class="d-flex align-items-center" style="<?php echo $depth > 0 ? 'padding-left: ' . ($depth * 28) . 'px;' : ''; ?>">
                                                    <?php if ($hasKids): ?>
                                                        <button type="button" class="btn-dept-toggle" data-cat-id="<?php echo $c['id']; ?>" onclick="toggleDeptBranch(<?php echo $c['id']; ?>, this)" title="Expand / Collapse Subcategories">
                                                            <i class="fas fa-chevron-down"></i>
                                                        </button>
                                                    <?php elseif ($depth > 0): ?>
                                                        <span style="display:inline-block; width: 14px; height: 2px; background: #cbd5e1; margin-right: 8px; flex-shrink: 0;"></span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($svgHtml)): ?>
                                                        <div class="cat-icon-bubble me-2 flex-shrink-0" title="<?php echo htmlspecialchars($iconKey); ?>">
                                                            <?php echo $svgHtml; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <span class="fw-bold text-dark fs-6 <?php echo $hasKids ? 'user-select-none' : ''; ?>" 
                                                                  <?php if ($hasKids): ?>onclick="toggleDeptBranch(<?php echo $c['id']; ?>)" style="cursor: pointer;" title="Click to expand/collapse"<?php endif; ?>>
                                                                <?php echo htmlspecialchars($c['name']); ?>
                                                            </span>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-0 fw-semibold" style="font-size: 0.74rem;">
                                                                <?php echo $totalProdCount; ?> <?php echo $totalProdCount === 1 ? 'product' : 'products'; ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted font-monospace" style="font-size: 0.72rem;">slug: <?php echo htmlspecialchars($c['slug'] ?: strtolower($c['name'])); ?></small>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill px-3"><?php echo $totalProdCount; ?> items</span>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border"><?php echo number_format($totalUnitsCount); ?> units</span>
                                            </td>

                                            <td class="text-center pe-3">
                                                <!-- Add Subcategory Shortcut -->
                                                <button type="button" class="btn btn-sm btn-outline-success me-1" 
                                                        onclick="addSubCategory(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['name'])); ?>')" 
                                                        title="Add Subcategory under <?php echo htmlspecialchars($c['name']); ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>

                                                <!-- Edit Category -->
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($c)); ?>)" 
                                                        title="Edit Category">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <!-- Delete Category -->
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete category <?php echo htmlspecialchars($c['name']); ?>?');">
                                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn btn-sm btn-outline-danger" title="Delete Category">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="addCategoryForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-tags me-2"></i> Add Department / Subcategory</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Department / Category Name *</label>
                            <input type="text" name="name" id="add_category_name" class="form-control" placeholder="e.g. Locks & Security" required autocomplete="off">
                            <div class="invalid-feedback" id="add_category_feedback">
                                A category with this name already exists. Category names must be unique.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Parent Department / Category</label>
                            <select name="parent_id" id="add_parent_id" class="form-select">
                                <option value="">-- [None] Main Department (Top Level) --</option>
                                <?php foreach ($flatCategories as $fc): ?>
                                    <option value="<?php echo $fc['id']; ?>">
                                        <?php echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$fc['depth']); ?>
                                        <?php echo htmlspecialchars($fc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Leave empty for top-level departments, or choose a parent.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-bold small"><i class="fas fa-icons text-primary me-1"></i> SVG Icon Preset</label>
                                <select name="icon" id="add_icon" class="form-select" onchange="updateIconPreview('add_icon', 'add_icon_preview')">
                                    <?php foreach ($availableIcons as $k => $v): ?>
                                        <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5 d-flex align-items-end">
                                <div class="p-2 border rounded-3 bg-light d-flex align-items-center gap-2 w-100">
                                    <div class="cat-icon-bubble" id="add_icon_preview">
                                        <?php echo getCategorySvgIcon('hardware', '', 22); ?>
                                    </div>
                                    <small class="text-muted fw-bold">Icon Preview</small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label fw-bold small">URL Slug</label>
                                <input type="text" name="slug" id="add_slug" class="form-control" placeholder="auto-generated">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold small">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_category" id="add_category_btn" class="btn btn-primary fw-bold">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="editCategoryForm">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Edit Department / Category</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Department / Category Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required autocomplete="off">
                            <div class="invalid-feedback" id="edit_category_feedback">
                                Another category with this name already exists. Category names must be unique.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Parent Department / Category</label>
                            <select name="parent_id" id="edit_parent_id" class="form-select">
                                <option value="">-- [None] Main Department (Top Level) --</option>
                                <?php foreach ($flatCategories as $fc): ?>
                                    <option value="<?php echo $fc['id']; ?>" data-id="<?php echo $fc['id']; ?>">
                                        <?php echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$fc['depth']); ?>
                                        <?php echo htmlspecialchars($fc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Modify parent department if needed.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-bold small"><i class="fas fa-icons text-primary me-1"></i> SVG Icon Preset</label>
                                <select name="icon" id="edit_icon" class="form-select" onchange="updateIconPreview('edit_icon', 'edit_icon_preview')">
                                    <?php foreach ($availableIcons as $k => $v): ?>
                                        <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5 d-flex align-items-end">
                                <div class="p-2 border rounded-3 bg-light d-flex align-items-center gap-2 w-100">
                                    <div class="cat-icon-bubble" id="edit_icon_preview">
                                        <?php echo getCategorySvgIcon('hardware', '', 22); ?>
                                    </div>
                                    <small class="text-muted fw-bold">Icon Preview</small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label fw-bold small">URL Slug</label>
                                <input type="text" name="slug" id="edit_slug" class="form-control">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold small">Display Order</label>
                                <input type="number" name="display_order" id="edit_display_order" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_category" id="edit_category_btn" class="btn btn-primary fw-bold">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Icon Map for JS Previews -->
    <div id="iconSvgLibrary" style="display: none;">
        <?php foreach ($availableIcons as $k => $v): ?>
            <div data-icon-key="<?php echo $k; ?>"><?php echo getCategorySvgIcon($k, '', 22); ?></div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Existing categories list for instant duplicate validation
        const existingCategories = <?php echo json_encode(array_map(function($c) {
            return ['id' => (int)$c['id'], 'name' => trim($c['name'])];
        }, $flatCategories)); ?>;

        function updateIconPreview(selectId, previewId) {
            const val = document.getElementById(selectId).value;
            const container = document.getElementById(previewId);
            const source = document.querySelector('#iconSvgLibrary [data-icon-key="' + val + '"]');
            if (container && source) {
                container.innerHTML = source.innerHTML;
            }
        }

        function addSubCategory(parentId, parentName) {
            const select = document.getElementById('add_parent_id');
            if (select) {
                select.value = parentId;
            }
            const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
            modal.show();
        }

        function editCategory(c) {
            const editIdInput = document.getElementById('edit_id');
            const editNameInput = document.getElementById('edit_name');
            const editParentInput = document.getElementById('edit_parent_id');
            const editIconInput = document.getElementById('edit_icon');
            const editSlugInput = document.getElementById('edit_slug');
            const editOrderInput = document.getElementById('edit_display_order');
            const editBtn = document.getElementById('edit_category_btn');

            editIdInput.value = c.id;
            editNameInput.value = c.name;
            editParentInput.value = c.parent_id || '';
            editIconInput.value = c.icon || 'hardware';
            editSlugInput.value = c.slug || '';
            editOrderInput.value = c.display_order || 0;

            // Disable option for self in parent dropdown
            Array.from(editParentInput.options).forEach(opt => {
                opt.disabled = (parseInt(opt.value, 10) === parseInt(c.id, 10));
            });

            updateIconPreview('edit_icon', 'edit_icon_preview');

            editNameInput.classList.remove('is-invalid', 'is-valid');
            editBtn.disabled = false;

            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }

        // Live validation for Add Category input
        const addNameInput = document.getElementById('add_category_name');
        const addBtn = document.getElementById('add_category_btn');
        if (addNameInput) {
            addNameInput.addEventListener('input', function() {
                const val = this.value.trim().toLowerCase();
                const slugInput = document.getElementById('add_slug');
                if (slugInput && (!slugInput.value || slugInput.dataset.touched !== 'true')) {
                    slugInput.value = this.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-');
                }

                if (!val) {
                    this.classList.remove('is-invalid', 'is-valid');
                    addBtn.disabled = false;
                    return;
                }
                const isDup = existingCategories.some(c => c.name.toLowerCase() === val);
                if (isDup) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                    addBtn.disabled = true;
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    addBtn.disabled = false;
                }
            });
        }

        document.getElementById('add_slug')?.addEventListener('input', function() {
            this.dataset.touched = 'true';
        });

        // Live validation for Edit Category input
        const editNameInput = document.getElementById('edit_name');
        const editIdInput = document.getElementById('edit_id');
        const editBtn = document.getElementById('edit_category_btn');
        if (editNameInput) {
            editNameInput.addEventListener('input', function() {
                const val = this.value.trim().toLowerCase();
                const currentId = parseInt(editIdInput.value, 10);
                if (!val) {
                    this.classList.remove('is-invalid', 'is-valid');
                    editBtn.disabled = false;
                    return;
                }
                const isDup = existingCategories.some(c => c.name.toLowerCase() === val && c.id !== currentId);
                if (isDup) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                    editBtn.disabled = true;
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    editBtn.disabled = false;
                }
            });
        }

        // Expand / Collapse Department Hierarchy Logic
        function getDescendantRows(parentId) {
            let rows = [];
            const directChildren = document.querySelectorAll(`tr[data-parent="${parentId}"]`);
            directChildren.forEach(child => {
                rows.push(child);
                const childId = child.dataset.id;
                rows = rows.concat(getDescendantRows(childId));
            });
            return rows;
        }

        function toggleDeptBranch(catId, btnElement = null) {
            if (!btnElement) {
                btnElement = document.querySelector(`.btn-dept-toggle[data-cat-id="${catId}"]`);
            }
            const isCurrentlyCollapsed = btnElement ? btnElement.classList.contains('collapsed') : false;
            const willCollapse = !isCurrentlyCollapsed;

            if (btnElement) {
                btnElement.classList.toggle('collapsed', willCollapse);
            }

            if (willCollapse) {
                // Hide all descendants
                const descendants = getDescendantRows(catId);
                descendants.forEach(r => {
                    r.style.display = 'none';
                    const childBtn = r.querySelector('.btn-dept-toggle');
                    if (childBtn) childBtn.classList.add('collapsed');
                });
            } else {
                // Show direct children
                const directChildren = document.querySelectorAll(`tr[data-parent="${catId}"]`);
                directChildren.forEach(r => {
                    r.style.display = '';
                    // If child itself was expanded, show its children too
                    const childBtn = r.querySelector('.btn-dept-toggle');
                    if (childBtn && !childBtn.classList.contains('collapsed')) {
                        toggleDeptBranch(r.dataset.id, childBtn);
                    }
                });
            }
            updateToggleAllButtonState();
        }

        let allDepartmentsExpanded = true;
        function toggleAllDepartments() {
            allDepartmentsExpanded = !allDepartmentsExpanded;
            const toggles = document.querySelectorAll('.btn-dept-toggle');
            const childRows = document.querySelectorAll('tr[data-depth]:not([data-depth="0"])');

            toggles.forEach(t => {
                t.classList.toggle('collapsed', !allDepartmentsExpanded);
            });

            childRows.forEach(row => {
                row.style.display = allDepartmentsExpanded ? '' : 'none';
            });

            const label = document.getElementById('toggleAllText');
            if (label) {
                label.textContent = allDepartmentsExpanded ? 'Collapse All' : 'Expand All';
            }
        }

        function updateToggleAllButtonState() {
            const toggles = document.querySelectorAll('.btn-dept-toggle');
            if (!toggles.length) return;
            const anyCollapsed = Array.from(toggles).some(t => t.classList.contains('collapsed'));
            allDepartmentsExpanded = !anyCollapsed;
            const label = document.getElementById('toggleAllText');
            if (label) {
                label.textContent = allDepartmentsExpanded ? 'Collapse All' : 'Expand All';
            }
        }

        // Table search filter with auto-expansion of matching branches
        document.getElementById('categoryTableFilter').addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#categoriesTable tbody tr.cat-row');
            
            if (!val) {
                // Reset to current expansion state
                rows.forEach(r => {
                    const depth = parseInt(r.dataset.depth, 10);
                    if (depth === 0) {
                        r.style.display = '';
                    } else {
                        const parentRow = document.getElementById(`cat-row-${r.dataset.parent}`);
                        const parentBtn = parentRow ? parentRow.querySelector('.btn-dept-toggle') : null;
                        const isParentCollapsed = parentBtn ? parentBtn.classList.contains('collapsed') : false;
                        r.style.display = isParentCollapsed ? 'none' : '';
                    }
                });
                return;
            }

            // When searching, unhide matching rows and all their ancestor rows
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(val)) {
                    row.style.display = '';
                    let currentParentId = row.dataset.parent;
                    while (currentParentId && currentParentId !== '0') {
                        const pRow = document.getElementById(`cat-row-${currentParentId}`);
                        if (pRow) {
                            pRow.style.display = '';
                            const pBtn = pRow.querySelector('.btn-dept-toggle');
                            if (pBtn) pBtn.classList.remove('collapsed');
                            currentParentId = pRow.dataset.parent;
                        } else {
                            break;
                        }
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
