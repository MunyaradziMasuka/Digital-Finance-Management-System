<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all invoices based on user role
if ($_SESSION['role'] == 'admin') {
    // Admins can see all invoices
    $stmt = $conn->prepare("SELECT i.*, u.username as created_by_name, a.username as approved_by_name 
                           FROM invoices i 
                           LEFT JOIN users u ON i.created_by = u.id 
                           LEFT JOIN users a ON i.approved_by = a.id 
                           ORDER BY i.created_at DESC");
} else {
    // Regular users only see their own invoices
    $stmt = $conn->prepare("SELECT i.*, u.username as created_by_name, a.username as approved_by_name 
                           FROM invoices i 
                           LEFT JOIN users u ON i.created_by = u.id 
                           LEFT JOIN users a ON i.approved_by = a.id 
                           WHERE i.created_by = ? 
                           ORDER BY i.created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - City of Harare Financial Management System</title>
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pending {
            background-color: #ffeeba;
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
                        <a class="nav-link active" href="invoices.php">
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
                    <?php if($_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> User Management
                        </a>
                    </li>
                    <?php endif; ?>
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
                        <div class="col-md-8">
                            <h2><i class="fas fa-file-invoice"></i> Invoices</h2>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="create_invoice.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Create New Invoice
                            </a>
                        </div>
                    </div>
                    
                    <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Customer Name</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($result->num_rows > 0): ?>
                                            <?php while($invoice = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                                    <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                                                    <td>
                                                        <?php if($invoice['status'] == 'pending'): ?>
                                                            <span class="status-badge status-pending">Pending Approval</span>
                                                        <?php elseif($invoice['status'] == 'approved'): ?>
                                                            <span class="status-badge status-approved">Approved</span>
                                                        <?php else: ?>
                                                            <span class="status-badge status-rejected">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($invoice['created_by_name']); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></td>
                                                    <td>
                                                        <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if($invoice['status'] == 'pending' && $_SESSION['user_id'] == $invoice['created_by']): ?>
                                                        <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No invoices found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
</body>
</html>