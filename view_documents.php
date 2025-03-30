<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Delete document if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get file path before deleting record
    $result = $conn->query("SELECT file_path FROM documents WHERE id = $id");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $file_path = $row['file_path'];
        
        // Delete file if it exists
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete record from database
        $conn->query("DELETE FROM documents WHERE id = $id");
    }
    
    // Redirect to avoid resubmission
    header("Location: view_documents.php");
    exit();
}

// Get all documents
$sql = "SELECT * FROM documents ORDER BY uploaded_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Documents</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .actions {
            width: 150px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Documents</h2>
            <div>
                <a href="upload_document.php" class="btn btn-primary"><i class="fas fa-upload"></i> Upload New Document</a>
                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Back to Dashboard</a>
            </div>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Document Name</th>
                        <th>Document Type</th>
                        <th>Uploaded By</th>
                        <th>Uploaded At</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['document_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['document_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($row['uploaded_at'])); ?></td>
                            <td class="actions">
                                <a href="<?php echo $row['file_path']; ?>" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-eye"></i> View</a>
                                <a href="view_documents.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this document?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No documents found. <a href="upload_document.php">Upload your first document</a>.</div>
        <?php endif; ?>
    </div>
</body>
</html>