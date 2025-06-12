<?php
// Simple yet configurable web interface for ibis-next
// Requires ibis-next CLI installed and available in PATH

$base = realpath(__DIR__ . '/../assets');
foreach (['styles', 'covers', 'fonts'] as $dir) {
    if (!is_dir("$base/$dir")) {
        mkdir("$base/$dir", 0777, true);
    }
}

function list_files($path)
{
    $files = [];
    foreach (scandir($path) as $f) {
        if ($f === '.' || $f === '..') continue;
        $files[] = $f;
    }
    return $files;
}

function upload_file($field, $target)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $name = basename($_FILES[$field]['name']);
    return move_uploaded_file($_FILES[$field]['tmp_name'], "$target/$name");
}

function import_config()
{
    if (!isset($_FILES['config_file']) || $_FILES['config_file']['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    return file_get_contents($_FILES['config_file']['tmp_name']);
}

function handle_generate($base)
{
    if (!isset($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="alert alert-danger">File upload failed.</div>';
        return;
    }

    $format = $_POST['format'] ?? 'pdf';
    $theme  = $_POST['theme'] ?? 'light';
    $style  = $_POST['style'] ?? '';
    $cover  = $_POST['cover'] ?? '';
    $font   = $_POST['font'] ?? '';
    $configText = $_POST['config'] ?? '';

    if ($style) {
        $configText .= PHP_EOL . "\$config['style'] = __DIR__ . '/../assets/styles/$style';";
    }
    if ($cover) {
        $configText .= PHP_EOL . "\$config['cover'] = __DIR__ . '/../assets/covers/$cover';";
    }
    if ($font) {
        $configText .= PHP_EOL . "\$config['font'] = __DIR__ . '/../assets/fonts/$font';";
    }

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

$message = '';
$configValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'generate';
    switch ($action) {
        case 'import_config':
            $configValue = import_config();
            if ($configValue === '') $message = 'Failed to import configuration';
            else $message = 'Configuration imported';
            break;
        case 'upload_style':
            $message = upload_file('style_file', "$base/styles") ? 'Style uploaded' : 'Style upload failed';
            break;
        case 'upload_cover':
            $message = upload_file('cover_file', "$base/covers") ? 'Cover uploaded' : 'Cover upload failed';
            break;
        case 'upload_font':
            $message = upload_file('font_file', "$base/fonts") ? 'Font uploaded' : 'Font upload failed';
            break;
        default:
            handle_generate($base);
            exit;
    }
}

$styles = list_files("$base/styles");
$covers = list_files("$base/covers");
$fonts  = list_files("$base/fonts");
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
    <?php if ($message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="action" value="generate">
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
        <div class="col-md-4">
            <label for="style" class="form-label">Style</label>
            <select name="style" id="style" class="form-select">
                <option value="">Default</option>
                <?php foreach ($styles as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="cover" class="form-label">Cover</label>
            <select name="cover" id="cover" class="form-select">
                <option value="">None</option>
                <?php foreach ($covers as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="font" class="form-label">Font</label>
            <select name="font" id="font" class="form-select">
                <option value="">Default</option>
                <?php foreach ($fonts as $f): ?>
                <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label for="config" class="form-label">ibis.php configuration</label>
            <textarea class="form-control" name="config" id="config" rows="10" placeholder="Paste ibis.php content here to override"><?= htmlspecialchars($configValue) ?></textarea>
            <div class="mt-2">
                <button type="button" id="exportBtn" class="btn btn-secondary btn-sm">Export Configuration</button>
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Generate</button>
        </div>
    </form>

    <hr class="my-4">
    <h2>Import configuration</h2>
    <form method="post" enctype="multipart/form-data" class="mb-3">
        <input type="hidden" name="action" value="import_config">
        <div class="input-group">
            <input type="file" name="config_file" class="form-control" required>
            <button class="btn btn-secondary" type="submit">Import</button>
        </div>
    </form>

    <h2>Manage assets</h2>
    <div class="row">
        <div class="col-md-4">
            <form method="post" enctype="multipart/form-data" class="mb-3">
                <input type="hidden" name="action" value="upload_style">
                <div class="input-group">
                    <input type="file" name="style_file" class="form-control" required>
                    <button class="btn btn-secondary" type="submit">Upload Style</button>
                </div>
            </form>
        </div>
        <div class="col-md-4">
            <form method="post" enctype="multipart/form-data" class="mb-3">
                <input type="hidden" name="action" value="upload_cover">
                <div class="input-group">
                    <input type="file" name="cover_file" class="form-control" required>
                    <button class="btn btn-secondary" type="submit">Upload Cover</button>
                </div>
            </form>
        </div>
        <div class="col-md-4">
            <form method="post" enctype="multipart/form-data" class="mb-3">
                <input type="hidden" name="action" value="upload_font">
                <div class="input-group">
                    <input type="file" name="font_file" class="form-control" required>
                    <button class="btn btn-secondary" type="submit">Upload Font</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('exportBtn').addEventListener('click', function () {
        const text = document.getElementById('config').value;
        const blob = new Blob([text], {type: 'text/plain'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'ibis.php';
        link.click();
    });
    </script>
  </body>
  </html>
