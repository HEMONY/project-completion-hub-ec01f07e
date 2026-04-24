<?php
// sanctions_functions.php

// Function to handle CSV upload
function handleCSVUpload($file, $pdo) {
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف.'];
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10485760) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 10MB).'];
    }
    
    // Check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileType != 'csv') {
        return ['success' => false, 'message' => 'يجب أن يكون الملف بصيغة CSV.'];
    }
    
    // Create backup before processing
    $backupCreated = createBackup($pdo);
    if (!$backupCreated) {
        return ['success' => false, 'message' => 'تعذر إنشاء نسخة احتياطية.'];
    }
    
    // Process the CSV
    return processCSVFile($file['tmp_name'], $pdo);
}

function createBackup($pdo) {
    try {
        $backupTable = "sanctions_list_backup_" . date('Ymd_His');
        
        // Create backup table
        $pdo->exec("CREATE TABLE IF NOT EXISTS $backupTable LIKE sanctions_list");
        $pdo->exec("INSERT INTO $backupTable SELECT * FROM sanctions_list");
        
        return true;
    } catch (Exception $e) {
        error_log("Backup failed: " . $e->getMessage());
        return false;
    }
}

function processCSVFile($filePath, $pdo) {
    ini_set('memory_limit', '512M');
    set_time_limit(300);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Clear existing data
        $pdo->exec("TRUNCATE TABLE sanctions_list");
        
        // Open CSV file
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headerSkipped = false;
            $rowCount = 0;
            $successCount = 0;
            
            // Prepare insert statement for better performance
            $sql = "INSERT INTO sanctions_list (
                english_name, arabic_name, country, type, status, source, 
                list_reference, effective_date, expiry_date, reason, notes,
                created_by, verified_at, verified_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if (!$headerSkipped) {
                    // Skip header row
                    $headerSkipped = true;
                    continue;
                }
                
                // Process row
                $rowData = processCSVRowData($data);
                if ($rowData) {
                    try {
                        $stmt->execute($rowData);
                        $successCount++;
                    } catch (Exception $e) {
                        error_log("Row insert error: " . $e->getMessage());
                        // Continue with next row instead of stopping
                    }
                }
                $rowCount++;
            }
            fclose($handle);
            
            // Commit transaction
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => "تم استيراد $successCount من أصل $rowCount سجلاً بنجاح."
            ];
        } else {
            // No transaction to rollback if file can't be opened
            return ['success' => false, 'message' => 'تعذر فتح ملف CSV.'];
        }
        
    } catch (Exception $e) {
        // Only rollback if transaction is active
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Exception $rollbackException) {
            error_log("Rollback failed: " . $rollbackException->getMessage());
        }
        
        error_log("CSV Processing Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطأ في معالجة الملف: ' . $e->getMessage()];
    }
}

function processCSVRowData($data) {
    try {
        // Map CSV columns to database fields based on your CSV structure
        // Adjust indices based on your CSV structure
        $arabicName = isset($data[5]) ? trim($data[5]) : '';
        $englishName = isset($data[6]) ? trim($data[6]) : '';
        
        // If English name is empty, use Arabic name (transliterated)
        if (empty($englishName) && !empty($arabicName)) {
            $englishName = transliterateArabic($arabicName);
        }
        
        // If both names are empty, skip this row
        if (empty($englishName) && empty($arabicName)) {
            return false;
        }
        
        // Get country - try multiple possible columns
        $country = '';
        if (isset($data[2]) && !empty(trim($data[2]))) {
            $country = trim($data[2]);
        } elseif (isset($data[12]) && !empty(trim($data[12]))) {
            $country = trim($data[12]);
        }
        
        return [
            $englishName,
            $arabicName,
            $country,
            'individual',
            'active',
            'UAE',
            isset($data[14]) ? trim($data[14]) : null,
            isset($data[16]) && !empty(trim($data[16])) && trim($data[16]) != '-' ? formatDate(trim($data[16])) : null,
            isset($data[17]) && !empty(trim($data[17])) && trim($data[17]) != '-' ? formatDate(trim($data[17])) : null,
            isset($data[18]) ? trim($data[18]) : null,
            null, // notes
            1, // created_by
            date('Y-m-d H:i:s'), // verified_at
            1 // verified_by
        ];
        
    } catch (Exception $e) {
        error_log("Row processing error: " . $e->getMessage());
        return false;
    }
}

function formatDate($dateString) {
    if (empty($dateString) || $dateString == '-' || $dateString == '//' || strtolower($dateString) == 'null') {
        return null;
    }
    
    try {
        // Remove any whitespace
        $dateString = trim($dateString);
        
        // Handle different date formats
        $formats = [
            'n/j/Y', // 1/1/1965
            'm/d/Y', // 01/01/1965
            'd/m/Y', // 13/7/1989
            'Y-m-d', // 1965-01-01
            'j/n/Y', // 1/1/1965 alternative
            'm-d-Y', // 01-01-1965
            'd-m-Y', // 13-07-1989
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        // Try strtotime as last resort
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Date format error for '$dateString': " . $e->getMessage());
        return null;
    }
}

function transliterateArabic($text) {
    // Simple transliteration for Arabic to English
    $transliteration = [
        'أ' => 'a', 'إ' => 'i', 'آ' => 'aa', 'ا' => 'a', 'ب' => 'b', 'ت' => 't',
        'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
        'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd',
        'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'و' => 'w',
        'ي' => 'y', 'ى' => 'a', 'ة' => 'h', 'ئ' => 'y', 'ء' => '', 'ؤ' => 'w',
        'َ' => 'a', 'ُ' => 'u', 'ِ' => 'i', 'ّ' => '', 'ْ' => '', 'ً' => 'an',
        'ٌ' => 'un', 'ٍ' => 'in', 'ٰ' => 'a'
    ];
    
    $result = '';
    $text = preg_replace('/\s+/', ' ', trim($text));
    
    for ($i = 0; $i < mb_strlen($text, 'UTF-8'); $i++) {
        $char = mb_substr($text, $i, 1, 'UTF-8');
        if (isset($transliteration[$char])) {
            $result .= $transliteration[$char];
        } else {
            $result .= $char;
        }
    }
    
    // Clean up the result
    $result = preg_replace('/[^a-zA-Z0-9\s]/', '', $result);
    $result = preg_replace('/\s+/', ' ', $result);
    
    return $result;
}

// Function to get sanctions with search
function getSanctions($pdo, $filters = [], $page = 1, $perPage = 50) {
    $offset = ($page - 1) * $perPage;
    
    $sql = "SELECT * FROM sanctions_list WHERE 1=1";
    $params = [];
    
    // Apply filters
    if (!empty($filters['search'])) {
        $sql .= " AND (english_name LIKE ? OR arabic_name LIKE ? OR country LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['country'])) {
        $sql .= " AND country = ?";
        $params[] = $filters['country'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['type'])) {
        $sql .= " AND type = ?";
        $params[] = $filters['type'];
    }
    
    // Get total count
    $countSql = str_replace('*', 'COUNT(*) as total', $sql);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Add ordering and pagination
    $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    return [
        'records' => $records,
        'total' => $total,
        'pages' => ceil($total / $perPage),
        'page' => $page
    ];
}

// Function to delete record
function deleteRecord($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM sanctions_list WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        return false;
    }
}

// Function to toggle status
function toggleStatus($pdo, $id) {
    try {
        // Get current status
        $stmt = $pdo->prepare("SELECT status FROM sanctions_list WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        
        if (!$current) {
            return false;
        }
        
        // Toggle status
        $newStatus = $current['status'] == 'active' ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE sanctions_list SET status = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $id]);
    } catch (Exception $e) {
        error_log("Toggle status error: " . $e->getMessage());
        return false;
    }
}

// Helper function to check if transaction is active (for PDO)
if (!method_exists('PDO', 'inTransaction')) {
    // Fallback for older PHP versions
    function pdo_in_transaction($pdo) {
        try {
            $pdo->rollBack();
            return false;
        } catch (PDOException $e) {
            if ($e->getCode() == 'HY000' && strpos($e->getMessage(), 'no active transaction') !== false) {
                return false;
            }
            return true;
        }
    }
}
?>