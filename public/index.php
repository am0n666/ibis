<?php
// Simple yet configurable web interface for ibis-next
// Requires ibis-next CLI installed and available in PATH

function handle_post()
{
    if (!isset($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="alert alert-danger">File upload failed.</div>';
        return;
    }

    $format = $_POST['format'] ?? 'pdf';
    $theme = $_POST['theme'] ?? 'light';
    $configText = $_POST['config'] ?? '';

    $tmpDir = sys_get_temp_dir() . '/ibis_' . uniqid();
    mkdir($tmpDir, 0777, true);

    $zipPath = $_FILES['archive']['tmp_name'];

    $zip = new ZipArchive();
    if ($zip->open($zipPath) === true) {
        $zip->extractTo($tmpDir);
        $zip->close();
    } else {
        echo '<div class="alert alert-danger">Could not extract archive.</div>';
        return;
    }

    if (!empty($configText)) {
        file_put_contents("$tmpDir/ibis.php", $configText);
    }

    $cmd = escapeshellcmd("ibis-next $format $theme --workingdir \"$tmpDir\"");
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);

    echo '<pre class="mt-3">' . htmlspecialchars(implode("\n", $output)) . '</pre>';

    if ($code === 0) {
        $ext = $format === 'pdf' ? 'pdf' : ($format === 'epub' ? 'epub' : 'html');
        $filePath = "$tmpDir/export/book.$ext";
        if (file_exists($filePath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="book.' . $ext . '"');
            readfile($filePath);
            return;
        } else {
            echo '<div class="alert alert-warning">Output file not found.</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Error running ibis-next.</div>';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h1 class="mb-4">Ibis Next UI</h1>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-12">
            <label for="archive" class="form-label">Upload zipped book content</label>
            <input type="file" class="form-control" name="archive" id="archive" required>
        </div>
        <div class="col-md-6">
            <label for="format" class="form-label">Format</label>
            <select name="format" id="format" class="form-select">
                <option value="pdf">PDF</option>
                <option value="epub">EPUB</option>
                <option value="html">HTML</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="theme" class="form-label">Theme</label>
            <select name="theme" id="theme" class="form-select">
                <option value="light">Light</option>
                <option value="dark">Dark</option>
            </select>
        </div>
        <div class="col-12">
            <label for="config" class="form-label">ibis.php configuration</label>
            <textarea class="form-control" name="config" id="config" rows="10" placeholder="Paste ibis.php content here to override"></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Generate</button>
        </div>
    </form>
</body>
</html>
