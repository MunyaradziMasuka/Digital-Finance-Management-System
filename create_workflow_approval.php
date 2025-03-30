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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $document_id = !empty($_POST['document_id']) ? $_POST['document_id'] : NULL;
    $invoice_id = !empty($_POST['invoice_id']) ? $_POST['invoice_id'] : NULL;
    $workflow_type_id = !empty($_POST['workflow_type_id']) ? $_POST['workflow_type_id'] : NULL; // Added workflow_type_id
    $initiated_by = $_SESSION['user_id'];
    
    // Validate inputs
    if (empty($title)) {
        $error = "Please provide a title for this workflow";
    } elseif (empty($workflow_type_id)) {
        $error = "Please select a workflow type";
    } else {
        // Create new workflow
        $stmt = $conn->prepare("INSERT INTO approval_workflows 
                               (title, description, document_id, invoice_id, workflow_type_id, initiated_by, initiated_at, status) 
                               VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')");
        
        $stmt->bind_param("ssiiis", $title, $description, $document_id, $invoice_id, $workflow_type_id, $initiated_by);
        
        if ($stmt->execute()) {
            $success = "Workflow approval request has been submitted successfully";
        } else {
            $error = "Error submitting workflow: " . $conn->error;
        }
    }
}

// Get all documents for selection
$stmt = $conn->prepare("SELECT id, document_name, document_type FROM documents ORDER BY uploaded_at DESC");
$stmt->execute();
$documents = $stmt->get_result();

// Get all invoices for selection
$stmt = $conn->prepare("SELECT id, invoice_number FROM invoices WHERE status != 'paid' ORDER BY created_at DESC");
$stmt->execute();
$invoices = $stmt->get_result();

// First, let's discover the column names in the workflow_types table
$workflow_types_columns = [];
$result = $conn->query("SHOW COLUMNS FROM workflow_types");
while($row = $result->fetch_assoc()) {
    $workflow_types_columns[] = $row['Field'];
}

// Determine which columns to use for the dropdown
$name_column = 'id'; // Default to just showing the ID
// Look for common name column patterns
foreach(['type', 'name', 'description', 'workflow_type'] as $possible_name) {
    if(in_array($possible_name, $workflow_types_columns)) {
        $name_column = $possible_name;
        break;
    }
}

// Get all workflow types for selection with dynamic column names
$stmt = $conn->prepare("SELECT id, $name_column FROM workflow_types ORDER BY id");
$stmt->execute();
$workflow_types = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Workflow Approval - City of Harare Financial Management System</title>
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
            margin-bottom: 20px;
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
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-plus-circle"></i> Create New Workflow Approval</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="title"><b>Workflow Title</b></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="E.g., Budget Approval Request">
                            </div>
                            
                            <div class="form-group">
                                <label for="workflow_type_id"><b>Workflow Type</b></label>
                                <select class="form-control" id="workflow_type_id" name="workflow_type_id" required>
                                    <option value="">-- Select workflow type --</option>
                                    <?php while($type = $workflow_types->fetch_assoc()): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['id']); ?> 
                                        <?php if($name_column != 'id'): ?>
                                            - <?php echo htmlspecialchars($type[$name_column]); ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="description"><b>Description</b></label>
                                <textarea class="form-control" id="description" name="description" rows="4" 
                                          placeholder="Provide details about this workflow approval request"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="document_id"><b>Related Document</b> (Optional)</label>
                                        <select class="form-control" id="document_id" name="document_id">
                                            <option value="">-- Select a document --</option>
                                            <?php while($doc = $documents->fetch_assoc()): ?>
                                            <option value="<?php echo $doc['id']; ?>">
                                                <?php echo htmlspecialchars($doc['document_name']); ?> 
                                                (<?php echo htmlspecialchars($doc['document_type']); ?>)
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_id"><b>Related Invoice</b> (Optional)</label>
                                        <select class="form-control" id="invoice_id" name="invoice_id">
                                            <option value="">-- Select an invoice --</option>
                                            <?php while($inv = $invoices->fetch_assoc()): ?>
                                            <option value="<?php echo $inv['id']; ?>">
                                                Invoice #<?php echo htmlspecialchars($inv['invoice_number']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Workflow for Approval
                                </button>
                                <a href="view_approvals.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Approvals
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Prevent selection of both document and invoice
            $('#document_id').change(function() {
                if ($(this).val() !== '') {
                    $('#invoice_id').val('');
                }
            });
            
            $('#invoice_id').change(function() {
                if ($(this).val() !== '') {
                    $('#document_id').val('');
                }
            });
        });
    </script>
</body>
</html>