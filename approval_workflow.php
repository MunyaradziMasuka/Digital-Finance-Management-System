<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Function to get all available workflow types
function getWorkflowTypes($conn) {
    $stmt = $conn->prepare("SELECT * FROM workflow_types ORDER BY name");
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get workflow steps for a specific workflow type
function getWorkflowSteps($conn, $workflow_type_id) {
    $stmt = $conn->prepare("
        SELECT ws.*, u.username, u.role as role_name 
        FROM workflow_steps ws
        LEFT JOIN users u ON ws.specific_approver_id = u.id
        WHERE ws.workflow_type_id = ?
        ORDER BY ws.step_order
    ");
    $stmt->execute([$workflow_type_id]);
    return $stmt->get_result();
}

// Handle form submission to create a new workflow type
if (isset($_POST['create_workflow_type'])) {
    $name = $_POST['workflow_name'];
    $description = $_POST['workflow_description'];
    
    $stmt = $conn->prepare("INSERT INTO workflow_types (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);
    
    $_SESSION['success_message'] = "Workflow type created successfully!";
    header("Location: approval_workflow.php");
    exit();
}

// Handle form submission to add a workflow step
if (isset($_POST['add_workflow_step'])) {
    $workflow_type_id = $_POST['workflow_type_id'];
    $step_name = $_POST['step_name'];
    $step_order = $_POST['step_order'];
    
    if ($_POST['approver_type'] === 'role') {
        $required_role_id = !empty($_POST['required_role_id']) ? $_POST['required_role_id'] : NULL;
        $specific_approver_id = NULL;
    } else {
        $required_role_id = NULL;
        $specific_approver_id = !empty($_POST['specific_approver_id']) ? $_POST['specific_approver_id'] : NULL;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO workflow_steps 
        (workflow_type_id, step_name, step_order, required_role_id, specific_approver_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$workflow_type_id, $step_name, $step_order, $required_role_id, $specific_approver_id]);
    
    $_SESSION['success_message'] = "Workflow step added successfully!";
    header("Location: approval_workflow.php?workflow_id=" . $workflow_type_id);
    exit();
}

// Get distinct roles from users table for dropdown
$stmt = $conn->prepare("SELECT DISTINCT role FROM users ORDER BY role");
$stmt->execute();
$roles = $stmt->get_result();

// Get all users for dropdown
$stmt = $conn->prepare("SELECT id, username, role FROM users ORDER BY username");
$stmt->execute();
$users = $stmt->get_result();

// Get all workflow types
$workflow_types = getWorkflowTypes($conn);

// Get steps for a specific workflow if requested
$selected_workflow_id = isset($_GET['workflow_id']) ? $_GET['workflow_id'] : null;
$workflow_steps = null;
$selected_workflow_name = '';

if ($selected_workflow_id) {
    $workflow_steps = getWorkflowSteps($conn, $selected_workflow_id);
    
    // Get workflow name
    $stmt = $conn->prepare("SELECT name FROM workflow_types WHERE id = ?");
    $stmt->execute([$selected_workflow_id]);
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $selected_workflow_name = $result->fetch_assoc()['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Workflow Management - City of Harare Financial Management System</title>
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
        .card-header {
            border-radius: 10px 10px 0 0;
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
        .step-card {
            border-left: 4px solid #1a3a68;
            margin-bottom: 10px;
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
                <div class="welcome-header">
                    <h2>Approval Workflow Management</h2>
                    <p>Create and manage approval workflows for various document types.</p>
                </div>
                
                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Workflow Types List -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Workflow Types</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php if($workflow_types->num_rows > 0): ?>
                                        <?php while($type = $workflow_types->fetch_assoc()): ?>
                                            <a href="approval_workflow.php?workflow_id=<?php echo $type['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_workflow_id == $type['id']) ? 'active' : ''; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($type['description']); ?></small>
                                            </a>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p>No workflow types defined yet.</p>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#createWorkflowModal">
                                    <i class="fas fa-plus"></i> Create New Workflow Type
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Workflow Steps -->
                    <div class="col-md-8">
                        <?php if($selected_workflow_id): ?>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title">Workflow Steps: <?php echo htmlspecialchars($selected_workflow_name); ?></h5>
                                    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#addStepModal">
                                        <i class="fas fa-plus"></i> Add Step
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if($workflow_steps && $workflow_steps->num_rows > 0): ?>
                                        <div class="workflow-steps">
                                            <?php while($step = $workflow_steps->fetch_assoc()): ?>
                                                <div class="card step-card">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <h5><?php echo htmlspecialchars($step['step_name']); ?></h5>
                                                                <small class="text-muted">Order: <?php echo $step['step_order']; ?></small>
                                                            </div>
                                                            <div>
                                                                <?php if($step['required_role_id']): ?>
                                                                    <span class="badge badge-info">Role: <?php echo htmlspecialchars($step['required_role_id']); ?></span>
                                                                <?php endif; ?>
                                                                <?php if($step['specific_approver_id']): ?>
                                                                    <span class="badge badge-primary">Approver: <?php echo htmlspecialchars($step['username']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>No steps defined for this workflow yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-center">Select a workflow type from the list to view or edit its steps.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Workflow Type Modal -->
    <div class="modal fade" id="createWorkflowModal" tabindex="-1" role="dialog" aria-labelledby="createWorkflowModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createWorkflowModalLabel">Create New Workflow Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="approval_workflow.php" method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="workflow_name">Workflow Name</label>
                            <input type="text" class="form-control" id="workflow_name" name="workflow_name" required>
                        </div>
                        <div class="form-group">
                            <label for="workflow_description">Description</label>
                            <textarea class="form-control" id="workflow_description" name="workflow_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_workflow_type" class="btn btn-primary">Create Workflow</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Workflow Step Modal -->
    <div class="modal fade" id="addStepModal" tabindex="-1" role="dialog" aria-labelledby="addStepModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStepModalLabel">Add Workflow Step</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="approval_workflow.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="workflow_type_id" value="<?php echo $selected_workflow_id; ?>">
                        
                        <div class="form-group">
                            <label for="step_name">Step Name</label>
                            <input type="text" class="form-control" id="step_name" name="step_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="step_order">Step Order</label>
                            <input type="number" class="form-control" id="step_order" name="step_order" min="1" value="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Approver Type</label>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="role_based" name="approver_type" class="custom-control-input" value="role" checked>
                                <label class="custom-control-label" for="role_based">Role-based</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="specific_user" name="approver_type" class="custom-control-input" value="user">
                                <label class="custom-control-label" for="specific_user">Specific User</label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="role_selector">
                            <label for="required_role_id">Required Role</label>
                            <select class="form-control" id="required_role_id" name="required_role_id">
                                <option value="">Select a role...</option>
                                <?php while($role = $roles->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($role['role']); ?>">
                                        <?php echo htmlspecialchars($role['role']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="user_selector" style="display:none;">
                            <label for="specific_approver_id">Specific Approver</label>
                            <select class="form-control" id="specific_approver_id" name="specific_approver_id">
                                <option value="">Select a user...</option>
                                <?php 
                                // Reset result pointer
                                $users->data_seek(0);
                                while($user = $users->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?> 
                                        <?php if(isset($user['full_name']) && $user['full_name']): ?>(<?php echo htmlspecialchars($user['full_name']); ?>)<?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_workflow_step" class="btn btn-primary">Add Step</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle between role-based and user-based approver selection
            $('input[name="approver_type"]').change(function() {
                if ($(this).val() === 'role') {
                    $('#role_selector').show();
                    $('#user_selector').hide();
                    $('#specific_approver_id').val('');
                } else {
                    $('#role_selector').hide();
                    $('#user_selector').show();
                    $('#required_role_id').val('');
                }
            });
        });
    </script>
</body>
</html>