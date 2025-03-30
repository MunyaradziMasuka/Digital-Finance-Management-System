<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';

// Get search parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query
$query = "
    SELECT aw.*, 
           d.document_name, 
           i.invoice_number,
           u.username as initiator,
           wt.name as workflow_type_name
    FROM approval_workflows aw
    LEFT JOIN documents d ON aw.document_id = d.id
    LEFT JOIN invoices i ON aw.invoice_id = i.id
    LEFT JOIN users u ON aw.initiated_by = u.id
    LEFT JOIN workflow_types wt ON aw.workflow_type_id = wt.id
    WHERE 1=1
";

// Add filters
$params = [];
$param_types = "";

if (!empty($status_filter)) {
    $query .= " AND aw.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($search_term)) {
    $query .= " AND (aw.title LIKE ? OR aw.description LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($date_from)) {
    $query .= " AND aw.initiated_at >= ?";
    $params[] = $date_from . " 00:00:00";
    $param_types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND aw.initiated_at <= ?";
    $params[] = $date_to . " 23:59:59";
    $param_types .= "s";
}

// Add sorting
$query .= " ORDER BY aw.initiated_at DESC";

// Prepare statement
$stmt = $conn->prepare($query);

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$workflows = $stmt->get_result();

// Counters for stats
$stmt = $conn->prepare("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                        FROM approval_workflows");
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Approvals - City of Harare Financial Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            background-color: #1a3a68;
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 20px;
        }
        .sidebar a {
            color: white;
            padding: 10px 15px;
            display: block;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #2c4f8c;
        }
        .sidebar i {
            margin-right: 10px;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .logo {
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-approved {
            background-color: #28a745;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0;
        }
        .stat-card p {
            margin-bottom: 0;
            font-size: 1rem;
        }
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .stat-total {
            background-color: #3498db;
        }
        .stat-pending {
            background-color: #f39c12;
        }
        .stat-approved {
            background-color: #2ecc71;
        }
        .stat-rejected {
            background-color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="logo">
                    <h4>City of Harare</h4>
                    <small>Financial System</small>
                </div>
                <div class="mt-4">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <small>Role: <?php echo htmlspecialchars($_SESSION['role']); ?></small>
                </div>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="invoices.php">
                            <i class="fas fa-file-invoice"></i> Invoices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="approvals.php">
                            <i class="fas fa-check-circle"></i> Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_documents.php">
                            <i class="fas fa-folder-open"></i> Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-5">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 content">
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tasks"></i> Workflow Approvals</h2>
                    <div>
                        <a href="create_workflow_approval.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create New Workflow
                        </a>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card stat-total">
                            <i class="fas fa-clipboard-list"></i>
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Workflows</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-pending">
                            <i class="fas fa-hourglass-half"></i>
                            <h3><?php echo $stats['pending']; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-approved">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $stats['approved']; ?></h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-rejected">
                            <i class="fas fa-times-circle"></i>
                            <h3><?php echo $stats['rejected']; ?></h3>
                            <p>Rejected</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-search"></i> Search & Filter</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date_from">Date From</label>
                                        <input type="text" class="form-control date-picker" id="date_from" name="date_from" value="<?php echo $date_from; ?>" placeholder="YYYY-MM-DD">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date_to">Date To</label>
                                        <input type="text" class="form-control date-picker" id="date_to" name="date_to" value="<?php echo $date_to; ?>" placeholder="YYYY-MM-DD">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="search">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search title, description...">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="view_approvals.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-list"></i> Workflow Approvals List</h4>
                    </div>
                    <div class="card-body">
                        <?php if($workflows->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Requested By</th>
                                            <th>Date Requested</th>
                                            <th>Status</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($workflow = $workflows->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $workflow['id']; ?></td>
                                                <td><?php echo htmlspecialchars($workflow['title']); ?></td>
                                                <td><?php echo htmlspecialchars($workflow['workflow_type_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($workflow['initiator']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($workflow['initiated_at'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $workflow['status']; ?>">
                                                        <?php echo ucfirst($workflow['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    echo $workflow['completed_at'] 
                                                        ? date('M d, Y', strtotime($workflow['completed_at'])) 
                                                        : 'N/A'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewModal<?php echo $workflow['id']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    
                                                    <?php if($workflow['status'] === 'pending' && ($_SESSION['role'] === 'admin' || $workflow['initiated_by'] == $_SESSION['user_id'])): ?>
                                                        <a href="edit_workflow.php?id=<?php echo $workflow['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- View Modal -->
                                                    <div class="modal fade" id="viewModal<?php echo $workflow['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-info text-white">
                                                                    <h5 class="modal-title" id="viewModalLabel">Workflow Details</h5>
                                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <h6 class="font-weight-bold">Basic Information</h6>
                                                                            <p><strong>ID:</strong> <?php echo $workflow['id']; ?></p>
                                                                            <p><strong>Title:</strong> <?php echo htmlspecialchars($workflow['title']); ?></p>
                                                                            <p><strong>Type:</strong> <?php echo htmlspecialchars($workflow['workflow_type_name'] ?? 'N/A'); ?></p>
                                                                            <p><strong>Status:</strong> 
                                                                                <span class="badge badge-<?php echo $workflow['status']; ?>">
                                                                                    <?php echo ucfirst($workflow['status']); ?>
                                                                                </span>
                                                                            </p>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <h6 class="font-weight-bold">Timeline</h6>
                                                                            <p><strong>Requested By:</strong> <?php echo htmlspecialchars($workflow['initiator']); ?></p>
                                                                            <p><strong>Date Requested:</strong> <?php echo date('M d, Y H:i', strtotime($workflow['initiated_at'])); ?></p>
                                                                            <p><strong>Date Completed:</strong> 
                                                                                <?php echo $workflow['completed_at'] 
                                                                                    ? date('M d, Y H:i', strtotime($workflow['completed_at'])) 
                                                                                    : 'Not yet completed'; ?>
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="row mt-3">
                                                                        <div class="col-md-12">
                                                                            <h6 class="font-weight-bold">Related Items</h6>
                                                                            <p><strong>Related Document:</strong> 
                                                                                <?php echo $workflow['document_name'] 
                                                                                    ? htmlspecialchars($workflow['document_name']) 
                                                                                    : 'No document attached'; ?>
                                                                            </p>
                                                                            <p><strong>Related Invoice:</strong> 
                                                                                <?php echo $workflow['invoice_number'] 
                                                                                    ? htmlspecialchars($workflow['invoice_number']) 
                                                                                    : 'No invoice attached'; ?>
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="row mt-3">
                                                                        <div class="col-md-12">
                                                                            <div class="card bg-light">
                                                                                <div class="card-header">
                                                                                    <h6 class="mb-0">Description</h6>
                                                                                </div>
                                                                                <div class="card-body">
                                                                                    <p><?php echo nl2br(htmlspecialchars($workflow['description'])); ?></p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <?php if($workflow['status'] !== 'pending' && !empty($workflow['admin_comments'])): ?>
                                                                    <div class="row mt-3">
                                                                        <div class="col-md-12">
                                                                            <div class="card bg-light">
                                                                                <div class="card-header">
                                                                                    <h6 class="mb-0">Admin Comments</h6>
                                                                                </div>
                                                                                <div class="card-body">
                                                                                    <p><?php echo nl2br(htmlspecialchars($workflow['admin_comments'])); ?></p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <?php if($workflow['status'] === 'pending' && $_SESSION['role'] === 'admin'): ?>
                                                                        <a href="workflow_approvals.php" class="btn btn-primary">
                                                                            <i class="fas fa-check-circle"></i> Review This Workflow
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No workflows found matching your criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(document).ready(function() {
            // Initialize date pickers
            $(".date-picker").flatpickr({
                dateFormat: "Y-m-d",
                allowInput: true
            });
        });
    </script>
</body>
</html>