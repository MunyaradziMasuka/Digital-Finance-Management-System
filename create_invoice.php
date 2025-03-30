<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Generate invoice number (COH + YEAR + random 3 digits)
    $invoiceNumber = 'COH' . date('Y') . '-' . rand(100, 999);
    
    // Prepare the SQL statement
    $stmt = $conn->prepare("INSERT INTO invoices (customer_name, amount, due_date, description, created_by, invoice_number) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sdssss", 
        $_POST['customer_name'], 
        $_POST['amount'],
        $_POST['due_date'],
        $_POST['description'],
        $_SESSION['user_id'],
        $invoiceNumber
    );
    
    // Execute the statement
    if ($stmt->execute()) {
        // Redirect to invoices page with success message
        $_SESSION['success_message'] = "Invoice {$invoiceNumber} created successfully and pending approval.";
        header("Location: invoices.php");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - City of Harare Financial Management System</title>
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
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                        <div class="col-md-12">
                            <h2><i class="fas fa-file-invoice"></i> Create New Invoice</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="invoices.php">Invoices</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Create Invoice</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    
                    <?php if(isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8 offset-md-2">
                            <div class="form-container">
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                    <div class="form-group">
                                        <label for="customer_name"><strong>Customer Name:</strong></label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="amount"><strong>Amount ($):</strong></label>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="due_date"><strong>Due Date:</strong></label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description"><strong>Description:</strong></label>
                                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-plus-circle"></i> Create Invoice
                                        </button>
                                    </div>
                                </form>
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
    <script>
        // Set due date default to 30 days from today
        document.addEventListener('DOMContentLoaded', function() {
            var thirtyDaysFromNow = new Date();
            thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30);
            
            var dueDateInput = document.getElementById('due_date');
            dueDateInput.valueAsDate = thirtyDaysFromNow;
        });
    </script>
</body>
</html>