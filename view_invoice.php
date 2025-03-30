<?php
include 'includes/db.php';
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Check if an ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid invoice ID";
    $_SESSION['message_type'] = "danger";
    header("Location: invoices.php");
    exit();
}
$id = $_GET['id'];
// Get invoice details
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['message'] = "Invoice not found";
    $_SESSION['message_type'] = "danger";
    header("Location: invoices.php");
    exit();
}
$invoice = $result->fetch_assoc();
// Calculate days until due or days overdue
$due_date = new DateTime($invoice['due_date']);
$today = new DateTime();
$interval = $today->diff($due_date);
$days_difference = $interval->days;
$is_overdue = $today > $due_date;
// Automatically update status to overdue if due date has passed and status is still pending
if ($is_overdue && $invoice['status'] === 'pending') {
    $update_stmt = $conn->prepare("UPDATE invoices SET status = 'overdue' WHERE id = ?");
    $update_stmt->bind_param("i", $id);
    $update_stmt->execute();
    $invoice['status'] = 'overdue';
}

// Check if clients table exists and if client_id is in the invoice table
$client = null;
if (isset($invoice['client_id']) || isset($invoice['customer_name'])) {
    $check_client_table = $conn->query("SHOW TABLES LIKE 'clients'");
    if ($check_client_table->num_rows > 0 && isset($invoice['client_id'])) {
        $client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
        $client_stmt->bind_param("i", $invoice['client_id']);
        $client_stmt->execute();
        $client_result = $client_stmt->get_result();
        if ($client_result->num_rows > 0) {
            $client = $client_result->fetch_assoc();
        }
    } else if (isset($invoice['customer_name'])) {
        // If we have customer_name but no proper client record, create a simple client array
        $client = [
            'name' => $invoice['customer_name'],
            'email' => isset($invoice['customer_email']) ? $invoice['customer_email'] : ''
        ];
    }
}

// Set default values if fields are missing
$description = isset($invoice['description']) ? $invoice['description'] : "Professional Services";
$amount = isset($invoice['amount']) ? $invoice['amount'] : 0;
$tax_rate = isset($invoice['tax_rate']) ? $invoice['tax_rate'] : 0;
$subtotal = $tax_rate > 0 ? $amount / (1 + ($tax_rate / 100)) : $amount;
$tax_amount = $amount - $subtotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice - Financial Management System</title>
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
            width: 230px;
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
        .invoice-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            border-bottom: 1px solid #dee2e6;
        }
        .invoice-body {
            padding: 20px;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-paid {
            background-color: #28a745;
        }
        .badge-overdue {
            background-color: #dc3545;
        }
        .badge-cancelled {
            background-color: #6c757d;
        }
        .badge-approved {
            background-color: #28a745;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
        .invoice-actions {
            margin-bottom: 20px;
        }
        .invoice-actions .btn {
            margin-right: 10px;
        }
        .total-row {
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }
        .status-indicator {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-pending {
            background-color: #ffc107;
        }
        .status-paid {
            background-color: #28a745;
        }
        .status-overdue {
            background-color: #dc3545;
        }
        .status-cancelled {
            background-color: #6c757d;
        }
        .status-approved {
            background-color: #28a745;
        }
        .status-rejected {
            background-color: #dc3545;
        }
        @media print {
            .sidebar, .invoice-actions, .navbar {
                display: none;
            }
            .content {
                margin-left: 0;
            }
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="logo">
            <h4>FinanceApp</h4>
        </div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="clients.php"><i class="fas fa-users"></i> Clients</a>
        <a href="invoices.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Invoices</a>
        <a href="expenses.php"><i class="fas fa-money-bill-wave"></i> Expenses</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="invoices.php">Invoices</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View Invoice <?php echo isset($invoice['invoice_number']) ? "#".$invoice['invoice_number'] : "#".$invoice['id']; ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Invoice Actions -->
        <div class="row invoice-actions">
            <div class="col-md-12">
                <a href="invoices.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
                <a href="edit_invoice.php?id=<?php echo $id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
                <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print</button>
                <a href="send_invoice.php?id=<?php echo $id; ?>" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send to Client</a>
                <?php if(isset($invoice['status']) && $invoice['status'] != 'paid' && $invoice['status'] != 'cancelled' && $invoice['status'] != 'approved'): ?>
                    <a href="mark_paid.php?id=<?php echo $id; ?>" class="btn btn-success"><i class="fas fa-check"></i> Mark as Paid</a>
                <?php endif; ?>
                <?php if(isset($invoice['status']) && $invoice['status'] != 'cancelled' && $invoice['status'] != 'rejected'): ?>
                    <a href="cancel_invoice.php?id=<?php echo $id; ?>" class="btn btn-danger"><i class="fas fa-times"></i> Cancel</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Card -->
        <div class="card">
            <div class="invoice-header">
                <div class="row">
                    <div class="col-md-6">
                        <h4>
                            Invoice 
                            <?php echo isset($invoice['invoice_number']) ? "#".$invoice['invoice_number'] : "#".$invoice['id']; ?>
                        </h4>
                        <div>
                            <?php if(isset($invoice['status'])): ?>
                            <small>Status: 
                                <span class="badge badge-<?php echo $invoice['status']; ?>">
                                    <?php echo ucfirst($invoice['status']); ?>
                                </span>
                                <?php if($invoice['status'] == 'pending'): ?>
                                    <small>(Due in <?php echo $days_difference; ?> days)</small>
                                <?php elseif($invoice['status'] == 'overdue'): ?>
                                    <small>(Overdue by <?php echo $days_difference; ?> days)</small>
                                <?php endif; ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        <h5>
                            Total Amount: 
                            $<?php echo isset($invoice['amount']) ? number_format($invoice['amount'], 2) : "0.00"; ?>
                        </h5>
                        <?php if(isset($invoice['created_at'])): ?>
                        <div>Created: <?php echo date("M d, Y", strtotime($invoice['created_at'])); ?></div>
                        <?php endif; ?>
                        <?php if(isset($invoice['due_date'])): ?>
                        <div>Due Date: <?php echo date("M d, Y", strtotime($invoice['due_date'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="invoice-body">
                <?php if($client): ?>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Billed To:</h5>
                        <div><?php echo $client['name']; ?></div>
                        <?php if(isset($client['company'])): ?>
                        <div><?php echo $client['company']; ?></div>
                        <?php endif; ?>
                        <?php if(isset($client['address'])): ?>
                        <div><?php echo $client['address']; ?></div>
                        <?php endif; ?>
                        <?php if(isset($client['city']) || isset($client['state']) || isset($client['zip'])): ?>
                        <div>
                            <?php echo isset($client['city']) ? $client['city'] : ""; ?>
                            <?php echo isset($client['state']) ? ", ".$client['state'] : ""; ?>
                            <?php echo isset($client['zip']) ? " ".$client['zip'] : ""; ?>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($client['country'])): ?>
                        <div><?php echo $client['country']; ?></div>
                        <?php endif; ?>
                        <?php if(isset($client['email'])): ?>
                        <div>Email: <?php echo $client['email']; ?></div>
                        <?php endif; ?>
                        <?php if(isset($client['phone'])): ?>
                        <div>Phone: <?php echo $client['phone']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-right">
                        <h5>From:</h5>
                        <div>Your Company Name</div>
                        <div>123 Business Street</div>
                        <div>City, State ZIP</div>
                        <div>Country</div>
                        <div>Email: contact@yourcompany.com</div>
                        <div>Phone: (123) 456-7890</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Billed To:</h5>
                        <div>Client information not available</div>
                    </div>
                    <div class="col-md-6 text-right">
                        <h5>From:</h5>
                        <div>Harare City Council</div>
                        <div>123 Business Street</div>
                        <div>City, State ZIP</div>
                        <div>Country</div>
                        <div>Email: cityofharare.com</div>
                        <div>Phone: (123) 456-7890</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Invoice Items - Simplified to use data from invoices table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Description</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td><?php echo $description; ?></td>
                                <td class="text-right">1</td>
                                <td class="text-right">$<?php echo number_format($subtotal, 2); ?></td>
                                <td class="text-right">$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>

                            <!-- Subtotal, Tax, and Total -->
                            <tr>
                                <td colspan="4" class="text-right">Subtotal:</td>
                                <td class="text-right">$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <?php if($tax_rate > 0): ?>
                            <tr>
                                <td colspan="4" class="text-right">Tax (<?php echo $tax_rate; ?>%):</td>
                                <td class="text-right">$<?php echo number_format($tax_amount, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td colspan="4" class="text-right">Total:</td>
                                <td class="text-right">$<?php echo number_format($amount, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Payment Information and Notes -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h5>Payment Information</h5>
                        <p>
                            <?php 
                            if(isset($invoice['payment_info'])) {
                                echo nl2br($invoice['payment_info']);
                            } else {
                                echo "Please make payment to:<br>
                                Bank: Your Bank Name<br>
                                Account: 123456789<br>
                                Routing: 987654321<br>
                                Or pay online at: www.yourcompany.com/pay";
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h5>Notes</h5>
                        <p>
                            <?php 
                            if(isset($invoice['notes'])) {
                                echo nl2br($invoice['notes']);
                            } else {
                                echo "Thank you for your business.";
                            }
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Payment History -->
                <?php if(isset($invoice['status']) && ($invoice['status'] == 'paid' || $invoice['status'] == 'approved')): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h5>Payment History</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transaction ID</th>
                                    <th>Method</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <?php 
                                        if(isset($invoice['paid_at'])) {
                                            echo date("M d, Y", strtotime($invoice['paid_at']));
                                        } else if(isset($invoice['approval_date'])) {
                                            echo date("M d, Y", strtotime($invoice['approval_date']));
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo isset($invoice['transaction_id']) ? $invoice['transaction_id'] : "N/A"; ?></td>
                                    <td><?php echo isset($invoice['payment_method']) ? $invoice['payment_method'] : "N/A"; ?></td>
                                    <td class="text-right">$<?php echo number_format($amount, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript dependencies -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>