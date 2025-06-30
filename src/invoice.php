<?php
namespace GSTInvoice;

class Invoice {
    private $db;
    
    public function __construct() {
        $this->db = \Database::getInstance();
    }
    
    public function create(array $data) {
        try {
            $this->db->beginTransaction();
            
            // Calculate taxes based on state
            $taxDetails = $this->calculateTaxes(
                $data['subtotal'],
                $data['customer_state'],
                $data['business_state']
            );

            // Save invoice
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, invoice_date, due_date, customer_id,
                    subtotal, discount_amount, cgst_amount, sgst_amount, 
                    igst_amount, tax_amount, total, tax_type, place_of_supply,
                    supply_type, status, notes, terms, bitrix_deal_id
                ) VALUES (
                    :invoice_number, :invoice_date, :due_date, :customer_id,
                    :subtotal, :discount, :cgst, :sgst, :igst, :tax, 
                    :total, :tax_type, :place_of_supply, :supply_type,
                    :status, :notes, :terms, :bitrix_deal_id
                )
            ");
            
            $stmt->execute([
                ':invoice_number' => $data['invoice_number'],
                ':invoice_date' => $data['invoice_date'],
                ':due_date' => $data['due_date'],
                ':customer_id' => $data['customer_id'],
                ':subtotal' => $data['subtotal'],
                ':discount' => $data['discount_amount'] ?? 0,
                ':cgst' => $taxDetails['cgst_amount'],
                ':sgst' => $taxDetails['sgst_amount'],
                ':igst' => $taxDetails['igst_amount'],
                ':tax' => $taxDetails['tax_amount'],
                ':total' => $data['subtotal'] + $taxDetails['tax_amount'],
                ':tax_type' => $taxDetails['tax_type'],
                ':place_of_supply' => $taxDetails['place_of_supply'],
                ':supply_type' => $taxDetails['supply_type'],
                ':status' => $data['status'] ?? 'draft',
                ':notes' => $data['notes'] ?? null,
                ':terms' => $data['terms'] ?? null,
                ':bitrix_deal_id' => $data['bitrix_deal_id'] ?? null
            ]);
            
            $invoiceId = $this->db->lastInsertId();
            
            // Save items
            foreach ($data['items'] as $item) {
                $this->addItem($invoiceId, $item, $taxDetails['tax_type']);
            }
            
            $this->db->commit();
            return $invoiceId;
            
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw new \Exception("Invoice creation failed: " . $e->getMessage());
        }
    }
    
    protected function calculateTaxes($subtotal, $customerState, $businessState) {
        $isSameState = ($customerState === $businessState);
        
        if ($isSameState) {
            // Intra-state (CGST + SGST)
            $cgstRate = 9;  // 9%
            $sgstRate = 9;   // 9%
            return [
                'cgst_amount' => $subtotal * ($cgstRate/100),
                'sgst_amount' => $subtotal * ($sgstRate/100),
                'igst_amount' => 0,
                'tax_amount' => $subtotal * (($cgstRate + $sgstRate)/100),
                'tax_type' => 'cgst_sgst',
                'supply_type' => 'Intra-State',
                'place_of_supply' => $this->getStateName($businessState)
            ];
        } else {
            // Inter-state (IGST)
            $igstRate = 18; // 18%
            return [
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => $subtotal * ($igstRate/100),
                'tax_amount' => $subtotal * ($igstRate/100),
                'tax_type' => 'igst',
                'supply_type' => 'Inter-State',
                'place_of_supply' => $this->getStateName($customerState)
            ];
        }
    }
    
    public function addItem($invoiceId, array $item, $taxType) {
        $taxRate = $item['tax_rate'] ?? ($taxType === 'cgst_sgst' ? 9 : 18);
        $taxableValue = $item['rate'] * $item['quantity'] - ($item['discount'] ?? 0);
        
        $stmt = $this->db->prepare("
            INSERT INTO invoice_items (
                invoice_id, description, hsn_sac_code, quantity, unit, rate,
                discount_percentage, discount_amount, taxable_value,
                cgst_rate, sgst_rate, igst_rate, cgst_amount, sgst_amount,
                igst_amount, total_amount
            ) VALUES (
                :invoice_id, :description, :hsn_code, :qty, :unit, :rate,
                :discount_pct, :discount_amt, :taxable_value,
                :cgst_rate, :sgst_rate, :igst_rate, :cgst_amt, :sgst_amt,
                :igst_amt, :total
            )
        ");
        
        $execData = [
            ':invoice_id' => $invoiceId,
            ':description' => $item['description'],
            ':hsn_code' => $item['hsn_sac'],
            ':qty' => $item['quantity'],
            ':unit' => $item['unit'] ?? 'NOS',
            ':rate' => $item['rate'],
            ':discount_pct' => $item['discount_percentage'] ?? 0,
            ':discount_amt' => $item['discount_amount'] ?? 0,
            ':taxable_value' => $taxableValue
        ];
        
        if ($taxType === 'cgst_sgst') {
            $execData += [
                ':cgst_rate' => $taxRate/2,
                ':sgst_rate' => $taxRate/2,
                ':igst_rate' => 0,
                ':cgst_amt' => $taxableValue * ($taxRate/200),
                ':sgst_amt' => $taxableValue * ($taxRate/200),
                ':igst_amt' => 0,
                ':total' => $taxableValue * (1 + $taxRate/100)
            ];
        } else {
            $execData += [
                ':cgst_rate' => 0,
                ':sgst_rate' => 0,
                ':igst_rate' => $taxRate,
                ':cgst_amt' => 0,
                ':sgst_amt' => 0,
                ':igst_amt' => $taxableValue * ($taxRate/100),
                ':total' => $taxableValue * (1 + $taxRate/100)
            ];
        }
        
        $stmt->execute($execData);
        return $this->db->lastInsertId();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT i.*, c.name AS customer_name, c.gstin AS customer_gstin, 
                   c.billing_address AS customer_address, c.state_code AS customer_state
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        
        if ($invoice) {
            $invoice['items'] = $this->getItems($id);
        }
        
        return $invoice;
    }
    
    public function getItems($invoiceId) {
        $stmt = $this->db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }
    
    public function updateBitrixId($invoiceId, $bitrixInvoiceId) {
        $stmt = $this->db->prepare("
            UPDATE invoices SET bitrix_invoice_id = ? WHERE id = ?
        ");
        return $stmt->execute([$bitrixInvoiceId, $invoiceId]);
    }
    
    protected function getStateName($stateCode) {
        // Implement state code to name mapping
        $states = [
            '24' => 'Maharashtra',
            '07' => 'Delhi',
            // Add all other states
        ];
        return $states[$stateCode] ?? 'Unknown State';
    }
}