<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tweets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .container-fluid { padding: 20px; }
        .table-responsive { margin-top: 20px; }
        .media-preview { max-height: 80px; width: auto; cursor: pointer; border-radius: 4px; transition: transform 0.2s; }
        .media-preview:hover { transform: scale(3.5); z-index: 1000; position: relative; box-shadow: 0 4px 8px rgba(0,0,0,0.3); }
        .avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        /* Condensed text for readability */
        td, th { font-size: 0.9rem; white-space: nowrap; max-width: 300px; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        td:hover { white-space: normal; overflow: visible; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 border-bottom pb-2">
        <h2 class="m-0">X Scraper Viewer</h2>
        <?php if (!isset($_GET['url']) && !isset($_GET['file'])): ?>
            <a href="https://github.com/wsaqaf/xscraper" class="btn btn-outline-secondary btn-sm" target="_blank">Source code</a>
        <?php endif; ?>
    </div>

    <?php
    // --- CONFIGURATION ---
    $upload_dir = 'UPLOAD_FOLDER/';
    
    // 1. GET REQUESTED FILE (Support 'url' or 'file' params)
    $raw_filename = isset($_GET['url']) ? $_GET['url'] : (isset($_GET['file']) ? $_GET['file'] : '');
    $selected_file = basename($raw_filename); // Security: prevent directory traversal
    $file_path = $upload_dir . $selected_file;

    // --- LOGIC: SHOW TABLE OR SHOW MENU ---
    
    if ($selected_file && file_exists($file_path)) {
        // ==========================================
        // MODE A: VIEWING A SPECIFIC FILE
        // ==========================================
        
        echo '<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">';
        echo '<a href="view_tweets.php" class="btn btn-secondary">&larr; Choose Another File</a> ';
        echo '<a href="' . htmlspecialchars($file_path) . '" class="btn btn-success" download>Download CSV</a> ';
        echo '<span class="badge bg-primary fs-6 text-break">' . htmlspecialchars($selected_file) . '</span>';
        echo '</div>';

        // Open file safely
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            // Read Header
            $headers = fgetcsv($handle);
            
            // Read All Data to Memory (needed to check column emptiness)
            $rows = [];
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Skip completely empty lines
                if (array_filter($data, function($value) { return $value !== null && $value !== false && $value !== ''; })) { 
                    $rows[] = $data;
                }
            }
            fclose($handle);

            if (!empty($headers) && !empty($rows)) {
                $total_cols = count($headers);
                
                // --- INTELLIGENT COLUMN HIDING ---
                // Initialize visibility array: assume all hidden (false)
                $visible_cols = array_fill(0, $total_cols, false);

                // Scan every cell in the file
                foreach ($rows as $row) {
                    for ($i = 0; $i < $total_cols; $i++) {
                        if ($visible_cols[$i]) continue; // Already marked visible
                        
                        // STRICT CHECK: Show '0', hide '' or null.
                        if (isset($row[$i]) && trim((string)$row[$i]) !== '') {
                            $visible_cols[$i] = true;
                        }
                    }
                }
                
                // --- RENDER TABLE ---
                echo '<div class="table-responsive">';
                echo '<table id="tweetsTable" class="table table-striped table-bordered table-hover table-sm">';
                
                // HEADER
                echo '<thead class="table-dark">';
                echo '<tr>';
                foreach ($headers as $index => $col_name) {
                    if (isset($visible_cols[$index]) && $visible_cols[$index]) {
                        echo "<th>" . htmlspecialchars($col_name) . "</th>";
                    }
                }
                echo '</tr>';
                echo '</thead>';
                
                // BODY
                echo '<tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($headers as $index => $col_name) {
                        if (isset($visible_cols[$index]) && $visible_cols[$index]) {
                            $cell = isset($row[$index]) ? $row[$index] : '';
                            
                            // -- SMART FORMATTING --
                            
                            // 0. Explicitly ignore has_image/has_video (treat as plain text)
                            if ($col_name === 'has_image' || $col_name === 'has_video') {
                                echo "<td class='text-center'>" . htmlspecialchars($cell) . "</td>";
                            }
                            // 1. Profile Images
                            elseif (strpos($col_name, 'user_image_url') !== false && $cell) {
                                echo "<td class='text-center'><img src='" . htmlspecialchars($cell) . "' class='avatar-img' alt='user'></td>";
                            }
                            // 2. Media Links / Images (Only if NOT has_image/has_video)
                            elseif ((strpos($col_name, 'media_link') !== false || strpos($col_name, 'image') !== false) && $cell) {
                                $links = explode(' ', $cell);
                                echo "<td>";
                                foreach($links as $link) {
                                    if(trim($link)) {
                                        echo "<a href='" . htmlspecialchars($link) . "' target='_blank'><img src='" . htmlspecialchars($link) . "' class='media-preview' alt='media'></a> ";
                                    }
                                }
                                echo "</td>";
                            }
                            // 3. URLs
                            elseif (filter_var($cell, FILTER_VALIDATE_URL) && strlen($cell) > 25) {
                                echo "<td><a href='" . htmlspecialchars($cell) . "' target='_blank'>View Link</a></td>";
                            }
                            // 4. Standard Text
                            else {
                                echo "<td>" . htmlspecialchars($cell) . "</td>";
                            }
                        }
                    }
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';

            } else {
                echo "<div class='alert alert-warning'>This CSV file appears to be empty or invalid.</div>";
            }
        }
    } else {
        // ==========================================
        // MODE B: SHOW MENU (No file selected or file not found)
        // ==========================================
        
        if ($selected_file) {
            echo "<div class='alert alert-danger'>File <strong>" . htmlspecialchars($selected_file) . "</strong> not found. Please select a valid file below.</div>";
        }

        // Get file list
        $files = glob($upload_dir . '*.csv');
        
        // Show Dropdown
        echo '<div class="card p-4 bg-light shadow-sm" style="max-width: 600px; margin: 0 auto;">';
        echo '<h4 class="card-title mb-3">Select a Dataset to View</h4>';
        
        if (count($files) > 0) {
            echo '<form method="get" action="view_tweets.php">';
            echo '<div class="input-group">';
            echo '<select name="url" class="form-select">'; // Using 'url' as name to match your request
            echo '<option value="">-- Choose a CSV File --</option>';
            foreach ($files as $file) {
                $basename = basename($file);
                echo "<option value=\"$basename\">$basename</option>";
            }
            echo '</select>';
            echo '<button type="submit" class="btn btn-primary">Load Data</button>';
            echo '</div>';
            echo '</form>';
        } else {
            echo '<p class="text-muted">No CSV files found in <code>UPLOAD_FOLDER/</code>.</p>';
        }
        echo '</div>';
    }
    ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('#tweetsTable').length) {
            $('#tweetsTable').DataTable({
                "pageLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "scrollX": true,
                "order": [] // Disable initial sorting to preserve CSV order
            });
        }
    });
</script>

</body>
</html>
