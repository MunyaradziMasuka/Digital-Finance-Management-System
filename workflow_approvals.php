<?php
include 'includes/db.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';

// Handle approval or rejection
if (isset($_POST['action']) && isset($_POST['workflow_id'])) {
    $workflow_id = $_POST['workflow_id'];
    $action = $_POST['action'];
    $comments = isset($_POST['comments']) ? $_POST['comments'] : '';
    
    if ($action === 'approve') {
        $status = 'approved';
    } else if ($action === 'reject') {
        $status = 'rejected';
    } else {
        $status = 'pending';
    }
    
    // Update the workflow status
    $stmt = $conn->prepare("UPDATE approval_workflows SET 
                           status = ?, 
                           completed_at = NOW(),
                           admin_comments = ? 
                           WHERE id = ?");
    
    $stmt->bind_param("ssi", $status, $comments, $workflow_id);
    
    if ($stmt->execute()) {
        $success = "Workflow has been " . $status . " successfully";
    } else {
        $error = "Error updating workflow: " . $conn->error;
    }
}

// Get all pending approval workflows
$stmt = $conn->prepare("
    SELECT aw.*, 
           d.document_name, 
           i.invoice_number,
           u.username as initiator
    FROM approval_workflows aw
    LEFT JOIN documents d ON aw.document_id = d.id
    LEFT JOIN invoices i ON aw.invoice_id = i.id
    LEFT JOIN users u ON aw.initiated_by = u.id
    WHERE aw.status = 'pending'
    ORDER BY aw.initiated_at DESC
");
$stmt->execute();
$pending_workflows = $stmt->get_result();

// Get recent approved/rejected workflows
$stmt = $conn->prepare("
    SELECT aw.*, 
           d.document_name, 
           i.invoice_number,
           u.username as initiator
    FROM approval_workflows aw
    LEFT JOIN documents d ON aw.document_id = d.id
    LEFT JOIN invoices i ON aw.invoice_id = i.id
    LEFT JOIN users u ON aw.initiated_by = u.id
    WHERE aw.status IN ('approved', 'rejected')
    ORDER BY aw.completed_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_workflows = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Approvals - City of Harare Financial Management System</title>
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
                
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-check-circle"></i> Pending Workflow Approvals</h4>
                    </div>
                    <div class="card-body">
                        <?php if($pending_workflows->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Description</th>
                                            <th>Related Document</th>
                                            <th>Related Invoice</th>
                                            <th>Requested By</th>
                                            <th>Date Requested</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($workflow = $pending_workflows->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($workflow['title']); ?></td>
                                                <td>
                                                    <?php 
                                                    $desc = htmlspecialchars($workflow['description']);
                                                    echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo $workflow['document_name'] ? htmlspecialchars($workflow['document_name']) : 'N/A'; ?>
                                                </td>
                                                <td>
                                                    <?php echo $workflow['invoice_number'] ? htmlspecialchars($workflow['invoice_number']) : 'N/A'; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($workflow['initiator']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($workflow['initiated_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#approvalModal<?php echo $workflow['id']; ?>">
                                                        <i class="fas fa-eye"></i> Review
                                                    </button>
                                                    
                                                    <!-- Approval Modal -->
                                                    <div class="modal fade" id="approvalModal<?php echo $workflow['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="approvalModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title" id="approvalModalLabel">Review Workflow Approval</h5>
                                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <p><strong>Title:</strong> <?php echo htmlspecialchars($workflow['title']); ?></p>
                                                                            <p><strong>Requested By:</strong> <?php echo htmlspecialchars($workflow['initiator']); ?></p>
                                                                            <p><strong>Date Requested:</strong> <?php echo date('M d, Y H:i', strtotime($workflow['initiated_at'])); ?></p>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <p><strong>Related Document:</strong> 
                                                                                <?php echo $workflow['document_name'] ? htmlspecialchars($workflow['document_name']) : 'N/A'; ?>
                                                                            </p>
                                                                            <p><strong>Related Invoice:</strong> 
                                                                                <?php echo $workflow['invoice_number'] ? htmlspecialchars($workflow['invoice_number']) : 'N/A'; ?>
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
                                                                    
                                                                    <form action="" method="POST" class="mt-4">
                                                                        <input type="hidden" name="workflow_id" value="<?php echo $workflow['id']; ?>">
                                                                        
                                                                        <div class="form-group">
                                                                            <label for="comments"><b>Admin Comments</b></label>
                                                                            <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Add your comments about this approval..."></textarea>
                                                                        </div>
                                                                        
                                                                        <div class="form-group text-center">
                                                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-lg mr-2">
                                                                                <i class="fas fa-check"></i> Approve
                                                                            </button>
                                                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg">
                                                                                <i class="fas fa-times"></i> Reject
                                                                            </button>
                                                                        </div>
                                                                    </form>
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
                                <i class="fas fa-info-circle"></i> There are no pending workflow approvals at this time.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h4><i class="fas fa-history"></i> Recent Approval Activities</h4>
                    </div>
                    <div class="card-body">
                        <?php if($recent_workflows->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Requested By</th>
                                            <th>Date Requested</th>
                                            <th>Date Completed</th>
                                            <th>Status</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($workflow = $recent_workflows->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($workflow['title']); ?></td>
                                                <td><?php echo htmlspecialchars($workflow['initiator']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($workflow['initiated_at'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($workflow['completed_at'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $workflow['status']; ?>">
                                                        <?php echo ucfirst($workflow['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $comments = htmlspecialchars($workflow['admin_comments']);
                                                    echo strlen($comments) > 30 ? substr($comments, 0, 30) . '...' : $comments; 
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No recent approval activities.
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
</body>
</html>