<?php
// Simple web interface for ibis-next
// Requires ibis-next CLI installed and available in PATH

function handle_post() {
    if (!isset($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) {
        echo '<p>File upload failed.</p>';
        return;
    }

    $format = isset($_POST['format']) ? $_POST['format'] : 'pdf';
    $theme = isset($_POST['theme']) ? $_POST['theme'] : 'light';

    $tmpDir = sys_get_temp_dir() . '/ibis_' . uniqid();
    mkdir($tmpDir, 0777, true);

    $zipPath = $_FILES['archive']['tmp_name'];

    $zip = new ZipArchive();
    if ($zip->open($zipPath) === true) {
        $zip->extractTo($tmpDir);
        $zip->close();
    } else {
        echo '<p>Could not extract archive.</p>';
        return;
    }

    $cmd = escapeshellcmd("ibis-next $format $theme --workingdir \"$tmpDir\"");
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);

    echo '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';

    if ($code === 0) {
        $ext = $format === 'pdf' ? 'pdf' : ($format === 'epub' ? 'epub' : 'html');
        $filePath = "$tmpDir/export/book.$ext";
        if (file_exists($filePath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="book.' . $ext . '"');
            readfile($filePath);
            return;
        } else {
            echo '<p>Output file not found.</p>';
        }
    } else {
        echo '<p>Error running ibis-next.</p>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ibis Next UI</title>
</head>
<body>
    <h1>Ibis Next UI</h1>
    <form method="post" enctype="multipart/form-data">
        <p>
            <label for="archive">Upload zipped book content:</label>
            <input type="file" name="archive" id="archive" required>
        </p>
        <p>
            <label for="format">Format:</label>
            <select name="format" id="format">
                <option value="pdf">PDF</option>
                <option value="epub">EPUB</option>
                <option value="html">HTML</option>
            </select>
        </p>
        <p>
            <label for="theme">Theme:</label>
            <select name="theme" id="theme">
                <option value="light">Light</option>
                <option value="dark">Dark</option>
            </select>
        </p>
        <button type="submit">Generate</button>
    </form>
</body>
</html>
