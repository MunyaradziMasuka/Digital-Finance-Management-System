<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Define default values
$report_type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
$start_date = '';
$end_date = '';
$current_date = date('Y-m-d');

// Set default date ranges based on report type
if ($report_type == 'yearly') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
} elseif ($report_type == 'monthly') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($report_type == 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($report_type == 'custom') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
}

// Process form submission for custom date range
if (isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    
    if ($report_type == 'yearly') {
        $year = $_POST['year'];
        $start_date = $year . '-01-01';
        $end_date = $year . '-12-31';
    } elseif ($report_type == 'monthly') {
        $month = $_POST['month'];
        $year = $_POST['year'];
        $start_date = $year . '-' . $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
    } elseif ($report_type == 'weekly') {
        $week_start = $_POST['week_start'];
        $start_date = date('Y-m-d', strtotime($week_start));
        $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
    } elseif ($report_type == 'custom') {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
    }
    
    // Redirect to the same page with query parameters for the report
    header("Location: reports.php?type=" . $report_type . "&start_date=" . $start_date . "&end_date=" . $end_date);
    exit();
}

// Get activity data from database
$activities = [];

// SQL to get combined activity data
$sql = "
    (SELECT 'document' as type, id, document_name as name, document_type as item_type, 
           'upload' as action, uploaded_by as user_id, uploaded_at as date 
     FROM documents 
     WHERE uploaded_at BETWEEN ? AND ?)
    UNION
    (SELECT 'invoice' as type, id, invoice_number as name, CONCAT('$', amount) as item_type, 
           status as action, created_by as user_id, created_at as date 
     FROM invoices 
     WHERE created_at BETWEEN ? AND ?)
    UNION
    (SELECT 'workflow' as type, id, title as name, status as item_type, 
           'approval_workflow' as action, initiated_by as user_id, initiated_at as date
     FROM approval_workflows 
     WHERE initiated_at BETWEEN ? AND ?)
    ORDER BY date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get username for the user_id
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $row['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    $row['username'] = $user ? $user['username'] : 'Unknown User';
    $activities[] = $row;
}

// Get summary statistics
$stats = [
    'total_invoices' => 0,
    'total_documents' => 0,
    'total_workflows' => 0,
    'approved_workflows' => 0,
    'rejected_workflows' => 0,
    'pending_workflows' => 0
];

// Total invoices
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE created_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['total_invoices'] = $stmt->get_result()->fetch_assoc()['count'];

// Total documents
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM documents WHERE uploaded_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['total_documents'] = $stmt->get_result()->fetch_assoc()['count'];

// Workflow statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM approval_workflows 
    WHERE initiated_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$workflow_stats = $stmt->get_result()->fetch_assoc();
$stats['total_workflows'] = $workflow_stats['total'];
$stats['pending_workflows'] = $workflow_stats['pending'];
$stats['approved_workflows'] = $workflow_stats['approved'];
$stats['rejected_workflows'] = $workflow_stats['rejected'];

// Export to CSV if requested
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $start_date . '_to_' . $end_date . '.csv"');
    
    // Create file pointer connected to PHP output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, ['Type', 'ID', 'Name', 'Details', 'Action', 'User', 'Date']);
    
    // Write data rows
    foreach ($activities as $activity) {
        fputcsv($output, [
            $activity['type'],
            $activity['id'],
            $activity['name'],
            $activity['item_type'],
            $activity['action'],
            $activity['username'],
            $activity['date']
        ]);
    }
    
    fclose($output);
    exit();
}

// Extract unique years, months for selectors
$years = range(date('Y') - 5, date('Y'));
$months = [
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - City of Harare Financial Management System</title>
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
        .payment-icon {
            background-color: #16a085;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        .stats-card p {
            color: #666;
            margin-bottom: 0;
        }
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #1a3a68;
        }
        .date-form {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
        }
        .nav-tabs .nav-link.active {
            background-color: #1a3a68;
            color: white;
            border-color: #1a3a68;
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
                        <a class="nav-link active" href="reports.php">
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-chart-bar"></i> Financial Reports</h2>
                        <div>
                            <a href="reports.php?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" class="btn btn-success">
                                <i class="fas fa-file-csv"></i> Export to CSV
                            </a>
                            <a href="#" onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print Report
                            </a>
                        </div>
                    </div>
                    <p>Generate and view reports for all financial activities in the system.</p>
                </div>
                
                <!-- Report Selection Form -->
                <div class="date-form">
                    <form method="post" action="reports.php">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="report_type">Report Type</label>
                                    <select class="form-control" id="report_type" name="report_type" onchange="toggleDateFields()">
                                        <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly Report</option>
                                        <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                                        <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly Report</option>
                                        <option value="custom" <?php echo $report_type == 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Year Selection (for Yearly and Monthly) -->
                            <div class="col-md-2 yearly-field monthly-field">
                                <div class="form-group">
                                    <label for="year">Year</label>
                                    <select class="form-control" id="year" name="year">
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo date('Y', strtotime($start_date)) == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Month Selection (for Monthly) -->
                            <div class="col-md-2 monthly-field">
                                <div class="form-group">
                                    <label for="month">Month</label>
                                    <select class="form-control" id="month" name="month">
                                        <?php foreach ($months as $num => $name): ?>
                                            <option value="<?php echo $num; ?>" <?php echo date('m', strtotime($start_date)) == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Week Start Date (for Weekly) -->
                            <div class="col-md-2 weekly-field">
                                <div class="form-group">
                                    <label for="week_start">Week Starting</label>
                                    <input type="date" class="form-control" id="week_start" name="week_start" value="<?php echo $start_date; ?>">
                                </div>
                            </div>
                            
                            <!-- Custom Date Range -->
                            <div class="col-md-2 custom-field">
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-2 custom-field">
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="generate_report" class="btn btn-primary btn-block">
                                    <i class="fas fa-sync-alt"></i> Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Report Date Range Display -->
                <div class="alert alert-info">
                    <strong>Report Period:</strong> <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                </div>
                
                <!-- Statistics Summary -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <h3><?php echo $stats['total_invoices']; ?></h3>
                            <p>Invoices Generated</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h3><?php echo $stats['total_documents']; ?></h3>
                            <p>Documents Processed</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h3><?php echo $stats['total_workflows']; ?></h3>
                            <p>Approval Workflows</p>
                        </div>
                    </div>
                </div>
                
                <!-- Workflow Status Summary -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-tasks"></i> Workflow Status Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <div class="border rounded p-3 bg-warning text-dark">
                                            <h4><?php echo $stats['pending_workflows']; ?></h4>
                                            <p>Pending Workflows</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="border rounded p-3 bg-success text-white">
                                            <h4><?php echo $stats['approved_workflows']; ?></h4>
                                            <p>Approved Workflows</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="border rounded p-3 bg-danger text-white">
                                            <h4><?php echo $stats['rejected_workflows']; ?></h4>
                                            <p>Rejected Workflows</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-list"></i> Detailed Activity Log</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($activities) > 0): ?>
                                        <?php foreach ($activities as $activity): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></td>
                                                <td>
                                                    <div class="activity-icon <?php echo $activity['type']; ?>-icon">
                                                        <?php if ($activity['type'] == 'document'): ?>
                                                            <i class="fas fa-file-alt"></i>
                                                        <?php elseif ($activity['type'] == 'invoice'): ?>
                                                            <i class="fas fa-file-invoice"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-tasks"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($activity['type'] == 'document') {
                                                        echo 'Document: ' . htmlspecialchars($activity['name']);
                                                    } elseif ($activity['type'] == 'invoice') {
                                                        echo 'Invoice: ' . htmlspecialchars($activity['name']);
                                                    } else {
                                                        echo 'Workflow: ' . htmlspecialchars($activity['name']);
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['item_type']); ?></td>
                                                <td>
                                                    <?php if ($activity['type'] == 'workflow'): ?>
                                                        <span class="badge badge-<?php echo $activity['action']; ?>">
                                                            <?php echo ucfirst($activity['action']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?php echo ucfirst($activity['action']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No activities found for the selected period.</td>
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

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Function to toggle date fields based on report type
        function toggleDateFields() {
            const reportType = document.getElementById('report_type').value;
            
            // Hide all fields first
            document.querySelectorAll('.yearly-field, .monthly-field, .weekly-field, .custom-field').forEach(field => {
                field.style.display = 'none';
            });
            
            // Show relevant fields based on report type
            if (reportType === 'yearly') {
                document.querySelectorAll('.yearly-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (reportType === 'monthly') {
                document.querySelectorAll('.yearly-field, .monthly-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (reportType === 'weekly') {
                document.querySelectorAll('.weekly-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (reportType === 'custom') {
                document.querySelectorAll('.custom-field').forEach(field => {
                    field.style.display = 'block';
                });
            }
        }
        
        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function() {
            toggleDateFields();
            
            // Initialize flatpickr for date inputs if needed
            flatpickr("#week_start", {
                dateFormat: "Y-m-d",
                weekNumbers: true
            });
            
            flatpickr("#start_date", {
                dateFormat: "Y-m-d"
            });
            
            flatpickr("#end_date", {
                dateFormat: "Y-m-d"
            });
        });
    </script>
</body>
</html>