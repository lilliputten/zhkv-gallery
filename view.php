<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['image']) ? $_GET['image'] : '';

$config = loadConfig($imagePath);
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';
$maxWidth = isset($config['maxWidth']) ? $config['maxWidth'] : Null;

$useRedirectMode = !$isDev && isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false;
$thumbSize = isset($_GET['size']) ? (int)$_GET['size'] : (isset($config['thumbSize']) ? $config['thumbSize'] : 150);
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;

// Image properties - try to load from .md file first
$imageTitle = '';
$description = '';

$mdMetadata = loadImageMetadataFromMarkdown($imagePath);
if (!empty($mdMetadata['title'])) {
  $imageTitle = $mdMetadata['title'];
}
if (!empty($mdMetadata['description'])) {
  $description = $mdMetadata['description'];
}

// If no .md file or incomplete data, fallback to config
if (empty($imageTitle)) {
  $imageTitle = isset($config['name']) ? $config['name'] : '';
}
if (empty($description)) {
  $description = isset($config['description']) ? $config['description'] : '';
}

// Security: Validate the path to prevent directory traversal attacks
if (empty($imagePath)) {
  die('No image specified');
}

// Decode URL encoding (e.g., %20 becomes space)
$imagePath = urldecode($imagePath);

// Prevent directory traversal attacks
$basePath = realpath($basePath);
$fullPath = realpath($basePath . '/' . $imagePath);

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
$hasMetadata = !empty($imageTitle) || !empty($description);

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

$shortTitle = $imageTitle ? $imageTitle : $imagePath;
$pageTitle = $title . ': ' . $shortTitle;
$pageDescription = $description ? $description : basename($imagePath);

?>
<!DOCTYPE html>
<html>
  <head>
  <base href="<? echo $baseUrl ?>" />
  <title><? echo htmlspecialchars($shortTitle) ?></title>
  <!-- OpenGraph Meta Tags -->
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="<? echo htmlspecialchars($title) ?>" />
  <meta property="og:title" content="<? echo htmlspecialchars($shortTitle) ?>" />
  <meta property="og:description" content="<? echo htmlspecialchars($pageDescription) ?>" />
  <meta property="og:image" content="<? echo htmlspecialchars($thumbImageUrl) ?>" />
  <meta property="og:image:width" content="<? echo $thumbSize ?>" />
  <meta property="og:image:height" content="<? echo $thumbSize ?>" />
  <meta property="og:url" content="<? echo htmlspecialchars($currentUrl) ?>" />
  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<? echo htmlspecialchars($shortTitle) ?>" />
  <meta name="twitter:description" content="<? echo htmlspecialchars($pageDescription) ?>" />
  <meta name="twitter:image" content="<? echo htmlspecialchars($thumbImageUrl) ?>" />
  <meta property="twitter:image:width" content="<? echo $thumbSize ?>" />
  <meta property="twitter:image:height" content="<? echo $thumbSize ?>" />
  <!-- Resources -->
  <link rel="preload" href="<?= $previewImageUrl ?>" as="image">
  <link rel="stylesheet" href="view.css" />
  <style>
    .image {
<? if ($maxWidth) { ?>
      max-width: <?= $maxWidth ?>px;
<? } ?>
      background-image: url("<?= $previewImageUrl ?>");
    }
  </style>
  <? include('common-headers-post.php') ?>
  </head>
  <body>
  <img class="image" src="<? echo $encodedPath ?>" border="0" />

  <div class="float-panel left bottom">
    <? if ($prevViewUrl): ?>
    <a href="<? echo $prevViewUrl ?>" class="nav-button" title="Previous image"><i class="fa fa-chevron-left"></i></a>
    <? endif ?>
    <a href="<? echo $baseUrl ?>" class="nav-button" title="Back to the gallery"><i class="fa fa-home"></i></a>
    <? if ($nextViewUrl): ?>
    <a href="<? echo $nextViewUrl ?>" class="nav-button" title="Next image"><i class="fa fa-chevron-right"></i></a>
    <? endif ?>
  </div>

  <? if ($hasMetadata): ?>
  <div class="float-panel right bottom">
    <button class="nav-button" id="infoButton" title="Image information">
      <i class="fa fa-info"></i>
    </button>
  </div>
  <? endif ?>

  <? if ($hasMetadata): ?>
  <!-- Info Popup -->
  <div class="info-popup" id="infoPopup">
    <div class="popup-content">
      <? if (!empty($imageTitle)): ?>
      <div class="info-title"><? echo htmlspecialchars($imageTitle) ?></div>
      <? endif ?>
      <? if (!empty($description)): ?>
      <div class="info-description"><? echo htmlspecialchars($description) ?></div>
      <? endif ?>
    </div>
  </div>
  <script src="view.js" type="text/javascript"></script>
  <? endif ?>
  </body>
</html>
