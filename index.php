<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

$config = loadConfig();
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';
$thumbSize = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
$useRedirectMode = isset($_GET['redirect']) ? ($_GET['redirect'] === '1' || $_GET['redirect'] === 'true') : (isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false);
$thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.thumbs';
$indexCache = isset($config['indexCache']) ? $config['indexCache'] : '.cache.index';

$cacheFile = $indexCache ? __DIR__ . '/' . $indexCache : "";
$scanResults = [];

// Check if cache file exists
if (file_exists($cacheFile)) {
    // Read from cache
    $cachedData = file_get_contents($cacheFile);
    $scanResults = json_decode($cachedData, true);
} else {
    // Scan directories for images
    $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    $baseDir = __DIR__;

    // Get all subdirectories
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            $dirPath = $item->getPathname();
            $dirName = $item->getFilename();

            // Skip hidden directories
            if (strpos($dirName, '.') === 0) {
                continue;
            }

            // Get relative path from base directory
            $relativePath = str_replace('\\', '/', str_replace($baseDir . DIRECTORY_SEPARATOR, '', $dirPath));

            // Scan for images in this directory
            $images = [];
            $dirFiles = scandir($dirPath);

            if ($dirFiles !== false) {
                foreach ($dirFiles as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $filePath = $dirPath . '/' . $file;

                    // Check if it's a file (not directory)
                    if (!is_file($filePath)) {
                        continue;
                    }

                    // Check file extension
                    $pathInfo = pathinfo($file);
                    $extension = strtolower(isset($pathInfo['extension']) ? $pathInfo['extension'] : '');

                    if (in_array($extension, $supportedExtensions)) {
                        $imageName = str_replace('-', ' ', isset($pathInfo['filename']) ? $pathInfo['filename'] : $file);
                        $imageRelativePath = str_replace('\\', '/', str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath));

                        $images[] = [
                            'name' => $imageName,
                            'path' => $imageRelativePath,
                            'filename' => $file
                        ];
                    }
                }
            }

            // Only add directories that have images
            if (!empty($images)) {
                // S.cacheort images alphabetically by name (case-insensitive)
                usort($images, function($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });

                $scanResults[$relativePath] = [
                    'name' => str_replace('-', ' ', $dirName),
                    'images' => $images
                ];
            }
        }
    }

    // Sort folders alphabetically by name (case-insensitive)
    uasort($scanResults, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    // Save to cache
    if ($cacheFile) {
        file_put_contents($cacheFile, json_encode($scanResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="index.css" />
</head>
<body>
    <h1 class="title"><?= htmlspecialchars($title) ?></h1>

    <!--
    <?php if ($useRedirectMode): ?>
        <p style="text-align: center; color: #3498db; margin-bottom: 20px;">
            ✓ Clean URL mode enabled (<a href="?redirect=0">Switch to query string mode</a>)
        </p>
    <?php else: ?>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">
            Query string mode (<a href="?redirect=1">Enable clean URLs</a>)
        </p>
    <?php endif; ?>
    -->

    <?php if (empty($scanResults)): ?>
        <p style="text-align: center; color: #999;">No images found.</p>
    <?php else: ?>
        <?php foreach ($scanResults as $folderPath => $folderData): ?>
            <div class="section">
                <h2 class="section-title"><?= htmlspecialchars($folderData['name']) ?></h2>
                <div
                    class="image-grid"
                    style="grid-template-columns: repeat(auto-fill, minmax(<?= htmlspecialchars($thumbSize) ?>px, 1fr))"
                >
                    <?php foreach ($folderData['images'] as $image): ?>
                        <div class="image-item">
                            <?php
                            // Build the view URL based on redirect mode
                            // Use str_replace to keep slashes unencoded
                            $encodedPath = str_replace('%2F', '/', rawurlencode($image['path']));

                            if ($useRedirectMode) {
                                $viewUrl = 'view/' . $encodedPath;
                            } else {
                                $viewUrl = 'view.php?show=' . $encodedPath;
                            }
                            ?>
                            <a href="<?= $viewUrl ?>">
                                <img
                                    src="thumb.php?show=<?= $encodedPath ?>"
                                    alt="<?= htmlspecialchars($image['name']) ?>"
                                    style="height: <?= htmlspecialchars($thumbSize) ?>px"
                                    loading="lazy"
                                />
                                <div class="image-name"><?= htmlspecialchars($image['name']) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
