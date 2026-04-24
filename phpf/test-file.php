<?php
session_start();

$files = $_SESSION['form']['step1']['id_passport_files'] ?? [];

if (!$files) {
    die("No files uploaded.");
}

echo "<h3>Total files: " . count($files) . "</h3>";

foreach ($files as $i => $file) {

    echo "<hr>";
    echo "<strong>{$file['filename']}</strong><br>";
    echo "Type: {$file['mime']}<br>";
    echo "Size: {$file['size']} bytes<br>";

    if (str_starts_with($file['mime'], 'image/')) {
        echo "<img src='data:{$file['mime']};base64,{$file['data']}' style='max-width:250px;display:block;'>";
    } else {
        echo "<a href='download.php?i={$i}' target='_blank'>Download</a>";
    }
}
