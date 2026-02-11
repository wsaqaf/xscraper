<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tweets/Users Display</title>
    <link href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        img.media-image { width: 100px; height: auto; border-radius: 5px; }
        .container-fluid { padding: 20px; }
        table td { vertical-align: middle !important; }
    </style>
</head>
<body>

<div class="container-fluid">
    <h2 class="mb-4">Processed Data View</h2>
    <table id="tweetsTable" class="table table-striped table-bordered w-100">
        <thead>
            <tr></tr>
        </thead>
        <tbody>
            </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>

<?php
// Use a local path for portability
$fileName = htmlspecialchars($_GET['url']);
$localFilePath = __DIR__ . "/UPLOAD_FOLDER/" . $fileName;

function generateTableFromCSV($filePath) {
    if (!file_exists($filePath)) {
        echo "<div class='alert alert-danger'>File not found: " . basename($filePath) . "</div>";
        return;
    }

    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $headers = fgetcsv($handle);

        // Inject Headers
        $headerHtml = "";
        foreach ($headers as $header) {
            $headerHtml .= "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "<script>document.querySelector('#tweetsTable thead tr').innerHTML = \"$headerHtml\";</script>";

        // Process Rows
        while (($data = fgetcsv($handle)) !== FALSE) {
            $rowHTML = "<tr>";
            foreach ($data as $index => $value) {
                $colName = $headers[$index];
                $valSafe = htmlspecialchars($value);

                if ($colName == 'user_screen_name') {
                    $rowHTML .= "<td><a href='https://x.com/$valSafe' target='_blank'>@$valSafe</a></td>";
                } elseif (($colName == 'user_image_url' || $colName == 'media_link') && !empty($value)) {
                    $rowHTML .= "<td><img src='$value' class='media-image'></td>";
                } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                    $rowHTML .= "<td><a href='$valSafe' target='_blank'>Link</a></td>";
                } else {
                    $rowHTML .= "<td>" . nl2br($valSafe) . "</td>";
                }
            }
            $rowHTML .= "</tr>";

            // Escape backticks and newlines for JS insertion
            $jsRow = str_replace(["`", "\r", "\n"], ["\\`", "", ""], $rowHTML);
            echo "<script>document.querySelector('#tweetsTable tbody').insertAdjacentHTML('beforeend', `$jsRow`);</script>";
        }
        fclose($handle);
    }
}

generateTableFromCSV($localFilePath);
?>

<script>
    $(document).ready(function() {
        // Initialize DataTable AFTER PHP has injected the rows
        $('#tweetsTable').DataTable({
            "pageLength": 25,
            "order": [], // Maintain CSV order
            "scrollX": true
        });
    });
</script>

</body>
</html>
