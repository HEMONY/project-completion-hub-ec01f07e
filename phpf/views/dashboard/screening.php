<?php
// screening.php - FIXED VERSION - Works with actual database structure
session_start();
require_once '../../config/db.php';
require_once "../../views/widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? 'client';
$entity_id = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : 0;

if ($entity_id === 0) {
    die("Invalid entity ID");
}

// Get PDO connection
$pdo = Database::getInstance()->getConnection();

// Fetch entity information
$entity_query = "SELECT e.*, u.full_name, u.email 
                 FROM entities e 
                 JOIN users u ON e.user_id = u.id 
                 WHERE e.id = ?";
$stmt = $pdo->prepare($entity_query);
$stmt->execute([$entity_id]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entity) {
    die("Entity not found");
}

if ($user_role === 'client' && $entity['user_id'] != $_SESSION['user_id']) {
    die("You don't have permission to access this entity");
}

// Fetch Step 1 data
$step1_query = "SELECT * FROM entity_step1 WHERE entity_id = ?";
$stmt = $pdo->prepare($step1_query);
$stmt->execute([$entity_id]);
$step1 = $stmt->fetch(PDO::FETCH_ASSOC);

// Collect names to screen
$names_to_screen = [];

// Add Company Owner Name
if (!empty($step1['company_owner_name'])) {
    $names_to_screen[] = [
        'name' => trim($step1['company_owner_name']),
        'type' => 'Company Owner',
        'source' => 'entity_step1'
    ];
}

// Add Entity Name
if (!empty($entity['entity_name'])) {
    $names_to_screen[] = [
        'name' => trim($entity['entity_name']),
        'type' => 'Entity Name',
        'source' => 'entities'
    ];
}

// Add Shareholders - JSON array format: [{"name":"...", "capital_percentage":..., ...}]
if (!empty($step1['shareholders'])) {
    $shareholders = json_decode($step1['shareholders'], true);
    if (is_array($shareholders)) {
        foreach ($shareholders as $shareholder) {
            if (is_array($shareholder) && !empty($shareholder['name'])) {
                $names_to_screen[] = [
                    'name' => trim($shareholder['name']),
                    'type' => 'Shareholder',
                    'source' => 'entity_step1'
                ];
            }
        }
    }
}

// Add UBOs - JSON array format: [{"name":"...", "capital_percentage":..., ...}]
if (!empty($step1['ubos'])) {
    $ubos = json_decode($step1['ubos'], true);
    if (is_array($ubos)) {
        foreach ($ubos as $ubo) {
            if (is_array($ubo) && !empty($ubo['name'])) {
                $names_to_screen[] = [
                    'name' => trim($ubo['name']),
                    'type' => 'UBO',
                    'source' => 'entity_step1'
                ];
            }
        }
    }
}

// Add Management Control - JSON object format: {"name":"...", "position":"...", ...}
// FIXED: Handle both JSON object and plain string formats
if (!empty($step1['management_control'])) {
    $management_control_raw = $step1['management_control'];
    $management_name = null;
    
    // Try to decode as JSON first
    $management_data = json_decode($management_control_raw, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($management_data)) {
        // It's a valid JSON object
        if (!empty($management_data['name'])) {
            $management_name = trim($management_data['name']);
        }
    } else {
        // It's a plain string (legacy format), use as-is
        $management_name = trim($management_control_raw);
    }
    
    if (!empty($management_name)) {
        $names_to_screen[] = [
            'name' => $management_name,
            'type' => 'Management Control',
            'source' => 'entity_step1'
        ];
    }
}

// Remove duplicates based on name (case-insensitive)
$unique_names = [];
$seen_names = [];
foreach ($names_to_screen as $name_info) {
    $clean_name = strtolower(trim($name_info['name']));
    if (!empty($clean_name) && !isset($seen_names[$clean_name])) {
        $seen_names[$clean_name] = true;
        $unique_names[] = $name_info;
    }
}
$names_to_screen = $unique_names;

// Check existing screening results from database
$existing_screening_query = "SELECT * FROM screening_results WHERE entity_id = ? ORDER BY id ASC";
$stmt = $pdo->prepare($existing_screening_query);
$stmt->execute([$entity_id]);
$existing_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of existing results by name (case-insensitive)
$existing_results_map = [];
foreach ($existing_results as $result) {
    $key = strtolower(trim($result['name_to_screen']));
    $existing_results_map[$key] = $result;
}

// Function to perform AI screening
function performImprovedAIScreening($pdo, $name) {
    $search_name = trim($name);
    
    if (empty($search_name)) {
        return 'no-match';
    }
    
    $search_name_normalized = normalizeName($search_name);
    $name_parts = explode(' ', $search_name_normalized);
    $name_parts = array_filter($name_parts);
    
    $search_patterns = [
        $search_name_normalized,
    ];
    
    if (count($name_parts) > 1) {
        $first_last = $name_parts[0] . ' ' . end($name_parts);
        $search_patterns[] = $first_last;
        $search_patterns[] = end($name_parts);
        $search_patterns[] = $name_parts[0];
    }
    
    $search_patterns = array_unique(array_filter($search_patterns));
    
    try {
        $exact_match_found = false;
        $partial_match_found = false;
        
        foreach ($search_patterns as $pattern) {
            if (empty($pattern)) continue;
            
            $query = "SELECT * FROM sanctions_list 
                     WHERE status = 'active' 
                     AND (expiry_date IS NULL OR expiry_date > CURDATE())
                     AND (
                         LOWER(english_name) = LOWER(:pattern_exact)
                         OR LOWER(arabic_name) = LOWER(:pattern_exact_ar)
                         OR SOUNDEX(english_name) = SOUNDEX(:pattern_sound)
                         OR english_name LIKE :pattern_like
                         OR arabic_name LIKE :pattern_like_ar
                     )
                     LIMIT 10";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':pattern_exact' => $pattern,
                ':pattern_exact_ar' => $pattern,
                ':pattern_sound' => $pattern,
                ':pattern_like' => '%' . $pattern . '%',
                ':pattern_like_ar' => '%' . $pattern . '%'
            ]);
            
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($matches as $match) {
                if (strcasecmp(trim($match['english_name']), $search_name) === 0 ||
                    strcasecmp(trim($match['arabic_name']), $search_name) === 0) {
                    $exact_match_found = true;
                    break 2;
                }
                
                $similarity = calculateNameSimilarity($search_name, $match['english_name']);
                if ($similarity >= 80) {
                    $exact_match_found = true;
                    break 2;
                } elseif ($similarity >= 60) {
                    $partial_match_found = true;
                }
            }
        }
        
        if ($exact_match_found) {
            return 'confirmed';
        } elseif ($partial_match_found) {
            return 'partial';
        } else {
            return 'no-match';
        }
        
    } catch (PDOException $e) {
        error_log("Sanctions screening error: " . $e->getMessage());
        return 'no-match';
    }
}

function normalizeName($name) {
    $name = strtolower(trim($name));
    
    $titles = ['mr.', 'mrs.', 'miss', 'ms.', 'dr.', 'prof.', 'sir', 'madam', 'lord', 'lady'];
    foreach ($titles as $title) {
        $name = preg_replace('/\b' . preg_quote($title) . '\b\.?\s*/', '', $name);
    }
    
    $name = preg_replace('/\s+/', ' ', $name);
    $name = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $name);
    
    return trim($name);
}

function calculateNameSimilarity($name1, $name2) {
    $name1 = normalizeName($name1);
    $name2 = normalizeName($name2);
    
    if (strcasecmp($name1, $name2) === 0) {
        return 100;
    }
    
    $words1 = explode(' ', $name1);
    $words2 = explode(' ', $name2);
    
    $similarity = 0;
    $total_weight = 0;
    
    foreach ($words1 as $word1) {
        if (strlen($word1) < 3) continue;
        
        $best_match = 0;
        foreach ($words2 as $word2) {
            if (strlen($word2) < 3) continue;
            
            $lev_distance = levenshtein($word1, $word2);
            $max_len = max(strlen($word1), strlen($word2));
            $lev_similarity = $max_len > 0 ? (1 - ($lev_distance / $max_len)) * 100 : 0;
            
            $soundex_match = (soundex($word1) === soundex($word2)) ? 80 : 0;
            
            $word_similarity = max($lev_similarity, $soundex_match);
            $best_match = max($best_match, $word_similarity);
        }
        
        $weight = strlen($word1);
        $similarity += $best_match * $weight;
        $total_weight += $weight;
    }
    
    return $total_weight > 0 ? ($similarity / $total_weight) : 0;
}

// Build screening results array - merge existing results with names to screen
$screening_results = [];
foreach ($names_to_screen as $index => $name_info) {
    $name_key = strtolower(trim($name_info['name']));
    
    if (isset($existing_results_map[$name_key])) {
        // Use existing result from database
        $existing = $existing_results_map[$name_key];
        $screening_results[] = [
            'id' => $existing['id'],
            'name' => $name_info['name'],
            'type' => $name_info['type'],
            'source' => $name_info['source'],
            'ai_result' => $existing['ai_result'],
            'admin_result' => $existing['admin_result'],
            'screened_at' => $existing['screened_at'],
            'verified_by' => $existing['verified_by'],
            'verified_at' => $existing['verified_at']
        ];
    } else {
        // Perform fresh AI screening
        $ai_result = performImprovedAIScreening($pdo, $name_info['name']);
        $screening_results[] = [
            'id' => null,
            'name' => $name_info['name'],
            'type' => $name_info['type'],
            'source' => $name_info['source'],
            'ai_result' => $ai_result,
            'admin_result' => null,
            'screened_at' => date('Y-m-d H:i:s'),
            'verified_by' => null,
            'verified_at' => null
        ];
    }
}

// Handle form submission for saving screening results
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_screening'])) {
        $admin_results = $_POST['admin_results'] ?? [];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            $has_confirmed_match = false;
            $has_partial_match = false;
            
            // Process each name
            foreach ($names_to_screen as $index => $name_info) {
                $admin_result = isset($admin_results[$index]) ? $admin_results[$index] : null;
                
                // Convert empty string to null
                if ($admin_result === '' || $admin_result === 'unselected') {
                    $admin_result = null;
                }
                
                $ai_result = $screening_results[$index]['ai_result'] ?? 'no-match';
                $existing_id = $screening_results[$index]['id'] ?? null;
                
                // Auto-approve as 'no-match' if AI says no-match and admin hasn't selected
                if ($ai_result === 'no-match' && $admin_result === null) {
                    $admin_result = 'no-match';
                }
                
                // Track matches for eligibility determination
                if ($admin_result === 'confirmed') {
                    $has_confirmed_match = true;
                }
                if ($admin_result === 'partial') {
                    $has_partial_match = true;
                }
                
                // Determine verified_by and verified_at
                $verified_by = ($admin_result !== null) ? $_SESSION['user_id'] : null;
                $verified_at = ($admin_result !== null) ? date('Y-m-d H:i:s') : null;
                
                if ($existing_id) {
                    // Update existing record
                    $update_query = "UPDATE screening_results 
                                    SET admin_result = ?, verified_by = ?, verified_at = ?
                                    WHERE id = ?";
                    $stmt = $pdo->prepare($update_query);
                    $stmt->execute([$admin_result, $verified_by, $verified_at, $existing_id]);
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO screening_results 
                                    (entity_id, name_to_screen, name_type, source_table, ai_result, admin_result, screened_at, verified_by, verified_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($insert_query);
                    $stmt->execute([
                        $entity_id,
                        $name_info['name'],
                        $name_info['type'],
                        $name_info['source'],
                        $ai_result,
                        $admin_result,
                        $verified_by,
                        $verified_at
                    ]);
                }
                
                // Update the screening_results array for display
                $screening_results[$index]['admin_result'] = $admin_result;
                $screening_results[$index]['verified_by'] = $verified_by;
                $screening_results[$index]['verified_at'] = $verified_at;
                $screening_results[$index]['id'] = $existing_id ? $existing_id : $pdo->lastInsertId();
            }
            
            // Determine eligibility status and update entity
            // Entity is NOT eligible if there are ANY confirmed OR partial matches
            if ($has_confirmed_match || $has_partial_match) {
                // Entity has confirmed or partial matches - not eligible
                $update_status = "UPDATE entities SET screening_completed = 1, application_status = 'rejected', updated_at = NOW() WHERE id = ?";
                $status_message = "Screening completed. Entity is NOT eligible to proceed due to sanctions matches found.";
            } else {
                // No matches found - eligible
                $update_status = "UPDATE entities SET screening_completed = 1, updated_at = NOW() WHERE id = ?";
                $status_message = "Screening completed successfully! Entity is eligible to proceed to ICID step.";
            }
            
            // Update entity status
            $stmt = $pdo->prepare($update_status);
            $stmt->execute([$entity_id]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = $status_message;
            
            // Log the action
            $log_query = "INSERT INTO user_audit_logs 
                         (user_id, action, description, ip_address, user_agent) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($log_query);
            $stmt->execute([
                $_SESSION['user_id'],
                'screening_saved',
                'Saved screening results for entity ID: ' . $entity_id . ' - Matches: ' . ($has_confirmed_match || $has_partial_match ? 'Yes' : 'No'),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // Refresh the page to show updated results
            header("Location: screening.php?entity_id=" . $entity_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error saving screening results: " . $e->getMessage();
            error_log("Screening save error: " . $e->getMessage());
            header("Location: screening.php?entity_id=" . $entity_id);
            exit();
        }
    }
    
    // Handle sending "Not Eligible" notification
    if (isset($_POST['send_notification'])) {
        $update_query = "UPDATE entities 
                        SET screening_completed = 1, 
                            ind_completed = 0,
                            cdd_completed = 0,
                            application_status = 'rejected',
                            updated_at = NOW()
                        WHERE id = ?";
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([$entity_id]);
        
        $log_query = "INSERT INTO user_audit_logs 
                     (user_id, action, description, ip_address, user_agent) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($log_query);
        $stmt->execute([
            $_SESSION['user_id'],
            'sanctions_notification_sent',
            'Sent "Not Eligible to Proceed" notification for entity ID: ' . $entity_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $_SESSION['notification_sent'] = true;
        header("Location: screening.php?entity_id=" . $entity_id);
        exit();
    }
}

// Re-fetch entity to get updated status
$stmt = $pdo->prepare($entity_query);
$stmt->execute([$entity_id]);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);

// Re-fetch existing screening results after potential updates
$stmt = $pdo->prepare($existing_screening_query);
$stmt->execute([$entity_id]);
$existing_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update existing results map
$existing_results_map = [];
foreach ($existing_results as $result) {
    $key = strtolower(trim($result['name_to_screen']));
    $existing_results_map[$key] = $result;
}

// Update screening results array with saved data
foreach ($screening_results as $index => $result) {
    $name_key = strtolower(trim($result['name']));
    if (isset($existing_results_map[$name_key])) {
        $existing = $existing_results_map[$name_key];
        $screening_results[$index]['id'] = $existing['id'];
        $screening_results[$index]['admin_result'] = $existing['admin_result'];
        $screening_results[$index]['verified_by'] = $existing['verified_by'];
        $screening_results[$index]['verified_at'] = $existing['verified_at'];
    }
}

// Check for success messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
$notification_sent = $_SESSION['notification_sent'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['notification_sent']);

// Check workflow progress
$screening_completed = !empty($entity['screening_completed']) && $entity['screening_completed'] == 1;
$ind_completed = !empty($entity['ind_completed']) && $entity['ind_completed'] == 1;
$cdd_completed = !empty($entity['cdd_completed']) && $entity['cdd_completed'] == 1;

// Check if entity is not eligible (rejected)
$entity_not_eligible = $entity['application_status'] === 'rejected';

// Calculate statistics for display
$ai_confirmed = $ai_partial = $ai_no_match = 0;
$admin_confirmed = $admin_partial = $admin_no_match = $admin_unselected = 0;

// Collect matched names for display
$confirmed_matches = [];
$partial_matches = [];

foreach ($screening_results as $result) {
    $ai_result = $result['ai_result'] ?? 'no-match';
    $admin_result = $result['admin_result'] ?? null;
    
    // AI stats
    if ($ai_result === 'confirmed') $ai_confirmed++;
    elseif ($ai_result === 'partial') $ai_partial++;
    else $ai_no_match++;
    
    // Admin stats
    if ($admin_result === 'confirmed') {
        $admin_confirmed++;
        $confirmed_matches[] = $result;
    } elseif ($admin_result === 'partial') {
        $admin_partial++;
        $partial_matches[] = $result;
    } elseif ($admin_result === 'no-match') {
        $admin_no_match++;
    } else {
        $admin_unselected++;
    }
}

// Check if there are any confirmed or partial matches (for showing warning)
$has_any_matches = ($admin_confirmed > 0 || $admin_partial > 0);

// Check if all admin verifications are complete
$all_verified = ($admin_unselected === 0);

// Check if screening is completed and results are saved
$is_readonly = $screening_completed || $entity_not_eligible;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SANCTIONS SCREENING REPORT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #404040;
            --secondary-color: #666666;
            --accent-color: #2a5bd7;
            --light-gray: #f8f9fa;
            --border-color: #eaeaea;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --screening-color: #6f42c1;
            --ind-color: #17a2b8;
            --cdd-color: #20c997;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            color: #334155;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header {
            text-align: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        
        .header p {
            color: #64748b;
            font-size: 15px;
            font-weight: 300;
        }
        
        /* CDD-style Progress Bar */
        .steps-container-horizontal {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            position: relative;
            margin-bottom: 35px;
        }
        
        .steps-container-horizontal::before {
            content: '';
            position: absolute;
            top: 16px;
            left: 30px;
            right: 30px;
            height: 1px;
            background-color: var(--border-color);
            z-index: 1;
        }
        
        .step-item-horizontal {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
            max-width: 220px;
        }
        
        .step-number-horizontal {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--light-gray);
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 16px;
            border: 2px solid white;
        }
        
        .step-item-horizontal.active .step-number-horizontal {
            background-color: var(--screening-color);
            color: white;
            border-color: var(--screening-color);
        }
        
        .step-item-horizontal.completed .step-number-horizontal {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .step-item-horizontal.rejected .step-number-horizontal {
            background-color: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }
        
        .step-content-horizontal {
            text-align: center;
            padding: 0 10px;
        }
        
        .step-title-horizontal {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 6px;
            color: var(--primary-color);
        }
        
        .step-status-horizontal {
            font-size: 12px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .step-item-horizontal.completed .step-status-horizontal {
            color: var(--success-color);
        }
        
        .step-item-horizontal.rejected .step-status-horizontal {
            color: var(--danger-color);
        }
        
        /* Entity information */
        .entity-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .field-group {
            margin-bottom: 0;
        }
        
        .field-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        
        .field-value {
            padding: 12px 16px;
            background-color: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            min-height: 48px;
            display: flex;
            align-items: center;
            font-weight: 400;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
            padding: 25px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }
        
        .stamp {
            position: absolute;
            top: 30px;
            right: 30px;
            color: #ef4444;
            opacity: 0.8;
            font-weight: 500;
            font-size: 12px;
            letter-spacing: 1px;
            transform: rotate(15deg);
            padding: 6px 12px;
            border: 1.5px dashed #ef4444;
            border-radius: 4px;
            background-color: rgba(239, 68, 68, 0.05);
        }
        
        .section {
            margin-bottom: 35px;
            padding: 25px;
            border-radius: 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        
        .section-title {
            color: #1e293b;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 22px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            letter-spacing: 0.3px;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #3b82f6;
            font-size: 16px;
        }
        
        .names-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .name-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        
        .name-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .name-card.needs-verification {
            border-color: #f59e0b;
            background-color: #fffbeb;
        }
        
        .name-card.readonly {
            border-color: #d1d5db;
            background-color: #f9fafb;
            cursor: not-allowed;
        }
        
        .name-card.readonly:hover {
            transform: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .name-text {
            font-weight: 600;
            color: #1e293b;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .name-text::before {
            content: "•";
            color: #3b82f6;
            font-weight: bold;
            font-size: 20px;
            margin-right: 10px;
        }
        
        .name-type {
            font-size: 11px;
            color: #64748b;
            background-color: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .verified-badge {
            font-size: 11px;
            color: #10b981;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .screening-section {
            margin-top: 15px;
        }
        
        .screening-title {
            color: #1e293b;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        
        .screening-title i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .ai-title {
            color: #10b981;
        }
        
        .admin-title {
            color: #3b82f6;
        }
        
        .required-badge {
            font-size: 11px;
            color: #f59e0b;
            background-color: #fef3c7;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 8px;
        }
        
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background-color: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .radio-option:hover {
            background-color: #f1f5f9;
        }
        
        .radio-option.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        
        .radio-option.selected-confirmed {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .radio-option.selected-partial {
            border-color: #f59e0b;
            background-color: #fffbeb;
        }
        
        .radio-option.selected-no-match {
            border-color: #10b981;
            background-color: #ecfdf5;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 10px;
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        
        .radio-label {
            font-size: 14px;
            font-weight: 400;
            cursor: pointer;
        }
        
        .confirmed {
            color: #ef4444;
            font-weight: 500;
        }
        
        .partial {
            color: #f59e0b;
            font-weight: 500;
        }
        
        .no-match {
            color: #10b981;
            font-weight: 500;
        }
        
        .auto-check {
            font-style: italic;
            color: #64748b;
            font-size: 12px;
            margin-top: 5px;
            text-align: center;
        }
        
        .auto-approved-note {
            background-color: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .saved-note {
            background-color: #e0f2fe;
            border: 1px solid #0ea5e9;
            color: #0369a1;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .verified-section {
            background-color: #f8fafc;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            border: 1px solid #e2e8f0;
        }
        
        .verified-info {
            font-size: 15px;
            color: #1e293b;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            display: none;
            border-left: 4px solid #10b981;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }
        
        .verified-info h3 {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 12px;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        
        .verified-info h3 i {
            margin-right: 8px;
            color: #10b981;
        }
        
        .verified-info p {
            margin-bottom: 8px;
            font-weight: 400;
        }
        
        .summary-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .summary-box {
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background-color: white;
        }
        
        .summary-title {
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .summary-title i {
            margin-right: 10px;
        }
        
        .ai-summary-title {
            color: #10b981;
        }
        
        .admin-summary-title {
            color: #3b82f6;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .stat-box {
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-count {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
        }
        
        .stat-confirmed {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .stat-confirmed .stat-count {
            color: #ef4444;
        }
        
        .stat-partial {
            background-color: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .stat-partial .stat-count {
            color: #f59e0b;
        }
        
        .stat-no-match {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .stat-no-match .stat-count {
            color: #10b981;
        }
        
        .stat-pending {
            background-color: rgba(100, 116, 139, 0.1);
            border: 1px solid rgba(100, 116, 139, 0.2);
        }
        
        .stat-pending .stat-count {
            color: #64748b;
        }
        
        .buttons-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 35px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 13px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            min-width: 130px;
            letter-spacing: 0.3px;
            text-decoration: none;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-save {
            background-color: #3b82f6;
            color: white;
            border: 1px solid #3b82f6;
        }
        
        .btn-save:hover:not(:disabled) {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }
        
        .btn-print {
            background-color: #64748b;
            color: white;
            border: 1px solid #64748b;
        }
        
        .btn-print:hover {
            background-color: #475569;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.25);
        }
        
        .btn-back {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        
        .btn-back:hover {
            background-color: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.1);
        }
        
        .btn-next {
            background-color: var(--ind-color);
            color: white;
            border: 1px solid var(--ind-color);
        }
        
        .btn-next:hover:not(:disabled) {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.25);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .footer {
            text-align: center;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 13px;
            font-weight: 300;
        }
        
        /* Messages */
        .success-message {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }
        
        .success-message i {
            margin-right: 10px;
            color: #10b981;
        }
        
        .alert-message {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }
        
        .alert-message i {
            margin-right: 10px;
            color: #f59e0b;
        }
        
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #7f1d1d;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }
        
        .error-message i {
            margin-right: 10px;
            color: #ef4444;
        }
        
        .match-warning-message {
            background-color: #fff5f5;
            border: 1px solid #ef4444;
            color: #7f1d1d;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .match-warning-message i {
            margin-right: 10px;
            color: #ef4444;
        }
        
        .pending-warning-message {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .pending-warning-message i {
            margin-right: 10px;
            color: #f59e0b;
        }
        
        .next-step-info {
            background-color: #e8f4ff;
            border: 1px solid #3b82f6;
            color: #1e40af;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .next-step-info h4 {
            margin-bottom: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .next-step-info h4 i {
            margin-right: 8px;
        }
        
        /* Not Eligible to Proceed section */
        .status-notification {
            margin-top: 25px;
            padding: 20px;
            background-color: #fff5f5;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            color: #c0392b;
        }
        
        .status-header {
            font-weight: 600;
            font-size: 17px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .status-header i {
            margin-right: 10px;
        }
        
        .status-content {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .send-button {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-button i {
            margin-right: 8px;
        }
        
        .send-button:hover {
            background-color: #c0392b;
        }
        
        .sent-message {
            background-color: #fdd5d5;
            border-left: 3px solid #7f8c8d;
            color: #2c3e50;
            padding: 12px 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 14px;
        }
        
        /* Sanctions Matches Found Box - NEW */
        .sanctions-matches-box {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 2px solid #ef4444;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.15);
        }
        
        .sanctions-matches-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .sanctions-matches-header i {
            font-size: 28px;
            color: #dc2626;
            margin-right: 15px;
        }
        
        .sanctions-matches-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #991b1b;
            margin: 0;
        }
        
        .sanctions-matches-header .match-count {
            margin-left: auto;
            background-color: #dc2626;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .matches-section {
            margin-bottom: 20px;
        }
        
        .matches-section:last-child {
            margin-bottom: 0;
        }
        
        .matches-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .matches-section-title i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        .matches-section-title.confirmed-title {
            color: #b91c1c;
        }
        
        .matches-section-title.partial-title {
            color: #b45309;
        }
        
        .match-item {
            display: flex;
            align-items: center;
            background-color: white;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .match-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .match-item:last-child {
            margin-bottom: 0;
        }
        
        .match-item.confirmed-match {
            border-left-color: #dc2626;
            background: linear-gradient(90deg, #fef2f2 0%, white 100%);
        }
        
        .match-item.partial-match {
            border-left-color: #f59e0b;
            background: linear-gradient(90deg, #fffbeb 0%, white 100%);
        }
        
        .match-item-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
        }
        
        .match-item.confirmed-match .match-item-icon {
            background-color: #fecaca;
            color: #dc2626;
        }
        
        .match-item.partial-match .match-item-icon {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .match-item-content {
            flex: 1;
        }
        
        .match-item-name {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .match-item-type {
            font-size: 12px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .match-item-type span {
            background-color: #f3f4f6;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .match-item-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .match-item.confirmed-match .match-item-badge {
            background-color: #dc2626;
            color: white;
        }
        
        .match-item.partial-match .match-item-badge {
            background-color: #f59e0b;
            color: white;
        }
        
        .no-matches-found {
            display: none;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .buttons-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 280px;
            }
            
            .names-container {
                grid-template-columns: 1fr;
            }
            
            .summary-container {
                grid-template-columns: 1fr;
            }
            
            .steps-container-horizontal {
                flex-direction: column;
                align-items: flex-start;
                gap: 30px;
            }
            
            .steps-container-horizontal::before {
                display: none;
            }
            
            .step-item-horizontal {
                flex-direction: row;
                max-width: 100%;
                width: 100%;
            }
            
            .step-number-horizontal {
                margin-right: 20px;
                margin-bottom: 0;
            }
            
            .step-content-horizontal {
                text-align: left;
                padding: 0;
            }
            
            .sanctions-matches-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .sanctions-matches-header .match-count {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .match-item {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .match-item-badge {
                margin-left: auto;
            }
        }
        
        @media print {
            .btn, .buttons-container {
                display: none !important;
            }
            
            .container {
                box-shadow: none;
                border: 1px solid #ccc;
            }
            
            .sanctions-matches-box {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="stamp">CONFIDENTIAL</div>
        
        <div class="header">
            <h1>SANCTIONS SCREENING REPORT</h1>
            <p>Step 1: Complete sanctions screening before proceeding to ICID</p>
        </div>
        
        <!-- Progress Bar -->
        <div class="steps-container-horizontal">
            <div class="step-item-horizontal <?php 
                if ($entity_not_eligible) echo 'rejected';
                elseif ($screening_completed) echo 'completed';
                else echo 'active';
            ?>">
                <div class="step-number-horizontal">
                    <?php if ($entity_not_eligible): ?>
                        <i class="fas fa-times"></i>
                    <?php elseif ($screening_completed): ?>
                        <i class="fas fa-check"></i>
                    <?php else: ?>
                        1
                    <?php endif; ?>
                </div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Sanctions Screening Report</div>
                    <div class="step-status-horizontal">
                        <?php 
                        if ($entity_not_eligible) echo 'REJECTED';
                        elseif ($screening_completed) echo 'COMPLETED';
                        else echo 'IN PROGRESS';
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="step-item-horizontal <?php echo $ind_completed ? 'completed' : ''; ?>">
                <div class="step-number-horizontal">2</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Independence and Conflict of Interest</div>
                    <div class="step-status-horizontal"><?php echo $ind_completed ? 'COMPLETED' : 'PENDING'; ?></div>
                </div>
            </div>
            
            <div class="step-item-horizontal <?php echo $cdd_completed ? 'completed' : ''; ?>">
                <div class="step-number-horizontal">3</div>
                <div class="step-content-horizontal">
                    <div class="step-title-horizontal">Audit Client Verification</div>
                    <div class="step-status-horizontal"><?php echo $cdd_completed ? 'COMPLETED' : 'PENDING'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($success_message): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($notification_sent): ?>
        <div class="alert-message">
            <i class="fas fa-exclamation-triangle"></i>
            "Not Eligible to Proceed" notification sent successfully. Client onboarding has been terminated.
        </div>
        <?php endif; ?>
        
        <?php if ($has_any_matches && $screening_completed): ?>
        <div class="match-warning-message">
            <i class="fas fa-exclamation-triangle"></i>
            <?php 
            $total_matches = $admin_confirmed + $admin_partial;
            echo "Warning: $total_matches match(es) found. Entity is NOT eligible to proceed.";
            ?>
        </div>
        <?php endif; ?>
        
        <?php if ($admin_unselected > 0 && !$entity_not_eligible && !$screening_completed): ?>
        <div class="pending-warning-message" id="pending-warning">
            <i class="fas fa-clock"></i>
            <span id="pending-count-text"><?php echo $admin_unselected; ?> name(s) require admin verification before saving.</span>
        </div>
        <?php endif; ?>
        
        <!-- Entity Info -->
        <div class="report-info">
            <div class="field-group">
                <div class="field-label">Audit Client:</div>
                <div class="field-value"><?php echo htmlspecialchars($entity['entity_name'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="field-group">
                <div class="field-label">Engagement Number:</div>
                <div class="field-value"><?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="field-group">
                <div class="field-label">Screening Date:</div>
                <div class="field-value"><?php echo date('F j, Y'); ?></div>
            </div>
        </div>
        
        <form method="POST" action="" id="screening-form">
            <input type="hidden" name="entity_id" value="<?php echo $entity_id; ?>">
            
            <div class="section">
                <h2 class="section-title"><i class="fas fa-users"></i> Names - SCREENING RESULTS</h2>
                
                <?php if ($entity_not_eligible): ?>
                <div class="error-message">
                    <i class="fas fa-ban"></i>
                    This entity has been marked as "Not Eligible to Proceed" due to sanctions screening results. No further actions are available.
                </div>
                <?php endif; ?>
                
                <?php if (count($names_to_screen) > 0): ?>
                <div class="names-container">
                    <?php foreach ($screening_results as $index => $result): ?>
                    <?php 
                    $ai_result = $result['ai_result'] ?? 'no-match';
                    $admin_result = $result['admin_result'];
                    $is_verified = !empty($admin_result);
                    $needs_admin_verification = ($ai_result === 'confirmed' || $ai_result === 'partial') && !$is_verified;
                    $is_readonly_for_card = $is_readonly || $is_verified;
                    ?>
                    <div class="name-card <?php echo $needs_admin_verification && !$is_readonly_for_card ? 'needs-verification' : ''; ?> <?php echo $is_readonly_for_card ? 'readonly' : ''; ?>" data-index="<?php echo $index; ?>">
                        <div class="name-text">
                            <?php echo htmlspecialchars($result['name']); ?>
                            <span class="name-type"><?php echo htmlspecialchars($result['type']); ?></span>
                            <?php if ($is_verified): ?>
                            <span class="verified-badge">
                                <i class="fas fa-check-circle"></i> Verified
                                <?php if (!empty($result['verified_at'])): ?>
                                (<?php echo date('M j, Y', strtotime($result['verified_at'])); ?>)
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- AI Screening Result -->
                        <div class="screening-section">
                            <h3 class="screening-title ai-title"><i class="fas fa-robot"></i> AI Screening Result</h3>
                            <div class="radio-group">
                                <label class="radio-option <?php echo $ai_result === 'confirmed' ? 'selected-confirmed' : ''; ?>">
                                    <input type="radio" value="confirmed" <?php echo $ai_result === 'confirmed' ? 'checked' : ''; ?> disabled>
                                    <span class="radio-label confirmed">Confirmed Match</span>
                                </label>
                                <label class="radio-option <?php echo $ai_result === 'partial' ? 'selected-partial' : ''; ?>">
                                    <input type="radio" value="partial" <?php echo $ai_result === 'partial' ? 'checked' : ''; ?> disabled>
                                    <span class="radio-label partial">Partial Match</span>
                                </label>
                                <label class="radio-option <?php echo $ai_result === 'no-match' ? 'selected-no-match' : ''; ?>">
                                    <input type="radio" value="no-match" <?php echo $ai_result === 'no-match' ? 'checked' : ''; ?> disabled>
                                    <span class="radio-label no-match">No Match</span>
                                </label>
                            </div>
                            <div class="auto-check">Auto-generated by Muhasba.com</div>
                        </div>
                        
                        <!-- Admin Verification -->
                        <?php if ($ai_result === 'confirmed' || $ai_result === 'partial'): ?>
                        <div class="screening-section">
                            <h3 class="screening-title admin-title">
                                <i class="fas fa-user-shield"></i> Admin Verification
                                <?php if ($needs_admin_verification && !$is_readonly): ?>
                                <span class="required-badge">Required</span>
                                <?php endif; ?>
                            </h3>
                            <div class="radio-group" id="admin-group-<?php echo $index; ?>">
                                <label class="radio-option <?php echo $admin_result === 'confirmed' ? 'selected-confirmed' : ''; ?>">
                                    <input type="radio" 
                                           name="admin_results[<?php echo $index; ?>]" 
                                           value="confirmed" 
                                           <?php echo $admin_result === 'confirmed' ? 'checked' : ''; ?>
                                           <?php echo $is_readonly_for_card ? 'disabled' : ''; ?>
                                           onchange="updateSelection(this, <?php echo $index; ?>)">
                                    <span class="radio-label confirmed">Confirmed Match</span>
                                </label>
                                <label class="radio-option <?php echo $admin_result === 'partial' ? 'selected-partial' : ''; ?>">
                                    <input type="radio" 
                                           name="admin_results[<?php echo $index; ?>]" 
                                           value="partial" 
                                           <?php echo $admin_result === 'partial' ? 'checked' : ''; ?>
                                           <?php echo $is_readonly_for_card ? 'disabled' : ''; ?>
                                           onchange="updateSelection(this, <?php echo $index; ?>)">
                                    <span class="radio-label partial">Partial Match</span>
                                </label>
                                <label class="radio-option <?php echo $admin_result === 'no-match' ? 'selected-no-match' : ''; ?>">
                                    <input type="radio" 
                                           name="admin_results[<?php echo $index; ?>]" 
                                           value="no-match" 
                                           <?php echo $admin_result === 'no-match' ? 'checked' : ''; ?>
                                           <?php echo $is_readonly_for_card ? 'disabled' : ''; ?>
                                           onchange="updateSelection(this, <?php echo $index; ?>)">
                                    <span class="radio-label no-match">No Match</span>
                                </label>
                            </div>
                            <?php if ($is_verified): ?>
                            <div class="saved-note">
                                <i class="fas fa-check-circle"></i>
                                Verified and saved
                                <?php if (!empty($result['verified_at'])): ?>
                                on <?php echo date('M j, Y', strtotime($result['verified_at'])); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <!-- AI said No Match - auto-approved -->
                        <div class="screening-section">
                            <h3 class="screening-title admin-title"><i class="fas fa-user-shield"></i> Admin Verification</h3>
                            <?php if ($is_readonly_for_card): ?>
                            <input type="hidden" name="admin_results[<?php echo $index; ?>]" value="no-match">
                            <div class="saved-note">
                                <i class="fas fa-check-circle"></i>
                                Auto-approved: AI found no match
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="admin_results[<?php echo $index; ?>]" value="no-match">
                            <div class="auto-approved-note">
                                <i class="fas fa-check-circle"></i>
                                Auto-approved: AI found no match
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary Section -->
                <div class="summary-container">
                    <div class="summary-box">
                        <h3 class="summary-title ai-summary-title"><i class="fas fa-robot"></i> AI SCREENING SUMMARY</h3>
                        <div class="summary-stats">
                            <div class="stat-box stat-confirmed">
                                <div class="stat-count"><?php echo $ai_confirmed; ?></div>
                                <div class="stat-label">Confirmed</div>
                            </div>
                            <div class="stat-box stat-partial">
                                <div class="stat-count"><?php echo $ai_partial; ?></div>
                                <div class="stat-label">Partial</div>
                            </div>
                            <div class="stat-box stat-no-match">
                                <div class="stat-count"><?php echo $ai_no_match; ?></div>
                                <div class="stat-label">No Match</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-box">
                        <h3 class="summary-title admin-summary-title"><i class="fas fa-user-shield"></i> ADMIN VERIFICATION SUMMARY</h3>
                        <div class="summary-stats">
                            <div class="stat-box stat-confirmed">
                                <div class="stat-count" id="admin-confirmed-count"><?php echo $admin_confirmed; ?></div>
                                <div class="stat-label">Confirmed</div>
                            </div>
                            <div class="stat-box stat-partial">
                                <div class="stat-count" id="admin-partial-count"><?php echo $admin_partial; ?></div>
                                <div class="stat-label">Partial</div>
                            </div>
                            <div class="stat-box stat-no-match">
                                <div class="stat-count" id="admin-no-match-count"><?php echo $admin_no_match; ?></div>
                                <div class="stat-label">No Match</div>
                            </div>
                        </div>
                        <div class="auto-check" id="admin-status-text">
                            <?php if ($entity_not_eligible): ?>
                            <span style="color: #ef4444;">Entity Not Eligible</span>
                            <?php elseif ($screening_completed): ?>
                            <span style="color: #10b981;">Screening Completed</span>
                            <?php elseif ($admin_unselected > 0): ?>
                            Pending: <span id="pending-count"><?php echo $admin_unselected; ?></span> name(s)
                            <?php else: ?>
                            <span style="color: #10b981;">All names verified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sanctions Matches Found Box - Only shown when there are matches -->
                <?php if ($has_any_matches || (count($confirmed_matches) > 0 || count($partial_matches) > 0)): ?>
                <div class="sanctions-matches-box" id="sanctions-matches-box">
                    <div class="sanctions-matches-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Sanctions Matches Found</h3>
                        <span class="match-count">
                            <?php echo count($confirmed_matches) + count($partial_matches); ?> Match(es)
                        </span>
                    </div>
                    
                    <?php if (count($confirmed_matches) > 0): ?>
                    <div class="matches-section">
                        <div class="matches-section-title confirmed-title">
                            <i class="fas fa-times-circle"></i>
                            Confirmed Matches (<?php echo count($confirmed_matches); ?>)
                        </div>
                        <?php foreach ($confirmed_matches as $match): ?>
                        <div class="match-item confirmed-match">
                            <div class="match-item-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <div class="match-item-content">
                                <div class="match-item-name"><?php echo htmlspecialchars($match['name']); ?></div>
                                <div class="match-item-type">
                                    <span><?php echo htmlspecialchars($match['type']); ?></span>
                                    <?php if (!empty($match['verified_at'])): ?>
                                    <span>Verified: <?php echo date('M j, Y', strtotime($match['verified_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="match-item-badge">Confirmed</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (count($partial_matches) > 0): ?>
                    <div class="matches-section">
                        <div class="matches-section-title partial-title">
                            <i class="fas fa-exclamation-circle"></i>
                            Partial Matches (<?php echo count($partial_matches); ?>)
                        </div>
                        <?php foreach ($partial_matches as $match): ?>
                        <div class="match-item partial-match">
                            <div class="match-item-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="match-item-content">
                                <div class="match-item-name"><?php echo htmlspecialchars($match['name']); ?></div>
                                <div class="match-item-type">
                                    <span><?php echo htmlspecialchars($match['type']); ?></span>
                                    <?php if (!empty($match['verified_at'])): ?>
                                    <span>Verified: <?php echo date('M j, Y', strtotime($match['verified_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="match-item-badge">Partial</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    No names found for screening. Please complete the entity's KYC information first.
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (count($names_to_screen) > 0): ?>
            <div class="verified-section">
                <!-- Not Eligible Notification Section -->
                <?php if ($has_any_matches || $entity_not_eligible): ?>
                <div class="status-notification" id="status-notification">
                    <div class="status-header">
                        <i class="fas fa-ban"></i> Status: Not Eligible to Proceed
                    </div>
                    <div class="status-content">
                        Following our internal due diligence procedures, the client onboarding could not be completed as the assessed risk level exceeds the platform's acceptable threshold.
                    </div>
                    <?php if (!$entity_not_eligible && !$screening_completed): ?>
                    <button type="submit" name="send_notification" class="send-button" onclick="return confirm('Are you sure you want to mark this entity as Not Eligible to Proceed? This action cannot be undone.');">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                    <?php elseif ($entity_not_eligible): ?>
                    <div class="sent-message">
                        <i class="fas fa-check"></i> Status notification sent. Client onboarding terminated.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($screening_completed && !$has_any_matches && !$entity_not_eligible): ?>
                <div class="next-step-info">
                    <h4><i class="fas fa-arrow-right"></i> Next Step Available</h4>
                    <p>Screening completed successfully! Entity is eligible to proceed to the ICID (Independence Confirmation) step.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="buttons-container">
                <?php if (!$entity_not_eligible && !$screening_completed): ?>
                <button type="submit" name="save_screening" class="btn btn-save" id="save-btn" <?php echo $admin_unselected > 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> 
                    <span id="save-btn-text"><?php echo $admin_unselected > 0 ? 'Complete Verification First' : 'Save & Complete Screening'; ?></span>
                </button>
                <?php elseif ($screening_completed): ?>
                <button type="button" class="btn btn-save" disabled>
                    <i class="fas fa-check"></i> 
                    <span>Screening Completed</span>
                </button>
                <?php endif; ?>
                
                <?php if ($screening_completed && !$has_any_matches && !$entity_not_eligible): ?>
                <a href="ind.php?entity_id=<?php echo $entity_id; ?>" class="btn btn-next">
                    <i class="fas fa-arrow-right"></i> Proceed to ICID
                </a>
                <?php elseif (!$entity_not_eligible && !$screening_completed): ?>
                <button type="button" class="btn btn-next" disabled>
                    <i class="fas fa-lock"></i> Complete Screening First
                </button>
                <?php endif; ?>
                
                
                
                <a href="entities_dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <?php else: ?>
            <div class="buttons-container">
                <a href="entities_dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <?php endif; ?>
        </form>
        
        <div class="footer">
            <p>Sanctions Screening Report v1.0 | Generated by Muhasba.com</p>
            <p>Generated on <?php echo date('F j, Y'); ?> at <?php echo date('g:i A'); ?></p>
        </div>
    </div>

    <script>
        // Track admin selections
        let adminSelections = {};
        
        // Initialize from existing data
        <?php foreach ($screening_results as $index => $result): ?>
        <?php if ($result['ai_result'] === 'confirmed' || $result['ai_result'] === 'partial'): ?>
        adminSelections[<?php echo $index; ?>] = '<?php echo $result['admin_result'] ?? ''; ?>';
        <?php else: ?>
        adminSelections[<?php echo $index; ?>] = 'no-match';
        <?php endif; ?>
        <?php endforeach; ?>
        
        function updateSelection(radio, index) {
            const value = radio.value;
            adminSelections[index] = value;
            
            // Update visual selection
            const group = document.getElementById('admin-group-' + index);
            if (group) {
                const options = group.querySelectorAll('.radio-option');
                options.forEach(opt => {
                    opt.classList.remove('selected', 'selected-confirmed', 'selected-partial', 'selected-no-match');
                });
                
                const selectedOption = radio.closest('.radio-option');
                if (value === 'confirmed') {
                    selectedOption.classList.add('selected-confirmed');
                } else if (value === 'partial') {
                    selectedOption.classList.add('selected-partial');
                } else if (value === 'no-match') {
                    selectedOption.classList.add('selected-no-match');
                }
            }
            
            // Remove needs-verification class from card
            const card = document.querySelector('.name-card[data-index="' + index + '"]');
            if (card) {
                card.classList.remove('needs-verification');
            }
            
            updateSummary();
        }
        
        function updateSummary() {
            let confirmed = 0;
            let partial = 0;
            let noMatch = 0;
            let pending = 0;
            
            for (let key in adminSelections) {
                const val = adminSelections[key];
                if (val === 'confirmed') confirmed++;
                else if (val === 'partial') partial++;
                else if (val === 'no-match') noMatch++;
                else pending++;
            }
            
            // Update counts
            document.getElementById('admin-confirmed-count').textContent = confirmed;
            document.getElementById('admin-partial-count').textContent = partial;
            document.getElementById('admin-no-match-count').textContent = noMatch;
            
            // Update status text
            const statusText = document.getElementById('admin-status-text');
            const pendingCountEl = document.getElementById('pending-count');
            const pendingWarning = document.getElementById('pending-warning');
            const pendingCountText = document.getElementById('pending-count-text');
            
            if (pending > 0) {
                statusText.innerHTML = 'Pending: <span id="pending-count">' + pending + '</span> name(s)';
                if (pendingWarning) {
                    pendingWarning.style.display = 'flex';
                    pendingCountText.textContent = pending + ' name(s) require admin verification before saving.';
                }
            } else {
                statusText.innerHTML = '<span style="color: #10b981;">All names verified</span>';
                if (pendingWarning) {
                    pendingWarning.style.display = 'none';
                }
            }
            
            // Update save button
            const saveBtn = document.getElementById('save-btn');
            const saveBtnText = document.getElementById('save-btn-text');
            if (saveBtn) {
                if (pending > 0) {
                    saveBtn.disabled = true;
                    saveBtnText.textContent = 'Complete Verification First';
                } else {
                    saveBtn.disabled = false;
                    saveBtnText.textContent = 'Save & Complete Screening';
                }
            }
            
            // Show/hide status notification based on matches
            const statusNotification = document.getElementById('status-notification');
            if (statusNotification) {
                if (confirmed > 0 || partial > 0) {
                    statusNotification.style.display = 'block';
                } else {
                    statusNotification.style.display = 'none';
                }
            }
            
            // Show/hide sanctions matches box based on matches
            const sanctionsMatchesBox = document.getElementById('sanctions-matches-box');
            if (sanctionsMatchesBox) {
                if (confirmed > 0 || partial > 0) {
                    sanctionsMatchesBox.style.display = 'block';
                } else {
                    sanctionsMatchesBox.style.display = 'none';
                }
            }
        }
        
        // Form validation
        document.getElementById('screening-form')?.addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'save_screening') {
                let pending = 0;
                for (let key in adminSelections) {
                    if (!adminSelections[key]) pending++;
                }
                
                if (pending > 0) {
                    e.preventDefault();
                    alert('Please complete admin verification for all ' + pending + ' name(s) before saving.');
                    
                    // Highlight first unverified card
                    for (let key in adminSelections) {
                        if (!adminSelections[key]) {
                            const card = document.querySelector('.name-card[data-index="' + key + '"]');
                            if (card) {
                                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                card.style.animation = 'pulse 0.5s ease-in-out 3';
                            }
                            break;
                        }
                    }
                }
            }
        });
        
        // Initial update
        document.addEventListener('DOMContentLoaded', function() {
            updateSummary();
            
            // Disable all radio buttons if screening is completed
            <?php if ($is_readonly): ?>
            const allRadios = document.querySelectorAll('input[type="radio"][name^="admin_results"]');
            allRadios.forEach(radio => {
                radio.disabled = true;
            });
            <?php endif; ?>
        });
    </script>
    
    <style>
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); box-shadow: 0 0 20px rgba(245, 158, 11, 0.5); }
        }
    </style>
</body>
</html>