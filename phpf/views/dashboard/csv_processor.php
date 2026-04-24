<?php
// csv_processor.php
require_once '../../config/db.php';

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
    createBackup($pdo);
    
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
            
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                if (!$headerSkipped) {
                    // Skip header row
                    $headerSkipped = true;
                    continue;
                }
                
                // Process row based on CSV structure
                $processed = processCSVRow($data, $pdo);
                if ($processed) {
                    $successCount++;
                }
                $rowCount++;
                
                // Progress tracking for large files
                if ($rowCount % 1000 == 0) {
                    // Force output buffer flush if needed
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
            fclose($handle);
            
            // Commit transaction
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => "تم استيراد $successCount من أصل $rowCount سجلاً بنجاح."
            ];
        }
        
        return ['success' => false, 'message' => 'تعذر فتح ملف CSV.'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("CSV Processing Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطأ في معالجة الملف: ' . $e->getMessage()];
    }
}

function processCSVRow($data, $pdo) {
    try {
        // Map CSV columns to database fields based on your CSV structure
        $row = [
            'english_name' => isset($data[6]) ? trim($data[6]) : '', // الاسم الكامل باللاتينية
            'arabic_name' => isset($data[5]) ? trim($data[5]) : '', // الاسم الكامل بالعربية
            'country' => isset($data[2]) ? trim($data[2]) : '', // الجنسية
            'type' => 'individual', // Default type
            'status' => 'active',
            'source' => 'UAE', // Default source
            'list_reference' => isset($data[14]) ? trim($data[14]) : null, // رقم الوثيقة
            'effective_date' => isset($data[16]) ? formatDate($data[16]) : null,
            'expiry_date' => isset($data[17]) ? formatDate($data[17]) : null,
            'reason' => isset($data[18]) ? trim($data[18]) : null, // معلومات أخرى
            'notes' => null,
            'created_by' => 1,
            'verified_at' => date('Y-m-d H:i:s'),
            'verified_by' => 1
        ];
        
        // Validate required fields
        if (empty($row['english_name']) && empty($row['arabic_name'])) {
            return false;
        }
        
        // Insert into database
        $sql = "INSERT INTO sanctions_list (
            english_name, arabic_name, country, type, status, source, 
            list_reference, effective_date, expiry_date, reason, notes,
            created_by, verified_at, verified_by, created_at, updated_at
        ) VALUES (
            :english_name, :arabic_name, :country, :type, :status, :source,
            :list_reference, :effective_date, :expiry_date, :reason, :notes,
            :created_by, :verified_at, :verified_by, NOW(), NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($row);
        
    } catch (Exception $e) {
        error_log("Row processing error: " . $e->getMessage());
        return false;
    }
}

function formatDate($dateString) {
    if (empty($dateString) || $dateString == '-') {
        return null;
    }
    
    try {
        // Handle different date formats
        $date = DateTime::createFromFormat('n/j/Y', $dateString);
        if ($date) {
            return $date->format('Y-m-d');
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        if ($date) {
            return $date->format('Y-m-d');
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}
?>