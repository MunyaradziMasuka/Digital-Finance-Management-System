<?php
include 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["document"])) {
    $document_name = mysqli_real_escape_string($conn, $_POST['document_name']);
    $document_type = mysqli_real_escape_string($conn, $_POST['document_type']);
    $username = $_SESSION['username']; // Get current user's ID
    
    $file = $_FILES['document'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv');
    
    if (in_array($fileExt, $allowed)) {
        if ($fileError === 0) {
            if ($fileSize < 10000000) { // Less than 10MB
                $fileNameNew = uniqid('', true) . "." . $fileExt;
                $fileDestination = 'uploads/' . $fileNameNew;
                
                // Create uploads directory if it doesn't exist
                if (!file_exists('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    // Insert file info into database including the user ID
                    $sql = "INSERT INTO documents (document_name, document_type, file_path, uploaded_by, uploaded_at) 
                            VALUES ('$document_name', '$document_type', '$fileDestination', '$username', NOW())";
                    
                    if ($conn->query($sql) === TRUE) {
                        $message = "Document uploaded successfully!";
                    } else {
                        $message = "Error: " . $sql . "<br>" . $conn->error;
                    }
                } else {
                    $message = "Failed to upload file!";
                }
            } else {
                $message = "Your file is too large!";
            }
        } else {
            $message = "There was an error uploading your file!";
        }
    } else {
        $message = "You cannot upload files of this type!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Document</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Upload Document</h2>
        
        <?php if($message): ?>
            <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="document_name">Document Name</label>
                <input type="text" class="form-control" id="document_name" name="document_name" required>
            </div>
            
            <div class="form-group">
                <label for="document_type">Document Type</label>
                <select class="form-control" id="document_type" name="document_type" required>
                    <option value="">Select Document Type</option>
                    <option value="Financial Report">Financial Report</option>
                    <option value="Balance Sheet">Balance Sheet</option>
                    <option value="Invoice">Invoice</option>
                    <option value="Receipt">Receipt</option>
                    <option value="Contract">Contract</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="document">Select Document</label>
                <input type="file" class="form-control-file" id="document" name="document" required>
                <small class="form-text text-muted">Allowed file types: PDF, DOC, DOCX, XLS, XLSX, TXT, CSV (Max size: 10MB)</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Upload Document</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>
</body>
</html>