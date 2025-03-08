<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tweets/Users Display</title>
    <link href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        img.media-image { width: 100px; height: auto; }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="mb-4">Tweets/Users Display</h2>
    <table id="tweetsTable" class="table table-bordered"  style="margin-left: 0 !important; padding-left: 0 !mportant;">
        <thead>
            <tr>
                <!-- Table headers will be populated here -->
            </tr>
        </thead>
        <tbody>
            <!-- Table body will be populated here -->
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#tweetsTable').DataTable();
    });
</script>

</body>
</html>

<?php
// Assuming you have validated and sanitized the 'url' query parameter before using it
$csvUrl = "https://medialab.sh.se/mecodify/xscraper/UPLOAD_FOLDER/" . htmlspecialchars($_GET['url']);

// Function to generate HTML table from CSV data
function generateTableFromCSV($url) {
    if (($handle = fopen($url, "r")) !== FALSE) {
        $headers = fgetcsv($handle); // Read the headers
        echo "<script>document.querySelector('#tweetsTable thead tr').innerHTML = '" . implode('', array_map(fn($header) => "<th>$header</th>", $headers)) . "';</script>";

        while (($data = fgetcsv($handle)) !== FALSE) {
            $rowHTML = "<tr>";
            foreach ($data as $index => $value) {
                // Check if the value is a URL, and not in the 'media_link' column
                if (filter_var($value, FILTER_VALIDATE_URL) && $headers[$index] != 'media_link' && $headers[$index] != 'user_image_url') {
                    // Convert URLs to clickable links
                    $rowHTML .= "<td><a href='$value' target='_blank'>$value</a></td>";
                } elseif ( ( $headers[$index] =='user_image_url' || $headers[$index] == 'media_link') && !empty($value)) {
                    // Special handling for 'media_link' to convert to <img> tag
                    $rowHTML .= "<td><img src='$value' class='media-image'></td>";
                } elseif ($headers[$index]=='user_screen_name')
		{
		    $rowHTML .= "<td><a href='https://twitter.com/$value' target='_blank'>$value</a></td>";
		}
		 else {
                    // For normal text or empty values
                    $rowHTML .= "<td>$value</td>";
                }
            }
            $rowHTML .= "</tr>";

            // Output the row directly into the tbody of the table
            echo "<script>document.querySelector('#tweetsTable tbody').insertAdjacentHTML('beforeend', `" . addslashes($rowHTML) . "`);</script>";
        }
        fclose($handle);
    }
}

// Call the function to generate the table
generateTableFromCSV($csvUrl);
?>

