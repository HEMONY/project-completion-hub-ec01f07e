<?php
// DateFormatter.php
class DateFormatter {
    
    /**
     * Format date to readable format with language support
     */
    public static function formatDateToReadable($dateString, $lang = 'en') {
        if(empty($dateString)) {
            return $lang === 'ar' ? "لم يتم تقديم تاريخ" : "Date not provided";
        }
        
        // Try to parse as d/m/Y format first
        $date = DateTime::createFromFormat('d/m/Y', $dateString);
        
        // If that fails, try other common formats
        if(!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $dateString);
        }
        if(!$date) {
            $date = DateTime::createFromFormat('m/d/Y', $dateString);
        }
        if(!$date) {
            // Try generic parsing as last resort
            $date = date_create($dateString);
        }
        
        if(!$date) {
            return $lang === 'ar' ? "تنسيق التاريخ غير صالح" : "Invalid date format";
        }
        
        if($lang === 'ar') {
            // Arabic month names
            $arabic_months = [
                'January' => 'يناير',
                'February' => 'فبراير',
                'March' => 'مارس',
                'April' => 'أبريل',
                'May' => 'مايو',
                'June' => 'يونيو',
                'July' => 'يوليو',
                'August' => 'أغسطس',
                'September' => 'سبتمبر',
                'October' => 'أكتوبر',
                'November' => 'نوفمبر',
                'December' => 'ديسمبر'
            ];
            
            $english_month = $date->format('F');
            $arabic_month = $arabic_months[$english_month] ?? $english_month;
            
            return $date->format('j') . ' ' . $arabic_month . ' ' . $date->format('Y');
        }
        
        return $date->format('j F Y');
    }
    
    /**
     * Extract year from date string
     */
    public static function extractYear($dateString) {
        if(empty($dateString)) {
            return "";
        }
        
        // For d/m/Y format - fastest method
        if(preg_match('#/(\d{4})$#', $dateString, $matches)) {
            return $matches[1];
        }
        
        // For other formats
        $date = DateTime::createFromFormat('d/m/Y', $dateString) ?: date_create($dateString);
        return $date ? $date->format('Y') : "";
    }
    
    /**
     * Alternative: Simple year extraction
     */
    public static function getYear($dateString) {
        return empty($dateString) ? "" : substr($dateString, -4);
    }
    
    /**
     * Convert date from one format to another
     */
    public static function convertDateFormat($dateString, $fromFormat = 'd/m/Y', $toFormat = 'Y-m-d') {
        if(empty($dateString)) {
            return "";
        }
        
        $date = DateTime::createFromFormat($fromFormat, $dateString);
        return $date ? $date->format($toFormat) : "";
    }
    public static function formatMoney($amount) {
    // Remove any existing commas and format
    $cleanAmount = str_replace(',', '', $amount);
    return number_format($cleanAmount, 0, '.', ',');
}
}
?>