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

// Initialize variables
$error = null;
$submitted_entities = [];
$total_submitted = 0;
$status_stats = [
    'submitted' => 0,
    'under_review' => 0,
    'screening_pending' => 0,
    'ind_pending' => 0,
    'cdd_pending' => 0
];

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Query to get all submitted entities (submitted and under_review status)
    $query = "
        SELECT 
            e.*,
            u.full_name as client_name,
            u.email as client_email,
            u.mobile as client_phone
        FROM entities e
        INNER JOIN users u ON e.user_id = u.id
        WHERE e.application_status IN ('submitted', 'under_review')
        ORDER BY 
            CASE 
                WHEN e.application_status = 'under_review' THEN 1
                WHEN e.application_status = 'submitted' THEN 2
                ELSE 3
            END,
            e.submitted_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $submitted_entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_submitted = count($submitted_entities);
    
    // Calculate status statistics
    foreach ($submitted_entities as $entity) {
        $status = $entity['application_status'];
        
        if ($status === 'submitted') {
            $status_stats['submitted']++;
        } elseif ($status === 'under_review') {
            $status_stats['under_review']++;
        }
        
        // Determine current stage for workflow tracking
        $screening_completed = isset($entity['screening_completed']) && $entity['screening_completed'] == 1;
        $ind_completed = isset($entity['ind_completed']) && $entity['ind_completed'] == 1;
        $cdd_completed = isset($entity['cdd_completed']) && $entity['cdd_completed'] == 1;
        
        if (!$screening_completed) {
            $status_stats['screening_pending']++;
        } elseif ($screening_completed && !$ind_completed) {
            $status_stats['ind_pending']++;
        } elseif ($screening_completed && $ind_completed && !$cdd_completed) {
            $status_stats['cdd_pending']++;
        }
    }
    
} catch (Exception $e) {
    error_log("Submitted entities screen error: " . $e->getMessage());
    $error = "Unable to load submitted entities. Please try again later.";
    $submitted_entities = [];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submitted Entities - Muhasba</title>
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
            --submitted-color: #17a2b8;
            --under-review-color: #ffc107;
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
            color: var(--submitted-color);
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
            border-top: 4px solid var(--submitted-color);
            box-shadow: 0 2px 10px rgba(23, 162, 184, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--submitted-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 0 auto 15px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--submitted-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        /* Status Stats */
        .status-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .status-stat {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .status-stat.submitted {
            border-left-color: var(--submitted-color);
        }
        
        .status-stat.under-review {
            border-left-color: var(--under-review-color);
        }
        
        .status-stat.screening {
            border-left-color: #ff6b6b;
        }
        
        .status-stat.ind {
            border-left-color: #ffa726;
        }
        
        .status-stat.cdd {
            border-left-color: #42a5f5;
        }
        
        .status-count {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .status-stat.submitted .status-count {
            color: var(--submitted-color);
        }
        
        .status-stat.under-review .status-count {
            color: var(--under-review-color);
        }
        
        .status-stat.screening .status-count {
            color: #ff6b6b;
        }
        
        .status-stat.ind .status-count {
            color: #ffa726;
        }
        
        .status-stat.cdd .status-count {
            color: #42a5f5;
        }
        
        .status-label {
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
            background-color: var(--submitted-color);
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
            background-color: #138496;
        }
        
        /* Entities Table */
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
            background-color: rgba(23, 162, 184, 0.05);
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
        
        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-submitted {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-under-review {
            background-color: #fff3cd;
            color: #856404;
        }
        
        /* Stage Badges */
        .stage-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin: 2px;
        }
        
        .stage-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .stage-pending {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .stage-current {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        /* Timeline Info */
        .timeline-info {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .timeline-date {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
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
        
        .action-btn.process {
            background-color: var(--warning-color);
            color: var(--primary-color);
            border-color: var(--warning-color);
        }
        
        .action-btn.approve {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .action-btn.reject {
            background-color: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
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
        
        /* Priority Badge */
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            margin-left: 5px;
        }
        
        .priority-high {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .priority-medium {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .priority-low {
            background-color: #d4edda;
            color: #155724;
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
                <i class="fas fa-paper-plane"></i>
                <h1>Submitted Entities</h1>
            </a>
        </div>
        
        <div class="user-info">
            <div>
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <a href="entities_dashboard.php" class="back-btn">
                    <i class="fas fa-building"></i> All Entities
                </a>
                <a href="rejected_entities.php" class="back-btn" style="background-color: var(--danger-color); color: white;">
                    <i class="fas fa-ban"></i> Rejected
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
                <i class="fas fa-inbox" style="color: var(--submitted-color);"></i>
                Submitted Entities Management
            </h2>
            <div class="page-subtitle">Review and process all submitted entities awaiting action</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php elseif ($total_submitted == 0): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> 
                <div>
                    <strong>No Submitted Entities Found</strong>
                    <br>There are currently no submitted entities awaiting review.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($total_submitted > 0): ?>
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_submitted; ?></div>
                    <div class="stat-label">Total Submitted Entities</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $pending_review = $status_stats['submitted'];
                        echo $pending_review;
                        ?>
                    </div>
                    <div class="stat-label">Awaiting Initial Review</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number">
                        <?php 
                        try {
                            $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM entities");
                            $total_all = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                            echo $total_all > 0 ? round(($total_submitted / $total_all) * 100, 1) . '%' : '0%';
                        } catch (Exception $e) {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Submission Rate</div>
                </div>
            </div>
            
            <!-- Status Statistics -->
            <h3 style="margin-bottom: 15px; color: var(--primary-color);">Workflow Status Breakdown</h3>
            <div class="status-stats">
                <div class="status-stat submitted">
                    <div class="status-count"><?php echo $status_stats['submitted']; ?></div>
                    <div class="status-label">Submitted</div>
                    <small style="color: #666; font-size: 12px;">Awaiting initial review</small>
                </div>
                
                <div class="status-stat under-review">
                    <div class="status-count"><?php echo $status_stats['under_review']; ?></div>
                    <div class="status-label">Under Review</div>
                    <small style="color: #666; font-size: 12px;">In progress</small>
                </div>
                
                <div class="status-stat screening">
                    <div class="status-count"><?php echo $status_stats['screening_pending']; ?></div>
                    <div class="status-label">Screening Pending</div>
                    <small style="color: #666; font-size: 12px;">Awaiting screening</small>
                </div>
                
                <div class="status-stat ind">
                    <div class="status-count"><?php echo $status_stats['ind_pending']; ?></div>
                    <div class="status-label">ICID Pending</div>
                    <small style="color: #666; font-size: 12px;">Awaiting independence check</small>
                </div>
                
                <div class="status-stat cdd">
                    <div class="status-count"><?php echo $status_stats['cdd_pending']; ?></div>
                    <div class="status-label">CDD Pending</div>
                    <small style="color: #666; font-size: 12px;">Awaiting due diligence</small>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <input type="text" class="search-box" placeholder="Search submitted entities..." id="searchInput">
                
                <select class="filter-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="submitted">Submitted</option>
                    <option value="under_review">Under Review</option>
                </select>
                
                <select class="filter-select" id="stageFilter">
                    <option value="">All Stages</option>
                    <option value="screening">Screening Pending</option>
                    <option value="ind">ICID Pending</option>
                    <option value="cdd">CDD Pending</option>
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
            
            <!-- Submitted Entities Table -->
            <div class="entities-container">
                <table class="entities-table" id="submittedEntitiesTable">
                    <thead>
                        <tr>
                            <th>Entity Details</th>
                            <th>Client Information</th>
                            <th>Status & Workflow</th>
                            <th>Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submitted_entities as $entity): 
                            // Determine current stage
                            $screening_completed = !empty($entity['screening_completed']) && $entity['screening_completed'] == 1;
                            $ind_completed = !empty($entity['ind_completed']) && $entity['ind_completed'] == 1;
                            $cdd_completed = !empty($entity['cdd_completed']) && $entity['cdd_completed'] == 1;
                            
                            $current_stage = 'Screening';
                            $current_stage_class = 'stage-current';
                            
                            if (!$screening_completed) {
                                $current_stage = 'Screening';
                                $current_stage_class = 'stage-current';
                            } elseif ($screening_completed && !$ind_completed) {
                                $current_stage = 'ICID';
                                $current_stage_class = 'stage-current';
                            } elseif ($screening_completed && $ind_completed && !$cdd_completed) {
                                $current_stage = 'CDD';
                                $current_stage_class = 'stage-current';
                            } elseif ($screening_completed && $ind_completed && $cdd_completed) {
                                $current_stage = 'Completed';
                                $current_stage_class = 'stage-completed';
                            }
                            
                            // Format dates
                            $created_date = !empty($entity['created_at']) ? date('M d, Y', strtotime($entity['created_at'])) : 'N/A';
                            $submitted_date = !empty($entity['submitted_at']) ? date('M d, Y', strtotime($entity['submitted_at'])) : 'N/A';
                            $submitted_time = !empty($entity['submitted_at']) ? date('h:i A', strtotime($entity['submitted_at'])) : 'N/A';
                            
                            // Calculate days since submission
                            $days_since_submission = 0;
                            if (!empty($entity['submitted_at'])) {
                                $submitted = strtotime($entity['submitted_at']);
                                $now = time();
                                $days_since_submission = floor(($now - $submitted) / (60 * 60 * 24));
                            }
                            
                            // Determine priority
                            $priority = 'low';
                            $priority_class = 'priority-low';
                            if ($days_since_submission > 7) {
                                $priority = 'high';
                                $priority_class = 'priority-high';
                            } elseif ($days_since_submission > 3) {
                                $priority = 'medium';
                                $priority_class = 'priority-medium';
                            }
                        ?>
                            <tr data-status="<?php echo $entity['application_status']; ?>" 
                                data-stage="<?php echo strtolower($current_stage); ?>"
                                data-date="<?php echo !empty($entity['submitted_at']) ? date('Y-m-d', strtotime($entity['submitted_at'])) : ''; ?>"
                                data-priority="<?php echo $priority; ?>">
                                <td>
                                    <div class="entity-name">
                                        <?php echo htmlspecialchars($entity['entity_name'] ?? 'Unnamed Entity'); ?>
                                        <?php if ($priority !== 'low'): ?>
                                            <span class="priority-badge <?php echo $priority_class; ?>">
                                                <?php echo strtoupper($priority); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="entity-id">ID: <?php echo $entity['id']; ?></div>
                                    <div class="engagement-number" style="margin-top: 8px;">
                                        <?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <?php if ($entity['application_status'] === 'submitted'): ?>
                                            <span class="status-badge status-submitted">
                                                Submitted
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-under-review">
                                                Under Review
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 5px;">
                                        <?php echo htmlspecialchars($entity['client_name'] ?? 'Unknown Client'); ?>
                                    </div>
                                    <div style="color: #666; font-size: 14px; margin-bottom: 3px;">
                                        <i class="fas fa-envelope"></i> 
                                        <?php echo htmlspecialchars($entity['client_email'] ?? 'No email'); ?>
                                    </div>
                                    <div style="color: #666; font-size: 14px;">
                                        <i class="fas fa-phone"></i> 
                                        <?php echo htmlspecialchars($entity['client_phone'] ?? 'No phone'); ?>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 12px; color: #888;">
                                        Created: <?php echo $created_date; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div style="margin-bottom: 10px;">
                                        <span class="stage-badge <?php echo $screening_completed ? 'stage-completed' : 'stage-pending'; ?>">
                                            Screening <?php echo $screening_completed ? '✓' : '…'; ?>
                                        </span>
                                        <span class="stage-badge <?php echo $ind_completed ? 'stage-completed' : 'stage-pending'; ?>">
                                            ICID <?php echo $ind_completed ? '✓' : '…'; ?>
                                        </span>
                                        <span class="stage-badge <?php echo $cdd_completed ? 'stage-completed' : 'stage-pending'; ?>">
                                            CDD <?php echo $cdd_completed ? '✓' : '…'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="timeline-info">
                                        <strong>Current Stage:</strong> 
                                        <span class="stage-badge <?php echo $current_stage_class; ?>">
                                            <?php echo $current_stage; ?>
                                        </span>
                                        <br>
                                        <strong>Days in Queue:</strong> <?php echo $days_since_submission; ?> days
                                    </div>
                                </td>
                                
                                <td>
                                    <div style="font-size: 13px; color: #666;">
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-calendar-plus" style="color: var(--accent-color);"></i>
                                            <strong>Created:</strong> <?php echo $created_date; ?>
                                        </div>
                                        <div style="margin-bottom: 8px;">
                                            <i class="fas fa-paper-plane" style="color: var(--submitted-color);"></i>
                                            <strong>Submitted:</strong> <?php echo $submitted_date; ?>
                                            <?php if ($submitted_time != 'N/A'): ?>
                                                at <?php echo $submitted_time; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($entity['reviewed_at'])): ?>
                                            <div style="margin-bottom: 8px;">
                                                <i class="fas fa-search" style="color: var(--under-review-color);"></i>
                                                <strong>Review Started:</strong> <?php echo date('M d, Y', strtotime($entity['reviewed_at'])); ?>
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
                                        
                                        <?php if (!$screening_completed): ?>
                                            <a href="screening.php?entity_id=<?php echo $entity['id']; ?>" 
                                               class="action-btn process">
                                                <i class="fas fa-search"></i> Start Screening
                                            </a>
                                        <?php elseif ($screening_completed && !$ind_completed): ?>
                                            <a href="independence_check.php?entity_id=<?php echo $entity['id']; ?>" 
                                               class="action-btn process">
                                                <i class="fas fa-handshake"></i> ICID Check
                                            </a>
                                        <?php elseif ($screening_completed && $ind_completed && !$cdd_completed): ?>
                                            <a href="cdd_verification.php?entity_id=<?php echo $entity['id']; ?>" 
                                               class="action-btn process">
                                                <i class="fas fa-user-check"></i> CDD Check
                                            </a>
                                        <?php else: ?>
                                            <a href="approve_entity.php?id=<?php echo $entity['id']; ?>" 
                                               class="action-btn approve"
                                               onclick="return confirm('Are you sure you want to approve this entity?');">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="reject_entity.php?id=<?php echo $entity['id']; ?>" 
                                           class="action-btn reject"
                                           onclick="return confirm('Are you sure you want to reject this entity?');">
                                            <i class="fas fa-times"></i> Reject
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
                <i class="fas fa-inbox" style="color: var(--submitted-color);"></i>
                <h3>No Submitted Entities</h3>
                <p>There are currently no entities submitted for review.</p>
                <a href="entities_dashboard.php" class="export-btn" style="background-color: var(--accent-color); margin-top: 20px; width: auto; display: inline-flex;">
                    <i class="fas fa-building"></i> View All Entities
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const stageFilter = document.getElementById('stageFilter');
            const dateFilter = document.getElementById('dateFilter');
            const tableRows = document.querySelectorAll('#submittedEntitiesTable tbody tr');
            const exportBtn = document.getElementById('exportBtn');
            
            // Filter functionality
            function filterEntities() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const stageValue = stageFilter.value;
                const dateValue = dateFilter.value;
                const today = new Date();
                
                tableRows.forEach(row => {
                    const entityName = row.querySelector('.entity-name').textContent.toLowerCase();
                    const clientName = row.cells[1].textContent.toLowerCase();
                    const rowStatus = row.getAttribute('data-status');
                    const rowStage = row.getAttribute('data-stage');
                    const rowDate = new Date(row.getAttribute('data-date'));
                    const rowPriority = row.getAttribute('data-priority');
                    
                    // Search filter
                    const matchesSearch = entityName.includes(searchTerm) || 
                                          clientName.includes(searchTerm);
                    
                    // Status filter
                    const matchesStatus = statusValue === '' || rowStatus === statusValue;
                    
                    // Stage filter
                    let matchesStage = true;
                    if (stageValue !== '') {
                        if (stageValue === 'screening') {
                            matchesStage = rowStage === 'screening';
                        } else if (stageValue === 'ind') {
                            matchesStage = rowStage === 'icid';
                        } else if (stageValue === 'cdd') {
                            matchesStage = rowStage === 'cdd';
                        }
                    }
                    
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
                        matchesDate = false;
                    }
                    
                    if (matchesSearch && matchesStatus && matchesStage && matchesDate) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            // Add event listeners for filters
            if (searchInput) searchInput.addEventListener('input', filterEntities);
            if (statusFilter) statusFilter.addEventListener('change', filterEntities);
            if (stageFilter) stageFilter.addEventListener('change', filterEntities);
            if (dateFilter) dateFilter.addEventListener('change', filterEntities);
            
            // Export functionality
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    if (confirm('Export all submitted entities to Excel?')) {
                        // Create CSV data
                        let csvContent = "data:text/csv;charset=utf-8,";
                        csvContent += "Entity Name,Engagement Number,Client Name,Status,Current Stage,Days in Queue,Priority\n";
                        
                        tableRows.forEach(row => {
                            if (row.style.display !== 'none') {
                                const entityName = row.querySelector('.entity-name').textContent;
                                const engagementNumber = row.querySelector('.engagement-number').textContent;
                                const clientName = row.cells[1].querySelector('div').textContent;
                                const status = row.querySelector('.status-badge').textContent;
                                const currentStage = row.cells[2].querySelector('.stage-badge:last-child').textContent;
                                const daysInQueue = row.cells[2].querySelector('.timeline-info').textContent.split('Days in Queue:')[1]?.split(' days')[0]?.trim() || '0';
                                const priority = row.getAttribute('data-priority');
                                
                                csvContent += `"${entityName}","${engagementNumber}","${clientName}","${status}","${currentStage}","${daysInQueue}","${priority}"\n`;
                            }
                        });
                        
                        // Download the file
                        const encodedUri = encodeURI(csvContent);
                        const link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", "submitted_entities_" + new Date().toISOString().split('T')[0] + ".csv");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                });
            }
            
            // Sort by priority (High > Medium > Low)
            function sortByPriority() {
                const table = document.querySelector('#submittedEntitiesTable tbody');
                const rows = Array.from(table.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    const priorityOrder = { high: 0, medium: 1, low: 2 };
                    const aPriority = a.getAttribute('data-priority');
                    const bPriority = b.getAttribute('data-priority');
                    
                    return priorityOrder[aPriority] - priorityOrder[bPriority];
                });
                
                // Reorder rows
                rows.forEach(row => table.appendChild(row));
            }
            
            // Sort by submission date (newest first)
            function sortByDate() {
                const table = document.querySelector('#submittedEntitiesTable tbody');
                const rows = Array.from(table.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    const aDate = new Date(a.getAttribute('data-date'));
                    const bDate = new Date(b.getAttribute('data-date'));
                    
                    return bDate - aDate; // Newest first
                });
                
                // Reorder rows
                rows.forEach(row => table.appendChild(row));
            }
            
            // Initial sort by priority
            sortByPriority();
            
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
                
                // Ctrl+P sorts by priority
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    sortByPriority();
                }
                
                // Ctrl+D sorts by date
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    sortByDate();
                }
            });
        });
    </script>
</body>
</html>