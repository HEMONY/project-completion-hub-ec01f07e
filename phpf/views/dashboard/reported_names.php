<?php
session_start();
require_once '../../config/db.php';
require_once "../../views/widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../views/login/login.php');
    exit();
}

// Check if user has appropriate permissions (admin, auditor, or staff)
$user_role = $_SESSION['role'] ?? 'client';


// Initialize variables
$error = null;
$success = null;
$reported_names = [];
$filters = [
    'ai_result' => $_GET['ai_result'] ?? '',
    'admin_result' => $_GET['admin_result'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
        $selected_ids = $_POST['selected_ids'];
        $bulk_action = $_POST['bulk_action'];
        
        try {
            $pdo = Database::getInstance()->getConnection();
            
            // Convert selected_ids to integers for safety
            $ids = array_map('intval', $selected_ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            switch ($bulk_action) {
                case 'mark_no_match':
                    $stmt = $pdo->prepare("UPDATE screening_results SET admin_result = 'no-match', verified_by = ?, verified_at = NOW() WHERE id IN ($placeholders)");
                    $params = array_merge([$_SESSION['user_id']], $ids);
                    $stmt->execute($params);
                    $success = "Successfully marked " . count($ids) . " record(s) as 'No Match'";
                    break;
                    
                case 'mark_confirmed':
                    $stmt = $pdo->prepare("UPDATE screening_results SET admin_result = 'confirmed', verified_by = ?, verified_at = NOW() WHERE id IN ($placeholders)");
                    $params = array_merge([$_SESSION['user_id']], $ids);
                    $stmt->execute($params);
                    $success = "Successfully marked " . count($ids) . " record(s) as 'Confirmed'";
                    break;
                    
                case 'mark_partial':
                    $stmt = $pdo->prepare("UPDATE screening_results SET admin_result = 'partial', verified_by = ?, verified_at = NOW() WHERE id IN ($placeholders)");
                    $params = array_merge([$_SESSION['user_id']], $ids);
                    $stmt->execute($params);
                    $success = "Successfully marked " . count($ids) . " record(s) as 'Partial Match'";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM screening_results WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $success = "Successfully deleted " . count($ids) . " record(s)";
                    break;
            }
            
        } catch (Exception $e) {
            error_log("Bulk action error: " . $e->getMessage());
            $error = "Failed to perform bulk action. Please try again.";
        }
    }
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Build query with filters
    $query = "
        SELECT 
            sr.*,
            e.entity_name,
            e.engagement_number,
            e.application_status,
            u.full_name as client_name,
            u.email as client_email,
            u.mobile as client_mobile,
            CONCAT(u2.full_name, ' (', u2.role, ')') as verified_by_name
        FROM screening_results sr
        LEFT JOIN entities e ON sr.entity_id = e.id
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN users u2 ON sr.verified_by = u2.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filter by AI result
    if (!empty($filters['ai_result'])) {
        $query .= " AND sr.ai_result = ?";
        $params[] = $filters['ai_result'];
    }
    
    // Filter by admin result
    if (!empty($filters['admin_result'])) {
        if ($filters['admin_result'] === 'pending') {
            $query .= " AND sr.admin_result IS NULL";
        } else {
            $query .= " AND sr.admin_result = ?";
            $params[] = $filters['admin_result'];
        }
    }
    
    // Filter by search term
    if (!empty($filters['search'])) {
        $query .= " AND (
            sr.name_to_screen LIKE ? OR 
            e.entity_name LIKE ? OR 
            u.full_name LIKE ? OR 
            e.engagement_number LIKE ?
        )";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Filter by date range
    if (!empty($filters['date_from'])) {
        $query .= " AND DATE(sr.screened_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND DATE(sr.screened_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Only show confirmed or partial matches
    $query .= " AND sr.ai_result IN ('confirmed', 'partial')";
    
    // Order by most recent first
    $query .= " ORDER BY sr.screened_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reported_names = $stmt->fetchAll();
    
    // Get enhanced statistics - AI Results breakdown
    $ai_stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ai_result = 'confirmed' THEN 1 ELSE 0 END) as ai_confirmed,
            SUM(CASE WHEN ai_result = 'partial' THEN 1 ELSE 0 END) as ai_partial,
            SUM(CASE WHEN ai_result = 'no-match' THEN 1 ELSE 0 END) as ai_no_match
        FROM screening_results
        WHERE ai_result IN ('confirmed', 'partial')
    ";
    
    // Add filter conditions to stats query if they exist
    $ai_stats_conditions = [];
    $ai_stats_params = [];
    
    if (!empty($filters['date_from'])) {
        $ai_stats_conditions[] = "DATE(screened_at) >= ?";
        $ai_stats_params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $ai_stats_conditions[] = "DATE(screened_at) <= ?";
        $ai_stats_params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $ai_stats_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN sr.ai_result = 'confirmed' THEN 1 ELSE 0 END) as ai_confirmed,
                SUM(CASE WHEN sr.ai_result = 'partial' THEN 1 ELSE 0 END) as ai_partial,
                SUM(CASE WHEN sr.ai_result = 'no-match' THEN 1 ELSE 0 END) as ai_no_match
            FROM screening_results sr
            LEFT JOIN entities e ON sr.entity_id = e.id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE sr.ai_result IN ('confirmed', 'partial')
        ";
        
        if (!empty($ai_stats_conditions)) {
            $ai_stats_query .= " AND " . implode(" AND ", $ai_stats_conditions);
        }
        
        $ai_stats_query .= " AND (
            sr.name_to_screen LIKE ? OR 
            e.entity_name LIKE ? OR 
            u.full_name LIKE ? OR 
            e.engagement_number LIKE ?
        )";
        $searchTerm = "%" . $filters['search'] . "%";
        $ai_stats_params[] = $searchTerm;
        $ai_stats_params[] = $searchTerm;
        $ai_stats_params[] = $searchTerm;
        $ai_stats_params[] = $searchTerm;
    } elseif (!empty($ai_stats_conditions)) {
        $ai_stats_query .= " AND " . implode(" AND ", $ai_stats_conditions);
    }
    
    $ai_stats_stmt = $pdo->prepare($ai_stats_query);
    $ai_stats_stmt->execute($ai_stats_params);
    $ai_stats = $ai_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get enhanced statistics - Admin Results breakdown
    $admin_stats_query = "
        SELECT 
            COUNT(*) as total_admin,
            SUM(CASE WHEN admin_result = 'confirmed' THEN 1 ELSE 0 END) as admin_confirmed,
            SUM(CASE WHEN admin_result = 'partial' THEN 1 ELSE 0 END) as admin_partial,
            SUM(CASE WHEN admin_result = 'no-match' THEN 1 ELSE 0 END) as admin_no_match,
            SUM(CASE WHEN admin_result IS NULL THEN 1 ELSE 0 END) as admin_pending
        FROM screening_results
        WHERE ai_result IN ('confirmed', 'partial')
    ";
    
    $admin_stats_conditions = [];
    $admin_stats_params = [];
    
    if (!empty($filters['ai_result'])) {
        $admin_stats_conditions[] = "ai_result = ?";
        $admin_stats_params[] = $filters['ai_result'];
    }
    
    if (!empty($filters['date_from'])) {
        $admin_stats_conditions[] = "DATE(screened_at) >= ?";
        $admin_stats_params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $admin_stats_conditions[] = "DATE(screened_at) <= ?";
        $admin_stats_params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $admin_stats_query = "
            SELECT 
                COUNT(*) as total_admin,
                SUM(CASE WHEN sr.admin_result = 'confirmed' THEN 1 ELSE 0 END) as admin_confirmed,
                SUM(CASE WHEN sr.admin_result = 'partial' THEN 1 ELSE 0 END) as admin_partial,
                SUM(CASE WHEN sr.admin_result = 'no-match' THEN 1 ELSE 0 END) as admin_no_match,
                SUM(CASE WHEN sr.admin_result IS NULL THEN 1 ELSE 0 END) as admin_pending
            FROM screening_results sr
            LEFT JOIN entities e ON sr.entity_id = e.id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE sr.ai_result IN ('confirmed', 'partial')
        ";
        
        if (!empty($admin_stats_conditions)) {
            $admin_stats_query .= " AND " . implode(" AND ", $admin_stats_conditions);
        }
        
        $admin_stats_query .= " AND (
            sr.name_to_screen LIKE ? OR 
            e.entity_name LIKE ? OR 
            u.full_name LIKE ? OR 
            e.engagement_number LIKE ?
        )";
        $searchTerm = "%" . $filters['search'] . "%";
        $admin_stats_params[] = $searchTerm;
        $admin_stats_params[] = $searchTerm;
        $admin_stats_params[] = $searchTerm;
        $admin_stats_params[] = $searchTerm;
    } elseif (!empty($admin_stats_conditions)) {
        $admin_stats_query .= " AND " . implode(" AND ", $admin_stats_conditions);
    }
    
    $admin_stats_stmt = $pdo->prepare($admin_stats_query);
    $admin_stats_stmt->execute($admin_stats_params);
    $admin_stats = $admin_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate percentages for better visualization
    $ai_stats['ai_confirmed_pct'] = $ai_stats['total'] > 0 ? round(($ai_stats['ai_confirmed'] / $ai_stats['total']) * 100, 1) : 0;
    $ai_stats['ai_partial_pct'] = $ai_stats['total'] > 0 ? round(($ai_stats['ai_partial'] / $ai_stats['total']) * 100, 1) : 0;
    $ai_stats['ai_no_match_pct'] = $ai_stats['total'] > 0 ? round(($ai_stats['ai_no_match'] / $ai_stats['total']) * 100, 1) : 0;
    
    $admin_stats['admin_confirmed_pct'] = $admin_stats['total_admin'] > 0 ? round(($admin_stats['admin_confirmed'] / $admin_stats['total_admin']) * 100, 1) : 0;
    $admin_stats['admin_partial_pct'] = $admin_stats['total_admin'] > 0 ? round(($admin_stats['admin_partial'] / $admin_stats['total_admin']) * 100, 1) : 0;
    $admin_stats['admin_no_match_pct'] = $admin_stats['total_admin'] > 0 ? round(($admin_stats['admin_no_match'] / $admin_stats['total_admin']) * 100, 1) : 0;
    $admin_stats['admin_pending_pct'] = $admin_stats['total_admin'] > 0 ? round(($admin_stats['admin_pending'] / $admin_stats['total_admin']) * 100, 1) : 0;
    
} catch (Exception $e) {
    error_log("Reported names error: " . $e->getMessage());
    $error = "Unable to load reported names. Please try again later.";
    $reported_names = [];
    $ai_stats = [
        'total' => 0,
        'ai_confirmed' => 0,
        'ai_partial' => 0,
        'ai_no_match' => 0,
        'ai_confirmed_pct' => 0,
        'ai_partial_pct' => 0,
        'ai_no_match_pct' => 0
    ];
    $admin_stats = [
        'total_admin' => 0,
        'admin_confirmed' => 0,
        'admin_partial' => 0,
        'admin_no_match' => 0,
        'admin_pending' => 0,
        'admin_confirmed_pct' => 0,
        'admin_partial_pct' => 0,
        'admin_no_match_pct' => 0,
        'admin_pending_pct' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Names - Muhasba</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --info-color: #17a2b8;
            --purple-color: #6f42c1;
            --teal-color: #20c997;
            --orange-color: #fd7e14;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--primary-color);
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background-color: white;
            padding: 20px 40px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-container i {
            color: var(--accent-color);
            font-size: 28px;
        }
        
        .logo-container h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .logo-container a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .logout-btn {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background-color: #e9ecef;
        }
        
        /* Main Content */
        .main-content {
            padding: 40px;
        }
        
        .content-header {
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: 32px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: var(--secondary-color);
            font-size: 16px;
            font-weight: 400;
        }
        
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--accent-color);
        }
        
        .stat-card.total {
            border-left-color: var(--accent-color);
        }
        
        .stat-card.ai-confirmed {
            border-left-color: var(--danger-color);
        }
        
        .stat-card.ai-partial {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.ai-no-match {
            border-left-color: var(--info-color);
        }
        
        .stat-card.admin-confirmed {
            border-left-color: #e74c3c;
        }
        
        .stat-card.admin-partial {
            border-left-color: #f39c12;
        }
        
        .stat-card.admin-no-match {
            border-left-color: var(--success-color);
        }
        
        .stat-card.admin-pending {
            border-left-color: #95a5a6;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--secondary-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-percentage {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-trend {
            font-size: 12px;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-breakdown {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed var(--border-color);
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .stat-row:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            color: var(--secondary-color);
        }
        
        .stat-value {
            font-weight: 600;
        }
        
        .progress-bar {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 2px;
        }
        
        /* Filters */
        .filters-section {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filters-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filters-title i {
            color: var(--accent-color);
        }
        
        .clear-filters {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .clear-filters:hover {
            background-color: #e9ecef;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--secondary-color);
        }
        
        .filter-input,
        .filter-select {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background-color: white;
            transition: all 0.3s;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(42, 91, 215, 0.1);
        }
        
        .filter-button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            align-self: flex-end;
        }
        
        .filter-button:hover {
            background-color: #1a4bbf;
        }
        
        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background-color: white;
            border-radius: 10px;
            padding: 15px 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .bulk-select {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .bulk-select-all {
            font-weight: 500;
            color: var(--secondary-color);
        }
        
        .bulk-action-select {
            padding: 8px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background-color: white;
        }
        
        .bulk-action-button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .bulk-action-button:hover {
            background-color: #1a4bbf;
        }
        
        /* Table */
        .table-container {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .reported-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reported-table thead {
            background-color: var(--light-gray);
            border-bottom: 2px solid var(--border-color);
        }
        
        .reported-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .reported-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .reported-table tbody tr:hover {
            background-color: rgba(42, 91, 215, 0.03);
        }
        
        .reported-table td {
            padding: 16px 20px;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-badge.confirmed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.partial {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-badge.no-match {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn.view {
            background-color: var(--info-color);
            color: white;
        }
        
        .action-btn.view:hover {
            background-color: #138496;
        }
        
        .checkbox-cell {
            width: 40px;
        }
        
        .checkbox-cell input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background-color: white;
            border-top: 1px solid var(--border-color);
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background-color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background-color: var(--light-gray);
            border-color: var(--accent-color);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-pages {
            display: flex;
            gap: 5px;
        }
        
        .pagination-page {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            background-color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination-page:hover {
            background-color: var(--light-gray);
        }
        
        .pagination-page.active {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary-color);
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .header {
                padding: 15px;
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .reported-table {
                display: block;
                overflow-x: auto;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .bulk-actions > div {
                width: 100%;
                display: flex;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            <a href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <h1>Admin Dashboard</h1>
            </a>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo substr($_SESSION['full_name'] ?? 'A', 0, 1); ?>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></strong><br>
                <small><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Admin')); ?></small>
            </div>
            <a href="admin_dashboard.php" class="logout-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="../../views/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h2 class="page-title">Reported Names</h2>
            <div class="page-subtitle">Review and manage potential matches from sanctions screening</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards - AI Results -->
        <div class="stats-cards">
            <div class="stat-card total">
                <h3>Total Reported</h3>
                <div class="stat-number"><?php echo $ai_stats['total'] ?? 0; ?></div>
                <div class="stat-percentage">
                    <?php if ($ai_stats['total'] > 0): ?>
                        <span class="text-success"><?php echo $ai_stats['ai_confirmed_pct']; ?>% Confirmed</span>
                    <?php else: ?>
                        <span class="text-muted">No data</span>
                    <?php endif; ?>
                </div>
                <div class="stat-breakdown">
                    <div class="stat-row">
                        <span class="stat-label">Confirmed:</span>
                        <span class="stat-value"><?php echo $ai_stats['ai_confirmed'] ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $ai_stats['ai_confirmed_pct']; ?>%; background-color: var(--danger-color);"></div>
                    </div>
                    
                    <div class="stat-row">
                        <span class="stat-label">Partial:</span>
                        <span class="stat-value"><?php echo $ai_stats['ai_partial'] ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $ai_stats['ai_partial_pct']; ?>%; background-color: var(--warning-color);"></div>
                    </div>
                    
                    <div class="stat-row">
                        <span class="stat-label">No Match:</span>
                        <span class="stat-value"><?php echo $ai_stats['ai_no_match'] ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $ai_stats['ai_no_match_pct']; ?>%; background-color: var(--info-color);"></div>
                    </div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-chart-line"></i>
                    <span>AI Analysis Results</span>
                </div>
            </div>
            
            <div class="stat-card ai-confirmed">
                <h3>AI Confirmed Matches</h3>
                <div class="stat-number"><?php echo $ai_stats['ai_confirmed'] ?? 0; ?></div>
                <div class="stat-percentage">
                    <?php if ($ai_stats['total'] > 0): ?>
                        <span class="text-danger"><?php echo $ai_stats['ai_confirmed_pct']; ?>% of total</span>
                    <?php else: ?>
                        <span class="text-muted">No data</span>
                    <?php endif; ?>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>High priority review needed</span>
                </div>
            </div>
            
            <div class="stat-card ai-partial">
                <h3>AI Partial Matches</h3>
                <div class="stat-number"><?php echo $ai_stats['ai_partial'] ?? 0; ?></div>
                <div class="stat-percentage">
                    <?php if ($ai_stats['total'] > 0): ?>
                        <span class="text-warning"><?php echo $ai_stats['ai_partial_pct']; ?>% of total</span>
                    <?php else: ?>
                        <span class="text-muted">No data</span>
                    <?php endif; ?>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-search"></i>
                    <span>Manual verification required</span>
                </div>
            </div>
            
            <!-- Statistics Cards - Admin Results -->
            <div class="stat-card admin-confirmed">
                <h3>Admin Verified</h3>
                <div class="stat-number"><?php echo $admin_stats['admin_confirmed'] ?? 0; ?></div>
                <div class="stat-percentage">
                    <?php if ($admin_stats['total_admin'] > 0): ?>
                        <span class="text-danger"><?php echo $admin_stats['admin_confirmed_pct']; ?>% verified</span>
                    <?php else: ?>
                        <span class="text-muted">No data</span>
                    <?php endif; ?>
                </div>
                <div class="stat-breakdown">
                    <div class="stat-row">
                        <span class="stat-label">Pending:</span>
                        <span class="stat-value"><?php echo $admin_stats['admin_pending'] ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $admin_stats['admin_pending_pct']; ?>%; background-color: #95a5a6;"></div>
                    </div>
                    
                    <div class="stat-row">
                        <span class="stat-label">No Match:</span>
                        <span class="stat-value"><?php echo $admin_stats['admin_no_match'] ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $admin_stats['admin_no_match_pct']; ?>%; background-color: var(--success-color);"></div>
                    </div>
                    
                    <div class="stat-row">
                        <span class="stat-label">Partial:</span>
                        <span class="stat-value"><?php echo $admin_stats['admin_partial'] ?? 0; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $admin_stats['admin_partial_pct']; ?>%; background-color: #f39c12;"></div>
                    </div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-user-check"></i>
                    <span>Administrator Verification</span>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-header">
                <div class="filters-title">
                    <i class="fas fa-filter"></i>
                    <span>Filter Results</span>
                </div>
                <a href="reported_names.php" class="clear-filters">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </div>
            
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label">AI Result</label>
                    <select name="ai_result" class="filter-select">
                        <option value="">All AI Results</option>
                        <option value="confirmed" <?php echo $filters['ai_result'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="partial" <?php echo $filters['ai_result'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="no-match" <?php echo $filters['ai_result'] == 'no-match' ? 'selected' : ''; ?>>No Match</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Admin Result</label>
                    <select name="admin_result" class="filter-select">
                        <option value="">All Admin Results</option>
                        <option value="confirmed" <?php echo $filters['admin_result'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="partial" <?php echo $filters['admin_result'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="no-match" <?php echo $filters['admin_result'] == 'no-match' ? 'selected' : ''; ?>>No Match</option>
                        <option value="pending" <?php echo $filters['admin_result'] == '' ? 'selected' : ''; ?>>Pending Review</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search names, entities, or engagement..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date From</label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date To</label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="filter-button">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($reported_names)): ?>
            <!-- Bulk Actions -->
            <form method="POST" class="bulk-actions" id="bulkForm">
                <div>
                    <input type="checkbox" id="selectAll" class="bulk-select">
                    <label for="selectAll" class="bulk-select-all">Select All</label>
                </div>
                
                <div>
                    <select name="bulk_action" class="bulk-action-select" required>
                        <option value="">-- Bulk Actions --</option>
                        <option value="mark_confirmed">Mark as Confirmed</option>
                        <option value="mark_partial">Mark as Partial</option>
                        <option value="mark_no_match">Mark as No Match</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    
                    <button type="submit" class="bulk-action-button" onclick="return confirmBulkAction()">
                        <i class="fas fa-play"></i> Apply
                    </button>
                </div>
            </form>
            
            <!-- Results Table -->
            <div class="table-container">
                <table class="reported-table">
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <!-- Checkbox handled by selectAll -->
                            </th>
                            <th>Screened Name</th>
                            <th>AI Result</th>
                            <th>Admin Result</th>
                            <th>Entity</th>
                            <th>Client</th>
                            <th>Engagement #</th>
                            <th>Screened Date</th>
                            <th>Verified By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reported_names as $record): ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $record['id']; ?>" class="record-checkbox">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['name_to_screen']); ?></strong>
                                    <?php if ($record['name_type']): ?>
                                        <br><small class="text-muted">Type: <?php echo htmlspecialchars($record['name_type']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $record['ai_result']; ?>">
                                        <?php echo ucfirst($record['ai_result'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['admin_result']): ?>
                                        <span class="status-badge <?php echo $record['admin_result']; ?>">
                                            <?php echo ucfirst($record['admin_result']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['entity_name']): ?>
                                        <?php echo htmlspecialchars($record['entity_name']); ?>
                                        <br><small class="text-muted">Status: <?php echo ucfirst(str_replace('_', ' ', $record['application_status'])); ?></small>
                                    <?php else: ?>
                                        <em>Entity not found</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['client_name']): ?>
                                        <strong><?php echo htmlspecialchars($record['client_name']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($record['client_email']); ?></small>
                                        <?php if ($record['client_mobile']): ?>
                                            <br><small><?php echo htmlspecialchars($record['client_mobile']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <em>Client not found</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['engagement_number']): ?>
                                        <code><?php echo htmlspecialchars($record['engagement_number']); ?></code>
                                    <?php else: ?>
                                        <em>No engagement #</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($record['screened_at'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($record['screened_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($record['verified_by_name']): ?>
                                        <?php echo htmlspecialchars($record['verified_by_name']); ?><br>
                                        <small><?php echo date('M d, Y', strtotime($record['verified_at'])); ?></small>
                                    <?php else: ?>
                                        <em>Not verified</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($record['entity_id']): ?>
                                            <button type="button" class="action-btn view" onclick="viewScreening(<?php echo $record['entity_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="action-btn view" disabled title="No entity associated">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <h3>No Reported Names Found</h3>
                <p>No names have been flagged for review matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select All functionality
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.record-checkbox');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                });
                
                // Update select all when individual checkboxes change
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        selectAll.checked = allChecked;
                    });
                });
            }
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Alt + F to focus on search
                if (e.altKey && e.key === 'f') {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput) searchInput.focus();
                }
                // Alt + R to refresh
                if (e.altKey && e.key === 'r') {
                    window.location.href = window.location.pathname;
                }
                // Alt + D to go to dashboard
                if (e.altKey && e.key === 'd') {
                    window.location.href = 'admin_dashboard.php';
                }
            });
        });
        
        function confirmBulkAction() {
            const selectedCount = document.querySelectorAll('.record-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Please select at least one record to perform bulk action.');
                return false;
            }
            
            const actionSelect = document.querySelector('select[name="bulk_action"]');
            if (!actionSelect.value) {
                alert('Please select a bulk action to perform.');
                return false;
            }
            
            return confirm(`Are you sure you want to perform this action on ${selectedCount} selected record(s)?`);
        }
        
        function viewScreening(entityId) {
            // Redirect to screening.php with the entity_id parameter
            window.location.href = `../../views/dashboard/CDD.php?entity_id=${entityId}`;
        }
        
        function editResult(id) {
            // Function removed as per requirements
        }
        
        function deleteRecord(id) {
            // Function removed as per requirements
        }
    </script>
</body>
</html>