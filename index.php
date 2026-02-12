<?php
/**
 * Consolidated index.php for Xscraper
 * Fixes naming convention and restores View Table/Styling features.
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
$tweetsFile = "";
$usersFile = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["fileToUpload"])) {
    // 1. Get original filename and sanitize (No timestamp prefix added here)
    $originalName = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_FILENAME);
    $sanitizedName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalName);
    
    // The HAR file will be saved as "OriginalName.har"
    $baseFileName = $sanitizedName;
    $uploadedFileName = $baseFileName . ".har";
    $targetFile = UPLOAD_FOLDER . $uploadedFileName;

    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
        $output = [];
        $return_var = 0;
        
        // 2. Execute Python script
	$pythonPath = trim(shell_exec('which python3'));
	if (empty($pythonPath)) {
	    $pythonPath = 'python3';
	}

	$command = $pythonPath . " " . escapeshellarg(PYTHON_SCRIPT) . " " . escapeshellarg($targetFile) . " 2>&1";
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            // 3. Scan directory to find the files xscraper.py created (it adds its own timestamp postfix)
            $files = scandir(UPLOAD_FOLDER);
            foreach ($files as $file) {
                // Find files starting with "OriginalName_tweets_" and "OriginalName_users_"
                if (strpos($file, $baseFileName . "_tweets_") === 0) {
                    $tweetsFile = $file;
                }
                if (strpos($file, $baseFileName . "_users_") === 0) {
                    $usersFile = $file;
                }
            }
            
            if ($tweetsFile && $usersFile) {
                 $message = "File processed successfully!";
                 $processed = true;
            } else {
                 $message = "Error: Output files not found. Check if Python script produced files correctly.";
            }
        } else {
            $message = "Error executing Python script. Output: " . implode("\n", $output);
        }
    } else {
        $message = "Error uploading file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X Scraper - HAR to CSV</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding-top: 50px; padding-bottom: 50px; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .footer { margin-top: 20px; text-align: center; color: #777; }
        .btn-group-responsive { display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; }
        @media (max-width: 576px) {
            .list-group-item { flex-direction: column; align-items: flex-start !important; }
            .btn-group-responsive { width: 100%; justify-content: flex-start; margin-top: 10px; }
            .file-name { margin-bottom: 5px; word-break: break-all; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h2 class="m-0">X Scraper</h2>
        <a href="https://github.com/wsaqaf/xscraper" class="btn btn-outline-dark btn-sm" target="_blank">Source code</a>
    </div>
    
    <p class="lead text-center mb-4">Convert X (Twitter) HAR files to clean CSV datasets.</p>

    <?php if ($message): ?>
        <div class="alert <?php echo $processed ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
    <?php endif; ?>

    <div class="card p-4 border-0 bg-light">
        <?php if ($processed): ?>
            <h4 class="card-title text-success">Processing Complete</h4>
            <p>Your data is ready for download:</p>
            <div class="list-group shadow-sm">
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                    <span class="file-name text-break mr-2"><strong>Tweets CSV</strong> <br><small class="text-muted"><?php echo $tweetsFile; ?></small></span>
                    <div class="btn-group-responsive">
                        <a href="UPLOAD_FOLDER/<?php echo $tweetsFile; ?>" class="btn btn-sm btn-outline-primary" download>Download</a>
                        <a href="view_tweets.php?url=<?php echo $tweetsFile; ?>" class="btn btn-sm btn-primary" target="_blank">View Table</a>
                    </div>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                     <span class="file-name text-break mr-2"><strong>Users CSV</strong> <br><small class="text-muted"><?php echo $usersFile; ?></small></span>
                     <div class="btn-group-responsive">
                        <a href="UPLOAD_FOLDER/<?php echo $usersFile; ?>" class="btn btn-sm btn-outline-primary" download>Download</a>
                        <a href="view_tweets.php?url=<?php echo $usersFile; ?>" class="btn btn-sm btn-primary" target="_blank">View Table</a>
                    </div>
                </div>
            </div>
            <a href="index.php" class="btn btn-link mt-4 d-block text-center">Upload another file</a>
        <?php else: ?>
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="fileToUpload">Select a <strong>.har</strong> file exported from X/Twitter network traffic:</label>
                    <input type="file" class="form-control-file border p-2 bg-white rounded" name="fileToUpload" id="fileToUpload" required accept=".har,.json">
                    <small class="form-text text-muted">Original filename will be preserved.</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg shadow-sm">Upload & Process</button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <small>v1.2 | Dockerized Deployment</small>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>
