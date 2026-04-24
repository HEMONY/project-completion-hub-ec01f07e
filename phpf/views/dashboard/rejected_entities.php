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

// Check if user has permission to view rejected entities
$user_role = $_SESSION['role'] ?? 'client';
// Note: You may want to add role-based access control here

// Initialize variables
$error = null;
$rejected_entities = [];
$total_rejected = 0;
$rejection_stats = [
    'screening_rejected' => 0,
    'ind_rejected' => 0,
    'cdd_rejected' => 0
];

try {
    $pdo = Database::getInstance()->getConnection();
    
    // First, let's check what tables exist in the database
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Debug: Check available tables
    error_log("Available tables: " . implode(", ", $tables));
    
    // Query to get all rejected entities - FIXED VERSION
    $query = "
        SELECT 
            e.*,
            u.full_name as client_name,
            u.email as client_email,
            u.mobile as client_phone
        FROM entities e
        INNER JOIN users u ON e.user_id = u.id
        WHERE e.application_status = 'rejected'
        ORDER BY e.updated_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $rejected_entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_rejected = count($rejected_entities);
    
    // Calculate rejection statistics based on workflow progress
    foreach ($rejected_entities as $entity) {
        // Check if independence_confirmations table exists
        $ind_table_exists = in_array('independence_confirmations', $tables);
        
        // Get rejection details from independence_confirmations if available
        $ind_rejection = false;
        
        if ($ind_table_exists) {
            try {
                $ind_stmt = $pdo->prepare("
                    SELECT confirmation_status, confirmation_type 
                    FROM independence_confirmations 
                    WHERE entity_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $ind_stmt->execute([$entity['id']]);
                $ind_record = $ind_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ind_record) {
                    // Check if rejected in ICID stage
                    $ind_status = strtolower($ind_record['confirmation_status'] ?? '');
                    if (in_array($ind_status, ['conflict_declared', 'terminated', 'declined', 'rejected'])) {
                        $rejection_stats['ind_rejected']++;
                        continue; // Skip to next entity
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking independence_confirmations: " . $e->getMessage());
            }
        }
        
        // Determine stage based on workflow flags
        $screening_completed = isset($entity['screening_completed']) && $entity['screening_completed'] == 1;
        $ind_completed = isset($entity['ind_completed']) && $entity['ind_completed'] == 1;
        $cdd_completed = isset($entity['cdd_completed']) && $entity['cdd_completed'] == 1;
        
        // Better logic for determining rejection stage
        if (!$screening_completed && !$ind_completed && !$cdd_completed) {
            // Rejected before screening started
            $rejection_stats['screening_rejected']++;
        } elseif ($screening_completed && !$ind_completed) {
            // Rejected during screening
            $rejection_stats['screening_rejected']++;
        } elseif ($screening_completed && $ind_completed && !$cdd_completed) {
            // Check CDD verifications table if exists
            $cdd_table_exists = in_array('cdd_verifications', $tables);
            
            if ($cdd_table_exists) {
                try {
                    $cdd_stmt = $pdo->prepare("
                        SELECT * FROM cdd_verifications 
                        WHERE entity_id = ? 
                        LIMIT 1
                    ");
                    $cdd_stmt->execute([$entity['id']]);
                    $cdd_record = $cdd_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($cdd_record) {
                        // Check if any verification failed
                        $is_cdd_failed = false;
                        if (isset($cdd_record['identity_verification']) && $cdd_record['identity_verification'] == 'failed') {
                            $is_cdd_failed = true;
                        }
                        if (isset($cdd_record['eligibility_verification']) && $cdd_record['eligibility_verification'] == 'failed') {
                            $is_cdd_failed = true;
                        }
                        if (isset($cdd_record['auditor_verification']) && $cdd_record['auditor_verification'] == 'failed') {
                            $is_cdd_failed = true;
                        }
                        
                        if ($is_cdd_failed) {
                            $rejection_stats['cdd_rejected']++;
                        } else {
                            // If CDD exists but not rejected, check screening again
                            $rejection_stats['screening_rejected']++;
                        }
                    } else {
                        // No CDD record, likely rejected after ICID
                        $rejection_stats['ind_rejected']++;
                    }
                } catch (Exception $e) {
                    error_log("Error checking cdd_verifications: " . $e->getMessage());
                    // Default to ind rejection if we can't check CDD
                    $rejection_stats['ind_rejected']++;
                }
            } else {
                // No CDD table, assume ind rejection
                $rejection_stats['ind_rejected']++;
            }
        } elseif ($screening_completed && $ind_completed && $cdd_completed) {
            // All stages completed but still rejected - must be CDD stage
            $rejection_stats['cdd_rejected']++;
        } else {
            // Fallback - try to determine from updated_at vs created_at timing
            $created = strtotime($entity['created_at']);
            $updated = strtotime($entity['updated_at']);
            $days_diff = floor(($updated - $created) / (60 * 60 * 24));
            
            if ($days_diff < 2) {
                $rejection_stats['screening_rejected']++;
            } elseif ($days_diff < 5) {
                $rejection_stats['ind_rejected']++;
            } else {
                $rejection_stats['cdd_rejected']++;
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Rejected entities screen error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    $error = "Unable to load rejected entities. Please try again later.";
    $rejected_entities = [];
    
    // Try a simpler query if the first one fails
    try {
        $simple_query = "
            SELECT e.*, u.full_name as client_name, u.email as client_email, u.mobile as client_phone
            FROM entities e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.application_status = 'rejected' 
            ORDER BY e.updated_at DESC
        ";
        $stmt = $pdo->query($simple_query);
        $rejected_entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_rejected = count($rejected_entities);
        $error = null; // Clear error if simple query works
    } catch (Exception $e2) {
        error_log("Simple query also failed: " . $e2->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Entities - Muhasba</title>
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
            color: var(--danger-color);
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
        
        .back-btn, .logout-btn {
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
        
        .back-btn:hover, .logout-btn:hover {
            background-color: #e9ecef;
        }
        
        .back-btn {
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            padding: 40px;
        }
        
        .content-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 32px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-subtitle {
            color: var(--secondary-color);
            font-size: 16px;
            font-weight: 400;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            border-top: 4px solid var(--danger-color);
            box-shadow: 0 2px 10px rgba(220, 53, 69, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 0 auto 15px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--danger-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        /* Rejection Reasons Stats */
        .rejection-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .rejection-stat {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .rejection-stat.screening {
            border-left-color: #ff6b6b;
        }
        
        .rejection-stat.ind {
            border-left-color: #ffa726;
        }
        
        .rejection-stat.cdd {
            border-left-color: #42a5f5;
        }
        
        .rejection-count {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .rejection-stat.screening .rejection-count {
            color: #ff6b6b;
        }
        
        .rejection-stat.ind .rejection-count {
            color: #ffa726;
        }
        
        .rejection-stat.cdd .rejection-count {
            color: #42a5f5;
        }
        
        .rejection-stage {
            font-size: 14px;
            color: var(--secondary-color);
        }
        
        /* Filter Bar */
        .filter-bar {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background-color: white;
            min-width: 200px;
        }
        
        .export-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .export-btn:hover {
            background-color: #c82333;
        }
        
        /* Rejected Entities Table */
        .entities-container {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .entities-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .entities-table thead {
            background-color: var(--light-gray);
            border-bottom: 2px solid var(--border-color);
        }
        
        .entities-table th {
            padding: 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }
        
        .entities-table td {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--secondary-color);
            vertical-align: top;
        }
        
        .entities-table tbody tr:hover {
            background-color: rgba(220, 53, 69, 0.05);
        }
        
        /* Entity Info */
        .entity-name {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .entity-id {
            font-family: monospace;
            color: var(--secondary-color);
            font-size: 12px;
        }
        
        .engagement-number {
            font-family: monospace;
            font-weight: 600;
            color: var(--accent-color);
        }
        
        /* Rejection Info */
        .rejection-stage-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 8px;
        }
        
        .stage-screening {
            background-color: #ff6b6b;
            color: white;
        }
        
        .stage-ind {
            background-color: #ffa726;
            color: white;
        }
        
        .stage-cdd {
            background-color: #42a5f5;
            color: white;
        }
        
        .stage-unknown {
            background-color: #6c757d;
            color: white;
        }
        
        .rejection-reason {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid var(--danger-color);
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .rejection-date {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Actions */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 120px;
        }
        
        .action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background-color: white;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            text-align: center;
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .action-btn.view {
            background-color: var(--info-color);
            color: white;
            border-color: var(--info-color);
        }
        
        .action-btn.review {
            background-color: var(--warning-color);
            color: var(--primary-color);
            border-color: var(--warning-color);
        }
        
        .action-btn.restart {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
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
        
        /* Error Message */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Info Message */
        .info-message {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                padding: 20px;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
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
            
            .entities-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            <a href="admin_dashboard.php">
                <i class="fas fa-times-circle"></i>
                <h1>Rejected Entities</h1>
            </a>
        </div>
        
        <div class="user-info">
            <div>
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="entities_dashboard.php" class="back-btn">
                    <i class="fas fa-building"></i> All Entities
                </a>
            </div>
            <div class="user-avatar">
                <?php echo substr($_SESSION['full_name'] ?? 'A', 0, 1); ?>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></strong><br>
                <small><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Admin')); ?></small>
            </div>
            <a href="../../views/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h2 class="page-title">
                <i class="fas fa-ban" style="color: var(--danger-color);"></i>
                Rejected Entities Management
            </h2>
            <div class="page-subtitle">Review and manage all rejected entities in the system</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    <?php if ($total_rejected > 0): ?>
                        <br><small>Showing <?php echo $total_rejected; ?> rejected entities from simple query.</small>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($total_rejected == 0): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> 
                <div>
                    <strong>No Rejected Entities Found</strong>
                    <br>There are currently no rejected entities in the system.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($total_rejected > 0): ?>
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_rejected; ?></div>
                    <div class="stat-label">Total Rejected Entities</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-number"><?php echo date('M Y'); ?></div>
                    <div class="stat-label">Current Month</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-number">
                        <?php 
                        try {
                            $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM entities");
                            $total_all = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                            echo $total_all > 0 ? round(($total_rejected / $total_all) * 100, 1) . '%' : '0%';
                        } catch (Exception $e) {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Rejection Rate</div>
                </div>
            </div>
            
            <!-- Rejection Stage Statistics -->
            <h3 style="margin-bottom: 15px; color: var(--primary-color);">Rejection Breakdown by Stage</h3>
            <div class="rejection-stats">
                <div class="rejection-stat screening">
                    <div class="rejection-count"><?php echo $rejection_stats['screening_rejected']; ?></div>
                    <div class="rejection-stage">Screening Stage</div>
                    <small style="color: #666; font-size: 12px;">Initial screening failures</small>
                </div>
                
                <div class="rejection-stat ind">
                    <div class="rejection-count"><?php echo $rejection_stats['ind_rejected']; ?></div>
                    <div class="rejection-stage">ICID Stage</div>
                    <small style="color: #666; font-size: 12px;">Conflict of interest declarations</small>
                </div>
                
                <div class="rejection-stat cdd">
                    <div class="rejection-count"><?php echo $rejection_stats['cdd_rejected']; ?></div>
                    <div class="rejection-stage">CDD Stage</div>
                    <small style="color: #666; font-size: 12px;">Due diligence failures</small>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <input type="text" class="search-box" placeholder="Search rejected entities..." id="searchInput">
                
                <select class="filter-select" id="rejectionStageFilter">
                    <option value="">All Rejection Stages</option>
                    <option value="screening">Screening Stage</option>
                    <option value="ind">ICID Stage</option>
                    <option value="cdd">CDD Stage</option>
                </select>
                
                <select class="filter-select" id="dateFilter">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
                
                <button class="export-btn" id="exportBtn">
                    <i class="fas fa-file-export"></i> Export to Excel
                </button>
            </div>
            
            <!-- Rejected Entities Table -->
            <div class="entities-container">
                <table class="entities-table" id="rejectedEntitiesTable">
                    <thead>
                        <tr>
                            <th>Entity Details</th>
                            <th>Client Information</th>
                            <th>Rejection Details</th>
                            <th>Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejected_entities as $entity): 
                            // Determine rejection stage based on workflow progress
                            $screening_completed = !empty($entity['screening_completed']) && $entity['screening_completed'] == 1;
                            $ind_completed = !empty($entity['ind_completed']) && $entity['ind_completed'] == 1;
                            $cdd_completed = !empty($entity['cdd_completed']) && $entity['cdd_completed'] == 1;
                            
                            // Try to get actual rejection stage from stats calculation
                            $rejection_stage = 'Unknown';
                            $stage_class = 'stage-unknown';
                            
                            // Check if we can determine stage from the entity data
                            if (!$screening_completed && !$ind_completed && !$cdd_completed) {
                                $rejection_stage = 'Screening';
                                $stage_class = 'stage-screening';
                            } elseif ($screening_completed && !$ind_completed) {
                                $rejection_stage = 'Screening';
                                $stage_class = 'stage-screening';
                            } elseif ($screening_completed && $ind_completed && !$cdd_completed) {
                                // Check independence_confirmations table
                                if (isset($tables) && in_array('independence_confirmations', $tables)) {
                                    try {
                                        $ind_stmt = $pdo->prepare("
                                            SELECT confirmation_status 
                                            FROM independence_confirmations 
                                            WHERE entity_id = ? 
                                            ORDER BY created_at DESC 
                                            LIMIT 1
                                        ");
                                        $ind_stmt->execute([$entity['id']]);
                                        $ind_status = $ind_stmt->fetchColumn();
                                        
                                        if (in_array(strtolower($ind_status ?? ''), ['conflict_declared', 'terminated', 'declined', 'rejected'])) {
                                            $rejection_stage = 'ICID';
                                            $stage_class = 'stage-ind';
                                        } else {
                                            $rejection_stage = 'CDD';
                                            $stage_class = 'stage-cdd';
                                        }
                                    } catch (Exception $e) {
                                        $rejection_stage = 'ICID';
                                        $stage_class = 'stage-ind';
                                    }
                                } else {
                                    $rejection_stage = 'ICID';
                                    $stage_class = 'stage-ind';
                                }
                            } elseif ($screening_completed && $ind_completed && $cdd_completed) {
                                $rejection_stage = 'CDD';
                                $stage_class = 'stage-cdd';
                            }
                            
                            // Format dates
                            $created_date = !empty($entity['created_at']) ? date('M d, Y', strtotime($entity['created_at'])) : 'N/A';
                            $rejection_date_formatted = !empty($entity['updated_at']) ? date('M d, Y', strtotime($entity['updated_at'])) : 'N/A';
                            $rejection_time = !empty($entity['updated_at']) ? date('h:i A', strtotime($entity['updated_at'])) : 'N/A';
                            
                            // Calculate days difference
                            $days_diff = 0;
                            if (!empty($entity['created_at']) && !empty($entity['updated_at'])) {
                                $days_diff = floor((strtotime($entity['updated_at']) - strtotime($entity['created_at'])) / (60 * 60 * 24));
                            }
                            
                            // Get client information - FIXED
                            $client_name = htmlspecialchars($entity['client_name'] ?? 'Unknown Client');
                            $client_email = htmlspecialchars($entity['client_email'] ?? 'No email');
                            $client_phone = htmlspecialchars($entity['client_phone'] ?? 'No phone');
                        ?>
                            <tr data-stage="<?php echo strtolower($rejection_stage); ?>" 
                                data-date="<?php echo !empty($entity['updated_at']) ? date('Y-m-d', strtotime($entity['updated_at'])) : ''; ?>">
                                <td>
                                    <div class="entity-name">
                                        <?php echo htmlspecialchars($entity['entity_name'] ?? 'Unnamed Entity'); ?>
                                    </div>
                                    <div class="entity-id">ID: <?php echo $entity['id']; ?></div>
                                    <div class="engagement-number" style="margin-top: 8px;">
                                        <?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <span class="status-badge status-rejected">
                                            <?php echo htmlspecialchars(ucfirst($entity['application_status'] ?? 'rejected')); ?>
                                        </span>
                                    </div>
                                </td>
                                
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $client_name; ?>
                                    </div>
                                    <div style="color: #666; font-size: 14px; margin-bottom: 3px;">
                                        <i class="fas fa-envelope"></i> 
                                        <?php echo $client_email; ?>
                                    </div>
                                    <div style="color: #666; font-size: 14px;">
                                        <i class="fas fa-phone"></i> 
                                        <?php echo $client_phone; ?>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 12px; color: #888;">
                                        Created: <?php echo $created_date; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="rejection-stage-badge <?php echo $stage_class; ?>">
                                        <?php echo $rejection_stage; ?> Stage
                                    </div>
                                    <div class="rejection-reason">
                                        <strong>Status:</strong> Entity was rejected during <?php echo strtolower($rejection_stage); ?> phase.
                                        <?php if (!empty($entity['rejection_notes'])): ?>
                                            <br><strong>Notes:</strong> <?php echo htmlspecialchars($entity['rejection_notes']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rejection-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo $rejection_date_formatted; ?>
                                        <?php if ($rejection_time != 'N/A'): ?>
                                            at <?php echo $rejection_time; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div style="font-size: 13px; color: #666;">
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-calendar-plus" style="color: var(--accent-color);"></i>
                                            <strong>Created:</strong> <?php echo $created_date; ?>
                                        </div>
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-calendar-times" style="color: var(--danger-color);"></i>
                                            <strong>Rejected:</strong> <?php echo $rejection_date_formatted; ?>
                                        </div>
                                        <?php if ($days_diff > 0): ?>
                                            <div>
                                                <i class="fas fa-clock" style="color: var(--warning-color);"></i>
                                                <strong>Processing Time:</strong> <?php echo $days_diff; ?> days
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="actions">
                                        <a href="entity_details.php?id=<?php echo $entity['id']; ?>" 
                                           class="action-btn view">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        
                                        <a href="screening.php?entity_id=<?php echo $entity['id']; ?>" 
                                           class="action-btn review">
                                            <i class="fas fa-search"></i> Review
                                        </a>
                                        
                                        <a href="restart_entity.php?id=<?php echo $entity['id']; ?>" 
                                           class="action-btn restart"
                                           onclick="return confirm('Are you sure you want to restart this entity? This will reset its status to draft.');">
                                            <i class="fas fa-redo"></i> Restart
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                <h3>No Rejected Entities</h3>
                <p>Great! There are no rejected entities in the system.</p>
                <a href="entities_dashboard.php" class="export-btn" style="background-color: var(--accent-color); margin-top: 20px; width: auto; display: inline-flex;">
                    <i class="fas fa-building"></i> View All Entities
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const stageFilter = document.getElementById('rejectionStageFilter');
            const dateFilter = document.getElementById('dateFilter');
            const tableRows = document.querySelectorAll('#rejectedEntitiesTable tbody tr');
            const exportBtn = document.getElementById('exportBtn');
            
            // Filter functionality
            function filterEntities() {
                const searchTerm = searchInput.value.toLowerCase();
                const stageValue = stageFilter.value;
                const dateValue = dateFilter.value;
                const today = new Date();
                
                tableRows.forEach(row => {
                    const entityName = row.querySelector('.entity-name').textContent.toLowerCase();
                    const clientName = row.cells[1].textContent.toLowerCase();
                    const rowStage = row.getAttribute('data-stage');
                    const rowDate = new Date(row.getAttribute('data-date'));
                    
                    // Search filter
                    const matchesSearch = entityName.includes(searchTerm) || 
                                          clientName.includes(searchTerm);
                    
                    // Stage filter
                    const matchesStage = stageValue === '' || rowStage === stageValue;
                    
                    // Date filter
                    let matchesDate = true;
                    if (dateValue !== '' && row.getAttribute('data-date')) {
                        switch(dateValue) {
                            case 'today':
                                matchesDate = rowDate.toDateString() === today.toDateString();
                                break;
                            case 'week':
                                const weekAgo = new Date();
                                weekAgo.setDate(today.getDate() - 7);
                                matchesDate = rowDate >= weekAgo;
                                break;
                            case 'month':
                                const monthAgo = new Date();
                                monthAgo.setMonth(today.getMonth() - 1);
                                matchesDate = rowDate >= monthAgo;
                                break;
                        }
                    } else if (dateValue !== '' && !row.getAttribute('data-date')) {
                        // If no date but we're filtering by date, hide this row
                        matchesDate = false;
                    }
                    
                    if (matchesSearch && matchesStage && matchesDate) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            // Add event listeners for filters
            if (searchInput) searchInput.addEventListener('input', filterEntities);
            if (stageFilter) stageFilter.addEventListener('change', filterEntities);
            if (dateFilter) dateFilter.addEventListener('change', filterEntities);
            
            // Export functionality
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    if (confirm('Export all rejected entities to Excel?')) {
                        // Create CSV data
                        let csvContent = "data:text/csv;charset=utf-8,";
                        csvContent += "Entity Name,Engagement Number,Client Name,Rejection Stage,Rejection Date,Processing Days\n";
                        
                        tableRows.forEach(row => {
                            if (row.style.display !== 'none') {
                                const entityName = row.querySelector('.entity-name').textContent;
                                const engagementNumber = row.querySelector('.engagement-number').textContent;
                                const clientName = row.cells[1].querySelector('div').textContent;
                                const rejectionStage = row.querySelector('.rejection-stage-badge').textContent;
                                const rejectionDate = row.cells[2].querySelector('.rejection-date').textContent.split(' at ')[0];
                                const processingDays = row.cells[3].querySelectorAll('div')[2] ? 
                                    row.cells[3].querySelectorAll('div')[2].textContent.replace('Processing Time: ', '').replace(' days', '') : '0';
                                
                                csvContent += `"${entityName}","${engagementNumber}","${clientName}","${rejectionStage}","${rejectionDate}","${processingDays}"\n`;
                            }
                        });
                        
                        // Download the file
                        const encodedUri = encodeURI(csvContent);
                        const link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", "rejected_entities_" + new Date().toISOString().split('T')[0] + ".csv");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                });
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+F focuses search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    if (searchInput) searchInput.focus();
                }
                
                // Ctrl+E exports
                if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                    e.preventDefault();
                    if (exportBtn) exportBtn.click();
                }
            });
        });
    </script>
</body>
</html>