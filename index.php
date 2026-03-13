<?php

require_once "xscraper.php"; // Ensure this file is in the same folder

define("UPLOAD_FOLDER", __DIR__ . "/UPLOAD_FOLDER/");

if (!is_dir(UPLOAD_FOLDER)) {
    mkdir(UPLOAD_FOLDER, 0777, true);
}

$message = "";
$processed = false;
$tweetsFile = "";
$usersFile = "";
$jsonFile = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["fileToUpload"])) {
    $originalName = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_FILENAME);
    $sanitizedName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalName);

    $targetFile = UPLOAD_FOLDER . $sanitizedName . ".har";

    if ($_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = $_FILES['fileToUpload']['error'];
        $message = "Upload failed with error code: " . $uploadError;
    }
    elseif (!is_writable(UPLOAD_FOLDER)) {
        $message = "Error: UPLOAD_FOLDER is not writable. Check permissions.";
    }
    else {
        $uploadedTmpParams = $_FILES["fileToUpload"]["tmp_name"];
        $shouldProcess = true;

        // Check if file already exists and has same content (MD5 check)
        if (file_exists($targetFile)) {
            $existingHash = md5_file($targetFile);
            $newHash = md5_file($uploadedTmpParams);

            if ($existingHash === $newHash) {
                // File matches! Look for existing generated CSVs for this file base
                // Pattern: sanitizedName + "_tweets_" + timestamp + ".csv"
                $existingTweets = glob(UPLOAD_FOLDER . $sanitizedName . '_tweets_*.csv');
                $existingUsers = glob(UPLOAD_FOLDER . $sanitizedName . '_users_*.csv');

                if ($existingTweets && $existingUsers) {
                    // Sort to get the latest ones
                    usort($existingTweets, function ($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    usort($existingUsers, function ($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });

                    $tweetsFile = basename($existingTweets[0]);
                    $usersFile = basename($existingUsers[0]);

                    // Try to find the matching JSON file
                    $existingJson = glob(UPLOAD_FOLDER . $sanitizedName . '_data_*.json');
                    if ($existingJson) {
                        usort($existingJson, function ($a, $b) {
                            return filemtime($b) - filemtime($a);
                        });
                        $jsonFile = basename($existingJson[0]);
                    }

                    // Count lines helper
                    $countCsv = function ($file) {
                        $c = 0;
                        if (($handle = fopen($file, "r")) !== FALSE) {
                            while (fgets($handle) !== FALSE)
                                $c++;
                            fclose($handle);
                        }
                        return $c > 0 ? $c - 1 : 0; // subtract header
                    };
                    $tCount = $countCsv($existingTweets[0]);
                    $uCount = $countCsv($existingUsers[0]);

                    $message = "File with identical content already exists. Found $tCount tweets and $uCount users (from " . date("M d, Y H:i", filemtime($existingTweets[0])) . ").";
                    $processed = true;
                    $shouldProcess = false;
                }
            }
        }

        if ($shouldProcess) {
            if (move_uploaded_file($uploadedTmpParams, $targetFile)) {
                try {
                    // Instantiate the engine from xscraper.php
                    $engine = new XScraperEngine(UPLOAD_FOLDER);
                    $result = $engine->processHar($targetFile);

                    if ($result) {
                        $tweetsFile = $result['tweets'];
                        $usersFile = $result['users'];
                        $jsonFile = $result['json'] ?? "";
                        $tCount = $result['tweet_count'] ?? 0;
                        $uCount = $result['user_count'] ?? 0;
                        $message = "File processed successfully! Found $tCount tweets and $uCount users.";
                        $processed = true;
                    }
                    else {
                        $message = "Error: PHP Engine failed to process the file. Check if the HAR file is valid JSON.";
                    }
                }
                catch (Exception $e) {
                    $message = "System Error: " . $e->getMessage();
                }
            }
            else {
                $message = "Error uploading file. Check folder permissions for UPLOAD_FOLDER.";
            }
        }
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
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
            padding-bottom: 50px;
        }

        .container {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .btn-group-responsive {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h2 class="m-0">X Scraper</h2>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Refresh</a>
        </div>

        <p class="lead text-center mb-4">Convert X (Twitter) HAR files to CSV datasets.</p>

        <?php if ($message): ?>
        <div class="alert <?php echo $processed ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
        <?php
endif; ?>

        <div class="card p-4 border-0 bg-light">
            <?php if ($processed): ?>
            <h4 class="card-title text-success">Processing Complete</h4>
            <div class="list-group shadow-sm">
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                    <span><strong>Tweets CSV</strong><br><small>
                            <?php echo $tweetsFile; ?>
                        </small></span>
                    <div class="btn-group-responsive">
                        <a href="UPLOAD_FOLDER/<?php echo $tweetsFile; ?>" class="btn btn-sm btn-outline-primary"
                            download>Download</a>
                        <a href="view_tweets.php?url=<?php echo $tweetsFile; ?>" class="btn btn-sm btn-primary"
                            target="_blank">View Table</a>
                    </div>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                    <span><strong>Users CSV</strong><br><small>
                            <?php echo $usersFile; ?>
                        </small></span>
                    <div class="btn-group-responsive">
                        <a href="UPLOAD_FOLDER/<?php echo $usersFile; ?>" class="btn btn-sm btn-outline-primary"
                            download>Download</a>
                        <a href="view_tweets.php?url=<?php echo $usersFile; ?>" class="btn btn-sm btn-primary"
                            target="_blank">View Table</a>
                    </div>
                </div>
                <?php if ($jsonFile): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                    <span><strong>All Data JSON</strong><br><small>
                            <?php echo $jsonFile; ?>
                        </small></span>
                    <div class="btn-group-responsive">
                        <a href="UPLOAD_FOLDER/<?php echo $jsonFile; ?>" class="btn btn-sm btn-outline-info"
                            download>Download JSON</a>
                    </div>
                </div>
                <?php
    endif; ?>
            </div>
            <a href="index.php" class="btn btn-link mt-4 d-block text-center">Upload another file</a>
            <?php
else: ?>
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="fileToUpload">Select a <strong>.har</strong> file:</label>
                    <input type="file" class="form-control-file border p-2 bg-white rounded" name="fileToUpload"
                        id="fileToUpload" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg shadow-sm">Upload & Process</button>
            </form>
            <?php
endif; ?>
        </div>

        <?php
// SECTION: List Existing Files
$existing_csvs = array_merge(glob(UPLOAD_FOLDER . '*.csv'), glob(UPLOAD_FOLDER . '*.json'));
if ($existing_csvs) {
    // Sort by modification time (newest first)
    usort($existing_csvs, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    echo '<div class="mt-5">';
    echo '<h5 class="mb-3">Previously Processed Files</h5>';
    echo '<div class="list-group shadow-sm">';

    foreach ($existing_csvs as $csv) {
        $filename = basename($csv);
        $date = date("M d, Y H:i", filemtime($csv));
        $size = round(filesize($csv) / 1024, 1) . ' KB';

        // Identify type
        $isTweets = strpos($filename, '_tweets_') !== false;
        $isUsers = strpos($filename, '_users_') !== false;
        $isJson = strpos($filename, '.json') !== false;

        $badgeClass = $isTweets ? 'badge-info' : ($isUsers ? 'badge-warning' : ($isJson ? 'badge-dark' : 'badge-secondary'));
        $type = $isTweets ? 'Tweets' : ($isUsers ? 'Users' : ($isJson ? 'JSON' : 'File'));

        echo '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
        echo '<div>';
        echo '<span class="badge ' . $badgeClass . ' mr-2">' . $type . '</span>';
        echo '<span class="text-dark font-weight-bold">' . htmlspecialchars($filename) . '</span>';
        echo '<br><small class="text-muted">Processed: ' . $date . ' • Size: ' . $size . '</small>';
        echo '</div>';
        echo '<div class="btn-group-sm">';
        if (!$isJson) {
            echo '<a href="view_tweets.php?url=' . urlencode($filename) . '" class="btn btn-outline-primary mr-1" target="_blank">View</a>';
        }
        echo '<a href="UPLOAD_FOLDER/' . urlencode($filename) . '" class="btn btn-outline-secondary" download>Download</a>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}
?>
    </div>

    <footer class="text-center mt-5 mb-4 text-muted">
        <small>
            <a href="https://github.org/wsaqaf/xscraper" target="_blank" class="text-secondary text-decoration-none">
                View on GitHub
            </a>
        </small>
    </footer>

</body>

</html>