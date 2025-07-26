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
                    '03' => 'Punjab',
                    '04' => 'Chandigarh',
                    '05' => 'Uttarakhand',
                    '06' => 'Haryana',
                    '07' => 'Delhi',
                    '08' => 'Rajasthan',
                    '09' => 'Uttar Pradesh',
                    '10' => 'Bihar',
                    '11' => 'Sikkim',
                    '12' => 'Arunachal Pradesh',
                    '13' => 'Nagaland',
                    '14' => 'Manipur',
                    '15' => 'Mizoram',
                    '16' => 'Tripura',
                    '17' => 'Meghalaya',
                    '18' => 'Assam',
                    '19' => 'West Bengal',
                    '20' => 'Jharkhand',
                    '21' => 'Odisha',
                    '22' => 'Chhattisgarh',
                    '23' => 'Madhya Pradesh',
                    '24' => 'Gujarat',
                    '25' => 'Daman and Diu',
                    '26' => 'Dadra and Nagar Haveli',
                    '27' => 'Maharashtra',
                    '28' => 'Andhra Pradesh (Old)',
                    '29' => 'Karnataka',
                    '30' => 'Goa',
                    '31' => 'Lakshadweep',
                    '32' => 'Kerala',
                    '33' => 'Tamil Nadu',
                    '34' => 'Puducherry',
                    '35' => 'Andaman and Nicobar Islands',
                    '36' => 'Telangana',
                    '37' => 'Andhra Pradesh (New)',
                    '97' => 'Other Territory'
                ];
        
        return $states[$stateCode] ?? 'Unknown State';
    }
}