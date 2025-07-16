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
        $('#add-item').click(() => this.addNewItemRow());
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
        $('#invoice-number').val(`${prefix}${year}${month}-${sequence}`);
    }

    setDefaultDates() {
        const now = new Date();
        $('#invoice-date').val(now.toISOString().split('T')[0]);
        
        const dueDate = new Date();
        dueDate.setDate(now.getDate() + 15);
        $('#due-date').val(dueDate.toISOString().split('T')[0]);
    }

    addNewItemRow() {
        const rowCount = $('#invoice-items tbody tr').length + 1;
        const newRow = `
            <tr>
                <td>${rowCount}</td>
                <td><input type="text" class="form-control form-control-sm item-description" placeholder="Item description"></td>
                <td><input type="text" class="form-control form-control-sm item-hsn" placeholder="HSN/SAC"></td>
                <td><input type="number" class="form-control form-control-sm item-qty" value="1" min="1"></td>
                <td><input type="number" class="form-control form-control-sm item-rate" value="0.00" min="0" step="0.01"></td>
                <td><input type="text" class="form-control form-control-sm item-amount" value="0.00" readonly></td>
                <td><button class="btn btn-sm btn-danger remove-item"><i class="bi bi-trash"></i></button></td>
            </tr>
        `;
        $('#invoice-items tbody').append(newRow);
    }

    removeItemRow(event) {
        if ($('#invoice-items tbody tr').length > 1) {
            $(event.currentTarget).closest('tr').remove();
            this.updateRowNumbers();
            this.calculateTotals();
        } else {
            this.showAlert('Error', 'You must have at least one item in the invoice.', 'error');
        }
    }

    updateRowNumbers() {
        $('#invoice-items tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    calculateItemAmount() {
        const row = $(event.target).closest('tr');
        const qty = parseFloat(row.find('.item-qty').val()) || 0;
        const rate = parseFloat(row.find('.item-rate').val()) || 0;
        const amount = qty * rate;
        row.find('.item-amount').val(amount.toFixed(2));
        this.calculateTotals();
    }

    calculateTotals() {
        let subtotal = 0;
        $('.item-amount').each(function() {
            subtotal += parseFloat($(this).val()) || 0;
        });
        
        $('#subtotal').text(`₹${subtotal.toFixed(2)}`);
        this.updateTaxRates();
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
                dataType: 'json',
                // timeout: 5000 // 5 second timeout
            });

            if (!response.success) {
                throw new Error(response.message || 'Failed to load customers');
            }

            const dropdown = $('#customer-select');
            dropdown.empty().append('<option value="">Select Customer</option>');
            
            if (response.data && response.data.length > 0) {
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
            
            // Check if it's a connection error
            if (error.statusText === 'timeout' || error.status === 0) {
                console.error('Server might be down or CORS issue');
            }
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
                $('#customer-gstin').val(response.gstin);
                $('#customer-state').val(response.state);
                $('#customer-address').val(response.address);
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
        $('#invoice-items tbody tr').each(function() {
            items.push({
                description: $(this).find('.item-description').val(),
                hsn_sac: $(this).find('.item-hsn').val(),
                quantity: $(this).find('.item-qty').val(),
                rate: $(this).find('.item-rate').val(),
                amount: $(this).find('.item-amount').val()
            });
        });
        
        return {
            business: {
                name: $('#business-name').val(),
                gstin: $('#business-gstin').val(),
                address: $('#business-address').val()
            },
            customer: {
                name: $('#customer-name').val(),
                gstin: $('#customer-gstin').val(),
                state: $('#customer-state').val(),
                address: $('#customer-address').val()
            },
            invoice: {
                number: $('#invoice-number').val(),
                date: $('#invoice-date').val(),
                due_date: $('#due-date').val(),
                subtotal: $('#subtotal').text().replace('₹', ''),
                total: $('#total').text().replace('₹', ''),
                notes: $('#invoice-notes').val(),
                terms: $('#invoice-terms').val()
            },
            items: items
        };
    }

    async saveInvoice(status) {
        const formData = this.collectFormData();
        formData.status = status;
        
        try {
            const response = await $.ajax({
                url: `${this.baseUrl}api/save_invoice.php`,
                method: 'POST',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                dataType: 'json'
            });
            
            this.currentInvoiceId = response.invoice_id;
            this.showAlert('Success', `Invoice ${status === 'draft' ? 'saved as draft' : 'finalized'} successfully!`, 'success');
        } catch (error) {
            console.error('Error saving invoice:', error);
            this.showAlert('Error', 'Failed to save invoice', 'error');
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
});