<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

$config = loadConfig();
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';
$thumbSize = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;
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

$currentUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title) ?></title>

    <!-- OpenGraph & Twitter Card Meta Tags -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>" />
    <meta property="og:description" content="Image gallery with thumbnails and viewer" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>" />
    <meta name="twitter:description" content="Image gallery with thumbnails and viewer" />
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>" />
    <meta property="og:site_name" content="<?= htmlspecialchars($title) ?>" />
    <?php
    $baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    $firstImagePath = null;
    if (!empty($scanResults)) {
        $firstFolder = reset($scanResults);
        if (!empty($firstFolder['images'])) {
            $firstImage = reset($firstFolder['images']);
            $firstImagePath = $firstImage['path'];
        }
    }

    if ($firstImagePath):
        $encodedPath = str_replace('%2F', '/', rawurlencode($firstImagePath));
        $previewUrl = 'thumb.php?mode=full&size=' . $previewSize . '&show=' . $encodedPath;
        $thumbUrl = 'thumb.php?show=' . $encodedPath;
        $firstImageFullPath = __DIR__ . '/' . $firstImagePath;
        $imageInfo = getimagesize($firstImageFullPath);
        $aspectRatio = $imageInfo[0] / $imageInfo[1];
        $calculatedHeight = floor($previewSize / $aspectRatio);
        if ($maxHeightRatio && $calculatedHeight > $previewSize * $maxHeightRatio) {
            $calculatedHeight = $previewSize * $maxHeightRatio;
        }
        // Build URLs without escaping slashes
        $ogImageUrl = $baseUrl . $previewUrl;
        $thumbImageUrl = $baseUrl . $thumbUrl;
    ?>
    <meta property="og:image" content="<?= htmlspecialchars($thumbImageUrl) ?>" />
    <meta property="og:image:width" content="<?= $thumbSize ?>" />
    <meta property="og:image:height" content="<?= $thumbSize ?>" />
<?/*
    <meta property="og:image" content="<?= htmlspecialchars($ogImageUrl) ?>" />
    <meta property="og:image:width" content="<?= $thumbSize ?>" />
    <meta property="og:image:height" content="<?= floor($calculatedHeight) ?>" />
*/?>
    <meta name="twitter:image" content="<?= htmlspecialchars($thumbImageUrl) ?>" />
    <meta property="twitter:image:width" content="<?= $thumbSize ?>" />
    <meta property="twitter:image:height" content="<?= $thumbSize ?>" />
    <?php endif; ?>

    <link rel="stylesheet" href="index.css" />
    <?php faviconTag(); ?>
</head>
<body>
    <h1 class="title"><?= htmlspecialchars($title) ?></h1>

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

                            $previewUrl = 'thumb.php?mode=full&size=' . $previewSize . '&show=' . $encodedPath;
                            $thumbUrl = 'thumb.php?show=' . $encodedPath;

                            if ($useRedirectMode) {
                                $viewUrl = 'view/' . $encodedPath;
                            } else {
                                $viewUrl = 'view.php?show=' . $encodedPath;
                            }
                            ?>
                            <a href="<?= $viewUrl ?>">
                                <img
                                    src="<?= $previewUrl ?>"
                                    alt="<?= htmlspecialchars($image['name']) ?>"
                                    style="height: <?= htmlspecialchars($thumbSize) ?>px; width: <?= htmlspecialchars($thumbSize) ?>px"
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
