class GSTInvoiceApp {
    constructor() {
        this.businessState = ''; // Default: Maharashtra
        this.currentInvoiceId = null;
        this.baseUrl = window.location.origin + (window.location.pathname.includes('gst_invoice') ? '/gst_invoice/' : '/');
        this.init();
    }

    async init() {
        await this.loadBusinessSettings();
        this.setupEventListeners();
        this.setupProductSearch();
        this.generateInvoiceNumber();
        await this.loadCustomers();
        await this.loadIndianStates();
        this.setDefaultDates();
        this.updateTaxRates();
        this.checkBitrixConnection();
    }


    async loadBusinessSettings() {
        try {
            const response = await $.get(`${this.baseUrl}api/get_business_settings.php`);
            this.businessState = response.state_code;
            $('#business-name').val(response.name);
            $('#business-gstin').val(response.gstin);
            $('#business-address').val(response.address);
        } catch (error) {
            console.error('Failed to load business settings:', error);
        }
    }

    setupEventListeners() {
        // Invoice Items
        $('#add-item').click(() => {
        this.addNewItemRow();
        });

        $(document).on('click', '.remove-item', (e) => this.removeItemRow(e));
        $(document).on('input', '.item-qty, .item-rate', () => this.calculateItemAmount());
        
        // Customer
        $('#customer-select').change(() => this.loadCustomerDetails());
        $('#customer-state').change(() => this.updateTaxRates());
        
        // Actions
        $('#preview-invoice').click(() => this.showInvoicePreview());
        $('#save-draft').click(() => this.saveInvoice('draft'));
        $('#finalize-invoice').click(() => this.saveInvoice('finalized'));
        $('#generate-invoice').click(() => this.generatePDF());
        $('#send-email').click(() => this.sendInvoiceEmail());
        $('#bitrix-sync').click(() => this.syncWithBitrix());
        
        // Preview Modal
        $('#print-preview').click(() => this.printPreview());
        $('#download-preview').click(() => this.downloadPreview());

        // Bitrix Settings
        $('#bitrix-settings-form').submit((e) => {
            e.preventDefault();
            this.saveBitrixSettings();
        });
        $('#test-connection').click(() => this.testBitrixConnection());

         $(document).on('input', '.item-qty, .item-rate, .item-discount, .item-tax', () => {
            this.calculateItemAmount();
        });
        
        // Add product selection handler if you have a product dropdown
        $(document).on('change', '.product-select', function() {
            const productId = $(this).val();
            if (productId) {
                // Fetch product details and populate the row
                $.get(`${this.baseUrl}api/get_product.php?id=${productId}`, (product) => {
                    const row = $(this).closest('tr');
                    row.find('.item-description').val(product.name);
                    row.find('.item-hsn').val(product.hsn_sac_code);
                    row.find('.item-rate').val(product.price);
                    row.find('.item-discount').val(product.discount);
                    row.find('.item-tax').val(product.tax_rate);
                    row.find('.item-unit').val(product.unit || 'unit');
                    row.data('product-id', product.id);
                    this.calculateItemAmount(row);
                });
            }
        });

        // New event listeners for product search
            $(document).on('input', '.item-description', (e) => this.handleItemSearch(e));
            $(document).on('focus', '.item-description', (e) => this.showItemSuggestions(e));
            $(document).on('blur', '.item-description', (e) => setTimeout(() => this.hideItemSuggestions(e), 200));
            $(document).on('click', '.item-suggestion', (e) => this.selectItemSuggestion(e));
            
            // Toggle summary visibility
            $('#show-summary').change(() => {
                $('#summary-container').toggle($('#show-summary').is(':checked'));
            });
    }

    showAlert(title, message, type) {
        const alertClass = type === 'error' ? 'alert-danger' : 
                        type === 'info' ? 'alert-info' : 'alert-success';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <strong>${title}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#alerts-container').html(alertHtml);
        setTimeout(() => $('.alert').alert('close'), 5000);
    }

    async handleItemSearch(event) {
        const input = $(event.target);
        const suggestions = input.next('.item-suggestions');
        const query = input.val().trim();
        
        if (query.length < 2) {
            suggestions.hide().empty();
            return;
        }
        
        const products = await this.searchProducts(query);
        suggestions.empty();
        
        if (products.length === 0) {
            suggestions.append('<div class="dropdown-item">No products found</div>');
            suggestions.append(`
                <div class="item-suggestion add-new-item" data-search="${query}">
                    <i class="bi bi-plus-circle"></i> Add New Item: "${query}"
                </div>
            `);
        } else {
            products.forEach(product => {
                const rate = parseFloat(product.rate) || 0;
                const item = $(`
                    <div class="dropdown-item item-suggestion" 
                        data-id="${product.id}"
                        data-name="${product.name}"
                        data-hsn="${product.hsn_sac_code || ''}"
                        data-rate="${rate}"
                        data-tax="${product.tax_rate || 18}">
                        <strong>${product.name}</strong>
                        <div class="text-muted small">
                            ${product.hsn_sac_code ? `HSN/SAC: ${product.hsn_sac_code}` : ''}
                            <span class="float-end">₹${rate.toFixed(2)}</span>
                        </div>
                    </div>
                `);
                suggestions.append(item);
            });
            suggestions.append(`
                <div class="item-suggestion add-new-item" data-search="${query}">
                    <i class="bi bi-plus-circle"></i> Add New Item: "${query}"
                </div>
            `);
        }
        
        suggestions.show();
    }

    showItemSuggestions(event) {
        const input = $(event.target);
        if (input.val().trim() !== '') {
            input.next('.item-suggestions').show();
        }
    }

    hideItemSuggestions(event) {
        $(event.target).next('.item-suggestions').hide();
    }

    selectItemSuggestion(event) {
        const suggestion = $(event.currentTarget);
        const row = suggestion.closest('tr');
        const product = {
            id: suggestion.data('id'),
            name: suggestion.data('name'),
            hsn_sac_code: suggestion.data('hsn'),
            rate: parseFloat(suggestion.data('rate')) || 0,
            discount: parseFloat(suggestion.data('discount')) || 0,
            tax_rate: parseFloat(suggestion.data('tax')) || 18
        };
        
        row.find('.item-description').val(product.name);
        row.find('.item-hsn').val(product.hsn_sac_code);
        row.find('.item-rate').val(product.rate.toFixed(2));
        row.find('.item-discount').val(product.discount.toFixed(2));
        row.find('.item-tax').val(product.tax_rate.toFixed(2));
        row.data({
            'product-id': product.id,
            'tax-rate': product.tax_rate
        });
        
        row.find('.item-suggestions').hide().empty();
        this.calculateItemAmount(row);

        if (suggestion.hasClass('add-new-item')) {
            // Handle "Add New" click
            const searchTerm = suggestion.data('search');
            this.showAddItemModal(searchTerm, row);
        } 
    }

    showAddItemModal(searchTerm, row) {
        // Create or show your modal
        const modal = $('#addItemModal');
        
        if (modal.length === 0) {
            // Create the modal if it doesn't exist
            $('body').append(`
                <div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="quickAddItemForm">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add New Item</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="quick_add">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">HSN/SAC Code</label>
                                        <input type="text" class="form-control" name="hsn_sac">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rate (₹) *</label>
                                        <input type="number" class="form-control" name="rate" step="0.01" min="0" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Discount (%)</label>
                                        <input type="number" class="form-control" name="discount" id="productDiscount" step="0.01" min="0" max="100" value="0">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tax Rate (%) *</label>
                                        <input type="number" class="form-control" name="tax_rate" step="0.01" min="0" max="100" value="18" required>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="is_service" id="quickIsService">
                                        <label class="form-check-label" for="quickIsService">This is a service</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `);
            
            // Initialize the modal
            this.initQuickAddForm(row);
        }
        
        // Set the search term as the default name
        $('#addItemModal input[name="name"]').val(searchTerm);
        
        // Show the modal
        $('#addItemModal').modal('show');
    }

    initQuickAddForm(row) {
        $('#quickAddItemForm').submit((e) => {
            e.preventDefault();

            const formData = {
                action: 'quick_add',
                name: $('#quickAddItemForm input[name="name"]').val(),
                rate: $('#quickAddItemForm input[name="rate"]').val(),
                tax_rate: $('#quickAddItemForm input[name="tax_rate"]').val(),
                hsn_sac: $('#quickAddItemForm input[name="hsn_sac"]').val() || '',
                discount: $('#quickAddItemForm input[name="discount"]').val() || 0,
                is_service: $('#quickAddItemForm input[name="is_service"]').is(':checked') ? 1 : 0,
                description: '' // Optional field
            };
            
            $.ajax({
                url: `${this.baseUrl}/products.php`,
                method: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        // Populate the row with the new product
                        row.find('.item-description').val(response.product.name);
                        row.find('.item-hsn').val(response.product.hsn_sac_code);
                        row.find('.item-rate').val(response.product.rate);
                        row.find('.item-discount').val(response.product.discount);
                        row.find('.item-tax').val(response.product.tax_rate);
                        row.data('product-id', response.product.id);
                        this.calculateItemAmount(row);
                        
                        // Close the modal
                        $('#addItemModal').modal('hide');
                    } else {
                        alert('Error: ' + (response.message || 'Failed to add product'));
                    }
                },
                error: () => {
                    alert('Error adding item');
                }
            });
        });
    }

    calculateItemAmount(rowElement = null) {
        const row = rowElement || $(event.target).closest('tr');
        const qty = parseFloat(row.find('.item-qty').val()) || 0;
        const rate = parseFloat(row.find('.item-rate').val()) || 0;
        const discountPercent = parseFloat(row.find('.item-discount').val()) || 0;
        const taxRate = parseFloat(row.find('.item-tax').val()) || row.data('tax-rate') || 18;
        
        // Update the tax rate field and data attribute
        row.find('.item-tax').val(taxRate.toFixed(2));
        row.data('tax-rate', taxRate);
        
        // Calculate amounts
        const grossAmount = qty * rate;
        const discountAmount = grossAmount * (discountPercent / 100);
        const taxableValue = grossAmount - discountAmount;
        const taxAmount = taxableValue * (taxRate / 100);
        const netAmount = taxableValue + taxAmount;
        
        // Update row amounts
        row.find('.item-amount').val(netAmount.toFixed(2));
        this.calculateTotals();
    }

    calculateTotals() {
        let subtotal = 0;
        let totalTax = 0;
        const taxDetails = {};
        
        const businessState = '24'; // Your business state code
        const customerState = $('#customer-state').val();
        const isSameState = customerState === businessState;
        
        // Calculate totals from all items
        $('#invoice-items tbody tr').each(function() {
            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const rate = parseFloat($(this).find('.item-rate').val()) || 0;
            const itemTaxRate = parseFloat($(this).data('tax-rate')) || 18;
            
            const amount = qty * rate;
            subtotal += amount;
            
            // Calculate tax based on state and item's tax rate
            if (isSameState) {
                // CGST + SGST (split tax rate equally)
                const cgst = amount * (itemTaxRate / 200);
                const sgst = amount * (itemTaxRate / 200);
                totalTax += cgst + sgst;
                
                // Track tax details by rate
                const rateKey = (itemTaxRate / 2).toFixed(2);
                taxDetails[`cgst_${rateKey}`] = (taxDetails[`cgst_${rateKey}`] || 0) + cgst;
                taxDetails[`sgst_${rateKey}`] = (taxDetails[`sgst_${rateKey}`] || 0) + sgst;
            } else {
                // IGST (full tax rate)
                const igst = amount * (itemTaxRate / 100);
                totalTax += igst;
                
                // Track tax details by rate
                const rateKey = itemTaxRate.toFixed(2);
                taxDetails[`igst_${rateKey}`] = (taxDetails[`igst_${rateKey}`] || 0) + igst;
            }
        });
        
        // Update UI
        $('#subtotal').text('₹' + subtotal.toFixed(2));
        $('#total').text('₹' + (subtotal + totalTax).toFixed(2));
        
        // Update tax breakdown
        const taxContainer = $('#tax-container');
        taxContainer.empty();
        
        if (isSameState) {
            // Show CGST + SGST for each tax rate
            Object.keys(taxDetails).forEach(key => {
                if (key.startsWith('cgst_')) {
                    const rate = key.split('_')[1];
                    const amount = taxDetails[key];
                    const sgstAmount = taxDetails[`sgst_${rate}`] || 0;
                    
                    taxContainer.append(`
                        <div class="row mb-1">
                            <div class="col-6 text-end">CGST (${rate}%):</div>
                            <div class="col-6 text-end">₹${amount.toFixed(2)}</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-6 text-end">SGST (${rate}%):</div>
                            <div class="col-6 text-end">₹${sgstAmount.toFixed(2)}</div>
                        </div>
                    `);
                }
            });
        } else {
            // Show IGST for each tax rate
            Object.keys(taxDetails).forEach(key => {
                if (key.startsWith('igst_')) {
                    const rate = key.split('_')[1];
                    const amount = taxDetails[key];
                    
                    taxContainer.append(`
                        <div class="row mb-1">
                            <div class="col-6 text-end">IGST (${rate}%):</div>
                            <div class="col-6 text-end">₹${amount.toFixed(2)}</div>
                        </div>
                    `);
                }
            });
        }
    }



    async checkBitrixConnection() {
        try {
            const response = await $.get(`${this.baseUrl}api/get_bitrix_settings.php`);
            if (response.webhook_url) {
                $('#bitrix-webhook').val(response.webhook_url);
                $('#bitrix-token').val(response.auth_token);
                $('#bitrix-template').val(response.invoice_template_id);
                this.updateSyncStatus(response.sync_status);
            }
        } catch (error) {
            console.error('Failed to load Bitrix settings:', error);
        }
    }

    updateSyncStatus(status) {
        if (status.customers) {
            $('#customer-sync-time').text(status.customers.last_sync || 'Never');
            $('#customer-sync-status').text(status.customers.status || 'Not synced');
        }
        if (status.products) {
            $('#product-sync-time').text(status.products.last_sync || 'Never');
            $('#product-sync-status').text(status.products.status || 'Not synced');
        }
        if (status.invoices) {
            $('#invoice-sync-time').text(status.invoices.last_sync || 'Never');
            $('#invoice-sync-status').text(status.invoices.status || 'Not synced');
        }
    }

    generateInvoiceNumber() {
        const now = new Date();
        const prefix = $('#invoice-prefix').val() || 'INV';
        const year = now.getFullYear().toString().slice(-2);
        const month = (now.getMonth() + 1).toString().padStart(2, '0');
        const sequence = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        const ms = now.getMilliseconds().toString().padStart(3, '0');
        $('#invoice-number').val(`${prefix}${year}${month}-${sequence}-${ms}`);
    }

    setDefaultDates() {
        const now = new Date();
        $('#invoice-date').val(now.toISOString().split('T')[0]);
        
        const dueDate = new Date();
        dueDate.setDate(now.getDate() + 15);
        $('#due-date').val(dueDate.toISOString().split('T')[0]);
    }

    addNewItemRow(product = null) {
        const rowCount = $('#invoice-items tbody tr').length + 1;
        const newRow = `
            <tr data-product-id="${product ? product.id : ''}">
            <td></td>
                <td>
                    <input type="text" class="form-control form-control-sm item-description" 
                        value="${product ? product.name : ''}" 
                        placeholder="Type or click to select an item" autocomplete="off">
                    <div class="dropdown-menu w-100 item-suggestions"></div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm item-hsn" 
                        value="${product ? product.hsn_sac_code || '' : ''}" placeholder="HSN/SAC">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm item-qty" value="1" min="0.01" step="0.01">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm item-rate" 
                        value="${product ? product.rate.toFixed(2) : '0.00'}" min="0" step="0.01">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm item-discount" 
                        value="${product ? product.discount.toFixed(2) : '0'}" min="0" max="100" step="0.01">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm item-tax" 
                        value="${product ? product.tax_rate.toFixed(2) : '18'}" min="0" max="100" step="0.01">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm item-amount" value="0.00" readonly>
                </td>
                <td>
                    <button class="btn btn-sm btn-danger remove-item">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        const row = $(newRow);
        $('#invoice-items tbody').append(row);
        
        if (product) {
            row.data({
                'product-id': product.id,
                'tax-rate': product.tax_rate || 18
            });
        }
        
        this.calculateItemAmount(row);
        return row;
    }


    removeItemRow(event) {
        event.stopPropagation();
        const rowToRemove = $(event.currentTarget).closest('tr');
        const rowIndex = rowToRemove.index();
        
        if ($('#invoice-items tbody tr').length > 1) {
            rowToRemove.remove();
            this.updateRowNumbers();
            this.calculateTotals();
        } else {
            // Clear the row instead of removing if it's the last one
            rowToRemove.find('.item-description').val('');
            rowToRemove.find('.item-hsn').val('');
            rowToRemove.find('.item-qty').val('1');
            rowToRemove.find('.item-rate').val('0.00');
            rowToRemove.find('.item-discount').val('0');
            rowToRemove.find('.item-tax').val('18');
            rowToRemove.find('.item-amount').val('0.00');
            rowToRemove.removeData('product-id');
            rowToRemove.removeData('tax-rate');
            this.calculateTotals();
        }
    }


    updateRowNumbers() {
        $('#invoice-items tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    // calculateItemAmount(rowElement = null) {
    //     const row = rowElement || $(event.target).closest('tr');
    //     const qty = parseFloat(row.find('.item-qty').val()) || 0;
    //     const rate = parseFloat(row.find('.item-rate').val()) || 0;
    //     const discount = parseFloat(row.find('.item-discount').val()) || 0;
    //     const taxRate = parseFloat(row.find('.item-tax').val()) || 0;
        
    //     // Calculate amounts
    //     const itemAmount = qty * rate;
    //     const discountAmount = itemAmount * (discount / 100);
    //     const taxableValue = itemAmount - discountAmount;
        
    //     // Calculate taxes based on state
    //     const businessState = '24'; // Your business state code
    //     const customerState = $('#customer-state').val();
    //     const isSameState = customerState === businessState;
        
    //     let itemCgst = 0;
    //     let itemSgst = 0;
    //     let itemIgst = 0;
        
    //     if (isSameState) {
    //         // CGST + SGST (split tax rate equally)
    //         itemCgst = taxableValue * (taxRate / 200); // Half of tax rate
    //         itemSgst = taxableValue * (taxRate / 200); // Half of tax rate
    //     } else {
    //         // IGST (full tax rate)
    //         itemIgst = taxableValue * (taxRate / 100);
    //     }
        
    //     const itemTotal = taxableValue + itemCgst + itemSgst + itemIgst;
        
    //     // Update row amounts
    //     row.find('.item-amount').val(itemTotal.toFixed(2));
    //     this.calculateTotals();
    // }

    calculateTotals() {
        let subtotal = 0;
        let totalDiscount = 0;
        let totalTax = 0;
        let cgstAmount = 0;
        let sgstAmount = 0;
        let igstAmount = 0;
        
        const businessState = '24'; // Your business state code
        const customerState = $('#customer-state').val();
        const isSameState = customerState === businessState;
        
        // Calculate totals from all items
        $('#invoice-items tbody tr').each(function() {
            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const rate = parseFloat($(this).find('.item-rate').val()) || 0;
            const discount = parseFloat($(this).find('.item-discount').val()) || 0;
            const taxRate = parseFloat($(this).find('.item-tax').val()) || 0;
            
            const itemAmount = qty * rate;
            const discountAmount = itemAmount * (discount / 100);
            const taxableValue = itemAmount - discountAmount;
            
            let itemCgst = 0;
            let itemSgst = 0;
            let itemIgst = 0;
            
            if (isSameState) {
                itemCgst = taxableValue * (taxRate / 200);
                itemSgst = taxableValue * (taxRate / 200);
            } else {
                itemIgst = taxableValue * (taxRate / 100);
            }
            
            subtotal += itemAmount;
            totalDiscount += discountAmount;
            cgstAmount += itemCgst;
            sgstAmount += itemSgst;
            igstAmount += itemIgst;
            totalTax += itemCgst + itemSgst + itemIgst;
        });

        const grandTotal = subtotal - totalDiscount + totalTax;
        
        // Update UI
        $('#subtotal').text('₹' + subtotal.toFixed(2));
        $('#total-discount').text('₹' + totalDiscount.toFixed(2));
        $('#total-tax').text('₹' + totalTax.toFixed(2));
        $('#total').text('₹' + grandTotal.toFixed(2));
        
        // Update tax breakdown
        const taxContainer = $('#tax-container');
        taxContainer.empty();
        
        if (isSameState) {
            taxContainer.append(`
                <div class="row mb-1">
                    <div class="col-6 text-end">CGST (${(totalTax > 0 ? (cgstAmount/totalTax*18) : 0).toFixed(0)}%):</div>
                    <div class="col-6 text-end">₹${cgstAmount.toFixed(2)}</div>
                </div>
                <div class="row mb-1">
                    <div class="col-6 text-end">SGST (${(totalTax > 0 ? (sgstAmount/totalTax*18) : 0).toFixed(0)}%):</div>
                    <div class="col-6 text-end">₹${sgstAmount.toFixed(2)}</div>
                </div>
            `);
        } else {
            taxContainer.append(`
                <div class="row mb-1">
                    <div class="col-6 text-end">IGST (${(totalTax > 0 ? (igstAmount/totalTax*18) : 0).toFixed(0)}%):</div>
                    <div class="col-6 text-end">₹${igstAmount.toFixed(2)}</div>
                </div>
            `);
        }
    }


    updateTaxRates() {
        const customerState = $('#customer-state').val();
        const taxContainer = $('#tax-container');
        taxContainer.empty();
        
        if (!customerState || !this.businessState) {
            $('#total').text(`₹${parseFloat($('#subtotal').text().replace('₹', '') || 0)}`);
            return;
        }
        
        const subtotal = parseFloat($('#subtotal').text().replace('₹', '')) || 0;
        let taxHTML = '';
        let totalTax = 0;
        
        // Intra-state transaction (same state)
        if (customerState === this.businessState) {
            // CGST + SGST (equal split)
            const cgstRate = 9; // Example rate (total 18% GST)
            const sgstRate = 9; // Example rate (total 18% GST)
            const cgstAmount = (subtotal * cgstRate / 100);
            const sgstAmount = (subtotal * sgstRate / 100);
            totalTax = cgstAmount + sgstAmount;
            
            taxHTML = `
                <div class="row mb-1">
                    <div class="col-6 text-end">CGST (${cgstRate}%):</div>
                    <div class="col-6 text-end">₹${cgstAmount.toFixed(2)}</div>
                </div>
                <div class="row mb-1">
                    <div class="col-6 text-end">SGST (${sgstRate}%):</div>
                    <div class="col-6 text-end">₹${sgstAmount.toFixed(2)}</div>
                </div>
            `;
        } 
        // Inter-state transaction
        else {
            // IGST (single tax)
            const igstRate = 18; // Example rate
            const igstAmount = (subtotal * igstRate / 100);
            totalTax = igstAmount;
            
            taxHTML = `
                <div class="row mb-1">
                    <div class="col-6 text-end">IGST (${igstRate}%):</div>
                    <div class="col-6 text-end">₹${igstAmount.toFixed(2)}</div>
                </div>
            `;
        }
        
        taxContainer.html(taxHTML);
        const total = subtotal + totalTax;
        $('#total').text(`₹${total.toFixed(2)}`);
    }

    async loadIndianStates() {
        try {
            const response = await $.get(`${this.baseUrl}api/get_indian_states.php`);
            const dropdown = $('#customer-state');
            dropdown.empty().append('<option value="">Select State</option>');
            response.forEach(state => {
                dropdown.append(`<option value="${state.code}">${state.name}</option>`);
            });
        } catch (error) {
            console.error('Failed to load states:', error);
            this.showAlert('Error', 'Failed to load Indian states', 'error');
        }
    }

    async loadCustomers() {
        try {
            const response = await $.ajax({
                url: `${this.baseUrl}api/get_customers.php`,
                dataType: 'json'
            });

            if (!response.success) {
                throw new Error(response.message || 'Failed to load customers');
            }

            const dropdown = $('#customer-select');
            dropdown.empty().append('<option value="">Select Customer</option>');

            if (Array.isArray(response.data) && response.data.length > 0) {
                response.data.forEach(customer => {
                    dropdown.append(
                        `<option value="${customer.id}" 
                        data-gstin="${customer.gstin || ''}"
                        data-state="${customer.state_code || ''}"
                        data-address="${customer.address || ''}">
                        ${customer.name}${customer.gstin ? ` (${customer.gstin})` : ''}
                        </option>`
                    );
                });
            } else {
                this.showAlert('Info', 'No customers found in database', 'info');
            }

            dropdown.append('<option value="new">+ Add New Customer</option>');

        } catch (error) {
            console.error('Failed to load customers:', error);
            this.showAlert('Error', 
                `Failed to load customers: ${error.message || 'Unknown error'}`, 
                'error');
        }
    }
    

    async loadCustomerDetails() {
        const customerId = $('#customer-select').val();
        if (customerId === 'new') {
            $('#customer-details').show();
            $('#customer-name, #customer-gstin, #customer-state, #customer-address').val('');
        } else if (customerId) {
            try {
                const response = await $.get(`${this.baseUrl}api/get_customers.php?id=${customerId}`);
                $('#customer-name').val(response.name);
                $('#customer-gstin').val(response.gstin || '');
                $('#customer-state').val(response.state_code || '');
                $('#customer-address').val(response.billing_address || '');
                $('#customer-phone').val(response.phone || '');
                $('#customer-email').val(response.email || '');
                $('#customer-details').show();
                this.updateTaxRates();
            } catch (error) {
                console.error('Failed to load customer details:', error);
                this.showAlert('Error', 'Failed to load customer details', 'error');
            }
        } else {
            $('#customer-details').hide();
        }
    }

    async showInvoicePreview() {
        const formData = this.collectFormData();
        
        try {
            const response = await $.ajax({
                url: `${this.baseUrl}api/preview_invoice.php`,
                method: 'POST',
                data: formData,
                dataType: 'html'
            });
            
            const previewFrame = $('#preview-frame');
            previewFrame.attr('src', 'data:text/html;charset=utf-8,' + encodeURIComponent(response));
            
            const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            previewModal.show();
        } catch (error) {
            console.error('Error generating preview:', error);
            this.showAlert('Error', 'Failed to generate preview', 'error');
        }
    }

    collectFormData() {
        const items = [];
        let subtotal = 0;
        let totalDiscount = 0;
        let totalTax = 0;
        let cgstAmount = 0;
        let sgstAmount = 0;
        let igstAmount = 0;

        const businessState = '24'; // Your business state code
        const customerState = $('#customer-state').val();
        const isSameState = customerState === businessState;

        // Get selected customer ID from dropdown
        const customerSelect = $('#customer-select');
        const customerId = customerSelect.val() === 'new' ? null : customerSelect.val();

        // Safely get customer name
        const customerName = customerId 
        ? customerSelect.find('option:selected').text().trim()
        : $('#customer-name').val().trim();

        // Collect items data
        $('#invoice-items tbody tr').each(function() {
            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const rate = parseFloat($(this).find('.item-rate').val()) || 0;
            const discount = parseFloat($(this).find('.item-discount').val()) || 0;
            const taxRate = parseFloat($(this).find('.item-tax').val()) || 0;
            
            // Calculate item amounts
            const itemAmount = qty * rate;
            const discountAmount = itemAmount * (discount / 100);
            const taxableValue = itemAmount - discountAmount;
            
            // Calculate taxes based on state
            let itemCgst = 0;
            let itemSgst = 0;
            let itemIgst = 0;
            
            if (isSameState) {
                // CGST + SGST (split tax rate equally)
                itemCgst = taxableValue * (taxRate / 200); // Half of tax rate
                itemSgst = taxableValue * (taxRate / 200); // Half of tax rate
            } else {
                // IGST (full tax rate)
                itemIgst = taxableValue * (taxRate / 100);
            }
            
            const itemTotal = taxableValue + itemCgst + itemSgst + itemIgst;
            
            // Update totals
            subtotal += itemAmount;
            totalDiscount += discountAmount;
            cgstAmount += itemCgst;
            sgstAmount += itemSgst;
            igstAmount += itemIgst;
            totalTax += itemCgst + itemSgst + itemIgst;
            
            // Add item data
            items.push({
                product_id: $(this).data('product-id') || null,
                description: $(this).find('.item-description').val(),
                hsn_sac: $(this).find('.item-hsn').val(),
                quantity: qty,
                unit: $(this).find('.item-unit').val() || 'unit',
                rate: rate,
                discount_percentage: discount,
                discount_amount: discountAmount,
                taxable_value: taxableValue,
                cgst_rate: isSameState ? taxRate/2 : 0,
                sgst_rate: isSameState ? taxRate/2 : 0,
                igst_rate: !isSameState ? taxRate : 0,
                cgst_amount: itemCgst,
                sgst_amount: itemSgst,
                igst_amount: itemIgst,
                total_amount: itemTotal
            });
        });

        // Calculate grand total
        const grandTotal = subtotal - totalDiscount + totalTax;
        
        return {
            business: {
                name: $('#business-name').val(),
                gstin: $('#business-gstin').val(),
                address: $('#business-address').val()
            },
            customer: {
                id: customerId,
                name: customerName,
                gstin: $('#customer-gstin').val(),
                state: customerState,
                address: $('#customer-address').val(),
                phone: $('#customer-phone').val() || '',
                email: $('#customer-email').val() || '',
                billing_address: $('#customer-address').val(),
                shipping_address: $('#customer-shipping-address').val() || $('#customer-address').val()
            },
            invoice: {
                number: $('#invoice-number').val(),
                date: $('#invoice-date').val(),
                due_date: $('#due-date').val(),
                subtotal: subtotal,
                discount: totalDiscount,
                total: grandTotal,
                notes: $('#invoice-notes').val(),
                terms: $('#invoice-terms').val(),
                supply_type: $('#supply-type').val() || 'regular',
                payment_status: $('#payment-status').val() || 'unpaid'
            },
            items: items
        };
    }


 // Update the addNewItemRow function to include all fields
    // addNewItemRow(product = null) {
    //     const rowCount = $('#invoice-items tbody tr').length + 1;
    //     const newRow = `
    //         <tr>
    //             <td>
    //                 <input type="text" class="form-control form-control-sm item-description" 
    //                     value="${product ? product.name : ''}" 
    //                     placeholder="Type or click to select an item" autocomplete="off">
    //                 <div class="dropdown-menu w-100 item-suggestions"></div>
    //             </td>
    //             <td>
    //                 <input type="text" class="form-control form-control-sm item-hsn" 
    //                     value="${product ? product.hsn_sac_code || '' : ''}" placeholder="HSN/SAC">
    //             </td>
    //             <td>
    //                 <input type="number" class="form-control form-control-sm item-qty" value="1" min="0.01" step="0.01">
    //             </td>
    //             <td>
    //                 <input type="number" class="form-control form-control-sm item-rate" 
    //                     value="${product ? product.rate.toFixed(2) : '0.00'}" min="0" step="0.01">
    //             </td>
    //             <td>
    //                 <input type="number" class="form-control form-control-sm item-discount" value="0" min="0"   max="100" step="0.01">
    //             </td>
    //             <td>
    //                 <input type="number" class="form-control form-control-sm item-tax" value="18" min="0" max="100" step="0.01">
    //             </td>
    //             <td>
    //                 <input type="text" class="form-control form-control-sm item-amount" value="0.00" readonly>
    //             </td>
    //             <td>
    //                 <button class="btn btn-sm btn-danger remove-item">
    //                     <i class="bi bi-trash"></i>
    //                 </button>
    //             </td>
    //         </tr>
    //     `;
        
    //     const row = $(newRow);
    //     $('#invoice-items tbody').append(row);
        
    //     if (product) {
    //         row.data('tax-rate', product.tax_rate || 18);
    //     }
        
    //     this.calculateItemAmount(row);
    // }

    async searchProducts(query) {
    try {
        const response = await $.get(`${this.baseUrl}api/search_products.php`, { q: query });
        return response;
    } catch (error) {
        console.error('Error searching products:', error);
        return [];
    }
}

setupProductSearch() {
    const searchInput = $('#product-search');
    const resultsContainer = $('#product-results');
    
    // Show/hide dropdown on focus/blur
    searchInput.on('focus', () => {
        if (searchInput.val().trim() !== '') {
            resultsContainer.show();
        }
    });
    
    searchInput.on('blur', () => {
        setTimeout(() => resultsContainer.hide(), 200);
    });
    
    // Search as you type
    searchInput.on('input', async () => {
        const query = searchInput.val().trim();
        if (query.length < 2) {
            resultsContainer.hide().empty();
            return;
        }
        
        const products = await this.searchProducts(query);
        resultsContainer.empty();
        
        if (products.length === 0) {
            resultsContainer.append('<div class="dropdown-item">No products found</div>');
        } else {
            products.forEach(product => {
                // Ensure rate is a number and has a default value if missing
                const rate = parseFloat(product.rate) || 0;
                const hsn = product.hsn_sac_code || '';
                const taxRate = parseFloat(product.tax_rate) || 18;
                
                const item = $(`
                    <div class="dropdown-item product-suggestion" 
                         data-id="${product.id}"
                         data-name="${product.name}"
                         data-hsn="${hsn}"
                         data-rate="${rate}"
                         data-tax="${taxRate}">
                        <strong>${product.name}</strong>
                        <div class="text-muted small">
                            ${hsn ? `HSN/SAC: ${hsn}` : ''}
                            <span class="float-end">₹${rate.toFixed(2)}</span>
                        </div>
                    </div>
                `);
                resultsContainer.append(item);
            });
        }
        
        resultsContainer.show();
    });
    
    // Handle product selection
    resultsContainer.on('click', '.product-suggestion', (e) => {
        const product = {
            id: $(e.currentTarget).data('id'),
            name: $(e.currentTarget).data('name'),
            hsn_sac_code: $(e.currentTarget).data('hsn'),
            rate: parseFloat($(e.currentTarget).data('rate')) || 0,
            tax_rate: parseFloat($(e.currentTarget).data('tax')) || 18
        };
        
        this.addProductToInvoice(product);
        searchInput.val('');
        resultsContainer.hide().empty();
    });
    
    // Manual search button
    $('#search-product').click(() => {
        searchInput.trigger('input');
    });
}

addProductToInvoice(product) {
    const rowCount = $('#invoice-items tbody tr').length + 1;
    const newRow = `
        <tr>
            <td>${rowCount}</td>
            <td>
                <input type="text" class="form-control form-control-sm item-description" 
                       value="${product.name}" placeholder="Item description">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm item-hsn" 
                       value="${product.hsn_sac_code || ''}" placeholder="HSN/SAC">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-qty" value="1" min="1">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-rate" 
                       value="${product.rate || '0.00'}" min="0" step="0.01">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-discount" 
                       value="${product.discount || '0.00'}" min="0" step="0.01">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm item-amount" value="0.00" readonly>
            </td>
            <td>
                <button class="btn btn-sm btn-danger remove-item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#invoice-items tbody').append(newRow);
    this.calculateItemAmount($('#invoice-items tbody tr:last'));
}


    async saveInvoice(status) {

        const customerSelect = $('#customer-select');
        
        // Validate customer selection
        if (!customerSelect.val() || customerSelect.val() === '') {
            this.showAlert('Error', 'Please select a customer', 'error');
            return;
        }

        // Validate customer name for new customers
        if (customerSelect.val() === 'new' && !$('#customer-name').val().trim()) {
            this.showAlert('Error', 'Customer name is required', 'error');
            return;
        }
            const formData = this.collectFormData();
            formData.status = status;
            formData.invoice_id = this.currentInvoiceId;
            const url = this.currentInvoiceId 
            ? `${this.baseUrl}api/update_invoice.php`
            : `${this.baseUrl}api/save_invoice.php`;
            
            try {
                const response = await $.ajax({
                    url: url,
                    method: 'POST',
                    data: JSON.stringify(formData),
                    contentType: 'application/json',
                    dataType: 'json',
                    processData: false
                });
                
                if (response.success) {
                    this.currentInvoiceId = response.invoice_id;
                    const message = status === 'draft' 
                        ? 'Draft saved successfully!' 
                        : 'Invoice finalized successfully!';
                    this.showAlert('Success', message, 'success');
                    
                    // Update UI for draft
                    if (status === 'draft') {
                        $('#invoice-number').val(response.invoice_number);
                    }
                } else {
                    this.showAlert('Error', response.error || 'Failed to save invoice', 'error');
                }

                if (status === 'finalized') {
                    this.updateFormControls('finalized');
                    $('#invoice-number').val(response.invoice_number);
                }

            } catch (error) {
                console.error('Error saving invoice:', error);
                let errorMsg = 'Failed to save invoice';
                if (error.responseJSON && error.responseJSON.error) {
                    errorMsg = error.responseJSON.error;
                }
                this.showAlert('Error', errorMsg, 'error');
            }
}

    generatePDF() {
        if (!this.currentInvoiceId) {
            this.showAlert('Error', 'Please save the invoice before generating PDF', 'error');
            return;
        }
        
        window.open(`${this.baseUrl}api/generate_pdf.php?invoice_id=${this.currentInvoiceId}`, '_blank');
    }

    async sendInvoiceEmail() {
        if (!this.currentInvoiceId) {
            this.showAlert('Error', 'Please save the invoice before sending', 'error');
            return;
        }
        
        const email = prompt('Enter customer email address:', $('#customer-email').val() || '');
        if (email) {
            try {
                await $.post(`${this.baseUrl}api/send_email.php`, {
                    invoice_id: this.currentInvoiceId,
                    email: email
                });
                this.showAlert('Success', 'Invoice sent successfully!', 'success');
            } catch (error) {
                console.error('Error sending email:', error);
                this.showAlert('Error', 'Failed to send email', 'error');
            }
        }
    }

    async loadInvoice(invoiceId) {
        try {
            const response = await $.get(`${this.baseUrl}api/get_invoice.php?id=${invoiceId}`);
            
            if (response.success) {
                this.currentInvoiceId = invoiceId;
                this.populateInvoiceForm(response.data);
                
                // Enable/disable controls based on invoice status
                this.updateFormControls(response.data.status);
            }
        } catch (error) {
            console.error('Error loading invoice:', error);
            this.showAlert('Error', 'Failed to load invoice', 'error');
        }
    }

    populateInvoiceForm(data) {
        // Populate customer fields
        $('#customer-select').val(data.customer.id || 'new');
        if (data.customer.id) {
            $('#customer-name').val(data.customer.name);
            $('#customer-gstin').val(data.customer.gstin);
            // ... other customer fields ...
        }
        
        // Populate invoice fields
        $('#invoice-number').val(data.invoice.number);
        $('#invoice-date').val(data.invoice.date);
        // ... other invoice fields ...
        
        // Clear and repopulate items
        $('#invoice-items tbody').empty();
        data.items.forEach(item => {
            this.addNewItemRow(item);
        });
        
        this.calculateTotals();
    }

    updateFormControls(status) {
        const isDraft = status === 'draft';
        
        // Enable/disable fields based on draft status
        $('#customer-select').prop('disabled', !isDraft);
        $('#add-item').prop('disabled', !isDraft);
        $('.remove-item').prop('disabled', !isDraft);
        
        // Show/hide appropriate action buttons
        $('#save-draft').toggle(isDraft);
        $('#finalize-invoice').toggle(isDraft);
        $('#generate-invoice').toggle(!isDraft);
        $('#send-email').toggle(!isDraft);
    }

    async syncWithBitrix() {
        if (!this.currentInvoiceId) {
            this.showAlert('Error', 'Please save the invoice before syncing with Bitrix24', 'error');
            return;
        }
        
        try {
            const response = await $.post(`${this.baseUrl}api/bitrix_sync.php`, {
                invoice_id: this.currentInvoiceId
            });
            
            if (response.success) {
                this.showAlert('Success', 'Invoice synced with Bitrix24 successfully!', 'success');
                this.checkBitrixConnection(); // Refresh sync status
            } else {
                this.showAlert('Error', response.error || 'Failed to sync with Bitrix24', 'error');
            }
        } catch (error) {
            console.error('Error syncing with Bitrix24:', error);
            this.showAlert('Error', 'Failed to sync with Bitrix24', 'error');
        }
    }

    async saveBitrixSettings() {
        const settings = {
            webhook_url: $('#bitrix-webhook').val(),
            auth_token: $('#bitrix-token').val(),
            invoice_template_id: $('#bitrix-template').val()
        };
        
        try {
            await $.ajax({
                url: `${this.baseUrl}api/save_bitrix_settings.php`,
                method: 'POST',
                data: JSON.stringify(settings),
                contentType: 'application/json'
            });
            
            this.showAlert('Success', 'Bitrix24 settings saved successfully!', 'success');
            this.checkBitrixConnection(); // Refresh settings
        } catch (error) {
            console.error('Error saving Bitrix settings:', error);
            this.showAlert('Error', 'Failed to save Bitrix24 settings', 'error');
        }
    }

    async testBitrixConnection() {
        try {
            const response = await $.post(`${this.baseUrl}api/test_bitrix_connection.php`);
            
            if (response.success) {
                this.showAlert('Success', `Connection successful! Bitrix24 version: ${response.version}`, 'success');
            } else {
                this.showAlert('Error', response.error || 'Connection failed', 'error');
            }
        } catch (error) {
            console.error('Error testing connection:', error);
            this.showAlert('Error', 'Connection test failed', 'error');
        }
    }

    printPreview() {
        const previewFrame = document.getElementById('preview-frame');
        if (previewFrame.contentWindow) {
            previewFrame.contentWindow.print();
        }
    }

    downloadPreview() {
        this.generatePDF();
    }

    showAlert(title, message, type) {
        // You can replace this with Toastr, SweetAlert, or your preferred notification system
        const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <strong>${title}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#alerts-container').html(alertHtml);
        setTimeout(() => $('.alert').alert('close'), 5000);
    }
}

// Initialize the application when DOM is ready
$(document).ready(() => {
    window.invoiceApp = new GSTInvoiceApp();

    // When customer state changes
    $('#customer-state').change(function() {
        updateTaxFields();
        calculateTotal();
    });
    
    // Function to update tax fields based on customer state
    function updateTaxFields() {
        const businessState = '22'; // Your business state code (e.g., 22 for Chhattisgarh)
        const customerState = $('#customer-state').val();
        const taxContainer = $('#tax-container');
        taxContainer.empty(); // Clear existing tax fields
        
        if (!customerState) {
            return; // No state selected
        }
        
        if (customerState === businessState) {
            // Same state - show CGST + SGST
            taxContainer.append(`
                <div class="row mb-2">
                    <div class="col-6 text-end">CGST (9%):</div>
                    <div class="col-6 text-end" id="cgst-value">₹0.00</div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-end">SGST (9%):</div>
                    <div class="col-6 text-end" id="sgst-value">₹0.00</div>
                </div>
            `);
        } else {
            // Different state - show IGST
            taxContainer.append(`
                <div class="row mb-2">
                    <div class="col-6 text-end">IGST (18%):</div>
                    <div class="col-6 text-end" id="igst-value">₹0.00</div>
                </div>
            `);
        }
    }
    
    // Function to calculate taxes and total
    function calculateTotal() {
        let subtotal = 0;
        
        // Calculate subtotal from items
        $('#invoice-items tbody tr').each(function() {
            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const rate = parseFloat($(this).find('.item-rate').val()) || 0;
            const amount = qty * rate;
            $(this).find('.item-amount').val(amount.toFixed(2));
            subtotal += amount;
        });
        
        $('#subtotal').text('₹' + subtotal.toFixed(2));
        
        // Calculate taxes
        const businessState = '22'; // Your business state code
        const customerState = $('#customer-state').val();
        let totalTax = 0;
        
        if (customerState === businessState) {
            // Same state - CGST + SGST (9% each)
            const cgst = subtotal * 0.09;
            const sgst = subtotal * 0.09;
            $('#cgst-value').text('₹' + cgst.toFixed(2));
            $('#sgst-value').text('₹' + sgst.toFixed(2));
            totalTax = cgst + sgst;
        } else if (customerState) {
            // Different state - IGST (18%)
            const igst = subtotal * 0.18;
            $('#igst-value').text('₹' + igst.toFixed(2));
            totalTax = igst;
        }
        
        // Calculate total
        const total = subtotal + totalTax;
        $('#total').text('₹' + total.toFixed(2));
    }
    
    // Update totals when item quantities or rates change
    $(document).on('change', '.item-qty, .item-rate', calculateTotal);
    
    // Initialize tax fields
    updateTaxFields();
});

// In your products.php JavaScript
$(document).ready(function() {
    // Load products
    function loadProducts() {
        $.get('api/bitrix_products.php', function(response) {
            if (response.success) {
                renderProducts(response.data);
            } else {
                alert('Error loading products: ' + response.message);
            }
        });
    }
    
    // Sync products
    $('#sync-bitrix').click(function() {
        $(this).html('<span class="spinner-border spinner-border-sm"></span> Syncing...');
        
        $.post('api/bitrix_products.php?action=sync', function(response) {
            if (response.success) {
                alert('Synced ' + response.count + ' products');
                loadProducts();
            } else {
                alert('Error: ' + response.message);
            }
        }).always(() => {
            $('#sync-bitrix').html('<i class="bi bi-plug"></i> Sync with Bitrix24');
        });
    });

     // Sync Bitrix products form handler
    $('#sync-bitrix-form').submit(function(e) {
        e.preventDefault();
        const $btn = $('#sync-bitrix-btn');
        $btn.html('<span class="spinner-border spinner-border-sm"></span> Syncing...');
        $btn.prop('disabled', true);
        
        $.ajax({
            url: 'api/bitrix_products.php?action=sync',
            method: 'POST',
            success: function(response) {
                if (response.success) {
                    alert('Successfully synced ' + response.count + ' products from Bitrix24');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Error syncing products: ' + xhr.statusText);
            },
            complete: function() {
                $btn.html('<i class="bi bi-arrow-repeat"></i> Sync Products from Bitrix24');
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Import product
    $(document).on('click', '.import-product', function() {
        const productId = $(this).data('id').replace('bitrix_', '');
        const hsnSac = prompt('Enter HSN/SAC code for this product:');
        const type = prompt('Enter product type (goods/service):', 'service');
        
        if (hsnSac !== null) {
            $.ajax({
                url: 'api/bitrix_products.php?action=import',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    product_id: productId,
                    hsn_sac: hsnSac,
                    type: type
                }),
                success: function(response) {
                    if (response.success) {
                        alert('Product ' + response.action + ' successfully!');
                        loadProducts();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }
    });
    
    // Initial load
    loadProducts();

    // In your invoice list row click handler
    $('.invoice-row').click(function() {
        const invoiceId = $(this).data('id');
        const status = $(this).data('status');
        
        if (status === 'draft') {
            // Switch to create invoice tab
            $('[href="#create-invoice"]').tab('show');
            // Load the draft
            invoiceApp.loadInvoice(invoiceId);
        } else {
            // View finalized invoice
            window.open(`view_invoice.php?id=${invoiceId}`, '_blank');
        }
    });

    // Fill customer details when a customer is selected
    $('#customer-select').on('change', function () {
        const selected = $(this).find('option:selected');

        const gstin = selected.data('gstin') || '';
        const state = selected.data('state') || '';
        const address = selected.data('address') || '';
        const name = selected.text().trim();

        // console.log('Selected Option Values:', { gstin, state, address, name });

        $('#customer-name').val(name);
        $('#customer-gstin').val(gstin);
        $('#customer-state').val(state);
        $('#customer-address').val(address);
    });
});
