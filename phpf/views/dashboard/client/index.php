<?php
session_start();
require_once '../../../config/db.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's entities
$stmt = $pdo->prepare("
    SELECT e.*, 
           es1.company_owner_name,
           es1.license_number,
           es5.engagement_number as step5_engagement_number,
           es5.terms_accepted,
           es5.accepted_at
    FROM entities e
    LEFT JOIN entity_step1 es1 ON e.id = es1.entity_id
    LEFT JOIN entity_step5 es5 ON e.id = es5.entity_id
    WHERE e.user_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$user_id]);
$entities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count entities by status
$status_counts = [
    'draft' => 0,
    'submitted' => 0,
    'under_review' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($entities as $entity) {
    if (isset($status_counts[$entity['application_status']])) {
        $status_counts[$entity['application_status']]++;
    }
}

// Get recent documents
$stmt = $pdo->prepare("
    SELECT 
        e.id as entity_id,
        e.entity_name,
        e.application_status,
        es1.eid_passports,
        es1.trade_license,
        es1.authorization_letter,
        es3.previous_auditor_files,
        aam.id as memo_id,
        aam.created_at as memo_date,
        ic.id as independence_id,
        ic.confirmation_status,
        ic.confirmation_text,
        ic.created_at as independence_date
    FROM entities e
    LEFT JOIN entity_step1 es1 ON e.id = es1.entity_id
    LEFT JOIN entity_step3 es3 ON e.id = es3.entity_id
    LEFT JOIN audit_acceptance_memorandum aam ON e.id = aam.entity_id
    LEFT JOIN independence_confirmations ic ON e.id = ic.entity_id
    WHERE e.user_id = ?
    AND e.application_status IN ('submitted', 'under_review', 'approved')
    ORDER BY e.updated_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for new audits needed
$current_year = date('Y');
$previous_year = $current_year - 1;

$stmt = $pdo->prepare("
    SELECT e.* 
    FROM entities e
    WHERE e.user_id = ? 
    AND e.application_status = 'approved'
    AND YEAR(e.created_at) = ?
    ORDER BY e.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id, $previous_year]);
$last_year_entity = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Muhasba</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #0b2e59;
            --accent-color: #d17a0b;
            --light-bg: #eef1f6;
            --border-color: #e0e0e0;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: var(--light-bg); 
            color: #333; 
            line-height: 1.6;
            min-height: 100vh;
            padding: 0;
        }
        
        /* Top Header */
        .top-header {
            background: #ffffff;
            padding: 15px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .app-header {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .logout-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .logout-btn:hover {
            background: #f5f5f5;
            color: #333;
        }
        
        /* Main Container */
        .main-container {
            display: flex;
            min-height: 100vh;
            padding-top: 70px;
        }
        
        /* Left Sidebar */
        .sidebar {
            width: 280px;
            background: #ffffff;
            padding: 40px 30px;
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            overflow-y: auto;
        }
        
        .sidebar-title {
            font-size: 22px;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .sidebar-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 40px;
            line-height: 1.5;
        }
        
        /* Navigation Menu */
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .nav-link:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }
        
        .nav-link.active {
            background: var(--secondary-color);
            color: white;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--accent-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            background: var(--light-bg);
            min-height: calc(100vh - 70px);
        }
        
        /* Content Header */
        .content-header {
            margin-bottom: 40px;
        }
        
        .content-header h1 {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .content-header .subheading {
            font-size: 16px;
            color: #666;
        }
        
        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--secondary-color);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card.draft { border-left-color: #6c757d; }
        .stat-card.submitted { border-left-color: #17a2b8; }
        .stat-card.under_review { border-left-color: var(--warning-color); }
        .stat-card.approved { border-left-color: var(--success-color); }
        .stat-card.rejected { border-left-color: var(--danger-color); }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: var(--secondary-color);
        }
        
        .action-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }
        
        .action-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .action-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .action-badge {
            display: inline-block;
            padding: 5px 12px;
            background: var(--accent-color);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Recent Entities */
        .recent-entities {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title a {
            font-size: 14px;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .entity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .entity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .entity-item:hover {
            background: #f5f5f5;
            transform: translateX(5px);
        }
        
        .entity-item.draft { border-left-color: #6c757d; }
        .entity-item.submitted { border-left-color: #17a2b8; }
        .entity-item.under_review { border-left-color: var(--warning-color); }
        .entity-item.approved { border-left-color: var(--success-color); }
        .entity-item.rejected { border-left-color: var(--danger-color); }
        
        .entity-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .entity-meta {
            font-size: 14px;
            color: #666;
        }
        
        .entity-actions {
            display: flex;
            gap: 10px;
        }
        
        .entity-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-submitted { background: #17a2b8; color: white; }
        .status-under_review { background: var(--warning-color); color: white; }
        .status-approved { background: var(--success-color); color: white; }
        .status-rejected { background: var(--danger-color); color: white; }
        
        /* Recent Documents */
        .recent-documents {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .document-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .document-icon {
            font-size: 24px;
            color: var(--secondary-color);
            margin-right: 15px;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-info h5 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .document-meta {
            font-size: 13px;
            color: #666;
            display: flex;
            gap: 15px;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a244a;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: white;
            color: var(--secondary-color);
            border: 1px solid var(--secondary-color);
        }
        
        .btn-outline:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #888;
            margin-bottom: 30px;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--accent-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            .main-content {
                margin-left: 240px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -240px;
                transition: left 0.3s ease;
                z-index: 1001;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1002;
                background: white;
                border: none;
                padding: 10px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                cursor: pointer;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .content-header h1 {
                font-size: 24px;
            }
            
            .quick-stats,
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .top-header {
                padding: 15px 20px;
            }
            
            .user-info span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="top-header">
        <div class="app-header">Muhasba</div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../../../auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Menu Toggle for Mobile -->
    <button class="menu-toggle" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Container -->
    <div class="main-container">
        <!-- Left Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-title">Dashboard</div>
            <div class="sidebar-subtitle">
                Welcome back! Manage your audit engagements and documents.
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="documents.php" class="nav-link">
                        <i class="fas fa-folder"></i>
                        Documents Center
                        <?php if (count($documents) > 0): ?>
                        <span class="nav-badge"><?php echo count($documents); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="entities.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        My Engagements
                        <?php if (count($entities) > 0): ?>
                        <span class="nav-badge"><?php echo count($entities); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="new_entity.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        New Audit
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Content Header -->
            <div class="content-header">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <div class="subheading">
                    Here's your audit engagement overview
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card draft">
                    <div class="stat-number"><?php echo $status_counts['draft']; ?></div>
                    <div class="stat-label">Draft</div>
                </div>
                <div class="stat-card submitted">
                    <div class="stat-number"><?php echo $status_counts['submitted']; ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-card under_review">
                    <div class="stat-number"><?php echo $status_counts['under_review']; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-number"><?php echo $status_counts['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($entities); ?></div>
                    <div class="stat-label">Total Engagements</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="new_entity.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-title">New Audit Engagement</div>
                    <div class="action-desc">
                        Start a completely new audit process for your business
                    </div>
                    <div class="action-badge">Recommended</div>
                </a>
                
                <?php if ($last_year_entity): ?>
                <a href="new_audit.php?entity_id=<?php echo $last_year_entity['id']; ?>" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-redo-alt"></i>
                    </div>
                    <div class="action-title">Previous Year Audit</div>
                    <div class="action-desc">
                        Create audit for <?php echo $current_year; ?> based on last approved entity
                    </div>
                    <div class="action-badge">Quick Start</div>
                </a>
                <?php else: ?>
                <a href="new_entity.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="action-title">Start First Audit</div>
                    <div class="action-desc">
                        Begin your first audit engagement with Muhasba
                    </div>
                    <div class="action-badge">Get Started</div>
                </a>
                <?php endif; ?>
                
                <a href="documents.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="action-title">Documents Center</div>
                    <div class="action-desc">
                        Access all your audit documents and reports
                    </div>
                    <div class="action-badge">View All</div>
                </a>
            </div>
            
            <!-- Recent Entities -->
            <div class="recent-entities">
                <div class="section-title">
                    Recent Audit Engagements
                    <?php if (count($entities) > 0): ?>
                    <a href="entities.php">View All</a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($entities)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>No Audit Engagements Yet</h3>
                        <p>Start your first audit engagement to get started</p>
                        <a href="new_entity.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Create New Engagement
                        </a>
                    </div>
                <?php else: ?>
                    <div class="entity-list">
                        <?php foreach (array_slice($entities, 0, 5) as $entity): ?>
                        <div class="entity-item <?php echo $entity['application_status']; ?>">
                            <div class="entity-info">
                                <h4><?php echo htmlspecialchars($entity['entity_name'] ?: 'Unnamed Entity'); ?></h4>
                                <div class="entity-meta">
                                    <span>Created: <?php echo date('M d, Y', strtotime($entity['created_at'])); ?></span>
                                    <?php if ($entity['license_number']): ?>
                                    <span>• License: <?php echo htmlspecialchars($entity['license_number']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="entity-actions">
                                <span class="entity-status status-<?php echo $entity['application_status']; ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $entity['application_status'])); ?>
                                </span>
                                <a href="view_entity.php?id=<?php echo $entity['id']; ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($entity['application_status'] === 'draft'): ?>
                                <a href="edit_entity.php?step=1&id=<?php echo $entity['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Continue
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($entities) > 5): ?>
                    <div class="text-center mt-4">
                        <a href="entities.php" class="btn btn-outline">
                            View All Engagements (<?php echo count($entities); ?>)
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Recent Documents -->
            <div class="recent-documents">
                <div class="section-title">
                    Recent Documents
                    <?php if (count($documents) > 0): ?>
                    <a href="documents.php">View All</a>
                    <?php endif; ?>
                </div>
                
                <?php 
                $doc_count = 0;
                $has_documents = false;
                
                // Check if any documents exist
                foreach ($documents as $doc) {
                    $has_docs = false;
                    
                    if ($doc['eid_passports']) {
                        $docs = json_decode($doc['eid_passports'], true);
                        if (is_array($docs) && count($docs) > 0) $has_docs = true;
                    }
                    
                    if ($doc['trade_license']) {
                        $docs = json_decode($doc['trade_license'], true);
                        if (is_array($docs) && count($docs) > 0) $has_docs = true;
                    }
                    
                    if ($doc['authorization_letter']) {
                        $docs = json_decode($doc['authorization_letter'], true);
                        if (is_array($docs) && count($docs) > 0) $has_docs = true;
                    }
                    
                    if ($doc['previous_auditor_files']) {
                        $docs = json_decode($doc['previous_auditor_files'], true);
                        if (is_array($docs) && count($docs) > 0) $has_docs = true;
                    }
                    
                    if ($doc['memo_id'] || $doc['independence_id']) {
                        $has_docs = true;
                    }
                    
                    if ($has_docs) {
                        $has_documents = true;
                        break;
                    }
                }
                ?>
                
                <?php if (!$has_documents): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>No Documents Available</h3>
                        <p>Documents will appear here once your engagement is submitted and approved</p>
                        <a href="new_entity.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Start New Audit
                        </a>
                    </div>
                <?php else: ?>
                    <div class="document-list">
                        <?php 
                        $displayed_docs = 0;
                        foreach ($documents as $doc): 
                            if ($displayed_docs >= 3) break;
                            
                            $doc_types = [];
                            $has_display = false;
                            
                            if ($doc['memo_id']) {
                                $doc_types[] = 'Acceptance Memorandum';
                                $has_display = true;
                            }
                            
                            if ($doc['independence_id'] && $doc['confirmation_status'] === 'confirmed') {
                                $doc_types[] = 'Independence Confirmation';
                                $has_display = true;
                            }
                            
                            if ($doc['eid_passports']) {
                                $docs = json_decode($doc['eid_passports'], true);
                                if (is_array($docs) && count($docs) > 0) {
                                    $doc_types[] = 'EID/Passport';
                                    $has_display = true;
                                }
                            }
                            
                            if ($has_display):
                                $displayed_docs++;
                        ?>
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h5><?php echo htmlspecialchars($doc['entity_name'] ?: 'Unnamed Entity'); ?></h5>
                                <div class="document-meta">
                                    <span><?php echo implode(', ', $doc_types); ?></span>
                                    <span>Status: 
                                        <span class="entity-status status-<?php echo $doc['application_status']; ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $doc['application_status'])); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div class="document-actions">
                                <a href="view_documents.php?entity_id=<?php echo $doc['entity_id']; ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    
                    <?php if ($displayed_docs > 0): ?>
                    <div class="text-center mt-4">
                        <a href="documents.php" class="btn btn-outline">
                            <i class="fas fa-folder-open me-2"></i>Go to Documents Center
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Stats Footer -->
            <div style="margin-top: 40px; padding: 20px; background: white; border-radius: 10px; text-align: center;">
                <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">
                            <?php 
                            $total_docs = 0;
                            foreach ($documents as $doc) {
                                if ($doc['eid_passports']) {
                                    $docs = json_decode($doc['eid_passports'], true);
                                    if (is_array($docs)) $total_docs += count($docs);
                                }
                                if ($doc['trade_license']) {
                                    $docs = json_decode($doc['trade_license'], true);
                                    if (is_array($docs)) $total_docs += count($docs);
                                }
                                if ($doc['authorization_letter']) {
                                    $docs = json_decode($doc['authorization_letter'], true);
                                    if (is_array($docs)) $total_docs += count($docs);
                                }
                                if ($doc['previous_auditor_files']) {
                                    $docs = json_decode($doc['previous_auditor_files'], true);
                                    if (is_array($docs)) $total_docs += count($docs);
                                }
                                if ($doc['memo_id']) $total_docs++;
                                if ($doc['independence_id']) $total_docs++;
                            }
                            echo $total_docs;
                            ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">Total Documents</div>
                    </div>
                    
                    <div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">
                            <?php 
                            $approved_entities = array_filter($entities, function($e) {
                                return $e['application_status'] === 'approved';
                            });
                            echo count($approved_entities);
                            ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">Approved Engagements</div>
                    </div>
                    
                    <div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">
                            <?php 
                            $pending_entities = array_filter($entities, function($e) {
                                return in_array($e['application_status'], ['draft', 'under_review']);
                            });
                            echo count($pending_entities);
                            ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">Pending Actions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        // Show menu toggle on mobile
        if (window.innerWidth <= 992) {
            menuToggle.style.display = 'block';
        }
        
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
        
        // Auto-refresh for updates
        setInterval(() => {
            fetch('check_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.updates) {
                        // Show subtle notification
                        const notification = document.createElement('div');
                        notification.style.cssText = `
                            position: fixed;
                            bottom: 20px;
                            right: 20px;
                            background: var(--secondary-color);
                            color: white;
                            padding: 15px 20px;
                            border-radius: 8px;
                            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                            z-index: 1000;
                            cursor: pointer;
                            animation: slideIn 0.3s ease;
                        `;
                        notification.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-sync-alt"></i>
                                <span>New updates available. Click to refresh.</span>
                            </div>
                        `;
                        notification.addEventListener('click', () => {
                            location.reload();
                        });
                        document.body.appendChild(notification);
                        
                        // Auto remove after 5 seconds
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.style.animation = 'slideOut 0.3s ease';
                                setTimeout(() => notification.remove(), 300);
                            }
                        }, 5000);
                    }
                })
                .catch(error => console.error('Error checking updates:', error));
        }, 30000);
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Update menu toggle on resize
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 992) {
                menuToggle.style.display = 'block';
            } else {
                menuToggle.style.display = 'none';
                sidebar.classList.remove('active');
            }
        });
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Check for notifications on load
        window.addEventListener('load', () => {
            // Check for pending entities
            const pendingCount = <?php 
                $pending_entities = array_filter($entities, function($e) {
                    return in_array($e['application_status'], ['draft', 'under_review']);
                });
                echo count($pending_entities);
            ?>;
            
            if (pendingCount > 0) {
                // Show welcome notification
                setTimeout(() => {
                    const welcomeMsg = document.createElement('div');
                    welcomeMsg.style.cssText = `
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        background: var(--accent-color);
                        color: white;
                        padding: 15px 20px;
                        border-radius: 8px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                        z-index: 1000;
                        cursor: pointer;
                        animation: slideIn 0.3s ease;
                    `;
                    welcomeMsg.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-bell"></i>
                            <span>You have ${pendingCount} engagement(s) requiring attention</span>
                        </div>
                    `;
                    welcomeMsg.addEventListener('click', () => {
                        window.location.href = 'entities.php?filter=pending';
                    });
                    document.body.appendChild(welcomeMsg);
                    
                    // Auto remove after 10 seconds
                    setTimeout(() => {
                        if (welcomeMsg.parentNode) {
                            welcomeMsg.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => welcomeMsg.remove(), 300);
                        }
                    }, 10000);
                }, 2000);
            }
        });
    </script>
</body>
</html>