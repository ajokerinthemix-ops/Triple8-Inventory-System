<?php
$pageTitle = 'Products';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';
require_once '../includes/categories_helper.php';

requirePermission('products');

$message = '';

// Helper function to handle product image uploads
function uploadProductPhoto($fileInput, $productId, $currentPath = null) {
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        return $currentPath;
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
    $fileTmp = $fileInput['tmp_name'];
    $fileName = $fileInput['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts)) {
        return $currentPath;
    }

    // Limit to 5MB
    if ($fileInput['size'] > 5 * 1024 * 1024) {
        return $currentPath;
    }

    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'prod_' . $productId . '_' . time() . '.' . $fileExt;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        return 'uploads/products/' . $newFileName;
    }

    return $currentPath;
}

// Handle Add / Edit / Delete Product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name'] ?? '');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        
        $sku = 'SKU-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name ?: 'PRD'), 0, 3)) . '-' . rand(100, 999);
        $brand = 'Retail';
        $unit = 'pc';
        $costPrice = 0.00;
        $reorderLevel = 10;
        $supplierId = null;

        if (empty($name) || $price <= 0) {
            $message = '<div class="alert alert-danger py-2 small">Please enter a valid product name and price.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO products (sku, name, brand, category_id, supplier_id, cost_price, price, stock, unit, reorder_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiddidsi", $sku, $name, $brand, $categoryId, $supplierId, $costPrice, $price, $stock, $unit, $reorderLevel);

            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                
                // Handle uploaded product photo if provided
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadedPath = uploadProductPhoto($_FILES['product_image'], $newId);
                    if ($uploadedPath) {
                        $upStmt = $conn->prepare("UPDATE products SET image_path = ?, image = ? WHERE id = ?");
                        $upStmt->bind_param("ssi", $uploadedPath, $uploadedPath, $newId);
                        $upStmt->execute();
                        $upStmt->close();
                    }
                }

                $message = '<div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-1"></i> Product added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger py-2 small">Error: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_product'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1';

        if (empty($name) || $price <= 0) {
            $message = '<div class="alert alert-danger py-2 small">Please enter a valid product name and price.</div>';
        } else {
            // Get existing image path
            $curStmt = $conn->prepare("SELECT image_path, image FROM products WHERE id = ?");
            $curStmt->bind_param("i", $id);
            $curStmt->execute();
            $curRow = $curStmt->get_result()->fetch_assoc();
            $curStmt->close();

            $imagePath = $curRow['image_path'] ?? $curRow['image'] ?? null;

            if ($removePhoto) {
                $imagePath = null;
            } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = uploadProductPhoto($_FILES['product_image'], $id, $imagePath);
            }

            $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, price = ?, stock = ?, image_path = ?, image = ? WHERE id = ?");
            $stmt->bind_param("sidissi", $name, $categoryId, $price, $stock, $imagePath, $imagePath, $id);

            if ($stmt->execute()) {
                $message = '<div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-1"></i> Product updated successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger py-2 small">Error: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_product'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-warning py-2 small"><i class="fas fa-trash me-1"></i> Product deleted.</div>';
        } else {
            $message = '<div class="alert alert-danger py-2 small">Cannot delete product with sales history.</div>';
        }
        $stmt->close();
    }
}

// Fetch categories for dropdowns & hierarchy
$categoriesRaw = [];
$cRes = $conn->query("SELECT * FROM categories ORDER BY parent_id IS NOT NULL, display_order ASC, name ASC");
if ($cRes) while ($r = $cRes->fetch_assoc()) $categoriesRaw[] = $r;
$categoryTree = buildCategoryHierarchy($categoriesRaw);
$flatCategories = flattenCategoryTree($categoryTree);

// Filters
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = trim($_GET['search'] ?? '');

$where = [];
if ($categoryFilter > 0) {
    $descendantIds = getCategoryAndDescendantIds($conn, $categoryFilter);
    if (!empty($descendantIds)) {
        $where[] = "p.category_id IN (" . implode(',', $descendantIds) . ")";
    } else {
        $where[] = "p.category_id = $categoryFilter";
    }
}
if (!empty($search)) {
    $escaped = $conn->real_escape_string($search);
    $where[] = "(p.name LIKE '%$escaped%' OR p.sku LIKE '%$escaped%')";
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch products
$products = [];
$prodRes = $conn->query("
    SELECT p.*, c.name as category_name, c.icon as category_icon, c.path as category_path
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereSql
    ORDER BY p.name ASC
");
if ($prodRes) {
    while ($r = $prodRes->fetch_assoc()) $products[] = $r;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .product-img-box {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            object-fit: contain;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 2px;
            transition: transform 0.2s;
        }
        .product-img-box:hover {
            transform: scale(1.1);
        }
        .photo-upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
        }
        .photo-upload-zone:hover {
            border-color: #4a157e;
            background: #f3ebfa;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-boxes-stacked text-primary me-2"></i> Products</h2>
                    <p class="text-muted small mb-0">Manage catalog merchandise, pricing, stock levels, and product photos.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-1"></i> Add Product
                    </button>
                </div>
            </div>

            <?php if (!empty($message)) echo $message; ?>

            <!-- Filter & Search Toolbar -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search product name" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="category" class="form-select form-select-sm">
                                <option value="0">All Departments & Categories</option>
                                <?php foreach ($flatCategories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$cat['depth']); ?>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill fw-bold"><i class="fas fa-filter me-1"></i> Filter</button>
                            <a href="products.php" class="btn btn-light btn-sm"><i class="fas fa-rotate-left"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products Table Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-list text-primary me-2"></i> Product List (<?php echo count($products); ?> items)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-3" style="width: 70px;">Photo</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fa-2x mb-2 text-secondary opacity-50"></i><br>
                                            No products found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $p): 
                                        $photoUrl = (!empty($p['image_path']) && file_exists(__DIR__ . '/../' . $p['image_path'])) ? ('../' . $p['image_path']) : (!empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image']) ? ('../' . $p['image']) : '../assets/images/triple8_logo.png');
                                    ?>
                                        <tr data-product-id="<?php echo (int)$p['id']; ?>" data-product-name="<?php echo htmlspecialchars($p['name']); ?>">
                                            <td class="ps-3">
                                                <a href="javascript:void(0)" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="Click to view or change photo">
                                                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-img-box" onerror="this.onerror=null; this.src='../assets/images/triple8_logo.png';">
                                                </a>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($p['name']); ?></div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php 
                                                    $pIconHtml = ($p['category_name'] !== 'Locks & Security' && !empty($p['category_icon'])) ? getCategorySvgIcon($p['category_icon'], '', 16) : '';
                                                    if (!empty($pIconHtml)): ?>
                                                        <span class="text-primary" title="<?php echo htmlspecialchars($p['category_name'] ?? ''); ?>">
                                                            <?php echo $pIconHtml; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($p['category_name'] ?? 'General'); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold text-primary font-monospace fs-6">₱<?php echo number_format($p['price'], 2); ?></td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $p['stock'] <= 0 ? 'bg-danger' : ($p['stock'] <= 10 ? 'bg-warning text-dark' : 'bg-success'); ?> rounded-pill px-3 py-2">
                                                    <?php echo number_format($p['stock']); ?> <?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center pe-3">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="Edit Product">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete product <?php echo htmlspecialchars($p['name']); ?>?');">
                                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-sm btn-outline-danger" title="Delete Product">
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

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_product" value="1">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-plus me-2"></i> Add Product</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Photo Upload Section -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <label class="form-label fw-bold small d-block mb-2"><i class="fas fa-image text-primary me-1"></i> Product Photo (Optional)</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="text-center">
                                    <img id="add_photo_preview" src="../assets/images/triple8_logo.png" alt="Preview" style="width: 72px; height: 72px; border-radius: 12px; object-fit: contain; background: #f8fafc; border: 2px solid #cbd5e1;" class="shadow-sm">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="product_image" id="add_product_image" class="form-control form-control-sm" accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml" onchange="previewSelectedImage(this, 'add_photo_preview')">
                                    <div class="form-text text-muted" style="font-size: 0.75rem;">Supports PNG, JPG, JPEG, WEBP (Max 5MB)</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Product Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Great Taste White Coffee 30g" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Category / Subcategory *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($flatCategories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$cat['depth']); ?>
                                        <?php echo ((int)$cat['depth'] > 0 ? '└─ ' : ''); ?>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small">Price (₱) *</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small">Initial Stock</label>
                                <input type="number" name="stock" class="form-control" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-check me-1"></i> Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal with Photo Change / Upload -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="edit_product" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="remove_photo" id="edit_remove_photo" value="0">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-pencil me-2"></i> Edit Product & Photo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        
                        <!-- Photo Change Section -->
                        <div class="mb-3 p-3 bg-light rounded-3 border text-center">
                            <label class="form-label fw-bold small d-block mb-2"><i class="fas fa-image text-primary me-1"></i> Product Photo</label>
                            <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
                                <img id="edit_photo_preview" src="" alt="Product Photo" style="width: 85px; height: 85px; border-radius: 12px; object-fit: contain; background: #ffffff; border: 2px solid #cbd5e1;" class="shadow-sm" onerror="this.onerror=null; this.src='../assets/images/triple8_logo.png';">
                                <div class="text-start">
                                    <button type="button" class="btn btn-outline-danger btn-sm mb-1 d-block" onclick="removeProductPhoto()">
                                        <i class="fas fa-trash me-1"></i> Remove Photo
                                    </button>
                                    <span class="text-muted small" id="edit_photo_label" style="font-size: 0.75rem;">Upload new file below to replace</span>
                                </div>
                            </div>
                            <input type="file" name="product_image" id="edit_product_image" class="form-control form-control-sm" accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml" onchange="previewSelectedImage(this, 'edit_photo_preview')">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Product Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Category / Subcategory *</label>
                            <select name="category_id" id="edit_category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($flatCategories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int)$cat['depth']); ?>
                                        <?php echo ((int)$cat['depth'] > 0 ? '└─ ' : ''); ?>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small">Price (₱) *</label>
                                <input type="number" name="price" id="edit_price" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small">Stock Quantity</label>
                                <input type="number" name="stock" id="edit_stock" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark fw-bold px-4"><i class="fas fa-save me-1"></i> Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live image preview helper
        function previewSelectedImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
                document.getElementById('edit_remove_photo').value = '0';
            }
        }

        function removeProductPhoto() {
            document.getElementById('edit_remove_photo').value = '1';
            document.getElementById('edit_photo_preview').src = '../assets/images/triple8_logo.png';
            document.getElementById('edit_product_image').value = '';
            const label = document.getElementById('edit_photo_label');
            if (label) label.textContent = 'Photo marked for removal on Save';
        }

        function openEditModal(prod) {
            document.getElementById('edit_id').value = prod.id;
            document.getElementById('edit_name').value = prod.name;
            document.getElementById('edit_category_id').value = prod.category_id || '';
            document.getElementById('edit_price').value = parseFloat(prod.price).toFixed(2);
            document.getElementById('edit_stock').value = prod.stock;
            document.getElementById('edit_remove_photo').value = '0';
            document.getElementById('edit_product_image').value = '';
            
            const preview = document.getElementById('edit_photo_preview');
            if (preview) {
                const photoSrc = prod.image_path ? ('../' + prod.image_path) : (prod.image ? ('../' + prod.image) : '../assets/images/triple8_logo.png');
                preview.src = photoSrc;
            }

            const label = document.getElementById('edit_photo_label');
            if (label) label.textContent = 'Upload new file below to replace';

            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }

        // Auto open add modal if action=new
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'new') {
                new bootstrap.Modal(document.getElementById('addProductModal')).show();
            }
        });
    </script>
</body>
</html>
