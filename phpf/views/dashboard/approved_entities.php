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
$approved_entities = [];
$total_approved = 0;
$approval_stats = [
    'this_month' => 0,
    'last_month' => 0,
    'this_year' => 0,
    'by_application_type' => ['new' => 0, 'return' => 0]
];

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Query to get all approved entities
    $query = "
        SELECT 
            e.*,
            u.full_name as client_name,
            u.email as client_email,
            u.mobile as client_phone
        FROM entities e
        INNER JOIN users u ON e.user_id = u.id
        WHERE e.application_status = 'approved'
        ORDER BY e.updated_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $approved_entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_approved = count($approved_entities);
    
    // Calculate approval statistics
    $current_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));
    $current_year = date('Y');
    
    foreach ($approved_entities as $entity) {
        $approval_date = date('Y-m', strtotime($entity['updated_at']));
        $approval_year = date('Y', strtotime($entity['updated_at']));
        
        if ($approval_date === $current_month) {
            $approval_stats['this_month']++;
        }
        
        if ($approval_date === $last_month) {
            $approval_stats['last_month']++;
        }
        
        if ($approval_year === $current_year) {
            $approval_stats['this_year']++;
        }
        
        // Count by application type
        $app_type = $entity['application_type'] ?? 'new';
        if (isset($approval_stats['by_application_type'][$app_type])) {
            $approval_stats['by_application_type'][$app_type]++;
        } else {
            $approval_stats['by_application_type'][$app_type] = 1;
        }
    }
    
    // Get monthly approval trend for the last 6 months
    $monthly_trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM entities 
            WHERE application_status = 'approved' 
            AND DATE_FORMAT(updated_at, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $monthly_trend[$month_name] = $result['count'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log("Approved entities screen error: " . $e->getMessage());
    $error = "Unable to load approved entities. Please try again later.";
    $approved_entities = [];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Entities - Muhasba</title>
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
            color: var(--success-color);
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
            border-top: 4px solid var(--success-color);
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 0 auto 15px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        /* Approval Stats */
        .approval-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .approval-stat {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .approval-stat.month {
            border-left-color: #4caf50;
        }
        
        .approval-stat.last-month {
            border-left-color: #8bc34a;
        }
        
        .approval-stat.year {
            border-left-color: #cddc39;
        }
        
        .approval-stat.new {
            border-left-color: #2196f3;
        }
        
        .approval-stat.return {
            border-left-color: #ff9800;
        }
        
        .approval-count {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .approval-stat.month .approval-count {
            color: #4caf50;
        }
        
        .approval-stat.last-month .approval-count {
            color: #8bc34a;
        }
        
        .approval-stat.year .approval-count {
            color: #cddc39;
        }
        
        .approval-stat.new .approval-count {
            color: #2196f3;
        }
        
        .approval-stat.return .approval-count {
            color: #ff9800;
        }
        
        .approval-label {
            font-size: 14px;
            color: var(--secondary-color);
        }
        
        /* Trend Chart */
        .trend-container {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            border-top: 4px solid var(--success-color);
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.1);
        }
        
        .trend-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .trend-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .trend-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .bar-container {
            width: 30px;
            height: 150px;
            background-color: #f0f0f0;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        
        .bar-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--success-color);
            transition: height 0.5s ease;
        }
        
        .bar-label {
            font-size: 12px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .bar-value {
            font-size: 11px;
            font-weight: 600;
            color: var(--success-color);
            text-align: center;
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
            background-color: var(--success-color);
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
            background-color: #218838;
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
            background-color: rgba(40, 167, 69, 0.05);
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
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        /* Application Type Badge */
        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 5px;
        }
        
        .type-new {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .type-return {
            background-color: #fff3cd;
            color: #856404;
        }
        
        /* Approval Info */
        .approval-info {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid var(--success-color);
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .approval-date {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Timeline */
        .timeline-container {
            font-size: 13px;
            color: #666;
        }
        
        .timeline-item {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .timeline-icon {
            width: 20px;
            text-align: center;
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
        
        .action-btn.download {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .action-btn.audit {
            background-color: var(--warning-color);
            color: var(--primary-color);
            border-color: var(--warning-color);
        }
        
        .action-btn.revoke {
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
            
            .trend-chart {
                height: 150px;
            }
            
            .bar-container {
                width: 20px;
                height: 100px;
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
            
            .trend-chart {
                flex-wrap: wrap;
                height: auto;
            }
            
            .trend-bar {
                flex: 0 0 calc(33.333% - 20px);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            <a href="admin_dashboard.php">
                <i class="fas fa-check-circle"></i>
                <h1>Approved Entities</h1>
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
                <a href="submitted_entities.php" class="back-btn" style="background-color: #17a2b8; color: white;">
                    <i class="fas fa-paper-plane"></i> Submitted
                </a>
                <a href="rejected_entities.php" class="back-btn" style="background-color: #dc3545; color: white;">
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
                <i class="fas fa-award" style="color: var(--success-color);"></i>
                Approved Entities Management
            </h2>
            <div class="page-subtitle">View and manage all successfully approved entities in the system</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php elseif ($total_approved == 0): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> 
                <div>
                    <strong>No Approved Entities Found</strong>
                    <br>There are currently no approved entities in the system.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($total_approved > 0): ?>
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_approved; ?></div>
                    <div class="stat-label">Total Approved Entities</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $approval_stats['this_month']; ?></div>
                    <div class="stat-label">Approved This Month</div>
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
                            echo $total_all > 0 ? round(($total_approved / $total_all) * 100, 1) . '%' : '0%';
                        } catch (Exception $e) {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Approval Rate</div>
                </div>
            </div>
            
            <!-- Approval Statistics -->
            <h3 style="margin-bottom: 15px; color: var(--primary-color);">Approval Statistics</h3>
            <div class="approval-stats">
                <div class="approval-stat month">
                    <div class="approval-count"><?php echo $approval_stats['this_month']; ?></div>
                    <div class="approval-label">This Month</div>
                    <small style="color: #666; font-size: 12px;">Current month approvals</small>
                </div>
                
                <div class="approval-stat last-month">
                    <div class="approval-count"><?php echo $approval_stats['last_month']; ?></div>
                    <div class="approval-label">Last Month</div>
                    <small style="color: #666; font-size: 12px;">Previous month approvals</small>
                </div>
                
                <div class="approval-stat year">
                    <div class="approval-count"><?php echo $approval_stats['this_year']; ?></div>
                    <div class="approval-label">This Year</div>
                    <small style="color: #666; font-size: 12px;">Year-to-date approvals</small>
                </div>
                
                <div class="approval-stat new">
                    <div class="approval-count"><?php echo $approval_stats['by_application_type']['new'] ?? 0; ?></div>
                    <div class="approval-label">New Applications</div>
                    <small style="color: #666; font-size: 12px;">First-time approvals</small>
                </div>
                
                <div class="approval-stat return">
                    <div class="approval-count"><?php echo $approval_stats['by_application_type']['return'] ?? 0; ?></div>
                    <div class="approval-label">Return Applications</div>
                    <small style="color: #666; font-size: 12px;">Renewal approvals</small>
                </div>
            </div>
            
            <!-- Monthly Trend Chart -->
            <div class="trend-container">
                <div class="trend-title">
                    <i class="fas fa-chart-bar" style="color: var(--success-color);"></i>
                    Monthly Approval Trend (Last 6 Months)
                </div>
                <div class="trend-chart" id="trendChart">
                    <?php foreach ($monthly_trend as $month => $count): 
                        $max_count = max($monthly_trend);
                        $bar_height = $max_count > 0 ? ($count / $max_count) * 100 : 0;
                    ?>
                        <div class="trend-bar">
                            <div class="bar-container">
                                <div class="bar-fill" style="height: <?php echo $bar_height; ?>%;"></div>
                            </div>
                            <div class="bar-value"><?php echo $count; ?></div>
                            <div class="bar-label"><?php echo $month; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <input type="text" class="search-box" placeholder="Search approved entities..." id="searchInput">
                
                <select class="filter-select" id="applicationTypeFilter">
                    <option value="">All Application Types</option>
                    <option value="new">New Applications</option>
                    <option value="return">Return Applications</option>
                </select>
                
                <select class="filter-select" id="dateFilter">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
                
                <button class="export-btn" id="exportBtn">
                    <i class="fas fa-file-export"></i> Export to Excel
                </button>
            </div>
            
            <!-- Approved Entities Table -->
            <div class="entities-container">
                <table class="entities-table" id="approvedEntitiesTable">
                    <thead>
                        <tr>
                            <th>Entity Details</th>
                            <th>Client Information</th>
                            <th>Approval Details</th>
                            <th>Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved_entities as $entity): 
                            // Format dates
                            $created_date = !empty($entity['created_at']) ? date('M d, Y', strtotime($entity['created_at'])) : 'N/A';
                            $submitted_date = !empty($entity['submitted_at']) ? date('M d, Y', strtotime($entity['submitted_at'])) : 'N/A';
                            $approval_date_formatted = !empty($entity['updated_at']) ? date('M d, Y', strtotime($entity['updated_at'])) : 'N/A';
                            $approval_time = !empty($entity['updated_at']) ? date('h:i A', strtotime($entity['updated_at'])) : 'N/A';
                            
                            // Calculate processing time
                            $processing_days = 0;
                            if (!empty($entity['submitted_at']) && !empty($entity['updated_at'])) {
                                $processing_days = floor((strtotime($entity['updated_at']) - strtotime($entity['submitted_at'])) / (60 * 60 * 24));
                            }
                            
                            // Application type
                            $app_type = $entity['application_type'] ?? 'new';
                            $type_class = $app_type === 'return' ? 'type-return' : 'type-new';
                            $type_label = $app_type === 'return' ? 'Return' : 'New';
                        ?>
                            <tr data-type="<?php echo $app_type; ?>" 
                                data-date="<?php echo !empty($entity['updated_at']) ? date('Y-m-d', strtotime($entity['updated_at'])) : ''; ?>"
                                data-days="<?php echo $processing_days; ?>">
                                <td>
                                    <div class="entity-name">
                                        <?php echo htmlspecialchars($entity['entity_name'] ?? 'Unnamed Entity'); ?>
                                    </div>
                                    <div class="entity-id">ID: <?php echo $entity['id']; ?></div>
                                    <div class="engagement-number" style="margin-top: 8px;">
                                        <?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <span class="status-badge status-approved">
                                            Approved
                                        </span>
                                        <span class="type-badge <?php echo $type_class; ?>">
                                            <?php echo $type_label; ?>
                                        </span>
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
                                    <div class="approval-info">
                                        <strong>Processing Time:</strong> <?php echo $processing_days; ?> days
                                        <br>
                                        <strong>Application Type:</strong> <?php echo ucfirst($app_type); ?>
                                        <?php if (!empty($entity['reviewed_at'])): ?>
                                            <br>
                                            <strong>Reviewed By:</strong> System Administrator
                                        <?php endif; ?>
                                    </div>
                                    <div class="approval-date">
                                        <i class="far fa-calendar-check"></i>
                                        <strong>Approved:</strong> <?php echo $approval_date_formatted; ?>
                                        <?php if ($approval_time != 'N/A'): ?>
                                            at <?php echo $approval_time; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="timeline-container">
                                        <div class="timeline-item">
                                            <div class="timeline-icon">
                                                <i class="fas fa-calendar-plus" style="color: var(--accent-color);"></i>
                                            </div>
                                            <div>
                                                <strong>Created:</strong> <?php echo $created_date; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($submitted_date != 'N/A'): ?>
                                            <div class="timeline-item">
                                                <div class="timeline-icon">
                                                    <i class="fas fa-paper-plane" style="color: #17a2b8;"></i>
                                                </div>
                                                <div>
                                                    <strong>Submitted:</strong> <?php echo $submitted_date; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="timeline-item">
                                            <div class="timeline-icon">
                                                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                            </div>
                                            <div>
                                                <strong>Approved:</strong> <?php echo $approval_date_formatted; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($processing_days > 0): ?>
                                            <div class="timeline-item">
                                                <div class="timeline-icon">
                                                    <i class="fas fa-clock" style="color: var(--warning-color);"></i>
                                                </div>
                                                <div>
                                                    <strong>Processing:</strong> <?php echo $processing_days; ?> days
                                                </div>
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
                                        
                                        <a href="generate_certificate.php?id=<?php echo $entity['id']; ?>" 
                                           class="action-btn download">
                                            <i class="fas fa-file-pdf"></i> Certificate
                                        </a>
                                        
                                        <a href="audit_report.php?entity_id=<?php echo $entity['id']; ?>" 
                                           class="action-btn audit">
                                            <i class="fas fa-chart-bar"></i> Audit Report
                                        </a>
                                        
                                        <a href="revoke_approval.php?id=<?php echo $entity['id']; ?>" 
                                           class="action-btn revoke"
                                           onclick="return confirm('Are you sure you want to revoke approval for this entity? This action cannot be undone.');">
                                            <i class="fas fa-undo"></i> Revoke
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
                <h3>No Approved Entities</h3>
                <p>There are currently no approved entities in the system.</p>
                <a href="entities_dashboard.php" class="export-btn" style="background-color: var(--accent-color); margin-top: 20px; width: auto; display: inline-flex;">
                    <i class="fas fa-building"></i> View All Entities
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const typeFilter = document.getElementById('applicationTypeFilter');
            const dateFilter = document.getElementById('dateFilter');
            const tableRows = document.querySelectorAll('#approvedEntitiesTable tbody tr');
            const exportBtn = document.getElementById('exportBtn');
            
            // Filter functionality
            function filterEntities() {
                const searchTerm = searchInput.value.toLowerCase();
                const typeValue = typeFilter.value;
                const dateValue = dateFilter.value;
                const today = new Date();
                
                tableRows.forEach(row => {
                    const entityName = row.querySelector('.entity-name').textContent.toLowerCase();
                    const clientName = row.cells[1].textContent.toLowerCase();
                    const rowType = row.getAttribute('data-type');
                    const rowDate = new Date(row.getAttribute('data-date'));
                    const rowDays = parseInt(row.getAttribute('data-days'));
                    
                    // Search filter
                    const matchesSearch = entityName.includes(searchTerm) || 
                                          clientName.includes(searchTerm);
                    
                    // Type filter
                    const matchesType = typeValue === '' || rowType === typeValue;
                    
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
                            case 'year':
                                const yearAgo = new Date();
                                yearAgo.setFullYear(today.getFullYear() - 1);
                                matchesDate = rowDate >= yearAgo;
                                break;
                        }
                    } else if (dateValue !== '' && !row.getAttribute('data-date')) {
                        matchesDate = false;
                    }
                    
                    if (matchesSearch && matchesType && matchesDate) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            // Add event listeners for filters
            if (searchInput) searchInput.addEventListener('input', filterEntities);
            if (typeFilter) typeFilter.addEventListener('change', filterEntities);
            if (dateFilter) dateFilter.addEventListener('change', filterEntities);
            
            // Export functionality
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    if (confirm('Export all approved entities to Excel?')) {
                        // Create CSV data
                        let csvContent = "data:text/csv;charset=utf-8,";
                        csvContent += "Entity Name,Engagement Number,Client Name,Application Type,Approval Date,Processing Days\n";
                        
                        tableRows.forEach(row => {
                            if (row.style.display !== 'none') {
                                const entityName = row.querySelector('.entity-name').textContent;
                                const engagementNumber = row.querySelector('.engagement-number').textContent;
                                const clientName = row.cells[1].querySelector('div').textContent;
                                const applicationType = row.querySelector('.type-badge').textContent;
                                const approvalDate = row.cells[2].querySelector('.approval-date').textContent.replace('Approved: ', '');
                                const processingDays = row.cells[2].querySelector('.approval-info').textContent.split('Processing Time: ')[1]?.split(' days')[0]?.trim() || '0';
                                
                                csvContent += `"${entityName}","${engagementNumber}","${clientName}","${applicationType}","${approvalDate}","${processingDays}"\n`;
                            }
                        });
                        
                        // Download the file
                        const encodedUri = encodeURI(csvContent);
                        const link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", "approved_entities_" + new Date().toISOString().split('T')[0] + ".csv");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                });
            }
            
            // Animate trend chart bars
            function animateTrendBars() {
                const bars = document.querySelectorAll('.bar-fill');
                bars.forEach((bar, index) => {
                    setTimeout(() => {
                        const computedHeight = getComputedStyle(bar).height;
                        bar.style.height = '0%';
                        setTimeout(() => {
                            bar.style.height = computedHeight;
                        }, 50);
                    }, index * 100);
                });
            }
            
            // Initial animation
            setTimeout(animateTrendBars, 500);
            
            // Sort functionality
            function sortTable(column, order = 'asc') {
                const table = document.querySelector('#approvedEntitiesTable tbody');
                const rows = Array.from(table.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    let aValue, bValue;
                    
                    if (column === 'processing') {
                        aValue = parseInt(a.getAttribute('data-days')) || 0;
                        bValue = parseInt(b.getAttribute('data-days')) || 0;
                    } else if (column === 'date') {
                        aValue = new Date(a.getAttribute('data-date'));
                        bValue = new Date(b.getAttribute('data-date'));
                    } else if (column === 'name') {
                        aValue = a.querySelector('.entity-name').textContent.toLowerCase();
                        bValue = b.querySelector('.entity-name').textContent.toLowerCase();
                    }
                    
                    if (order === 'asc') {
                        return aValue > bValue ? 1 : -1;
                    } else {
                        return aValue < bValue ? 1 : -1;
                    }
                });
                
                // Reorder rows
                rows.forEach(row => table.appendChild(row));
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
                
                // Ctrl+D sorts by date (newest first)
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    sortTable('date', 'desc');
                }
                
                // Ctrl+P sorts by processing time (fastest first)
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    sortTable('processing', 'asc');
                }
            });
        });
    </script>
</body>
</html>