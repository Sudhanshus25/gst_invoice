<?php
class TaxCalculator {
    const BUSINESS_STATE = '24'; // Maharashtra
    
    public static function calculateTaxes($subtotal, $customerState) {
        if ($customerState === self::BUSINESS_STATE) {
            // Intra-state (CGST + SGST)
            $cgst = $subtotal * 0.09; // 9%
            $sgst = $subtotal * 0.09; // 9%
            return [
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => 0,
                'total_tax' => $cgst + $sgst,
                'total_amount' => $subtotal + $cgst + $sgst
            ];
        } else {
            // Inter-state (IGST)
            $igst = $subtotal * 0.18; // 18%
            return [
                'cgst' => 0,
                'sgst' => 0,
                'igst' => $igst,
                'total_tax' => $igst,
                'total_amount' => $subtotal + $igst
            ];
        }
    }
    
    public static function getStateName($stateCode) {
        $states = [
            '01' => 'Jammu and Kashmir',
            '02' => 'Himachal Pradesh',
            // ... all states ...
            '24' => 'Maharashtra',
            // ... remaining states ...
        ];
        
        return $states[$stateCode] ?? 'Unknown State';
    }
}