<?php
session_start();

$files = $_SESSION['files'] ?? [];

if (!$files) {
    die("No files in session.");
}

echo "<h3>Total files: " . count($files) . "</h3>";

foreach ($files as $i => $file) {

    echo "<hr>";
    echo "File #" . ($i + 1) . "<br>";
    echo "Name: {$file['name']}<br>";
    echo "Type: {$file['type']}<br>";
    echo "Size: {$file['size']} bytes<br>";

    if (str_starts_with($file['type'], 'image/')) {
        echo "<img src='data:{$file['type']};base64,{$file['data']}' style='max-width:200px;display:block;'>";
    } else {
        echo "<a href='download.php?i={$i}'>Download</a>";
    }
}
