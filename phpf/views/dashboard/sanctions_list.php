<?php
// sanctions_list.php
require_once '../../config/db.php';
require_once 'sanctions_functions.php';
require_once "../../views/widgets/chat_widget.php";
displayChatWidget([
    'support_agent_name' => 'Muhasba Support',
    'company_name' => 'Muhasba.com',
    'primary_color' => '#4285F4',
    'is_online' => true
]);
// Handle CSV upload
$upload_result = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $upload_result = handleCSVUpload($_FILES['csv_file'], $pdo);
}

// Handle delete request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (deleteRecord($pdo, $id)) {
        $message = "Record deleted successfully";
    } else {
        $message = "Error deleting record";
    }
}

// Handle toggle status request
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    if (toggleStatus($pdo, $id)) {
        $message = "Status updated successfully";
    } else {
        $message = "Error updating status";
    }
}

// Prepare filters
$filters = [];
if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
if (isset($_GET['country'])) $filters['country'] = $_GET['country'];
if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
if (isset($_GET['type'])) $filters['type'] = $_GET['type'];

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Get sanctions with filters
$result = getSanctions($pdo, $filters, $page, 50);
$sanctions = $result['records'];
$total_records = $result['total'];
$total_pages = $result['pages'];

// Get countries for filter dropdown
$countries = $pdo->query("SELECT DISTINCT country FROM sanctions_list WHERE country IS NOT NULL AND country != '' ORDER BY country")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanctions List Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .container-main {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-top: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header-section {
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }
        .upload-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stats-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .stats-card h4 {
            color: #2c3e50;
            margin: 10px 0 5px;
            font-weight: 600;
        }
        .table-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .btn-primary-custom {
            background-color: #2c3e50;
            border-color: #2c3e50;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-primary-custom:hover {
            background-color: #1a252f;
            border-color: #1a252f;
        }
        .btn-outline-custom {
            border-color: #2c3e50;
            color: #2c3e50;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-outline-custom:hover {
            background-color: #2c3e50;
            color: white;
        }
        .badge-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-active {
            background-color: #28a745;
            color: white;
        }
        .badge-inactive {
            background-color: #dc3545;
            color: white;
        }
        .search-box {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .pagination .page-link {
            color: #2c3e50;
            border-color: #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background-color: #2c3e50;
            border-color: #2c3e50;
            color: white;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        .table td {
            border-color: #edf2f7;
            vertical-align: middle;
        }
        .type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
            background-color: #e9ecef;
            color: #495057;
        }
        .modal-header-custom {
            background-color: #2c3e50;
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .action-btn {
            padding: 5px 10px;
            font-size: 13px;
            margin: 1px;
            border-radius: 4px;
        }
        .footer-custom {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 15px 0;
            margin-top: 30px;
            color: #6c757d;
            font-size: 14px;
        }
        .form-control, .form-select {
            border-color: #ced4da;
            border-radius: 6px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2c3e50;
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        .alert {
            border-radius: 6px;
            border: 1px solid transparent;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container container-main">
        <!-- Header -->
        <div class="header-section">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="h3 mb-2"><i class="fas fa-gavel me-2"></i>Sanctions List Management</h1>
                    <p class="mb-0">Manage and update individuals and entities under Cabinet decisions</p>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($upload_result): ?>
        <div class="alert alert-<?php echo $upload_result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $upload_result['success'] ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $upload_result['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Upload Section -->
        <div class="upload-card">
            <h4 class="mb-3"><i class="fas fa-file-upload me-2"></i>Upload New CSV File</h4>
            <div class="alert alert-info mb-3">
                <h6 class="mb-2"><i class="fas fa-info-circle me-1"></i> Important Notes:</h6>
                <ul class="mb-0" style="font-size: 14px;">
                    <li>New file will replace all existing records in the database</li>
                    <li>Automatic backup is created before each import</li>
                    <li>File must be CSV format with UTF-8 encoding</li>
                    <li>Must follow official sanctions list file structure</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-file-csv"></i></span>
                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="form-text"><i class="fas fa-info-circle me-1"></i> Maximum file size: 10MB</div>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <button type="submit" class="btn btn-primary-custom w-100">
                        <i class="fas fa-sync-alt me-2"></i> Import Data
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h5><i class="fas fa-users text-primary"></i></h5>
                    <h4><?php echo number_format($total_records); ?></h4>
                    <p class="text-muted mb-0">Total Records</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5><i class="fas fa-user-check text-success"></i></h5>
                    <h4>
                        <?php 
                        $active = $pdo->query("SELECT COUNT(*) as count FROM sanctions_list WHERE status = 'active'")->fetch()['count'];
                        echo number_format($active);
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Active Records</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5><i class="fas fa-globe text-info"></i></h5>
                    <h4>
                        <?php 
                        $countries_count = $pdo->query("SELECT COUNT(DISTINCT country) as count FROM sanctions_list WHERE country IS NOT NULL AND country != ''")->fetch()['count'];
                        echo number_format($countries_count);
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Countries</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5><i class="fas fa-history text-secondary"></i></h5>
                    <h4><?php echo date('Y-m-d'); ?></h4>
                    <p class="text-muted mb-0">Last Update</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search by name or nationality..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="country">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                        <option value="<?php echo htmlspecialchars($country['country']); ?>" 
                            <?php echo (isset($_GET['country']) && $_GET['country'] == $country['country']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($country['country']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary-custom w-100"><i class="fas fa-filter me-2"></i> Apply Filter</button>
                </div>
            </form>
            <?php if (isset($_GET['search']) || isset($_GET['country']) || isset($_GET['status'])): ?>
            <div class="mt-3">
                <a href="sanctions_list.php" class="btn btn-outline-custom">
                    <i class="fas fa-times me-1"></i> Clear Filter
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Table Section -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fas fa-list me-2"></i> Sanctions List</h4>
                <div>
                    <span class="badge bg-secondary">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    <a href="export_csv.php" class="btn btn-outline-custom ms-2">
                        <i class="fas fa-download me-1"></i> Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name (English)</th>
                            <th>Name (Arabic)</th>
                            <th>Nationality</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Added Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sanctions)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3"></i>
                                    <h5>No records found</h5>
                                    <p>Upload a CSV file to import data</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($sanctions as $index => $row): ?>
                        <tr>
                            <td><?php echo (($page - 1) * 50) + $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['english_name']); ?></strong>
                                <?php if (!empty($row['list_reference'])): ?>
                                <br><small class="text-muted"><i class="fas fa-hashtag me-1"></i> <?php echo htmlspecialchars($row['list_reference']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['arabic_name'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['country']); ?></span>
                            </td>
                            <td>
                                <span class="type-badge"><?php echo htmlspecialchars($row['type']); ?></span>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'active'): ?>
                                <span class="badge-status badge-active">
                                    <i class="fas fa-check-circle me-1"></i> Active
                                </span>
                                <?php else: ?>
                                <span class="badge-status badge-inactive">
                                    <i class="fas fa-times-circle me-1"></i> Inactive
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($row['source'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <small><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></small>
                                <br><small class="text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary action-btn" 
                                            data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $row['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="?toggle_status=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning action-btn"
                                       onclick="return confirm('Change status of this record?')">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger action-btn"
                                       onclick="return confirm('Delete this record?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Details Modal -->
                        <div class="modal fade" id="detailsModal<?php echo $row['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header modal-header-custom">
                                        <h5 class="modal-title">Record Details</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="border-bottom pb-2 mb-3">Basic Information</h6>
                                                <p><strong><i class="fas fa-user me-2"></i> English Name:</strong><br>
                                                   <?php echo htmlspecialchars($row['english_name']); ?></p>
                                                <p><strong><i class="fas fa-user-tag me-2"></i> Arabic Name:</strong><br>
                                                   <?php echo htmlspecialchars($row['arabic_name'] ?? '-'); ?></p>
                                                <p><strong><i class="fas fa-flag me-2"></i> Nationality:</strong><br>
                                                   <?php echo htmlspecialchars($row['country']); ?></p>
                                                <p><strong><i class="fas fa-tag me-2"></i> Type:</strong><br>
                                                   <?php echo htmlspecialchars($row['type']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="border-bottom pb-2 mb-3">Sanction Details</h6>
                                                <p><strong><i class="fas fa-circle me-2"></i> Status:</strong><br>
                                                    <?php if ($row['status'] == 'active'): ?>
                                                    <span class="badge bg-danger">Active</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </p>
                                                <p><strong><i class="fas fa-database me-2"></i> Source:</strong><br>
                                                   <?php echo htmlspecialchars($row['source'] ?? '-'); ?></p>
                                                <p><strong><i class="fas fa-hashtag me-2"></i> Reference No:</strong><br>
                                                   <?php echo htmlspecialchars($row['list_reference'] ?? '-'); ?></p>
                                                <p><strong><i class="fas fa-calendar-alt me-2"></i> Effective Date:</strong><br>
                                                   <?php echo $row['effective_date'] ?? '-'; ?></p>
                                                <p><strong><i class="fas fa-calendar-times me-2"></i> Expiry Date:</strong><br>
                                                   <?php echo $row['expiry_date'] ?? '-'; ?></p>
                                            </div>
                                        </div>
                                        <?php if (!empty($row['reason'])): ?>
                                        <hr>
                                        <h6><i class="fas fa-exclamation-circle me-2"></i> Sanction Reason</h6>
                                        <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($row['reason'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($row['notes'])): ?>
                                        <hr>
                                        <h6><i class="fas fa-sticky-note me-2"></i> Notes</h6>
                                        <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($row['notes'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['country']) ? '&country=' . urlencode($_GET['country']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['country']) ? '&country=' . urlencode($_GET['country']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['country']) ? '&country=' . urlencode($_GET['country']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['country']) ? '&country=' . urlencode($_GET['country']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['country']) ? '&country=' . urlencode($_GET['country']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer-custom text-center">
            <div class="row">
                <div class="col-md-12">
                    <p class="mb-1"><i class="fas fa-shield-alt me-2"></i> Sanctions List Management System</p>
                    <small class="text-muted">
                        <i class="far fa-copyright me-1"></i> <?php echo date('Y'); ?> | 
                        Last Update: <?php echo date('Y-m-d H:i:s'); ?> | 
                        Records: <?php echo number_format($total_records); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // File upload preview
    document.getElementById('csv_file').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            let fileName = e.target.files[0].name;
            let fileSize = (e.target.files[0].size / (1024*1024)).toFixed(2);
            
            let alert = document.createElement('div');
            alert.className = 'alert alert-info alert-dismissible fade show mt-3';
            alert.innerHTML = `
                <i class="fas fa-file-csv me-2"></i> Selected file: <strong>${fileName}</strong> (${fileSize} MB)
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            let uploadCard = document.querySelector('.upload-card');
            let existingAlert = uploadCard.querySelector('.alert-info');
            if (existingAlert) {
                existingAlert.parentNode.insertBefore(alert, existingAlert.nextSibling);
            }
        }
    });

    // Auto-refresh after 5 minutes
    setTimeout(function() {
        let alert = document.createElement('div');
        alert.className = 'alert alert-warning alert-dismissible fade show position-fixed top-0 end-0 m-3';
        alert.style.zIndex = '9999';
        alert.style.maxWidth = '300px';
        alert.innerHTML = `
            <i class="fas fa-sync-alt me-2"></i> Page will refresh in 5 minutes
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alert);
        
        setTimeout(function() {
            location.reload();
        }, 300000);
    }, 10000);

    // Scroll to top functionality
    function scrollToTop() {
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    window.addEventListener('scroll', function() {
        let scrollBtn = document.getElementById('scrollToTop');
        if (!scrollBtn && window.scrollY > 300) {
            scrollBtn = document.createElement('button');
            scrollBtn.id = 'scrollToTop';
            scrollBtn.className = 'btn btn-primary-custom position-fixed bottom-3 end-3 rounded-circle';
            scrollBtn.style.width = '45px';
            scrollBtn.style.height = '45px';
            scrollBtn.style.fontSize = '14px';
            scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
            scrollBtn.onclick = scrollToTop;
            document.body.appendChild(scrollBtn);
        } else if (scrollBtn && window.scrollY <= 300) {
            scrollBtn.remove();
        }
    });
    </script>
</body>
</html>