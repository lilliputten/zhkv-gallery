<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['image']) ? $_GET['image'] : '';

$config = loadConfig($imagePath);
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';
$maxWidth = isset($config['maxWidth']) ? $config['maxWidth'] : Null;

// Image properties
$name = isset($config['name']) ? $config['name'] : Null;
$description = isset($config['description']) ? $config['description'] : Null;

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

// Set maxWidth to image width if not configured
if (!$maxWidth) {
    $maxWidth = $imageInfo[0];
}

// Get navigation info for previous/next images
$imageData = getImageList($config, $imagePath);
$navInfo = $imageData['navInfo'];

// Check if we have any metadata to display
$hasMetadata = !empty($name) || !empty($description);

// Generate view URLs for previous and next images
$prevViewUrl = '';
$nextViewUrl = '';

if ($navInfo['prev']) {
    $encodedPrev = str_replace('%2F', '/', rawurlencode($navInfo['prev']));
    $prevViewUrl = 'view.php?image=' . $encodedPrev;
    if ($useRedirectMode) {
        $prevViewUrl = 'view/' . $encodedPrev;
    }
}

if ($navInfo['next']) {
    $encodedNext = str_replace('%2F', '/', rawurlencode($navInfo['next']));
    $nextViewUrl = 'view.php?image=' . $encodedNext;
    if ($useRedirectMode) {
        $nextViewUrl = 'view/' . $encodedNext;
    }
}

$encodedPath = str_replace('%2F', '/', rawurlencode($imagePath));
$useFullMode = true;
$previewMode = $useFullMode ? 'full' : 'preview';
$previewUrl = 'thumb.php?mode=' . $previewMode . '&image=' . $encodedPath;
$thumbUrl   = 'thumb.php?image='               . $encodedPath;
$viewUrl    = 'view.php?image='                . $encodedPath;
if ($useRedirectMode) {
    $previewUrl = $previewMode . '/' . $encodedPath;
    $thumbUrl   = 'thumb/'   . $encodedPath;
    $viewUrl    = 'view/'    . $encodedPath;
}
$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';

// Build URLs without escaping issues
$ogImageUrl = $baseUrl . $previewUrl;
$thumbImageUrl = $baseUrl . $thumbUrl;
$previewImageUrl = $baseUrl . $previewUrl;
$currentUrl = currentUrl();

$shortTitle = $name ? $name : $imagePath;
$pageTitle = $title . ': ' . $shortTitle;
$pageDescription = $description ? $description : basename($imagePath);

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <base href="<?php echo $baseUrl; ?>" />
    <title><?php echo htmlspecialchars($shortTitle); ?></title>
    <!-- OpenGraph Meta Tags -->
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="<?php echo htmlspecialchars($title); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($shortTitle); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($thumbImageUrl); ?>" />
    <meta property="og:image:width" content="<?php echo $thumbSize; ?>" />
    <meta property="og:image:height" content="<?php echo $thumbSize; ?>" />
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>" />
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($shortTitle); ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($thumbImageUrl); ?>" />
    <meta property="twitter:image:width" content="<?php echo $thumbSize; ?>" />
    <meta property="twitter:image:height" content="<?php echo $thumbSize; ?>" />
    <!-- Resources -->
    <link rel="preload" href="<?= $previewImageUrl ?>" as="image">
    <?php faviconTag(); ?>
    <link rel="stylesheet" href="view.css" />
    <style>
        .image {
<?php if ($maxWidth) { ?>
            max-width: <?= $maxWidth ?>px;
<?php } ?>
            background-image: url("<?= $previewImageUrl ?>");
        }
    </style>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
      rel="stylesheet"
      type="text/css"
    />
  </head>
  <body>
    <img class="image" src="<?php echo $encodedPath; ?>" border="0" />

    <div class="float-panel left bottom">
        <?php if ($prevViewUrl): ?>
        <a href="<?php echo $prevViewUrl; ?>" class="nav-button" title="Previous image"><i class="fa fa-chevron-left"></i></a>
        <?php endif; ?>
        <a href="<?php echo $baseUrl; ?>" class="nav-button" title="Back to the gallery"><i class="fa fa-home"></i></a>
        <?php if ($nextViewUrl): ?>
        <a href="<?php echo $nextViewUrl; ?>" class="nav-button" title="Next image"><i class="fa fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>

    <?php if ($hasMetadata): ?>
    <div class="float-panel right bottom">
        <button class="nav-button" id="infoButton" title="Image information">
            <i class="fa fa-info"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($hasMetadata): ?>
    <!-- Info Popup -->
    <div class="info-popup show" id="infoPopup">
        <div class="popup-content">
            <?php if (!empty($name)): ?>
            <div class="info-title"><?php echo htmlspecialchars($name); ?></div>
            <?php endif; ?>
            <?php if (!empty($description)): ?>
            <div class="info-description"><?php echo htmlspecialchars($description); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const infoButton = document.getElementById('infoButton');
            const infoPopup = document.getElementById('infoPopup');
            if (infoButton && infoPopup) {
                infoButton.addEventListener('click', function(event) {
                    event.preventDefault();
                    infoPopup.classList.toggle('show');
                });
                // Close popup when clicking outside
                document.addEventListener('click', function(event) {
                    if (!infoButton.contains(event.target) && !infoPopup.contains(event.target)) {
                        infoPopup.classList.remove('show');
                    }
                });
                // Close popup with Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        infoPopup.classList.remove('show');
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
  </body>
</html>
