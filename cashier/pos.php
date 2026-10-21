<?php
$pageTitle = 'Point of Sale (POS)';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';

requirePermission('pos');
date_default_timezone_set('Asia/Manila');

require_once '../includes/categories_helper.php';

// Fetch all retail categories with product counts
$categoriesRaw = [];
$catRes = $conn->query("
    SELECT c.id, c.parent_id, c.name, c.slug, c.icon, c.path, c.display_order,
           COUNT(pr.id) as product_count
    FROM categories c
    LEFT JOIN products pr ON c.id = pr.category_id
    GROUP BY c.id
    ORDER BY c.parent_id IS NOT NULL, c.display_order ASC, c.name ASC
");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $categoriesRaw[] = $r;
    }
}

// Build category map with descendant IDs for filtering
$categoriesData = [];
foreach ($categoriesRaw as $c) {
    $c['descendant_ids'] = getCategoryAndDescendantIds($conn, (int)$c['id']);
    $categoriesData[] = $c;
}
$categoryTree = buildCategoryHierarchy($categoriesRaw);

// Fetch all active products in stock or catalog
$products = [];
$prodRes = $conn->query("
    SELECT p.id, p.sku, p.name, p.brand, p.category_id, p.price, p.stock, p.barcode, p.unit, p.image, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.name ASC
");
if ($prodRes) {
    while ($r = $prodRes->fetch_assoc()) {
        $products[] = $r;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .pos-wrapper {
            display: grid;
            grid-template-columns: 1fr 440px;
            gap: 20px;
            height: calc(100vh - 110px);
            margin-top: -5px;
        }

        .products-panel {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .cart-panel {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-md);
            height: 100%;
        }

        .category-pills {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 8px;
            scrollbar-width: thin;
        }

        .cat-pill {
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            color: #475569;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .cat-pill .category-svg-icon {
            width: 16px;
            height: 16px;
        }

        .cat-pill .cat-caret {
            font-size: 0.72rem;
            opacity: 0.75;
            margin-left: 2px;
            font-weight: bold;
        }

        .cat-pill:hover, .cat-pill.active {
            background: #4a157e;
            color: #ffffff;
            border-color: #4a157e;
            box-shadow: 0 2px 8px rgba(74, 21, 126, 0.3);
        }

        .sub-category-pills {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 12px;
            scrollbar-width: thin;
            animation: fadeIn 0.2s ease-in-out;
        }

        .sub-cat-pill {
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.76rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            border: 1px dashed #cbd5e1;
            background: #ffffff;
            color: #64748b;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .sub-cat-pill:hover, .sub-cat-pill.active {
            background: #6b21a8;
            color: #ffffff;
            border-color: #6b21a8;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
            gap: 16px;
            align-content: start;
            align-items: stretch;
            overflow-y: auto;
            padding-right: 6px;
            padding-bottom: 12px;
            flex: 1;
        }

        .pos-item-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 14px;
            min-height: 225px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            user-select: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .pos-item-card:hover {
            border-color: #4a157e;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(74, 21, 126, 0.16);
        }

        .pos-item-card.out-of-stock {
            opacity: 0.55;
            background: #f8fafc;
            border-style: dashed;
            cursor: not-allowed;
        }

        .pos-item-card .item-brand {
            font-size: 0.72rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .pos-item-thumb {
            height: 90px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            margin: 8px 0;
            padding: 4px;
            overflow: hidden;
        }

        .pos-item-thumb img {
            max-height: 82px;
            max-width: 100%;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .pos-item-card:hover .pos-item-thumb img {
            transform: scale(1.05);
        }

        .pos-item-card .item-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
            margin: 4px 0 8px 0;
            word-break: break-word;
            flex-grow: 1;
        }

        .pos-item-card .item-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
        }

        .pos-item-card .item-price {
            font-size: 1.10rem;
            font-weight: 800;
            color: #4a157e;
        }

        .cart-items-container {
            flex: 1;
            overflow-y: auto;
            border: 1px solid #f1f5f9;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #fafbfc;
        }

        .cart-table {
            width: 100%;
            font-size: 0.86rem;
            margin-bottom: 0;
        }

        .cart-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 0.76rem;
            padding: 8px 10px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .cart-table td {
            padding: 8px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f7;
            background: #ffffff;
        }

        .qty-stepper {
            display: inline-flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #ffffff;
        }

        .qty-stepper button {
            background: #f8fafc;
            border: none;
            width: 26px;
            height: 26px;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            cursor: pointer;
        }

        .qty-stepper button:hover {
            background: #e2e8f0;
        }

        .qty-stepper input {
            width: 32px;
            text-align: center;
            border: none;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0;
        }

        .bill-btn {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            border-radius: 8px;
            padding: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #4a157e;
            transition: all 0.15s;
        }

        .bill-btn:hover {
            background: #f3ebfa;
            border-color: #4a157e;
        }

        @media (max-width: 992px) {
            .pos-wrapper {
                grid-template-columns: 1fr;
                height: auto;
            }
            .cart-panel {
                height: 520px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h2 class="mb-0 fw-bold"><i class="fas fa-cash-register text-primary"></i> Point of Sale</h2>
                    <span class="badge bg-success ms-2"><i class="fas fa-bolt me-1"></i> Fast Checkout</span>
                </div>
                <div class="text-end d-none d-md-block">
                    <span class="text-muted small">Cashier: <strong><?php echo htmlspecialchars(getUsername()); ?></strong></span>
                    <span class="mx-2 text-muted">|</span>
                    <span class="text-muted small" id="liveClock"><?php echo date('F d, Y h:i A'); ?></span>
                </div>
            </div>

            <div class="pos-wrapper">
                
                <!-- Left: Catalog & Fast Search -->
                <div class="products-panel">
                    <div class="mb-3">
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search text-primary"></i></span>
                            <input type="text" id="productSearchFilter" class="form-control border-start-0 ps-0" 
                                   placeholder="Search products by name." autofocus autocomplete="off">
                        </div>
                    </div>

                    <!-- Category Pills -->
                    <div class="category-pills" id="categoryPills">
                        <button class="cat-pill active" onclick="filterCategory('all', this)">
                            <i class="fas fa-layer-group me-1"></i> All Items (<?php echo count($products); ?>)
                        </button>
                        <?php foreach ($categoryTree as $rootCat): 
                            $iconKey = $rootCat['icon'] ?? '';
                            $isLocksSecurity = (trim(strtolower($rootCat['name'])) === 'locks & security' || ($rootCat['slug'] ?? '') === 'locks-security');
                            $svgHtml = (!$isLocksSecurity && !empty($iconKey)) ? getCategorySvgIcon($iconKey, '', 16) : '';
                            $rootProdCount = (int)($rootCat['total_product_count'] ?? $rootCat['product_count'] ?? 0);
                        ?>
                            <button class="cat-pill" onclick="filterCategory(<?php echo $rootCat['id']; ?>, this)">
                                <?php if (!empty($svgHtml)) echo $svgHtml; ?>
                                <span><?php echo htmlspecialchars($rootCat['name']); ?> (<?php echo $rootProdCount; ?>)</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Subcategory Navigation Pills (Drill-Down) -->
                    <div class="sub-category-pills" id="subCategoryPills" style="display: none;"></div>

                    <!-- Products Grid -->
                    <div class="products-grid" id="productsGrid">
                        <div id="noProductsFound" class="text-center py-5 text-muted" style="display: none; grid-column: 1 / -1;">
                            <i class="fas fa-boxes-stacked fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                            No products found in this selection.
                        </div>
                        <?php foreach ($products as $p): ?>
                            <div class="pos-item-card <?php echo $p['stock'] <= 0 ? 'out-of-stock' : ''; ?>"
                                 data-id="<?php echo $p['id']; ?>"
                                 data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                 data-sku="<?php echo htmlspecialchars($p['sku'] ?? ''); ?>"
                                 data-price="<?php echo $p['price']; ?>"
                                 data-stock="<?php echo $p['stock']; ?>"
                                 data-unit="<?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?>"
                                 data-cat="<?php echo $p['category_id']; ?>"
                                 onclick="addToCart(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="item-brand"><?php echo htmlspecialchars($p['brand'] ?? 'Retail'); ?></span>
                                    <span class="badge <?php echo $p['stock'] > 15 ? 'bg-success' : ($p['stock'] > 0 ? 'bg-warning text-dark' : 'bg-danger'); ?> rounded-pill" style="font-size: 0.68rem;">
                                        <?php echo $p['stock'] > 0 ? $p['stock'] . ' ' . ($p['unit'] ?? 'pc') : 'Out of stock'; ?>
                                    </span>
                                </div>
                                
                                <div class="pos-item-thumb">
                                    <img src="../<?php echo htmlspecialchars((!empty($p['image_path']) && file_exists(__DIR__ . '/../' . $p['image_path'])) ? $p['image_path'] : (!empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image']) ? $p['image'] : 'assets/images/triple8_logo.png')); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" onerror="this.onerror=null; this.src='../assets/images/triple8_logo.png';">
                                </div>

                                <div class="item-name" title="<?php echo htmlspecialchars($p['name']); ?>">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </div>

                                <div class="item-footer">
                                    <span class="text-muted small" style="font-size: 0.72rem;">
                                        <?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?>
                                    </span>
                                    <span class="item-price">₱<?php echo number_format($p['price'], 2); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Active Cart & Checkout Register -->
                <div class="cart-panel">


                    <!-- Cart Table Container -->
                    <div class="cart-items-container">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                    <th style="width: 24px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cartTableBody">
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-shopping-basket fa-2x mb-2 text-secondary opacity-50"></i><br>
                                        Click items or search to start sale
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Discount Selector -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Discount Type</label>
                            <select class="form-select form-select-sm" id="discountTypeSelect" onchange="calculateTotals()">
                                <option value="none">None (Regular)</option>
                                <option value="senior_pwd">Senior / PWD (20%)</option>
                                <option value="member">Member Promo (5%)</option>
                                <option value="custom">Custom ₱ Discount</option>
                            </select>
                        </div>
                        <div class="col-6" id="customDiscountWrapper" style="display: none;">
                            <label class="form-label small fw-bold mb-1">Discount (₱)</label>
                            <input type="number" id="customDiscountInput" class="form-control form-select-sm" value="0" min="0" step="1" oninput="calculateTotals()">
                        </div>
                    </div>

                    <!-- Summary Breakdown -->
                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span>Subtotal:</span>
                            <span id="summarySubtotal">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span>VAT (12% Included):</span>
                            <span id="summaryVat">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-danger small mb-1" id="discountRow" style="display: none;">
                            <span>Discount:</span>
                            <span id="summaryDiscount">-₱0.00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-6">Net Total:</span>
                            <span class="fs-4 fw-bold text-primary" id="summaryTotal">₱0.00</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger w-25" onclick="clearCart()">
                            <i class="fas fa-trash-can"></i>
                        </button>
                        <button type="button" class="btn btn-primary flex-grow-1 fw-bold py-2 fs-6" id="btnCheckout" onclick="openCheckoutModal()" disabled>
                            <i class="fas fa-money-bill-wave me-1"></i> PAY
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Checkout Payment Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #4a157e 0%, #681f9a 100%);">
                    <h5 class="modal-title fw-bold" id="checkoutModalLabel"><i class="fas fa-cash-register me-2"></i> Payment Tender</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <div class="text-center mb-4 p-3 bg-light rounded-3 border">
                        <div class="text-uppercase small text-muted fw-bold">Total Amount Payable</div>
                        <div class="fs-2 fw-black text-primary" id="modalPayableAmount">₱0.00</div>
                    </div>

                    <!-- Cash Payment Only Banner -->
                    <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 small">
                        <i class="fas fa-money-bill-wave fa-lg me-2 text-success"></i>
                        <div>
                            <strong>Cash Payments Only:</strong> GCash, Maya, and electronic payments are not accepted.
                        </div>
                    </div>

                    <!-- Cash Payment Section -->
                    <div id="cashPaymentSection">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Amount Tendered (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg fw-bold text-center" id="tenderAmountInput" 
                                   placeholder="0.00" min="0" step="any" oninput="calculateChange()">
                        </div>

                        <!-- Quick Cash Bills -->
                        <div class="row g-2 mb-3">
                            <div class="col-4"><button type="button" class="bill-btn w-100" onclick="quickCash(50)">₱50</button></div>
                            <div class="col-4"><button type="button" class="bill-btn w-100" onclick="quickCash(100)">₱100</button></div>
                            <div class="col-4"><button type="button" class="bill-btn w-100" onclick="quickCash(200)">₱200</button></div>
                            <div class="col-4"><button type="button" class="bill-btn w-100" onclick="quickCash(500)">₱500</button></div>
                            <div class="col-4"><button type="button" class="bill-btn w-100" onclick="quickCash(1000)">₱1,000</button></div>
                            <div class="col-4"><button type="button" class="bill-btn w-100" onclick="quickExact()">Exact</button></div>
                        </div>

                        <!-- Change Display -->
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 border">
                            <span class="fw-bold">Change Due:</span>
                            <span class="fs-4 fw-bold text-success" id="modalChangeAmount">₱0.00</span>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="btnSubmitSale" onclick="processSale()">
                        <i class="fas fa-check-circle me-1"></i> Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white py-2">
                    <h5 class="modal-title fw-bold fs-6"><i class="fas fa-receipt me-2"></i> Transaction Complete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetPos()"></button>
                </div>
                <div class="modal-body p-3 bg-light" id="receiptModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Generating official receipt...</p>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print me-1"></i> Print Receipt
                    </button>
                    <button type="button" class="btn btn-success fw-bold" data-bs-dismiss="modal" onclick="resetPos()">
                        <i class="fas fa-plus me-1"></i> Next Transaction
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- POS Engine JS -->
    <script>
        const allProducts = <?php echo json_encode($products); ?>;
        window.allProducts = allProducts;

        // Persistent Cart State
        let cart = [];
        try {
            const savedCart = sessionStorage.getItem('t8_pos_cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                if (!Array.isArray(cart)) cart = [];
            }
        } catch (e) {
            cart = [];
        }
        window.cart = cart;
        let currentPaymentMethod = 'cash';
        let lastSaleId = null;

        // Live Search Filter & Enter-to-Add Keydown Listener
        const searchInput = document.getElementById('productSearchFilter');
        if (searchInput) {
            searchInput.addEventListener('input', applyProductFilters);
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim().toLowerCase();
                    if (!query) return;

                    // Match product by exact SKU or Name or partial
                    const matched = allProducts.find(p => 
                        (p.sku && p.sku.toLowerCase() === query) ||
                        (p.name.toLowerCase() === query)
                    ) || allProducts.find(p => p.name.toLowerCase().includes(query) || (p.sku && p.sku.toLowerCase().includes(query)));

                    if (matched) {
                        addToCart(matched);
                        this.value = '';
                        applyProductFilters();
                    } else {
                        alert('No product found matching: ' + query);
                    }
                }
            });
        }

        const categoriesData = <?php echo json_encode($categoriesData); ?>;
        const categoryTree = <?php echo json_encode($categoryTree); ?>;
        window.categoriesData = categoriesData;
        window.categoryTree = categoryTree;

        let activeCategoryId = 'all';
        let activeCategoryDescendants = [];

        function filterCategory(catId, btn, isSub = false) {
            if (catId === 'all') {
                activeCategoryId = 'all';
                activeCategoryDescendants = [];
                const subContainer = document.getElementById('subCategoryPills');
                if (subContainer) {
                    subContainer.style.display = 'none';
                    subContainer.innerHTML = '';
                }
            } else {
                activeCategoryId = parseInt(catId, 10);
                const catObj = categoriesData.find(c => parseInt(c.id, 10) === activeCategoryId);
                activeCategoryDescendants = catObj ? (catObj.descendant_ids || [activeCategoryId]) : [activeCategoryId];

                if (!isSub) {
                    renderSubCategoryPills(activeCategoryId);
                }
            }

            // Update active pill styling
            if (!isSub) {
                document.querySelectorAll('#categoryPills .cat-pill').forEach(p => p.classList.remove('active'));
                if (btn) btn.classList.add('active');
            } else {
                document.querySelectorAll('#subCategoryPills .sub-cat-pill').forEach(p => p.classList.remove('active'));
                if (btn) btn.classList.add('active');
            }

            applyProductFilters();
        }

        function renderSubCategoryPills(rootId) {
            const container = document.getElementById('subCategoryPills');
            if (!container) return;

            const rootNode = categoryTree.find(c => parseInt(c.id, 10) === rootId);
            if (!rootNode || !rootNode.children || rootNode.children.length === 0) {
                container.style.display = 'none';
                container.innerHTML = '';
                return;
            }

            const rootTotal = rootNode.total_product_count !== undefined ? rootNode.total_product_count : (rootNode.product_count || 0);
            let html = `
                <button type="button" class="sub-cat-pill active" onclick="filterCategory(${rootId}, this, true)">
                    All ${rootNode.name} (${rootTotal})
                </button>
            `;

            const renderKids = (kids) => {
                kids.forEach(k => {
                    const count = k.total_product_count !== undefined ? k.total_product_count : (k.product_count || 0);
                    html += `
                        <button type="button" class="sub-cat-pill" onclick="filterCategory(${k.id}, this, true)">
                            ${k.name} (${count})
                        </button>
                    `;
                    if (k.children && k.children.length > 0) {
                        renderKids(k.children);
                    }
                });
            };

            renderKids(rootNode.children);

            container.innerHTML = html;
            container.style.display = 'flex';
        }

        function applyProductFilters() {
            const searchVal = document.getElementById('productSearchFilter').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.pos-item-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardCat = parseInt(card.getAttribute('data-cat'), 10);
                const name = card.getAttribute('data-name').toLowerCase();
                const sku = (card.getAttribute('data-sku') || '').toLowerCase();

                let matchesCategory = false;
                if (activeCategoryId === 'all') {
                    matchesCategory = true;
                } else if (activeCategoryDescendants && activeCategoryDescendants.length > 0) {
                    matchesCategory = activeCategoryDescendants.includes(cardCat);
                } else {
                    matchesCategory = (cardCat === activeCategoryId);
                }

                const matchesSearch = (!searchVal || name.includes(searchVal) || sku.includes(searchVal));

                if (matchesCategory && matchesSearch) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const emptyEl = document.getElementById('noProductsFound');
            if (emptyEl) {
                emptyEl.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        // Add item to cart
        function addToCart(productOrId) {
            try {
                let product = productOrId;
                if (typeof productOrId === 'number' || typeof productOrId === 'string') {
                    product = allProducts.find(p => p.id == productOrId);
                }
                if (!product) return;

                // Sync with AI Assistant
                if (window.RetailAI && typeof window.RetailAI.updateContextLabel === 'function') {
                    window.RetailAI.activeProductId = parseInt(product.id);
                    window.RetailAI.activeProductName = product.name;
                    window.RetailAI.updateContextLabel();
                }

                if (parseInt(product.stock) <= 0) {
                    alert('Product is out of stock!');
                    return;
                }

                // Sync with canonical store
                if (!Array.isArray(cart)) cart = [];
                const existing = cart.find(item => item.id == product.id);
                if (existing) {
                    if (existing.quantity + 1 > parseInt(product.stock)) {
                        alert('Cannot exceed available stock (' + product.stock + ')');
                        return;
                    }
                    existing.quantity += 1;
                } else {
                    cart.push({
                        id: product.id,
                        name: product.name,
                        brand: product.brand || 'Retail',
                        price: parseFloat(product.price),
                        stock: parseInt(product.stock),
                        unit: product.unit || 'pc',
                        quantity: 1
                    });
                }
                renderCart();
            } catch (err) {
                console.error('[POS] addToCart error:', err);
            }
        }

        // Stepper updates
        function updateQty(productId, delta) {
            const item = cart.find(i => i.id == productId);
            if (!item) return;

            const newQty = item.quantity + delta;
            if (newQty <= 0) {
                removeFromCart(productId);
            } else if (newQty > item.stock) {
                alert('Cannot exceed available stock (' + item.stock + ')');
            } else {
                item.quantity = newQty;
                renderCart();
            }
        }

        function setDirectQty(productId, val) {
            const item = cart.find(i => i.id == productId);
            if (!item) return;

            let qty = parseInt(val) || 1;
            if (qty < 1) qty = 1;
            if (qty > item.stock) {
                alert('Cannot exceed available stock (' + item.stock + ')');
                qty = item.stock;
            }
            item.quantity = qty;
            renderCart();
        }

        function removeFromCart(productId) {
            cart = cart.filter(i => i.id != productId);
            renderCart();
        }

        function clearCart(force = false) {
            if (!cart || cart.length === 0) return;
            if (force || confirm('Clear all items from current cart?')) {
                cart = [];
                sessionStorage.removeItem('t8_pos_cart');
                renderCart();
            }
        }

        // Render Cart Table
        function renderCart() {
            window.cart = cart;
            try {
                sessionStorage.setItem('t8_pos_cart', JSON.stringify(cart));
            } catch (e) {}

            const tbody = document.getElementById('cartTableBody');
            if (!tbody) return;

            if (cart.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fas fa-shopping-basket fa-2x mb-2 text-secondary opacity-50"></i><br>
                            Click items or search to start sale
                        </td>
                    </tr>`;
                const btnCheckout = document.getElementById('btnCheckout');
                if (btnCheckout) btnCheckout.disabled = true;
            } else {
                let html = '';
                cart.forEach(item => {
                    const itemSub = item.price * item.quantity;
                    html += `
                        <tr>
                            <td>
                                <div class="fw-bold">${item.name}</div>
                                <div class="text-muted" style="font-size:0.75rem;">₱${item.price.toFixed(2)} / ${item.unit}</div>
                            </td>
                            <td class="text-center">
                                <div class="qty-stepper">
                                    <button type="button" onclick="updateQty(${item.id}, -1)">-</button>
                                    <input type="text" value="${item.quantity}" onchange="setDirectQty(${item.id}, this.value)">
                                    <button type="button" onclick="updateQty(${item.id}, 1)">+</button>
                                </div>
                            </td>
                            <td class="text-end fw-semibold">₱${item.price.toFixed(2)}</td>
                            <td class="text-end fw-bold text-primary">₱${itemSub.toFixed(2)}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${item.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                tbody.innerHTML = html;
                const btnCheckout = document.getElementById('btnCheckout');
                if (btnCheckout) btnCheckout.disabled = false;
            }
            calculateTotals();
        }

        // Calculate Totals & Discounts
        function calculateTotals() {
            let gross = 0;
            cart.forEach(item => gross += item.price * item.quantity);

            const discountType = document.getElementById('discountTypeSelect').value;
            const customInput = document.getElementById('customDiscountInput');
            const customWrapper = document.getElementById('customDiscountWrapper');
            const discountRow = document.getElementById('discountRow');

            if (discountType === 'custom') {
                customWrapper.style.display = 'block';
            } else {
                customWrapper.style.display = 'none';
            }

            let discount = 0;
            if (discountType === 'senior_pwd') {
                // Senior/PWD: 20% discount on VAT-exempt sales (subtotal / 1.12 * 0.20 + VAT exempt)
                const vatable = gross / 1.12;
                discount = (gross - vatable) + (vatable * 0.20);
            } else if (discountType === 'member') {
                discount = gross * 0.05; // 5% Member promo
            } else if (discountType === 'custom') {
                discount = Math.min(gross, parseFloat(customInput.value) || 0);
            }

            const netTotal = Math.max(0, gross - discount);
            const vatableSales = netTotal / 1.12;
            const vatAmount = netTotal - vatableSales;

            document.getElementById('summarySubtotal').innerText = '₱' + gross.toFixed(2);
            document.getElementById('summaryVat').innerText = '₱' + vatAmount.toFixed(2);
            
            if (discount > 0) {
                discountRow.style.display = 'flex';
                document.getElementById('summaryDiscount').innerText = '-₱' + discount.toFixed(2);
            } else {
                discountRow.style.display = 'none';
            }

            document.getElementById('summaryTotal').innerText = '₱' + netTotal.toFixed(2);

            return { gross, discount, netTotal, vatableSales, vatAmount };
        }

        function onCustomerChange() {
            const select = document.getElementById('customerSelect');
            const badge = document.getElementById('customerBadge');
            const opt = select.selectedOptions[0];
            if (select.value) {
                const points = opt.getAttribute('data-points');
                const tier = opt.getAttribute('data-tier');
                badge.innerText = `✓ ${tier} Tier Member: ${points} points available`;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }

        // Open Checkout Modal
        function openCheckoutModal() {
            const { netTotal } = calculateTotals();
            if (netTotal <= 0 && cart.length === 0) return;

            document.getElementById('modalPayableAmount').innerText = '₱' + netTotal.toFixed(2);
            setPaymentMethod('cash');
            calculateChange();

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('checkoutModal'));
            modal.show();
        }

        function setPaymentMethod(method) {
            currentPaymentMethod = 'cash';
            calculateChange();
        }

        function quickCash(amount) {
            document.getElementById('tenderAmountInput').value = amount.toFixed(2);
            calculateChange();
        }

        function quickExact() {
            const { netTotal } = calculateTotals();
            document.getElementById('tenderAmountInput').value = netTotal.toFixed(2);
            calculateChange();
        }

        function calculateChange() {
            const { netTotal } = calculateTotals();
            const tendered = parseFloat(document.getElementById('tenderAmountInput').value) || 0;
            const change = Math.max(0, tendered - netTotal);
            document.getElementById('modalChangeAmount').innerText = '₱' + change.toFixed(2);

            const btn = document.getElementById('btnSubmitSale');
            if (tendered < netTotal) {
                btn.disabled = true;
            } else {
                btn.disabled = false;
            }
        }

        // Process Sale Request
        async function processSale() {
            const { gross, discount, netTotal, vatableSales, vatAmount } = calculateTotals();
            const tendered = parseFloat(document.getElementById('tenderAmountInput').value) || 0;
            const change = Math.max(0, tendered - netTotal);
            const discountType = document.getElementById('discountTypeSelect').value;
            const paymentRef = '';

            const payload = {
                customer_id: null,
                items: cart,
                subtotal: gross,
                total: netTotal,
                payment: tendered,
                change: change,
                vatable_sales: vatableSales,
                vat_amount: vatAmount,
                payment_method: currentPaymentMethod,
                payment_reference: paymentRef,
                discount_type: discountType,
                discount_amount: discount
            };

            const submitBtn = document.getElementById('btnSubmitSale');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

            try {
                const response = await fetch('process_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm';

                if (result.success) {
                    lastSaleId = result.sale_id;
                    
                    // Close checkout modal
                    const checkoutModal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
                    if (checkoutModal) checkoutModal.hide();

                    // Show Receipt Modal
                    loadReceipt(result.sale_id);
                } else {
                    alert('Checkout Error: ' + (result.message || 'Unable to complete sale.'));
                }
            } catch (err) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm';
                alert('Network / Server Error: ' + err.message);
            }
        }

        // Load Receipt into modal
        async function loadReceipt(saleId) {
            const modalBody = document.getElementById('receiptModalBody');
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading receipt #${saleId}...</p>
                </div>`;

            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();

            try {
                const res = await fetch(`receipt.php?id=${saleId}&format=modal`);
                const html = await res.text();
                modalBody.innerHTML = html;
            } catch (e) {
                modalBody.innerHTML = `<div class="p-4 text-danger">Failed to load receipt: ${e.message}</div>`;
            }
        }

        function printReceipt() {
            if (!lastSaleId) return;
            const printWindow = window.open(`receipt.php?id=${lastSaleId}&print=1`, '_blank', 'width=450,height=650');
            if (printWindow) printWindow.focus();
        }

        function resetPos() {
            cart = [];
            sessionStorage.removeItem('t8_pos_cart');
            renderCart();
            const discountSelect = document.getElementById('discountTypeSelect');
            if (discountSelect) discountSelect.value = 'none';
            const searchInput = document.getElementById('productSearchFilter');
            if (searchInput) searchInput.focus();
        }

        // Bind POS functions to window for Voice Assistant integration
        window.openCheckoutModal = openCheckoutModal;
        window.setPaymentMethod = setPaymentMethod;
        window.quickCash = quickCash;
        window.quickExact = quickExact;
        window.calculateChange = calculateChange;
        window.calculateTotals = calculateTotals;
        window.processSale = processSale;
        window.loadReceipt = loadReceipt;
        window.printReceipt = printReceipt;
        window.resetPos = resetPos;
        window.clearCart = clearCart;
        window.addToCart = addToCart;
        window.removeFromCart = removeFromCart;
        window.updateQty = updateQty;
        window.setDirectQty = setDirectQty;

        // Restore active cart on page load
        document.addEventListener('DOMContentLoaded', () => {
            renderCart();
        });
    </script>
</body>
</html>
