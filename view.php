<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['image']) ? $_GET['image'] : '';

$config = loadConfig($imagePath);
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';
$maxWidth = isset($config['maxWidth']) ? $config['maxWidth'] : Null;
$useRedirectMode = isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false;
$thumbSize = isset($_GET['size']) ? (int)$_GET['size'] : (isset($config['thumbSize']) ? $config['thumbSize'] : 150);
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;

// Security: Validate the path to prevent directory traversal attacks
if (empty($imagePath)) {
    die('No image specified');
}

// Decode URL encoding (e.g., %20 becomes space)
$imagePath = urldecode($imagePath);

// Prevent directory traversal attacks
$basePath = realpath(__DIR__);
$fullPath = realpath(__DIR__ . '/' . $imagePath);

if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
    die('Invalid image path');
}

// Check if file exists and is an image
if (!file_exists($fullPath)) {
    die('Image not found: ' . htmlspecialchars($imagePath));
}

// Get image info to determine MIME type
$imageInfo = getimagesize($fullPath);
if ($imageInfo === false) {
    die('Not a valid image file');
}

$encodedPath = str_replace('%2F', '/', rawurlencode($imagePath));
$useFullMode = true;
$previewMode = $useFullMode ? 'full' : 'preview';
$previewUrl = 'thumb.php?mode=' . $useFullMode . '&image=' . $encodedPath;
$thumbUrl   = 'thumb.php?image='               . $encodedPath;
if ($useRedirectMode) {
    $previewUrl = $previewMode . '/' . $encodedPath;
    $thumbUrl   = 'thumb/'   . $encodedPath;
}
$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';

// Build URLs without escaping issues
$ogImageUrl = $baseUrl . $previewUrl;
$thumbImageUrl = $baseUrl . $thumbUrl;
$currentUrl = currentUrl();
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <base href="<?php echo $baseUrl; ?>" />
    <title><?php echo htmlspecialchars($title) . ': ' . htmlspecialchars($imagePath); ?></title>
    <!-- OpenGraph Meta Tags -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?php echo htmlspecialchars($title) . ': ' . htmlspecialchars(basename($imagePath)); ?>" />
    <meta property="og:description" content="View image: <?php echo htmlspecialchars(basename($imagePath)); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($thumbImageUrl); ?>" />
    <meta property="og:image:width" content="<?php echo $thumbSize; ?>" />
    <meta property="og:image:height" content="<?php echo $thumbSize; ?>" />
<?/*
    <meta property="og:image:height" content="<?php
        $aspectRatio = $imageInfo[0] / $imageInfo[1];
        $calculatedHeight = floor($previewSize / $aspectRatio);
        if ($maxHeightRatio && $calculatedHeight > $previewSize * $maxHeightRatio) {
            $calculatedHeight = $previewSize * $maxHeightRatio;
        }
        echo floor($calculatedHeight);
    ?>" >
*/?>
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>" />
    <meta property="og:site_name" content="<?php echo htmlspecialchars($title); ?>" />
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title) . ': ' . htmlspecialchars(basename($imagePath)); ?>" />
    <meta name="twitter:description" content="View image: <?php echo htmlspecialchars(basename($imagePath)); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($thumbImageUrl); ?>" />
    <meta property="twitter:image:width" content="<?php echo $thumbSize; ?>" />
    <meta property="twitter:image:height" content="<?php echo $thumbSize; ?>" />

    <link rel="preload" href="<?= $previewUrl ?>" as="image">
    <?php faviconTag(); ?>
    <style>
      body {
        padding: 0;
        margin: 0;
      }
      .image {
            width: 100%;
            height: auto;
            margin: 0 auto;
            display: block;
<?php if ($maxWidth) { ?>
            max-width: <?= $maxWidth ?>px;
<?php } ?>
            background-image: url("<?= $previewUrl ?>");
            background-position: center top;
            background-size: 100% auto;
            background-repeat: no-repeat;
      }
      .back-button {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background-color 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.5);
            opacity: 0.5;
      }
      .back-button:hover {
            background-color: rgba(0, 0, 0, 0.9);
      }
      .back-button:active {
            transform: scale(0.95);
      }
    </style>
  </head>
  <body>
    <img class="image" src="<?php echo $encodedPath; ?>" border="0" />
    <a href="<?php echo $baseUrl; ?>" class="back-button" title="Back to the Gallery">←</a>
  </body>
</html>
