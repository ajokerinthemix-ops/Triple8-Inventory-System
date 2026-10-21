/**
 * Context-Aware Smart Barcode Management Engine (Client-Side)
 * 
 * Features:
 * - Global USB/Bluetooth HID Hardware Scanner keystroke listener.
 * - Interactive Camera-based Video Barcode Scanner Modal.
 * - Context-aware workflow execution (POS, Inventory, Stock Counting, Receiving, Products, Transactions).
 * - Instant Audio & Visual HUD Feedback.
 * - Shared Context synchronization with window.RetailAI.
 */

(function(window) {
    'use strict';

    class SmartBarcodeManager {
        constructor() {
            this.buffer = '';
            this.lastCharTime = 0;
            this.isScanning = false;
            this.currentProduct = null;
            this.currentBarcode = null;
            this.currentWorkflow = 'general';
            this.audioCtx = null;
            this.soundEnabled = localStorage.getItem('smart_barcode_sound') !== 'false';
            this.videoStream = null;
            this.detectorInterval = null;

            this.init();
        }

        init() {
            this.detectContext();
            this.setupHardwareScannerListener();
            this.injectHUD();
            this.injectCameraModal();

            // Export to window
            window.SmartBarcode = this;
            console.log('[SmartBarcode] Initialized in context: ' + this.currentWorkflow);
        }

        detectContext() {
            const path = window.location.pathname.toLowerCase();
            if (path.includes('pos.php')) {
                this.currentWorkflow = 'pos';
            } else if (path.includes('inventory.php')) {
                if (window.activeInventoryTab === 'stock_counting' || document.getElementById('stockCountingTab')?.classList.contains('active')) {
                    this.currentWorkflow = 'stock_counting';
                } else if (window.activeInventoryTab === 'receiving' || document.getElementById('receivingTab')?.classList.contains('active')) {
                    this.currentWorkflow = 'receiving';
                } else {
                    this.currentWorkflow = 'inventory';
                }
            } else if (path.includes('products.php')) {
                this.currentWorkflow = 'products';
            } else if (path.includes('transactions.php') || path.includes('reports.php')) {
                this.currentWorkflow = 'transactions';
            } else {
                this.currentWorkflow = 'general';
            }
            return this.currentWorkflow;
        }

        setWorkflow(mode) {
            this.currentWorkflow = mode;
            console.log('[SmartBarcode] Workflow switched to: ' + mode);
            this.showHUD(`Scanner Mode: ${mode.toUpperCase().replace('_', ' ')}`, 'info');
        }

        /**
         * Global Keyboard Interceptor for USB/Bluetooth Scanners
         */
        setupHardwareScannerListener() {
            document.addEventListener('keydown', (e) => {
                const now = Date.now();
                const target = e.target;
                const isInputElement = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
                
                // If user is typing manually into a search or other normal input (except dedicated scanner input)
                const isDedicatedScanner = target && (target.id === 'barcodeScannerInput' || target.classList.contains('smart-barcode-target'));
                
                if (e.key === 'Enter') {
                    if (this.buffer.length >= 3) {
                        e.preventDefault();
                        const scannedCode = this.buffer.trim();
                        this.buffer = '';
                        this.processBarcode(scannedCode);
                        return;
                    }
                    this.buffer = '';
                    return;
                }

                // If characters arrive with very short interval (<50ms), it's a hardware scanner
                const timeDiff = now - this.lastCharTime;
                if (timeDiff > 80 && !isDedicatedScanner) {
                    this.buffer = ''; // Reset buffer on human typing speed pause
                }

                if (e.key.length === 1) {
                    this.buffer += e.key;
                    this.lastCharTime = now;
                }
            }, true);
        }

        /**
         * Centralized Barcode Processing
         */
        async processBarcode(barcode, forcedContext = null) {
            if (!barcode || barcode.trim() === '') return;
            const cleanBarcode = barcode.trim();
            const context = forcedContext || this.detectContext();

            console.log(`[SmartBarcode] Processing '${cleanBarcode}' in context '${context}'`);
            this.showHUD(`Scanning ${cleanBarcode}...`, 'info');

            try {
                // Determine API root path
                const isCashier = window.location.pathname.includes('/cashier/');
                const isAdmin = window.location.pathname.includes('/admin/');
                const apiPath = (isCashier || isAdmin) ? '../api/barcode_service.php' : 'api/barcode_service.php';

                const response = await fetch(`${apiPath}?action=lookup&barcode=${encodeURIComponent(cleanBarcode)}&context=${encodeURIComponent(context)}`);
                const result = await response.json();

                if (result.success && result.product) {
                    this.playBeep(true);
                    this.currentProduct = result.product;
                    this.currentBarcode = cleanBarcode;

                    // Sync with AI Assistant context
                    this.syncWithAI(result.product, cleanBarcode);

                    // Execute Context-Specific Workflow
                    this.executeWorkflowAction(context, result.product);
                } else {
                    this.playBeep(false);
                    this.handleUnregisteredBarcode(cleanBarcode, result, context);
                }
            } catch (err) {
                console.error('[SmartBarcode] Lookup error:', err);
                this.playBeep(false);
                this.showHUD('⚠ Barcode service communication error.', 'danger');
            }
        }

        /**
         * Synchronize Scanned Product with AI Assistant
         */
        syncWithAI(product, barcode) {
            if (window.RetailAI) {
                window.RetailAI.activeProductId = product.id;
                window.RetailAI.activeProductName = product.name;
                window.RetailAI.activeBarcode = barcode;
                console.log(`[SmartBarcode] AI Context updated -> #${product.id} ${product.name}`);

                // Update assistant badge if panel open
                const badge = document.getElementById('aiContextBadge');
                if (badge) {
                    badge.innerHTML = `<i class="fas fa-barcode text-warning me-1"></i> Scanned: <strong>${product.name}</strong> (${product.stock} ${product.unit})`;
                    badge.style.display = 'inline-flex';
                }
            }
        }

        /**
         * Context-Aware Workflow Execution
         */
        executeWorkflowAction(context, product) {
            switch (context) {
                case 'pos':
                    this.handlePOSScan(product);
                    break;
                case 'inventory':
                    this.handleInventoryScan(product);
                    break;
                case 'stock_counting':
                    this.handleStockCountingScan(product);
                    break;
                case 'receiving':
                    this.handleReceivingScan(product);
                    break;
                case 'products':
                    this.handleProductsScan(product);
                    break;
                case 'transactions':
                    this.handleTransactionsScan(product);
                    break;
                default:
                    this.showHUD(`✓ ${product.name} (${product.stock} ${product.unit})`, 'success');
                    break;
            }
        }

        /**
         * 1. POS Workflow: Auto add or increment quantity in cart
         */
        handlePOSScan(product) {
            if (product.is_out_of_stock) {
                this.showHUD(`⚠ ${product.name} is OUT OF STOCK!`, 'danger');
                if (typeof window.showToast === 'function') {
                    window.showToast(`${product.name} is currently out of stock.`, 'danger');
                }
                return;
            }

            // If pos.php has addToCart
            if (typeof window.addToCart === 'function') {
                window.addToCart(product.id);
                this.showHUD(`✓ Added to Cart: ${product.name} (₱${product.price.toFixed(2)})`, 'success');
            } else {
                // Trigger click on product card in POS grid
                const card = document.querySelector(`.product-card[data-id="${product.id}"], .pos-product-item[data-id="${product.id}"]`);
                if (card) {
                    card.click();
                    this.showHUD(`✓ Added: ${product.name}`, 'success');
                } else {
                    this.showHUD(`✓ Identified: ${product.name} • ₱${product.price.toFixed(2)}`, 'success');
                }
            }
        }

        /**
         * 2. Inventory Workflow: Highlight and show diagnostics
         */
        handleInventoryScan(product) {
            this.showHUD(`✓ ${product.name} • Stock: ${product.stock} ${product.unit} (Reorder: ${product.reorder_level})`, 'success');

            // Highlight table row if present
            const rows = document.querySelectorAll('table tbody tr');
            let foundRow = null;
            rows.forEach(tr => {
                const text = tr.innerText || '';
                if (text.includes(product.name) || (product.barcode && text.includes(product.barcode))) {
                    foundRow = tr;
                    tr.style.transition = 'background-color 0.4s';
                    tr.style.backgroundColor = 'rgba(78, 115, 223, 0.25)';
                    tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        tr.style.backgroundColor = '';
                    }, 3500);
                }
            });

            // Open Quick Diagnostic Drawer / Modal
            this.openProductDiagnosticModal(product);
        }

        /**
         * 3. Stock Counting Workflow: Prompt physical count vs expected stock
         */
        handleStockCountingScan(product) {
            this.openStockCountModal(product);
        }

        /**
         * 4. Receiving Workflow: Inward delivery quantity entry
         */
        handleReceivingScan(product) {
            this.openReceivingModal(product);
        }

        /**
         * 5. Product Management Workflow: Open edit modal or filter
         */
        handleProductsScan(product) {
            this.showHUD(`✓ Found ${product.name}`, 'success');
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.value = product.barcode || product.name;
                searchInput.form?.submit();
            } else {
                this.openProductDiagnosticModal(product);
            }
        }

        /**
         * 6. Transactions Workflow: Filter history
         */
        handleTransactionsScan(product) {
            this.showHUD(`Filtering transactions for ${product.name}...`, 'info');
            const searchInput = document.querySelector('input[type="search"], input[name="search"], #transactionSearch');
            if (searchInput) {
                searchInput.value = product.name;
                if (typeof window.filterTransactions === 'function') {
                    window.filterTransactions();
                } else if (searchInput.form) {
                    searchInput.form.submit();
                }
            }
        }

        /**
         * Unregistered Barcode Handler
         */
        handleUnregisteredBarcode(barcode, result, context) {
            this.showHUD(`⚠ Unregistered Barcode: ${barcode}`, 'warning');
            
            const isAdmin = ['admin', 'manager'].includes(window.currentUserRole || 'admin');
            const modalHtml = `
                <div class="modal fade" id="unregisteredBarcodeModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                            <div class="modal-header bg-warning bg-opacity-10 border-0">
                                <h5 class="modal-title text-warning-emphasis fw-bold">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Unregistered Barcode
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center p-4">
                                <div class="mb-3">
                                    <span class="badge bg-light text-dark fs-5 font-monospace border px-3 py-2">
                                        <i class="fas fa-barcode me-2"></i> ${barcode}
                                    </span>
                                </div>
                                <p class="text-muted">This barcode is not currently linked to any product in your inventory database.</p>
                                ${isAdmin ? `
                                    <div class="d-flex justify-content-center gap-2 mt-4">
                                        <a href="${window.location.pathname.includes('/admin/') ? 'products.php?action=new&barcode=' : '../admin/products.php?action=new&barcode='}${encodeURIComponent(barcode)}" class="btn btn-primary px-4 fw-semibold">
                                            <i class="fas fa-plus-circle me-1"></i> Register New Product
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Scan Another</button>
                                    </div>
                                ` : `
                                    <p class="small text-secondary">Please contact a manager or administrator to register this barcode.</p>
                                    <button type="button" class="btn btn-secondary px-4 mt-2" data-bs-dismiss="modal">Close</button>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove old modal if exists
            document.getElementById('unregisteredBarcodeModal')?.remove();
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const m = new bootstrap.Modal(document.getElementById('unregisteredBarcodeModal'));
            m.show();
        }

        /**
         * Product Stock Diagnostic Modal
         */
        openProductDiagnosticModal(product) {
            const isCashier = window.location.pathname.includes('/cashier/');
            const isAdmin = window.location.pathname.includes('/admin/');
            const inventoryUrl = (isCashier || isAdmin) ? '../admin/inventory.php' : 'admin/inventory.php';

            const modalHtml = `
                <div class="modal fade" id="smartBarcodeDiagnosticModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                            <div class="modal-header bg-primary text-white border-0" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                                <h5 class="modal-title fw-bold">
                                    <i class="fas fa-cube me-2"></i> Smart Product Diagnostic
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-light rounded p-3 me-3 text-center border" style="min-width: 70px;">
                                        <i class="fas fa-barcode fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-1">${product.name}</h5>
                                        <div class="text-muted small font-monospace">Barcode: ${product.barcode || 'N/A'}</div>
                                        <span class="badge bg-secondary me-1">${product.category_name}</span>
                                        <span class="badge bg-info text-dark">${product.supplier_name}</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="row g-2 text-center mb-3">
                                    <div class="col-4">
                                        <div class="p-2 border rounded bg-light">
                                            <small class="text-muted d-block">On Hand</small>
                                            <strong class="fs-5 ${product.is_out_of_stock ? 'text-danger' : (product.is_low_stock ? 'text-warning' : 'text-success')}">
                                                ${product.stock} ${product.unit}
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 border rounded bg-light">
                                            <small class="text-muted d-block">Reorder Point</small>
                                            <strong class="fs-5 text-dark">${product.reorder_level} ${product.unit}</strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 border rounded bg-light">
                                            <small class="text-muted d-block">Price</small>
                                            <strong class="fs-5 text-primary">₱${product.price.toFixed(2)}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-2 bg-light rounded small border mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>30-Day Sales Velocity (ADS):</span>
                                        <strong>${product.ads_30d} ${product.unit}/day</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Estimated Days of Supply:</span>
                                        <strong>${product.days_of_supply} days</strong>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.VoiceAssistant && window.VoiceAssistant.speak('Product ${product.name.replace(/'/g, &quot;\\'&quot;)}. Current stock is ${product.stock} units.')">
                                        <i class="fas fa-volume-high me-1"></i> Speak Product Status
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('smartBarcodeDiagnosticModal')?.remove();
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const m = new bootstrap.Modal(document.getElementById('smartBarcodeDiagnosticModal'));
            m.show();
        }

        /**
         * Physical Stock Counting Modal
         */
        openStockCountModal(product) {
            const modalHtml = `
                <div class="modal fade" id="smartBarcodeStockCountModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                            <div class="modal-header bg-dark text-white border-0">
                                <h5 class="modal-title fw-bold">
                                    <i class="fas fa-clipboard-check text-warning me-2"></i> Physical Stock Count Audit
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <h5 class="fw-bold mb-1">${product.name}</h5>
                                <div class="text-muted small mb-3">Barcode: ${product.barcode || 'N/A'}</div>
                                <div class="alert alert-info py-2 small mb-3">
                                    System Expected Stock: <strong>${product.stock} ${product.unit}</strong>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Physical Counted Quantity (${product.unit}):</label>
                                    <input type="number" id="physicalCountInput" class="form-control form-control-lg text-center fw-bold" value="${product.stock}" min="0">
                                </div>
                                <div id="stockCountVariancePreview" class="p-2 rounded text-center small fw-semibold bg-light border mb-3">
                                    Variance: 0 (Matches system stock)
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="applyStockCorrectionCheck" checked>
                                    <label class="form-check-label small" for="applyStockCorrectionCheck">
                                        Update live inventory to physical count if verified
                                    </label>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="submitStockCountBtn" class="btn btn-primary px-4 fw-bold">Confirm Count</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('smartBarcodeStockCountModal')?.remove();
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const m = new bootstrap.Modal(document.getElementById('smartBarcodeStockCountModal'));
            m.show();

            const countInput = document.getElementById('physicalCountInput');
            const preview = document.getElementById('stockCountVariancePreview');
            countInput.focus();
            countInput.select();

            countInput.addEventListener('input', () => {
                const physical = parseInt(countInput.value, 10) || 0;
                const diff = physical - product.stock;
                if (diff === 0) {
                    preview.className = 'p-2 rounded text-center small fw-semibold bg-success bg-opacity-10 text-success border border-success';
                    preview.innerHTML = `✓ Variance: 0 ${product.unit} (Exact Match)`;
                } else if (diff > 0) {
                    preview.className = 'p-2 rounded text-center small fw-semibold bg-warning bg-opacity-10 text-warning-emphasis border border-warning';
                    preview.innerHTML = `⚠️ Variance: +${diff} ${product.unit} (Over expected stock)`;
                } else {
                    preview.className = 'p-2 rounded text-center small fw-semibold bg-danger bg-opacity-10 text-danger border border-danger';
                    preview.innerHTML = `🚨 Variance: ${diff} ${product.unit} (Shrinkage / Missing)`;
                }
            });

            document.getElementById('submitStockCountBtn').addEventListener('click', async () => {
                const physical = parseInt(countInput.value, 10) || 0;
                const apply = document.getElementById('applyStockCorrectionCheck').checked;

                const isCashier = window.location.pathname.includes('/cashier/');
                const isAdmin = window.location.pathname.includes('/admin/');
                const apiPath = (isCashier || isAdmin) ? '../api/barcode_service.php' : 'api/barcode_service.php';

                try {
                    const res = await fetch(`${apiPath}?action=stock_count_log`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            product_id: product.id,
                            physical_count: physical,
                            apply_correction: apply
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showHUD(data.message, 'success');
                        m.hide();
                    } else {
                        alert(data.error || 'Failed to record physical count.');
                    }
                } catch (e) {
                    alert('Communication error recording stock count.');
                }
            });
        }

        /**
         * Receiving / Inward Delivery Modal
         */
        openReceivingModal(product) {
            const modalHtml = `
                <div class="modal fade" id="smartBarcodeReceivingModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                            <div class="modal-header bg-success text-white border-0">
                                <h5 class="modal-title fw-bold">
                                    <i class="fas fa-truck-loading me-2"></i> Receive Inward Delivery
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <h5 class="fw-bold mb-1">${product.name}</h5>
                                <div class="text-muted small mb-3">Current Stock: <strong>${product.stock} ${product.unit}</strong> | Supplier: ${product.supplier_name}</div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Received Quantity (${product.unit}):</label>
                                    <input type="number" id="receiveQuantityInput" class="form-control form-control-lg text-center fw-bold" value="10" min="1">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Reference Invoice / PO No. (Optional):</label>
                                    <input type="text" id="receiveInvoiceRef" class="form-control" placeholder="e.g. INV-9901">
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="confirmReceiveBtn" class="btn btn-success px-4 fw-bold">Confirm Receiving</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('smartBarcodeReceivingModal')?.remove();
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const m = new bootstrap.Modal(document.getElementById('smartBarcodeReceivingModal'));
            m.show();

            const qtyInput = document.getElementById('receiveQuantityInput');
            qtyInput.focus();
            qtyInput.select();

            document.getElementById('confirmReceiveBtn').addEventListener('click', async () => {
                const qty = parseInt(qtyInput.value, 10) || 0;
                const ref = document.getElementById('receiveInvoiceRef').value.trim();

                const isCashier = window.location.pathname.includes('/cashier/');
                const isAdmin = window.location.pathname.includes('/admin/');
                const apiPath = (isCashier || isAdmin) ? '../api/barcode_service.php' : 'api/barcode_service.php';

                try {
                    const res = await fetch(`${apiPath}?action=receive_delivery`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            product_id: product.id,
                            received_quantity: qty,
                            reference_invoice: ref
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showHUD(data.message, 'success');
                        m.hide();
                    } else {
                        alert(data.error || 'Failed to receive stock.');
                    }
                } catch (e) {
                    alert('Communication error receiving stock.');
                }
            });
        }

        /**
         * Interactive Camera Barcode Scanner Modal
         */
        injectCameraModal() {
            if (document.getElementById('smartCameraScannerModal')) return;

            const modalHtml = `
                <div class="modal fade" id="smartCameraScannerModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg bg-dark text-white" style="border-radius: 20px;">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                                    <i class="fas fa-camera text-primary me-2"></i> Smart Barcode Scanner
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <div class="scanner-viewport-wrapper position-relative mx-auto rounded-3 overflow-hidden border border-secondary" style="max-width: 380px; height: 260px; background: #000;">
                                    <video id="smartCameraVideo" playsinline autoplay muted style="width: 100%; height: 100%; object-fit: cover;"></video>
                                    <div class="scanner-overlay position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center pointer-events-none">
                                        <div class="scanner-target-box border border-primary rounded" style="width: 220px; height: 120px; box-shadow: 0 0 0 9999px rgba(0,0,0,0.5); position: relative;">
                                            <div class="scanner-laser position-absolute w-100 bg-danger" style="height: 2px; top: 0; box-shadow: 0 0 8px #ff4d4f; animation: scanLaser 2s infinite alternate;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 small text-white-50">
                                    Point camera at product barcode or type manual barcode below:
                                </div>
                                <div class="input-group mt-3 mx-auto" style="max-width: 380px;">
                                    <input type="text" id="manualBarcodeInput" class="form-control font-monospace text-center" placeholder="Enter barcode number...">
                                    <button class="btn btn-primary fw-semibold" id="manualBarcodeSubmitBtn" type="button">Scan</button>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0 justify-content-between">
                                <span class="badge bg-secondary" id="scannerModeBadge">Context: POS</span>
                                <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes scanLaser {
                        0% { top: 5%; }
                        100% { top: 95%; }
                    }
                </style>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Manual barcode input listener
            document.getElementById('manualBarcodeSubmitBtn')?.addEventListener('click', () => {
                const val = document.getElementById('manualBarcodeInput')?.value.trim();
                if (val) {
                    const m = bootstrap.Modal.getInstance(document.getElementById('smartCameraScannerModal'));
                    if (m) m.hide();
                    this.processBarcode(val);
                }
            });

            document.getElementById('manualBarcodeInput')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('manualBarcodeSubmitBtn')?.click();
                }
            });
        }

        openCameraScanner() {
            this.detectContext();
            const modalEl = document.getElementById('smartCameraScannerModal');
            if (!modalEl) return;

            const modeBadge = document.getElementById('scannerModeBadge');
            if (modeBadge) modeBadge.innerText = `Context: ${this.currentWorkflow.toUpperCase()}`;

            const m = new bootstrap.Modal(modalEl);
            m.show();

            const video = document.getElementById('smartCameraVideo');
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(stream => {
                        this.videoStream = stream;
                        video.srcObject = stream;
                        video.play();
                        this.startVideoBarcodeDetection(video);
                    })
                    .catch(err => {
                        console.warn('[SmartBarcode] Camera access denied or not available:', err);
                    });
            }

            modalEl.addEventListener('hidden.bs.modal', () => {
                if (this.videoStream) {
                    this.videoStream.getTracks().forEach(track => track.stop());
                    this.videoStream = null;
                }
                if (this.detectorInterval) {
                    clearInterval(this.detectorInterval);
                    this.detectorInterval = null;
                }
            }, { once: true });
        }

        startVideoBarcodeDetection(video) {
            if ('BarcodeDetector' in window) {
                const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'] });
                this.detectorInterval = setInterval(async () => {
                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        try {
                            const barcodes = await detector.detect(video);
                            if (barcodes.length > 0) {
                                const detected = barcodes[0].rawValue;
                                const m = bootstrap.Modal.getInstance(document.getElementById('smartCameraScannerModal'));
                                if (m) m.hide();
                                this.processBarcode(detected);
                            }
                        } catch (e) {}
                    }
                }, 300);
            }
        }

        /**
         * Floating HUD Feedback
         */
        injectHUD() {
            if (document.getElementById('smartBarcodeHUD')) return;
            const hud = document.createElement('div');
            hud.id = 'smartBarcodeHUD';
            hud.style.cssText = `
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                background: rgba(17, 24, 39, 0.95);
                color: #fff;
                padding: 10px 20px;
                border-radius: 30px;
                font-size: 14px;
                font-weight: 600;
                box-shadow: 0 10px 25px rgba(0,0,0,0.3);
                backdrop-filter: blur(10px);
                z-index: 10000;
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
                opacity: 0;
                pointer-events: none;
                display: flex;
                align-items: center;
                gap: 8px;
            `;
            document.body.appendChild(hud);
        }

        showHUD(message, type = 'info') {
            const hud = document.getElementById('smartBarcodeHUD');
            if (!hud) return;

            let icon = 'fa-info-circle text-info';
            if (type === 'success') icon = 'fa-check-circle text-success';
            if (type === 'danger') icon = 'fa-times-circle text-danger';
            if (type === 'warning') icon = 'fa-exclamation-triangle text-warning';

            hud.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
            hud.style.opacity = '1';
            hud.style.transform = 'translateX(-50%) translateY(0)';

            clearTimeout(this.hudTimeout);
            this.hudTimeout = setTimeout(() => {
                hud.style.opacity = '0';
                hud.style.transform = 'translateX(-50%) translateY(100px)';
            }, 3000);
        }

        /**
         * Web Audio Synthesizer Beep Feedback (Zero assets needed)
         */
        playBeep(success = true) {
            if (!this.soundEnabled) return;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                if (!this.audioCtx) this.audioCtx = new AudioContext();

                const ctx = this.audioCtx;
                if (ctx.state === 'suspended') ctx.resume();

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);

                if (success) {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime); // A5
                    osc.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.08); // A6
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.12);
                } else {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    gain.gain.setValueAtTime(0.18, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.25);
                }
            } catch (e) {}
        }
    }

    // Initialize when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new SmartBarcodeManager());
    } else {
        new SmartBarcodeManager();
    }

})(window);
