<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Display</title>
    <link href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        img.media-image { width: 80px; height: auto; border-radius: 5px; }
        .container-fluid { padding: 20px; }
        table { font-size: 0.85rem; }
        .td-text { max-width: 400px; white-space: normal; }
    </style>
</head>
<body>

<div class="container-fluid">
    <h2 class="mb-4">Data View: <?php echo htmlspecialchars($_GET['url'] ?? 'No File'); ?></h2>
    
    <?php
    $fileName = $_GET['url'] ?? '';
    $localFilePath = __DIR__ . "/UPLOAD_FOLDER/" . basename($fileName);

    if (empty($fileName) || !file_exists($localFilePath)) {
        echo "<div class='alert alert-danger'>File not found or no URL provided.</div>";
    } else {
        echo '<table id="tweetsTable" class="table table-striped table-bordered w-100">';
        
        if (($handle = fopen($localFilePath, "r")) !== FALSE) {
            // 1. Read Headers
            $headers = fgetcsv($handle);
            if ($headers) {
                echo "<thead><tr>";
                foreach ($headers as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr></thead>";
            }

            // 2. Read Rows
            echo "<tbody>";
            while (($data = fgetcsv($handle)) !== FALSE) {
                echo "<tr>";
                foreach ($data as $index => $value) {
                    $colName = $headers[$index] ?? '';
                    
                    if ($colName == 'user_screen_name') {
                        echo "<td><a href='https://x.com/" . htmlspecialchars($value) . "' target='_blank'>@" . htmlspecialchars($value) . "</a></td>";
                    } elseif (($colName == 'user_image_url' || $colName == 'media_link') && !empty($value)) {
                        echo "<td><img src='" . htmlspecialchars($value) . "' class='media-image'></td>";
                    } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                        echo "<td><a href='" . htmlspecialchars($value) . "' target='_blank'>Link</a></td>";
                    } else {
                        // Use nl2br for the tweet text
                        echo "<td class='td-text'>" . nl2br(htmlspecialchars($value)) . "</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</tbody>";
            fclose($handle);
        }
        echo '</table>';
    }
    ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tweetsTable').DataTable({
            "pageLength": 25,
            "scrollX": true,
            "order": [], // Keep original order
            "autoWidth": false
        });
    });
</script>

</body>
</html>
