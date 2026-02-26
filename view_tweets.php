<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X Scraper Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container-fluid {
            padding: 20px;
        }

        .table-responsive {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .media-preview {
            max-height: 80px;
            width: auto;
            cursor: pointer;
            border-radius: 4px;
            transition: transform 0.2s;
        }

        .media-preview:hover {
            transform: scale(3.5);
            z-index: 1050;
            position: relative;
        }

        .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Fix alignment and overflow */
        td,
        th {
            font-size: 0.85rem;
            vertical-align: middle;
            white-space: nowrap;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        td:hover {
            white-space: normal;
            overflow: visible;
            word-break: break-all;
        }

        thead th {
            background-color: #212529 !important;
            color: white !important;
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="m-0">X Scraper Viewer</h2>
            <a href="index.php" class="btn btn-outline-primary btn-sm">Upload New File</a>
        </div>

        <?php
$upload_dir = 'UPLOAD_FOLDER/';

// 1. Scan and Sort CSV Files
$csv_files = glob($upload_dir . '*.csv');
if ($csv_files) {
    usort($csv_files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
}
else {
    $csv_files = [];
}

$raw_filename = $_GET['url'] ?? ($_GET['file'] ?? '');
$selected_file = basename($raw_filename);
$file_path = $upload_dir . $selected_file;
?>

        <form action="" method="get" class="mb-4 p-3 bg-light rounded border">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <label for="fileSelect" class="col-form-label fw-bold">Select Dataset:</label>
                </div>
                <div class="col-md-6">
                    <select name="url" id="fileSelect" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose a file --</option>
                        <?php foreach ($csv_files as $file): ?>
                        <?php
    $basename = basename($file);
    $date = date("Y-m-d H:i", filemtime($file));
?>
                        <option value="<?= htmlspecialchars($basename)?>" <?= $selected_file === $basename ? 'selected'
        : '' ?>>
                            <?= htmlspecialchars($basename)?> (
                            <?= $date?>)
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div class="col-auto ms-auto">
                    <small class="text-muted">Sorted by Date (Newest First)</small>
                </div>
            </div>
        </form>

        <?php

if ($selected_file && file_exists($file_path)) {
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 0, ",", "\"", "\\");
        $rows = [];
        $visible_indices = [];

        // 1. Read all rows first to determine which columns actually have data
        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            $rows[] = $data;
            foreach ($data as $index => $value) {
                if (!isset($visible_indices[$index]) && trim((string)$value) !== '') {
                    $visible_indices[$index] = true;
                }
            }
        }
        fclose($handle);

        if (!empty($headers)) {
            $rowCount = count($rows);
            echo "<div class='alert alert-info py-2'><strong>Total Records:</strong> $rowCount</div>";
            echo '<div class="table-responsive">';
            echo '<table id="tweetsTable" class="table table-striped table-bordered table-hover table-sm w-100">';

            // HEADER
            echo '<thead><tr>';
            foreach ($headers as $index => $col_name) {
                if (isset($visible_indices[$index])) {
                    echo "<th>" . htmlspecialchars($col_name) . "</th>";
                }
            }
            echo '</tr></thead>';

            // BODY
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($headers as $index => $col_name) {
                    if (isset($visible_indices[$index])) {
                        $cell = $row[$index] ?? '';

                        // Formatting logic
                        if (strpos($col_name, 'user_image_url') !== false && $cell) {
                            echo "<td class='text-center'><img src='$cell' class='avatar-img'></td>";
                        }
                        elseif (strpos($col_name, 'media_link') !== false && $cell) {
                            $links = explode(' ', $cell);
                            echo "<td>";
                            foreach ($links as $link) {
                                if (trim($link))
                                    echo "<img src='$link' class='media-preview' title='Click to zoom'>";
                            }
                            echo "</td>";
                        }
                        else {
                            echo "<td>" . htmlspecialchars($cell) . "</td>";
                        }
                    }
                }
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    }
}
else {
    echo '<div class="alert alert-info">Please select a CSV file to view data.</div>';
}
?>
        <footer class="text-center mt-4 mb-4 text-muted">
            <small>
                <a href="https://github.org/wsaqaf/xscraper" target="_blank"
                    class="text-secondary text-decoration-none">
                    View on GitHub
                </a>
            </small>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            if ($('#tweetsTable').length) {
                $('#tweetsTable').DataTable({
                    "pageLength": 50,
                    "scrollX": true,
                    "autoWidth": false,
                    "order": []
                });
            }
        });
    </script>
</body>

</html>