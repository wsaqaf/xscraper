<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tweets/Users Display</title>
    <link href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Remove all margins and padding from body and html */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow-x: auto;
        }
        
        /* Remove container margins and make it full-width */
        .container-fluid {
            margin: 0;
            padding: 0;
            width: 100%;
            max-width: 100%;
        }
        
        /* Make table take full width */
        .dataTables_wrapper {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        
        /* Ensure images are properly sized */
        img.media-image {
            width: 100px;
            height: auto;
        }
        
        /* Style header to be less obtrusive */
        .header {
            background-color: #f8f9fa;
            padding: 5px 15px;
            margin-bottom: 5px;
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Table styling */
        table.dataTable {
            width: 100% !important;
            margin: 0 !important;
        }
        
        /* Cell styling */
        table.dataTable td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px; /* Set a reasonable max-width for cells */
        }
        
        /* Expandable cells on hover */
        table.dataTable td:hover {
            white-space: normal;
            overflow: visible;
            max-width: none;
        }
        
        /* Add some padding to controls but keep them minimal */
        .dataTables_length, .dataTables_filter {
            padding: 8px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Tweets/Users Display</h4>
        <a href="index.php" class="btn btn-sm btn-primary">Back to Home</a>
    </div>
</div>

<div class="container-fluid">
    <?php
    // Sanitize the URL parameter
    $csvUrl = "";
    if (isset($_GET['url'])) {
        $file = htmlspecialchars($_GET['url']);
        $csvUrl = "UPLOAD_FOLDER/" . $file;
    }
    
    if (!empty($csvUrl) && file_exists($csvUrl)) {
        // Read the CSV file
        if (($handle = fopen($csvUrl, "r")) !== FALSE) {
            $headers = fgetcsv($handle); // Read the headers
            $rows = [];
            
            // Read all data rows
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
            
            if (!empty($headers) && !empty($rows)) {
                echo '<table id="tweetsTable" class="display nowrap" style="width:100%">';
                
                // Output table headers
                echo '<thead><tr>';
                foreach ($headers as $header) {
                    echo '<th>' . htmlspecialchars($header) . '</th>';
                }
                echo '</tr></thead>';
                
                // Output table body
                echo '<tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $index => $value) {
                        $header = isset($headers[$index]) ? $headers[$index] : '';
                        
                        // Check if the value is a URL and not in certain columns
                        if (filter_var($value, FILTER_VALIDATE_URL) && $header != 'media_link' && $header != 'user_image_url') {
                            // Convert URLs to clickable links
                            echo '<td><a href="' . htmlspecialchars($value) . '" target="_blank">' . htmlspecialchars($value) . '</a></td>';
                        } 
                        // Special handling for media_link and user_image_url to convert to <img> tag
                        elseif (($header == 'media_link' || $header == 'user_image_url') && !empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                            echo '<td><img src="' . htmlspecialchars($value) . '" class="media-image"></td>';
                        }
                        // Special handling for user_screen_name 
                        elseif ($header == 'user_screen_name' && !empty($value)) {
                            echo '<td><a href="https://twitter.com/' . htmlspecialchars($value) . '" target="_blank">' . htmlspecialchars($value) . '</a></td>';
                        }
                        else {
                            // For normal text or empty values
                            echo '<td>' . htmlspecialchars($value) . '</td>';
                        }
                    }
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                
                // Initialize DataTables with specific options for full width
                echo '<script src="https://code.jquery.com/jquery-3.5.1.js"></script>';
                echo '<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>';
                echo '<script>
                    $(document).ready(function() {
                        $("#tweetsTable").DataTable({
                            "scrollX": true,
                            "scrollCollapse": true,
                            "paging": true,
                            "pageLength": 50,
                            "autoWidth": true,
                            "ordering": true,
                            "responsive": false,
                            "fixedHeader": true,
                            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
                        });
                    });
                </script>';
            } else {
                echo '<div class="alert alert-warning m-3">The CSV file appears to be empty or malformed.</div>';
            }
        } else {
            echo '<div class="alert alert-danger m-3">Could not open the CSV file. Please check the file path.</div>';
        }
    } else {
        echo '<div class="alert alert-danger m-3">Please provide a valid CSV file URL parameter.</div>';
    }
    ?>
</div>

</body>
</html>