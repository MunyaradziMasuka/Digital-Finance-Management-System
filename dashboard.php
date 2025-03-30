<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Get document statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM documents");
$stmt->execute();
$result = $stmt->get_result();
$documentCount = $result->fetch_assoc()['total'];

// Get invoice statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM invoices WHERE status = 'pending'");
$stmt->execute();
$result = $stmt->get_result();
$pendingInvoiceCount = $result->fetch_assoc()['total'];

// Get approval statistics with more detailed information
$stmt = $conn->prepare("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                        FROM approval_workflows");
$stmt->execute();
$approvalStats = $stmt->get_result()->fetch_assoc();
$pendingApprovalCount = $approvalStats['pending'];

// Get recent activities using a more comprehensive approach to include workflows
$stmt = $conn->prepare("
    (SELECT 'document' as type, id, document_name as name, document_type as item_type, uploaded_at as date 
     FROM documents ORDER BY uploaded_at DESC LIMIT 2)
    UNION
    (SELECT 'invoice' as type, id, invoice_number as name, CONCAT('$', amount) as item_type, created_at as date 
     FROM invoices ORDER BY created_at DESC LIMIT 2)
    UNION
    (SELECT 'workflow' as type, id, title as name, status as item_type, initiated_at as date
     FROM approval_workflows ORDER BY initiated_at DESC LIMIT 2)
    ORDER BY date DESC LIMIT 5
");
$stmt->execute();
$recentActivities = $stmt->get_result();

// Get revenue statistics
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM invoices WHERE status = 'paid'");
$stmt->execute();
$result = $stmt->get_result();
$totalRevenue = $result->fetch_assoc()['total'];
$totalRevenue = $totalRevenue ? $totalRevenue : 0;

// Get percentage changes
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM invoices WHERE status = 'pending' AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE_SUB(NOW(), INTERVAL 1 MONTH)) as prev_month_invoices,
        (SELECT COUNT(*) FROM approval_workflows WHERE status = 'pending' AND initiated_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE_SUB(NOW(), INTERVAL 1 MONTH)) as prev_month_approvals,
        (SELECT COUNT(*) FROM documents WHERE uploaded_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE_SUB(NOW(), INTERVAL 1 MONTH)) as prev_month_documents,
        (SELECT SUM(amount) FROM invoices WHERE status = 'paid' AND paid_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE_SUB(NOW(), INTERVAL 1 MONTH)) as prev_month_revenue
");
$stmt->execute();
$prevMonthStats = $stmt->get_result()->fetch_assoc();

// Calculate percentage changes
$invoiceChange = $prevMonthStats['prev_month_invoices'] > 0 ? 
    round((($pendingInvoiceCount - $prevMonthStats['prev_month_invoices']) / $prevMonthStats['prev_month_invoices']) * 100) : 0;

$approvalChange = $prevMonthStats['prev_month_approvals'] > 0 ? 
    round((($pendingApprovalCount - $prevMonthStats['prev_month_approvals']) / $prevMonthStats['prev_month_approvals']) * 100) : 0;

$documentChange = $prevMonthStats['prev_month_documents'] > 0 ? 
    round((($documentCount - $prevMonthStats['prev_month_documents']) / $prevMonthStats['prev_month_documents']) * 100) : 0;

$revenueChange = $prevMonthStats['prev_month_revenue'] > 0 ? 
    round((($totalRevenue - $prevMonthStats['prev_month_revenue']) / $prevMonthStats['prev_month_revenue']) * 100) : 0;

// Get workflow types for the quick actions
$stmt = $conn->prepare("SELECT id, name FROM workflow_types LIMIT 3");
$stmt->execute();
$workflowTypes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - City of Harare Financial Management System</title>
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
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            border-radius: 10px 10px 0 0;
        }
        .card-stats {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        .logo {
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 150px;
        }
        .welcome-header {
            background-color: #f8f9fa;
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
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
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }
        .document-icon {
            background-color: #3498db;
        }
        .invoice-icon {
            background-color: #f39c12;
        }
        .workflow-icon {
            background-color: #9b59b6;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="logo">
                    <!-- You can add your logo here -->
                    <h4>City of Harare</h4>
                    <small>Financial System</small>
                </div>
                <div class="mt-4">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <small>Role: <?php echo htmlspecialchars($_SESSION['role']); ?></small>
                </div>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="invoices.php">
                            <i class="fas fa-file-invoice"></i> Invoices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="approvals.php">
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
                <div class="welcome-header">
                    <h2>Welcome to the City of Harare Financial Management System</h2>
                    <p>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>! You are logged in as <?php echo htmlspecialchars($_SESSION['role']); ?>.</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card stat-pending">
                            <i class="fas fa-file-invoice"></i>
                            <h3><?php echo $pendingInvoiceCount; ?></h3>
                            <p>Pending Invoices</p>
                            <div class="mt-2">
                                <span class="badge <?php echo $invoiceChange >= 0 ? 'badge-light' : 'badge-danger'; ?>">
                                    <i class="fas fa-arrow-<?php echo $invoiceChange >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($invoiceChange); ?>% from last month
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card stat-total">
                            <i class="fas fa-tasks"></i>
                            <h3><?php echo $approvalStats['total']; ?></h3>
                            <p>Total Workflows</p>
                            <div class="mt-2 d-flex justify-content-center">
                                <span class="badge badge-light mr-1">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $approvalStats['pending']; ?> Pending
                                </span>
                                <span class="badge badge-success mr-1">
                                    <i class="fas fa-check"></i> <?php echo $approvalStats['approved']; ?>
                                </span>
                                <span class="badge badge-danger">
                                    <i class="fas fa-times"></i> <?php echo $approvalStats['rejected']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card stat-approved">
                            <i class="fas fa-folder-open"></i>
                            <h3><?php echo $documentCount; ?></h3>
                            <p>Documents Processed</p>
                            <div class="mt-2">
                                <span class="badge <?php echo $documentChange >= 0 ? 'badge-light' : 'badge-danger'; ?>">
                                    <i class="fas fa-arrow-<?php echo $documentChange >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($documentChange); ?>% from last month
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card" style="background-color: #16a085;">
                            <i class="fas fa-dollar-sign"></i>
                            <h3>$<?php echo number_format($totalRevenue, 0); ?></h3>
                            <p>Total Revenue</p>
                            <div class="mt-2">
                                <span class="badge <?php echo $revenueChange >= 0 ? 'badge-light' : 'badge-danger'; ?>">
                                    <i class="fas fa-arrow-<?php echo $revenueChange >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($revenueChange); ?>% from last month
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities and Quick Actions -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php if($recentActivities->num_rows > 0): ?>
                                        <?php while($activity = $recentActivities->fetch_assoc()): ?>
                                            <div class="list-group-item list-group-item-action flex-column align-items-start">
                                                <div class="d-flex w-100">
                                                    <div class="activity-icon <?php echo $activity['type']; ?>-icon">
                                                        <?php if($activity['type'] == 'document'): ?>
                                                            <i class="fas fa-file-alt"></i>
                                                        <?php elseif($activity['type'] == 'invoice'): ?>
                                                            <i class="fas fa-file-invoice"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-tasks"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h6 class="mb-1">
                                                                <?php if($activity['type'] == 'document'): ?>
                                                                    Document: <?php echo htmlspecialchars($activity['name']); ?>
                                                                <?php elseif($activity['type'] == 'invoice'): ?>
                                                                    Invoice: <?php echo htmlspecialchars($activity['name']); ?>
                                                                <?php else: ?>
                                                                    Workflow: <?php echo htmlspecialchars($activity['name']); ?>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <small><?php echo date('M d, H:i', strtotime($activity['date'])); ?></small>
                                                        </div>
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <p class="mb-1">
                                                                <?php if($activity['type'] == 'document'): ?>
                                                                    <small class="text-muted">Type: <?php echo htmlspecialchars($activity['item_type']); ?></small>
                                                                <?php elseif($activity['type'] == 'invoice'): ?>
                                                                    <small class="text-muted">Amount: <?php echo htmlspecialchars($activity['item_type']); ?></small>
                                                                <?php else: ?>
                                                                    <small class="text-muted">Status: 
                                                                        <span class="badge badge-<?php echo $activity['item_type']; ?>">
                                                                            <?php echo ucfirst($activity['item_type']); ?>
                                                                        </span>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </p>
                                                            <a href="<?php echo $activity['type']; ?>s.php?id=<?php echo $activity['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">No recent activities to display.</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="activity_log.php" class="btn btn-outline-primary">View All Activities</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <a href="upload_document.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="bg-primary text-white rounded p-2 mr-3">
                                                <i class="fas fa-upload"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Upload Document</h6>
                                                <small>Add new documents to the system</small>
                                            </div>
                                        </div>
                                    </a>
                                    <a href="create_invoice.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="bg-warning text-white rounded p-2 mr-3">
                                                <i class="fas fa-file-invoice"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Create New Invoice</h6>
                                                <small>Generate a new invoice for services</small>
                                            </div>
                                        </div>
                                    </a>
                                    <a href="create_workflow_approval.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="bg-info text-white rounded p-2 mr-3">
                                                <i class="fas fa-tasks"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Start Approval Workflow</h6>
                                                <small>Submit a document for approval</small>
                                            </div>
                                        </div>
                                    </a>
                                    <a href="generate_report.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="bg-success text-white rounded p-2 mr-3">
                                                <i class="fas fa-chart-bar"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Generate Report</h6>
                                                <small>Create financial or operational reports</small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Pending Approvals Card -->
                        <div class="card mt-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0"><i class="fas fa-exclamation-circle"></i> Pending Approvals</h5>
                            </div>
                            <div class="card-body">
                                <?php if($pendingApprovalCount > 0): ?>
                                    <div class="text-center mb-3">
                                        <div class="display-4"><?php echo $pendingApprovalCount; ?></div>
                                        <p>approval<?php echo $pendingApprovalCount != 1 ? 's' : ''; ?> awaiting your review</p>
                                    </div>
                                    <a href="approvals.php?status=pending" class="btn btn-warning btn-block">
                                        <i class="fas fa-check-circle"></i> Review Pending Approvals
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-success mb-0">
                                        <i class="fas fa-check-circle"></i> No pending approvals at this time.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>