<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['image']) ? $_GET['image'] : '';

$config = loadConfig($imagePath);
$vTag = isset($config['vTag']) ? $config['vTag'] : $projectTag;
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';
$maxWidth = isset($config['maxWidth']) ? $config['maxWidth'] : Null;

$useRedirectMode = !$isDev && isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false;
$thumbSize = isset($_GET['size']) ? (int) $_GET['size'] : (isset($config['thumbSize']) ? $config['thumbSize'] : 150);
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;

$vTagPostfix = ''; // isset($vTag) ? '?v=' . $vTag : '';
$vTagPostfixPlus = ''; // isset($vTag) ? ($useRedirectMode ? '&v=' . $vTag : $vTagPostfix) : '';

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
$thumbUrl = 'thumb.php?image=' . $encodedPath;
$viewUrl = 'view.php?image=' . $encodedPath;
if ($useRedirectMode) {
  $previewUrl = $previewMode . '/' . $encodedPath;
  $thumbUrl = 'thumb/' . $encodedPath;
  $viewUrl = 'view/' . $encodedPath;
}
$baseUrl = getCurrentUrlPrefix();

// Build URLs without escaping issues
$ogImageUrl = $baseUrl . $previewUrl;
$thumbImageUrl = $baseUrl . $thumbUrl;
$previewImageUrl = $baseUrl . $previewUrl;
$getCurrentUrl = getCurrentUrl();

$shortTitle = $imageTitle ? $imageTitle : $imagePath;
$pageTitle = $title . ': ' . $shortTitle;
$pageDescription = $description ? $description : basename($imagePath);

?>
<!DOCTYPE html>
<html>

<head>
  <base href="<?= $baseUrl ?>" />
  <title><?= htmlspecialchars($shortTitle) ?></title>
  <!-- OpenGraph Meta Tags -->
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="<?= prepareRichText($shortTitle) ?>" />
  <meta property="og:title" content="<?= prepareRichText($shortTitle) ?>" />
  <meta property="og:description" content="<?= prepareRichText($pageDescription) ?>" />
  <meta property="og:image" content="<?= $thumbImageUrl . $vTagPostfixPlus ?>" />
  <meta property="og:image:width" content="<?= $thumbSize ?>" />
  <meta property="og:image:height" content="<?= $thumbSize ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($getCurrentUrl) ?>" />
  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= prepareRichText($shortTitle) ?>" />
  <meta name="twitter:description" content="<?= prepareRichText($pageDescription) ?>" />
  <meta name="twitter:image" content="<?= $thumbImageUrl . $vTagPostfixPlus ?>" />
  <meta name="twitter:image:width" content="<?= $thumbSize ?>" />
  <meta name="twitter:image:height" content="<?= $thumbSize ?>" />
  <!-- Shared headers -->
<? include('common-headers-post.php') ?>
  <!-- Resources -->
  <link rel="preload" href="<?= $thumbImageUrl . $vTagPostfixPlus ?>" as="image">
  <link rel="preload" href="<?= $previewImageUrl . $vTagPostfixPlus ?>" as="image">
  <link rel="stylesheet" href="view.css?v=<?= $projectTag ?>" />
  <style>
    .image-wrapper,
    .image {
<? if ($maxWidth) { ?>
      max-width: <?= $maxWidth ?>px;
<? } ?>
      background-image: url("<?= $previewImageUrl . $vTagPostfixPlus ?>");
    }
    .image-wrapper {
      background-image: url("<?= $thumbImageUrl . $vTagPostfixPlus ?>");
    }
  </style>
</head>

<body>
  <div class="image-wrapper">
    <img class="image" src="<?= $encodedPath . $vTagPostfix ?>" border="0" loading="lazy" />
  </div>

  <div class="float-panel left bottom">
<? if ($prevViewUrl): ?>
      <a href="<?= $prevViewUrl ?>" class="nav-button" title="Previous image"><i data-lucide="arrow-left"></i></a>
<? endif ?>
    <a href="<?= $baseUrl ?>" class="nav-button" title="Back to the gallery"><i data-lucide="home"></i></a>
<? if ($nextViewUrl): ?>
      <a href="<?= $nextViewUrl ?>" class="nav-button" title="Next image"><i data-lucide="arrow-right"></i></a>
<? endif ?>
  </div>

<? if ($hasMetadata): ?>
    <div class="float-panel right bottom">
      <button class="nav-button" id="infoButton" title="Show/hide image information">
        <i data-lucide="info"></i>
      </button>
    </div>
<? endif ?>

<? if ($hasMetadata): ?>
    <!-- Info Popup -->
    <div class="info-popup" id="infoPopup">
      <div class="popup-content">
<? if (!empty($imageTitle)): ?>
          <div class="info-title"><?= prepareRichText($imageTitle) ?></div>
<? endif ?>
<? if (!empty($description)): ?>
          <div class="info-description"><?= prepareRichText($description) ?></div>
<? endif ?>
      </div>
    </div>
    <script src="view.js" type="text/javascript"></script>
<? endif ?>

  <!-- Shared scripts -->
<? include('common-html-footer.php') ?>
</body>

</html>
