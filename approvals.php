<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Process approval or rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $invoiceId = $_GET['id'];
    $action = $_GET['action'];

    if ($action == 'approve' || $action == 'reject') {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        
        $stmt = $conn->prepare("UPDATE invoices SET status = ?, approved_by = ?, approval_date = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $status, $_SESSION['user_id'], $invoiceId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Invoice has been " . $status . " successfully.";
        } else {
            $_SESSION['error_message'] = "Error processing the request: " . $stmt->error;
        }
        
        // Redirect to avoid resubmission
        header("Location: approvals.php");
        exit();
    }
}

// Get pending invoices
$stmt = $conn->prepare("SELECT i.*, u.username as created_by_name 
                       FROM invoices i 
                       LEFT JOIN users u ON i.created_by = u.id 
                       WHERE i.status = 'pending' 
                       ORDER BY i.created_at DESC");
$stmt->execute();
$pendingInvoices = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals - City of Harare Financial Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        }
        .logo {
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
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
                <div class="container">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h2><i class="fas fa-check-circle"></i> Invoice Approvals</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Approvals</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    
                    <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-clock"></i> Pending Invoice Approvals</h5>
                        </div>
                        <div class="card-body">
                            <?php if($pendingInvoices->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Customer Name</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Created By</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($invoice = $pendingInvoices->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                                <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                                <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($invoice['created_by_name']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></td>
                                                <td>
                                                    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info mb-1">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="approvals.php?action=approve&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('Are you sure you want to approve this invoice?');">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="approvals.php?action=reject&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Are you sure you want to reject this invoice?');">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pending invoices requiring approval at this time.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>