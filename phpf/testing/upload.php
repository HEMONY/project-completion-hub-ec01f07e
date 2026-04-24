<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Init session storage
    $_SESSION['files'] ??= [];

    if (!empty($_FILES['docs']['name'][0])) {

        foreach ($_FILES['docs']['tmp_name'] as $i => $tmp) {

            if ($_FILES['docs']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $_SESSION['files'][] = [
                'name' => $_FILES['docs']['name'][$i],
                'type' => $_FILES['docs']['type'][$i],
                'size' => $_FILES['docs']['size'][$i],
                'data' => base64_encode(file_get_contents($tmp))
            ];
        }
    }

    header("Location: show.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload</title>
</head>
<body>

<h3>Upload multiple files (ONE submit)</h3>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="docs[]" multiple accept=".pdf,.jpg,.jpeg,.png">
    <br><br>
    <button type="submit">Upload</button>
</form>

</body>
</html>
