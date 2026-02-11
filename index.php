<?php
/**
 * Consolidated index.php for Xscraper
 * Ensures paths are dynamic and compatible with Docker /var/www/html
 */

// Define absolute paths relative to this file
define("UPLOAD_FOLDER", __DIR__ . "/UPLOAD_FOLDER/");
define("PYTHON_SCRIPT", __DIR__ . "/xscraper.py");

// Ensure the upload directory exists and is writable
if (!is_dir(UPLOAD_FOLDER)) {
    mkdir(UPLOAD_FOLDER, 0777, true);
}

// Handle File Upload and Processing
$message = "";
$processed = false;
$uniqueId = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["fileToUpload"])) {
    $uniqueId = uniqid(date('YmdHis') . '_', true);
    $uploadedFileName = "$uniqueId.har";
    $targetFile = UPLOAD_FOLDER . $uploadedFileName;

    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
        $output = [];
        $return_var = 0;
        
        // Execute Python script using the Python 3.11 binary inside the container
        $command = "/usr/local/bin/python3 " . escapeshellarg(PYTHON_SCRIPT) . " " . escapeshellarg($uploadedFileName) . " 2>&1";
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $tweetsFile = "tweets_$uniqueId.csv";
            $usersFile = "users_$uniqueId.csv";

            if (file_exists(UPLOAD_FOLDER . $tweetsFile) && file_exists(UPLOAD_FOLDER . $usersFile)) {
                $processed = true;
                $message = "<div class='alert alert-success'>Success! HAR file processed.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Script finished but output files were not found. Output: <pre>" . implode("\n", $output) . "</pre></div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Python script error (Exit Code: $return_var). Output: <pre>" . implode("\n", $output) . "</pre></div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Failed to move uploaded file. Check UPLOAD_FOLDER permissions.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xscraper | HAR Processor</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; max-width: 800px; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #1DA1F2; border: none; }
        .btn-primary:hover { background-color: #1a91da; }
        pre { background: #eee; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card p-4">
        <h1 class="text-center mb-4">Xscraper Processor</h1>
        
        <?php echo $message; ?>

        <?php if ($processed): ?>
            <div class="results-box mt-3">
                <h4 class="mb-3">Generated Data:</h4>
                <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Tweets Data (CSV)
                        <div>
                            <a href="UPLOAD_FOLDER/tweets_<?php echo $uniqueId; ?>.csv" class="btn btn-sm btn-outline-primary" download>Download</a>
                            <a href="view_tweets.php?url=tweets_<?php echo $uniqueId; ?>.csv" class="btn btn-sm btn-primary" target="_blank">View Table</a>
                        </div>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        Users Data (CSV)
                        <div>
                            <a href="UPLOAD_FOLDER/users_<?php echo $uniqueId; ?>.csv" class="btn btn-sm btn-outline-primary" download>Download</a>
                            <a href="view_tweets.php?url=users_<?php echo $uniqueId; ?>.csv" class="btn btn-sm btn-primary" target="_blank">View Table</a>
                        </div>
                    </div>
                </div>
                <a href="index.php" class="btn btn-link mt-4 d-block text-center">Upload another file</a>
            </div>
        <?php else: ?>
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="fileToUpload">Select a <strong>.har</strong> file exported from X/Twitter network traffic:</label>
                    <input type="file" class="form-control-file" name="fileToUpload" id="fileToUpload" required>
                    <small class="form-text text-muted">Maximum file size: 100MB (as configured in Docker/PHP).</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Upload & Process</button>
            </form>
        <?php endif; ?>
    </div>
    
    <p class="text-center text-muted mt-4"><small>v1.2 | Dockerized Deployment</small></p>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>
