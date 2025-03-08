<?php
// Update the path to point to the correct location in the Docker container
define("UPLOAD_FOLDER", "UPLOAD_FOLDER/");
define("PYTHON_SCRIPT", "/app/xscraper.py");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload for Processing</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { margin-top: 20px; }
        .form-upload { margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1 class="text-center">Welcome to the HAR File Processor</h1>
    <p class="text-center">Please upload a .har file to receive processed tweets and users CSV files.</p>

    <?php if ($_SERVER["REQUEST_METHOD"] === "POST"): ?>
        <?php
        $uniqueId = uniqid(date('YmdHis') . '_', true); // Unique identifier
        $uploadedFileName = "$uniqueId.har"; // Unique uploaded file name
        $target_file = UPLOAD_FOLDER . $uploadedFileName;
        $script_arg = $uploadedFileName;

        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            echo "<div class='alert alert-success'>The file has been uploaded.</div>";

            $output = [];
            $return_var = 0;
            exec("/usr/local/bin/python3 " . escapeshellarg(PYTHON_SCRIPT) . " " . escapeshellarg($script_arg) . " 2>&1", $output, $return_var);

            if ($return_var == 0 && !empty($output)) {
                $tweetsFile = UPLOAD_FOLDER . "tweets_$uniqueId.csv";
                $usersFile = UPLOAD_FOLDER . "users_$uniqueId.csv";

                if (file_exists($tweetsFile) && file_exists($usersFile)) {
                    echo "<div class='alert alert-success'>Script executed successfully.</div>";
                    echo "<div>Files created:</div>";
                    echo "<a href='$tweetsFile'>Download Tweets CSV</a> - <a href='view_tweets.php?url=tweets_$uniqueId.csv' target=_blank>View tweets</a><br>";
                    echo "<a href='$usersFile'>Download Users CSV</a>  - <a href='view_tweets.php?url=users_$uniqueId.csv' target=_blank>View users</a><br>";
                } else {
                    echo "<div class='alert alert-danger'>Error: Output files do not exist.</div>";
                    echo "<div>Script output: <pre>" . implode("\n", $output) . "</pre></div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Error in script execution.</div>";
                echo "<div>Script output: <pre>" . implode("\n", $output) . "</pre></div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Sorry, there was an error uploading your file.</div>";
        }
        ?>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form action="index.php" method="post" enctype="multipart/form-data" class="form-upload">
                    <div class="form-group">
                        <label for="fileToUpload">Select .har file to upload:</label>
                        <input type="file" class="form-control-file" name="fileToUpload" id="fileToUpload" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="submit">Upload File</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>