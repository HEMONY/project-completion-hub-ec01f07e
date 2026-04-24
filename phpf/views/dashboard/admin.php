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

// Get user role for permissions
$user_role = $_SESSION['role'] ?? 'client';

// Initialize variables
$error = null;
$entities = [];
$stats = [
    'new_entities' => 0, // Submitted entities
    'processing_entities' => 0, // Entities needing processing (draft + under_review)
    'reported_names' => 0,
    'edited_waiting' => 0 // Additional count for edited entities waiting
];

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Different queries based on user role
    if ($user_role === 'admin' || $user_role === 'auditor' || $user_role === 'staff') {
        // Admin/Staff/Auditor can see all entities
        $query = "
            SELECT e.*, 
                   u.full_name as client_name, 
                   u.email as client_email,
                   ic.confirmation_status as ind_confirmation_status
            FROM entities e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN independence_confirmations ic ON e.id = ic.entity_id
            ORDER BY e.created_at DESC
        ";
        $stmt = $pdo->query($query);
        $entities = $stmt->fetchAll();
        
        // Get statistics for the three cards
        $stats_query = "
            SELECT 
                SUM(CASE WHEN application_status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN application_status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN application_status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN application_status = 'edited' THEN 1 ELSE 0 END) as edited,
                SUM(CASE WHEN application_status = 'pending_review' THEN 1 ELSE 0 END) as pending_review
            FROM entities
        ";
        $stats_stmt = $pdo->query($stats_query);
        $stats_result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats_result) {
            // New entities (submitted)
            $stats['new_entities'] = $stats_result['submitted'] ?? 0;
            
            // Entities needing processing (draft + under_review)
            $stats['processing_entities'] = ($stats_result['draft'] ?? 0) + ($stats_result['under_review'] ?? 0);
            
            // Edited entities waiting for edit/approval
            $stats['edited_waiting'] = ($stats_result['edited'] ?? 0) + ($stats_result['pending_review'] ?? 0);
        }
        
        // Get reported names count
        try {
            $reported_names_query = "
                SELECT COUNT(*) as reported_count 
                FROM name_change_requests 
                WHERE status IN ('pending', 'under_review')
            ";
            $reported_stmt = $pdo->query($reported_names_query);
            $reported_result = $reported_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['reported_names'] = $reported_result['reported_count'] ?? 0;
        } catch (Exception $e) {
            $stats['reported_names'] = 0;
        }
        
    } else {
        // Clients can only see their own entities
        $query = "
            SELECT e.*, 
                   u.full_name as client_name, 
                   u.email as client_email,
                   ic.confirmation_status as ind_confirmation_status
            FROM entities e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN independence_confirmations ic ON e.id = ic.entity_id
            WHERE e.user_id = ?
            ORDER BY e.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $entities = $stmt->fetchAll();
        
        // Get client statistics for the three cards
        foreach ($entities as $entity) {
            $status = $entity['application_status'] ?? 'draft';
            
            if ($status === 'submitted') {
                $stats['new_entities']++;
            } elseif ($status === 'draft' || $status === 'under_review') {
                $stats['processing_entities']++;
            } elseif ($status === 'edited' || $status === 'pending_review') {
                $stats['edited_waiting']++;
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Unable to load dashboard data. Please try again later.";
    $entities = [];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Muhasba</title>
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
        
        /* Dashboard Cards - Updated for 3 cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }
        
        .dashboard-card.new-entities::before {
            background-color: var(--info-color);
        }
        
        .dashboard-card.processing-entities::before {
            background-color: var(--warning-color);
        }
        
        .dashboard-card.reported-names::before {
            background-color: var(--danger-color);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .card-icon.new-entities {
            background-color: var(--info-color);
        }
        
        .card-icon.processing-entities {
            background-color: var(--warning-color);
        }
        
        .card-icon.reported-names {
            background-color: var(--danger-color);
        }
        
        .card-arrow {
            color: var(--secondary-color);
            opacity: 0.6;
            transition: all 0.3s;
        }
        
        .dashboard-card:hover .card-arrow {
            opacity: 1;
            transform: translateX(5px);
        }
        
        .card-content h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .card-content p {
            color: var(--secondary-color);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 25px;
        }
        
        .card-numbers {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .number-item {
            flex: 1;
        }
        
        .number-label {
            font-size: 14px;
            color: var(--secondary-color);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .number-value {
            font-size: 36px;
            font-weight: 700;
            line-height: 1;
        }
        
        .number-value.small {
            font-size: 28px;
        }
        
        .card-trend {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .card-trend.positive {
            color: var(--success-color);
        }
        
        .card-trend.warning {
            color: var(--warning-color);
        }
        
        .card-trend.neutral {
            color: var(--secondary-color);
        }
        
        /* Recent Activity Section */
        .recent-activity {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--accent-color);
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .activity-icon.create {
            background-color: var(--success-color);
        }
        
        .activity-icon.update {
            background-color: var(--info-color);
        }
        
        .activity-icon.submit {
            background-color: var(--purple-color);
        }
        
        .activity-icon.reject {
            background-color: var(--danger-color);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--primary-color);
        }
        
        .activity-time {
            font-size: 12px;
            color: var(--secondary-color);
        }
        
        .activity-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .activity-status.draft {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .activity-status.submitted {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .activity-status.approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .activity-status.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .quick-action-btn {
            background-color: white;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action-btn:hover {
            border-color: var(--accent-color);
            background-color: rgba(42, 91, 215, 0.05);
            transform: translateY(-3px);
        }
        
        .quick-action-btn i {
            font-size: 24px;
            color: var(--accent-color);
        }
        
        .quick-action-btn span {
            font-weight: 600;
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
        
        /* Processing Breakdown */
        .processing-breakdown {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .breakdown-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            background-color: var(--light-gray);
            border-radius: 6px;
        }
        
        .breakdown-label {
            font-size: 12px;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .breakdown-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
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
            
            .dashboard-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            .card-numbers {
                flex-direction: column;
                gap: 20px;
            }
            
            .recent-activity {
                padding: 20px;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .processing-breakdown {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .activity-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .activity-status {
                align-self: center;
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
            <a href="../../views/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h2 class="page-title">System Overview</h2>
            <div class="page-subtitle">Welcome back! Here's what's happening with your entities.</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Dashboard Cards - Now only 3 cards -->
        <div class="dashboard-cards">
            <!-- New Entities Card -->
            <a href="entities_dashboard.php" class="dashboard-card new-entities">
                <div class="card-header">
                    <div class="card-icon new-entities">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>New Entities</h3>
                    <p>Recently submitted entities awaiting initial review</p>
                    <div class="card-numbers">
                        <div class="number-item">
                            <div class="number-label">
                                <i class="fas fa-inbox"></i> Submitted
                            </div>
                            <div class="number-value"><?php echo $stats['new_entities']; ?></div>
                        </div>
                    </div>
                    <div class="card-trend <?php echo $stats['new_entities'] > 0 ? 'positive' : 'neutral'; ?>">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $stats['new_entities']; ?> waiting for review</span>
                    </div>
                </div>
            </a>
            
            <!-- Entities Needing Processing Card -->
            <a href="processing_entities.php" class="dashboard-card processing-entities">
                <div class="card-header">
                    <div class="card-icon processing-entities">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Entities Needing Processing</h3>
                    <p>Entities that require action or review</p>
                    <div class="card-numbers">
                        <div class="number-item">
                            <div class="number-label">
                                <i class="fas fa-edit"></i> To Edit
                            </div>
                            <div class="number-value"><?php echo $stats['processing_entities']; ?></div>
                        </div>
                        <div class="number-item">
                            <div class="number-label">
                                <i class="fas fa-history"></i> Edited/Waiting
                            </div>
                            <div class="number-value small"><?php echo $stats['edited_waiting']; ?></div>
                        </div>
                    </div>
                    <div class="processing-breakdown">
                        <div class="breakdown-item">
                            <div class="breakdown-label">Draft</div>
                            <div class="breakdown-value">
                                <?php 
                                    // Assuming we have draft count - you would need to modify the SQL to get this separately
                                    echo "0"; // Replace with actual draft count
                                ?>
                            </div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-label">Under Review</div>
                            <div class="breakdown-value">
                                <?php 
                                    // Assuming we have under_review count
                                    echo "0"; // Replace with actual under_review count
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
            
            <!-- Reported Names Card -->
            <a href="reported_names.php" class="dashboard-card reported-names">
                <div class="card-header">
                    <div class="card-icon reported-names">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="card-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Reported Names</h3>
                    <p>Names that require attention or verification</p>
                    <div class="card-numbers">
                        <div class="number-item">
                            <div class="number-label">
                                <i class="fas fa-exclamation-triangle"></i> Total Reported
                            </div>
                            <div class="number-value"><?php echo $stats['reported_names']; ?></div>
                        </div>
                    </div>
                    <div class="card-trend <?php echo $stats['reported_names'] > 0 ? 'warning' : 'neutral'; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span><?php echo $stats['reported_names']; ?> require attention</span>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Quick Actions -->
        <div class="section-title">
            <i class="fas fa-bolt"></i>
            <span>Quick Actions</span>
        </div>
        
        <div class="quick-actions">
            <a href="create_entity.php" class="quick-action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Create New Entity</span>
            </a>
            <a href="submitted_entities.php" class="quick-action-btn">
                <i class="fas fa-inbox"></i>
                <span>Review New Entities</span>
            </a>
            <a href="processing_entities.php" class="quick-action-btn">
                <i class="fas fa-cogs"></i>
                <span>Process Entities</span>
            </a>
            <a href="reported_names.php" class="quick-action-btn">
                <i class="fas fa-flag"></i>
                <span>Review Reported Names</span>
            </a>
            <a href="users_management.php" class="quick-action-btn">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="section-title">
                <i class="fas fa-history"></i>
                <span>Recent Activity</span>
            </div>
            
            <?php if (!empty($entities)): ?>
                <ul class="activity-list">
                    <?php 
                    // Show only the 5 most recent entities
                    $recent_entities = array_slice($entities, 0, 5);
                    foreach ($recent_entities as $entity): 
                        $status = $entity['application_status'] ?? 'draft';
                        $status_text = ucfirst(str_replace('_', ' ', $status));
                        $created_date = date('M d, Y', strtotime($entity['created_at']));
                        
                        // Determine icon and class based on status
                        $icon_class = 'create';
                        $icon = 'fas fa-plus';
                        if ($status === 'submitted') {
                            $icon_class = 'submit';
                            $icon = 'fas fa-paper-plane';
                        } elseif ($status === 'approved') {
                            $icon_class = 'update';
                            $icon = 'fas fa-check';
                        } elseif ($status === 'rejected') {
                            $icon_class = 'reject';
                            $icon = 'fas fa-times';
                        } elseif ($status === 'under_review') {
                            $icon_class = 'update';
                            $icon = 'fas fa-search';
                        }
                    ?>
                        <li class="activity-item">
                            <div class="activity-icon <?php echo $icon_class; ?>">
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($entity['entity_name'] ?? 'Unnamed Entity'); ?>
                                </div>
                                <div class="activity-time">
                                    Created: <?php echo $created_date; ?> • Client: <?php echo htmlspecialchars($entity['client_name'] ?? 'Unknown'); ?>
                                </div>
                            </div>
                            <div class="activity-status <?php echo $status; ?>">
                                <?php echo $status_text; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No Recent Activity</h3>
                    <p>No entities have been created yet. Create your first entity to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click animation to cards
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Add ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.7);
                        transform: scale(0);
                        animation: ripple-animation 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        top: ${y}px;
                        left: ${x}px;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Alt + 1 for New Entities
                if (e.altKey && e.key === '1') {
                    window.location.href = 'submitted_entities.php';
                }
                // Alt + 2 for Processing Entities
                if (e.altKey && e.key === '2') {
                    window.location.href = 'processing_entities.php';
                }
                // Alt + 3 for Reported Names
                if (e.altKey && e.key === '3') {
                    window.location.href = 'reported_names.php';
                }
                // Alt + N for New Entity
                if (e.altKey && e.key === 'n') {
                    window.location.href = 'create_entity.php';
                }
            });
        });
        
        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>