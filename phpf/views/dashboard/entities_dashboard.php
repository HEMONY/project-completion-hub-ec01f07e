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

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Different queries based on user role
    if ($user_role === 'admin' || $user_role === 'auditor' || $user_role === 'staff') {
        // Admin/Staff/Auditor can see all entities
        // Only show submitted entities with application_type = 'new'
        $query = "
            SELECT e.*, 
                   u.full_name as client_name, 
                   u.email as client_email,
                   ic.confirmation_status as ind_confirmation_status
            FROM entities e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN independence_confirmations ic ON e.id = ic.entity_id
            WHERE e.application_status = 'submitted' 
            AND e.application_type = 'new'
            ORDER BY e.created_at DESC
        ";
        $stmt = $pdo->query($query);
    } else {
        // Clients can only see their own entities
        // Only show submitted entities with application_type = 'new'
        $query = "
            SELECT e.*, 
                   u.full_name as client_name, 
                   u.email as client_email,
                   ic.confirmation_status as ind_confirmation_status
            FROM entities e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN independence_confirmations ic ON e.id = ic.entity_id
            WHERE e.user_id = ?
            AND e.application_status = 'submitted'
            AND e.application_type = 'new'
            ORDER BY e.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    $entities = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Entities dashboard error: " . $e->getMessage());
    $error = "Unable to load entities. Please try again later.";
    $entities = [];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Entities Dashboard - Muhasba</title>
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
            margin-bottom: 30px;
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
        
        .actions-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .create-entity-btn {
            background-color: var(--accent-color);
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
        
        .create-entity-btn:hover {
            background-color: #1f4bc2;
        }
        
        .search-filter {
            display: flex;
            gap: 15px;
        }
        
        .search-box {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            width: 300px;
            font-size: 14px;
        }
        
        /* Entities Table */
        .entities-table-container {
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
        }
        
        .entities-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .entity-name {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 16px;
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
        
        .status-draft {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .status-submitted {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-under_review {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 10px;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .action-btn.screening {
            background-color: var(--screening-color);
            color: white;
            border-color: var(--screening-color);
        }
        
        .action-btn.ind {
            background-color: var(--ind-color);
            color: white;
            border-color: var(--ind-color);
        }
        
        .action-btn.cdd {
            background-color: var(--cdd-color);
            color: white;
            border-color: var(--cdd-color);
        }
        
        .action-btn.edit {
            background-color: var(--warning-color);
            color: var(--primary-color);
            border-color: var(--warning-color);
        }
        
        .action-btn.edit:hover {
            background-color: #e0a800;
        }
        
        .action-btn:disabled,
        .action-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Rejected/Blocked button styling */
        .action-btn.rejected-blocked {
            background-color: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Workflow Progress */
        .workflow-progress {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
        }
        
        .workflow-step {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        
        .workflow-step.completed {
            background-color: var(--success-color);
            color: white;
        }
        
        .workflow-step.current {
            background-color: var(--accent-color);
            color: white;
        }
        
        .workflow-step.pending {
            background-color: var(--light-gray);
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }
        
        /* Rejected workflow step styling */
        .workflow-step.rejected {
            background-color: var(--danger-color);
            color: white;
        }
        
        /* Blocked workflow step styling */
        .workflow-step.blocked {
            background-color: #6c757d;
            color: white;
        }
        
        .workflow-line {
            flex: 1;
            height: 2px;
            background-color: var(--border-color);
        }
        
        .workflow-line.completed {
            background-color: var(--success-color);
        }
        
        /* Rejected workflow line styling */
        .workflow-line.rejected {
            background-color: var(--danger-color);
        }
        
        /* No Entities Message */
        .no-entities {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary-color);
        }
        
        .no-entities i {
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
            
            .actions-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .search-filter {
                width: 100%;
            }
            
            .search-box {
                width: 100%;
            }
            
            .actions {
                flex-wrap: wrap;
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
            
            .create-entity-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Workflow Legend */
        .workflow-legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .legend-color.screening {
            background-color: var(--screening-color);
        }
        
        .legend-color.ind {
            background-color: var(--ind-color);
        }
        
        .legend-color.cdd {
            background-color: var(--cdd-color);
        }
        
        .legend-color.locked {
            background-color: var(--light-gray);
            border: 1px solid var(--border-color);
        }
        
        /* Rejected legend color */
        .legend-color.rejected {
            background-color: var(--danger-color);
        }
        
        /* Blocked legend color */
        .legend-color.blocked {
            background-color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            <a href="entities_dashboard.php">
                <i class="fas fa-building"></i>
                <h1>New Entities Dashboard</h1>
            </a>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo substr($_SESSION['full_name'] ?? 'U', 0, 1); ?>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></strong><br>
                <small><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Client')); ?></small>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h2 class="page-title">New Submitted Entities</h2>
            <div class="page-subtitle">View and process newly submitted client entities</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Workflow Legend -->
        <div class="workflow-legend">
            <div class="legend-item">
                <div class="legend-color screening"></div>
                <span>Screening (Step 1)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color ind"></div>
                <span>ICID (Step 2) - Requires completed Screening</span>
            </div>
            <div class="legend-item">
                <div class="legend-color cdd"></div>
                <span>CDD (Step 3) - Requires completed ICID</span>
            </div>
            <div class="legend-item">
                <div class="legend-color locked"></div>
                <span>Locked - Previous step not completed</span>
            </div>
            <div class="legend-item">
                <div class="legend-color rejected"></div>
                <span>Rejected - Step was rejected/declined</span>
            </div>
            <div class="legend-item">
                <div class="legend-color blocked"></div>
                <span>Blocked - Previous step was rejected</span>
            </div>
        </div>
        
        <div class="actions-container">
            <a href="create_entity.php" class="create-entity-btn">
                <i class="fas fa-plus"></i> Create New Entity
            </a>
            
            <div class="search-filter">
                <input type="text" class="search-box" placeholder="Search entities..." id="searchInput">
            </div>
        </div>
        
        <div class="entities-table-container">
            <?php if (empty($entities)): ?>
                <div class="no-entities">
                    <i class="fas fa-folder-open"></i>
                    <h3>No New Submitted Entities Found</h3>
                    <p>There are currently no new entities in submitted status.</p>
                </div>
            <?php else: ?>
                <table class="entities-table" id="entitiesTable">
                    <thead>
                        <tr>
                            <th>Entity Name</th>
                            <th>Engagement Number</th>
                            <th>Application Type</th>
                            <th>Status</th>
                            <th>Client</th>
                            <th>Workflow Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entities as $entity): 
                            // Check workflow progress based on your database structure
                            $screening_completed = !empty($entity['screening_completed']) && $entity['screening_completed'] == 1;
                            $ind_completed = !empty($entity['ind_completed']) && $entity['ind_completed'] == 1;
                            $cdd_completed = !empty($entity['cdd_completed']) && $entity['cdd_completed'] == 1;
                            
                            // Get application status
                            $application_status = $entity['application_status'] ?? 'submitted';
                            
                            // Get IND confirmation status from independence_confirmations table
                            $ind_confirmation_status = $entity['ind_confirmation_status'] ?? 'pending';
                            
                            // ============================================
                            // REJECTION LOGIC - Based on actual database
                            // ============================================
                            
                            // SCREENING REJECTION:
                            // If screening_completed = 1 AND application_status = 'rejected' AND ind_completed = 0
                            // This means the entity was rejected during the screening phase
                            $screening_rejected = ($screening_completed && $application_status === 'rejected' && !$ind_completed);
                            
                            // IND/ICID REJECTION:
                            // Check independence_confirmations.confirmation_status for 'conflict_declared' or 'terminated'
                            // OR if screening passed but IND completed with application_status = 'rejected'
                            $ind_rejected = false;
                            if ($screening_completed && !$screening_rejected) {
                                // Check if IND was declined/rejected
                                $ind_rejected = in_array(strtolower($ind_confirmation_status), ['conflict', 'conflict_declared', 'terminated', 'declined', 'rejected']);
                                
                                // Alternative check: if IND completed and status is rejected but screening wasn't the cause
                                if (!$ind_rejected && $ind_completed && $application_status === 'rejected' && !$cdd_completed) {
                                    $ind_rejected = true;
                                }
                            }
                            
                            // CDD REJECTION:
                            // If IND completed successfully but CDD was rejected
                            $cdd_rejected = false;
                            if ($ind_completed && !$ind_rejected && !$screening_rejected) {
                                // If cdd_completed = 1 and status is rejected, CDD was rejected
                                // OR check cdd_verifications table if exists
                                if ($cdd_completed && $application_status === 'rejected') {
                                    $cdd_rejected = true;
                                }
                            }
                            
                            // ============================================
                            // BLOCKING LOGIC
                            // ============================================
                            
                            // If screening is rejected, IND and CDD are blocked
                            $ind_blocked_by_rejection = $screening_rejected;
                            
                            // If IND is rejected (or screening was rejected), CDD is blocked
                            $cdd_blocked_by_rejection = $ind_rejected || $screening_rejected;
                            
                            // ============================================
                            // DETERMINE CURRENT STEP
                            // ============================================
                            $current_step = 'screening';
                            $workflow_status_text = 'Screening In Progress';
                            
                            if ($screening_rejected) {
                                $current_step = 'screening_rejected';
                                $workflow_status_text = '<span style="color: var(--danger-color);">Screening Rejected</span>';
                            } elseif ($screening_completed && !$ind_completed && !$ind_rejected) {
                                $current_step = 'ind';
                                $workflow_status_text = 'ICID In Progress';
                            } elseif ($ind_rejected) {
                                $current_step = 'ind_rejected';
                                $workflow_status_text = '<span style="color: var(--danger-color);">ICID Declined</span>';
                            } elseif ($ind_completed && !$cdd_completed && !$cdd_rejected) {
                                $current_step = 'cdd';
                                $workflow_status_text = 'CDD In Progress';
                            } elseif ($cdd_rejected) {
                                $current_step = 'cdd_rejected';
                                $workflow_status_text = '<span style="color: var(--danger-color);">CDD Rejected</span>';
                            } elseif ($cdd_completed && !$cdd_rejected) {
                                $current_step = 'completed';
                                $workflow_status_text = '<span style="color: var(--success-color);">Completed</span>';
                            } elseif ($screening_completed) {
                                $workflow_status_text = 'Screening Completed';
                            }
                            
                            // ============================================
                            // WORKFLOW STEP CLASSES
                            // ============================================
                            
                            // Step 1 (Screening)
                            $step1_class = 'pending';
                            if ($screening_rejected) {
                                $step1_class = 'rejected';
                            } elseif ($screening_completed) {
                                $step1_class = 'completed';
                            } elseif ($current_step == 'screening') {
                                $step1_class = 'current';
                            }
                            
                            // Step 2 (IND/ICID)
                            $step2_class = 'pending';
                            if ($ind_blocked_by_rejection) {
                                $step2_class = 'blocked';
                            } elseif ($ind_rejected) {
                                $step2_class = 'rejected';
                            } elseif ($ind_completed) {
                                $step2_class = 'completed';
                            } elseif ($current_step == 'ind') {
                                $step2_class = 'current';
                            }
                            
                            // Step 3 (CDD)
                            $step3_class = 'pending';
                            if ($cdd_blocked_by_rejection) {
                                $step3_class = 'blocked';
                            } elseif ($cdd_rejected) {
                                $step3_class = 'rejected';
                            } elseif ($cdd_completed) {
                                $step3_class = 'completed';
                            } elseif ($current_step == 'cdd') {
                                $step3_class = 'current';
                            }
                            
                            // Workflow line classes
                            $line1_class = '';
                            if ($screening_rejected) {
                                $line1_class = 'rejected';
                            } elseif ($screening_completed) {
                                $line1_class = 'completed';
                            }
                            
                            $line2_class = '';
                            if ($ind_rejected || $ind_blocked_by_rejection) {
                                $line2_class = 'rejected';
                            } elseif ($ind_completed) {
                                $line2_class = 'completed';
                            }
                        ?>
                            <tr data-status="<?php echo htmlspecialchars($entity['application_status']); ?>" 
                                data-type="<?php echo htmlspecialchars($entity['application_type']); ?>">
                                <td>
                                    <div class="entity-name">
                                        <?php echo htmlspecialchars($entity['entity_name'] ?? 'Unnamed Entity'); ?>
                                    </div>
                                    <?php if ($entity['entity_name'] === null || $entity['entity_name'] === ''): ?>
                                        <small style="color: #999; font-style: italic;">No name provided</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="engagement-number">
                                        <?php echo htmlspecialchars($entity['engagement_number'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $type = $entity['application_type'] ?? 'new';
                                    $type_class = 'status-submitted'; // All are 'new' and 'submitted'
                                    ?>
                                    <span class="status-badge <?php echo $type_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst($type)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status = $entity['application_status'] ?? 'submitted';
                                    $status_class = 'status-' . $status;
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($entity['client_name'] ?? 'Unknown'); ?><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($entity['client_email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <div class="workflow-progress">
                                        <div class="workflow-step <?php echo $step1_class; ?>" title="Screening">
                                            <?php if ($screening_rejected): ?>
                                                <i class="fas fa-times" style="font-size: 10px;"></i>
                                            <?php elseif ($screening_completed): ?>
                                                <i class="fas fa-check" style="font-size: 10px;"></i>
                                            <?php else: ?>
                                                1
                                            <?php endif; ?>
                                        </div>
                                        <div class="workflow-line <?php echo $line1_class; ?>"></div>
                                        <div class="workflow-step <?php echo $step2_class; ?>" title="ICID">
                                            <?php if ($ind_rejected): ?>
                                                <i class="fas fa-times" style="font-size: 10px;"></i>
                                            <?php elseif ($ind_blocked_by_rejection): ?>
                                                <i class="fas fa-ban" style="font-size: 10px;"></i>
                                            <?php elseif ($ind_completed): ?>
                                                <i class="fas fa-check" style="font-size: 10px;"></i>
                                            <?php else: ?>
                                                2
                                            <?php endif; ?>
                                        </div>
                                        <div class="workflow-line <?php echo $line2_class; ?>"></div>
                                        <div class="workflow-step <?php echo $step3_class; ?>" title="CDD">
                                            <?php if ($cdd_rejected): ?>
                                                <i class="fas fa-times" style="font-size: 10px;"></i>
                                            <?php elseif ($cdd_blocked_by_rejection): ?>
                                                <i class="fas fa-ban" style="font-size: 10px;"></i>
                                            <?php elseif ($cdd_completed): ?>
                                                <i class="fas fa-check" style="font-size: 10px;"></i>
                                            <?php else: ?>
                                                3
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small style="display: block; text-align: center; margin-top: 5px; color: #666;">
                                        <?php echo $workflow_status_text; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="actions">
                                        <!-- Screening Button -->
                                        <?php 
                                        $screening_btn_class = 'screening';
                                        $screening_title = '';
                                        
                                        if ($screening_rejected) {
                                            $screening_btn_class = 'screening rejected-blocked';
                                            $screening_title = 'Screening was rejected - Entity not eligible';
                                        }
                                        ?>
                                        <a href="screening.php?entity_id=<?php echo $entity['id']; ?>" 
                                           class="action-btn <?php echo $screening_btn_class; ?>"
                                           <?php if ($screening_title): ?>title="<?php echo $screening_title; ?>"<?php endif; ?>>
                                            <i class="fas fa-search"></i> Screening
                                            <?php if ($screening_rejected): ?>
                                                <i class="fas fa-times-circle" style="margin-left: 3px;"></i>
                                            <?php endif; ?>
                                        </a>
                                        
                                        <!-- IND/ICID Button -->
                                        <?php 
                                        $ind_btn_class = 'ind';
                                        $ind_title = '';
                                        $ind_disabled = false;
                                        
                                        if ($screening_rejected) {
                                            $ind_btn_class = 'ind rejected-blocked';
                                            $ind_title = 'Blocked: Screening was rejected';
                                            $ind_disabled = true;
                                        } elseif ($ind_rejected) {
                                            $ind_btn_class = 'ind rejected-blocked';
                                            $ind_title = 'ICID was declined - Conflict of interest';
                                            $ind_disabled = true;
                                        } elseif (!$screening_completed) {
                                            $ind_btn_class = 'ind disabled';
                                            $ind_title = 'Complete Screening first';
                                            $ind_disabled = true;
                                        }
                                        ?>
                                        <a href="<?php echo $ind_disabled ? 'javascript:void(0);' : 'ind.php?entity_id=' . $entity['id']; ?>" 
                                           class="action-btn <?php echo $ind_btn_class; ?>"
                                           <?php if ($ind_title): ?>title="<?php echo $ind_title; ?>"<?php endif; ?>
                                           <?php if ($ind_disabled): ?>onclick="showBlockedMessage('<?php echo addslashes($ind_title); ?>'); return false;"<?php endif; ?>>
                                            <i class="fas fa-file-alt"></i> ICID
                                            <?php if ($ind_rejected): ?>
                                                <i class="fas fa-times-circle" style="margin-left: 3px;"></i>
                                            <?php elseif ($screening_rejected): ?>
                                                <i class="fas fa-ban" style="margin-left: 3px;"></i>
                                            <?php endif; ?>
                                        </a>
                                        
                                        <!-- CDD Button -->
                                        <?php 
                                        $cdd_btn_class = 'cdd';
                                        $cdd_title = '';
                                        $cdd_disabled = false;
                                        
                                        if ($screening_rejected) {
                                            $cdd_btn_class = 'cdd rejected-blocked';
                                            $cdd_title = 'Blocked: Screening was rejected';
                                            $cdd_disabled = true;
                                        } elseif ($ind_rejected) {
                                            $cdd_btn_class = 'cdd rejected-blocked';
                                            $cdd_title = 'Blocked: ICID was declined';
                                            $cdd_disabled = true;
                                        } elseif ($cdd_rejected) {
                                            $cdd_btn_class = 'cdd rejected-blocked';
                                            $cdd_title = 'CDD verification was rejected';
                                            $cdd_disabled = true;
                                        } elseif (!$ind_completed) {
                                            $cdd_btn_class = 'cdd disabled';
                                            $cdd_title = 'Complete ICID first';
                                            $cdd_disabled = true;
                                        }
                                        ?>
                                        <a href="<?php echo $cdd_disabled ? 'javascript:void(0);' : 'CDD.php?entity_id=' . $entity['id']; ?>" 
                                           class="action-btn <?php echo $cdd_btn_class; ?>"
                                           <?php if ($cdd_title): ?>title="<?php echo $cdd_title; ?>"<?php endif; ?>
                                           <?php if ($cdd_disabled): ?>onclick="showBlockedMessage('<?php echo addslashes($cdd_title); ?>'); return false;"<?php endif; ?>>
                                            <i class="fas fa-check-double"></i> CDD
                                            <?php if ($cdd_rejected): ?>
                                                <i class="fas fa-times-circle" style="margin-left: 3px;"></i>
                                            <?php elseif ($cdd_blocked_by_rejection): ?>
                                                <i class="fas fa-ban" style="margin-left: 3px;"></i>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Function to show blocked message
        function showBlockedMessage(message) {
            if (message) {
                alert(message);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#entitiesTable tbody tr');
            
            function filterEntities() {
                const searchTerm = searchInput.value.toLowerCase();
                
                tableRows.forEach(row => {
                    const entityName = row.querySelector('.entity-name').textContent.toLowerCase();
                    const engagementNumber = row.querySelector('.engagement-number').textContent.toLowerCase();
                    const clientName = row.cells[4] ? row.cells[4].textContent.toLowerCase() : '';
                    
                    const matchesSearch = entityName.includes(searchTerm) || 
                                          engagementNumber.includes(searchTerm) ||
                                          clientName.includes(searchTerm);
                    
                    if (matchesSearch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            searchInput.addEventListener('input', filterEntities);
            
            // Add keyboard shortcut for search
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    this.focus();
                }
            });
            
            // Focus search on Ctrl+F
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        });
    </script>
</body>
</html>