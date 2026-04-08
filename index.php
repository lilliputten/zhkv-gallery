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
$indexCacheValidMins = isset($config['indexCacheValidHours']) ? $config['indexCacheValidHours'] : 30;
$indexCache = isset($config['indexCache']) ? $config['indexCache'] : '.cache.index';

// Get image list using the shared cache function
$imageData = getImageList($config);
$scanResults = $imageData['foldered'];

$currentUrl = currentUrl();
?>
<!DOCTYPE html>
<html>
<head>
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
    $previewUrl = 'thumb.php?mode=preview&image=' . $encodedPath;
    $thumbUrl   = 'thumb.php?image='               . $encodedPath;
    if ($useRedirectMode) {
      $previewUrl = 'preview/' . $encodedPath;
      $thumbUrl   = 'thumb/'   . $encodedPath;
    }
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
  <meta name="twitter:image" content="<?= htmlspecialchars($thumbImageUrl) ?>" />
  <meta property="twitter:image:width" content="<?= $thumbSize ?>" />
  <meta property="twitter:image:height" content="<?= $thumbSize ?>" />
  <? endif ?>
  <link rel="stylesheet" href="index.css" />
  <? include('common-headers-post.php') ?>
</head>
<body>
  <h1 class="title"><?= htmlspecialchars($title) ?></h1>

  <? if (empty($scanResults)): ?>
    <p style="text-align: center; color: #999;">No images found.</p>
  <? else: ?>
    <? foreach ($scanResults as $folderPath => $folderData): ?>
      <a name="<?= $folderPath ?>"></a>
      <div class="section">
        <h2 class="section-title">
          <?= htmlspecialchars($folderData['name']) ?>
          <a class="anchor-link" href="#<?= $folderPath ?>"><i class="fa fa-link"></i></a>
        </h2>
        <div
          class="image-grid"
          style="grid-template-columns: repeat(auto-fill, minmax(<?= htmlspecialchars($thumbSize) ?>px, 1fr))"
        >
          <? foreach ($folderData['images'] as $image): ?>
            <div class="image-item">
              <?php
              $encodedPath = str_replace('%2F', '/', rawurlencode($image['path']));
              $previewUrl = 'thumb.php?mode=preview&image=' . $encodedPath;
              $thumbUrl   = 'thumb.php?image='               . $encodedPath;
              $viewUrl    = 'view.php?image='                . $encodedPath;
              if ($useRedirectMode) {
                $previewUrl = 'preview/' . $encodedPath;
                $thumbUrl   = 'thumb/'   . $encodedPath;
                $viewUrl    = 'view/'    . $encodedPath;
              }

              // Load image metadata from JSON file if exists
              $imageName = $image['name'];
              $imageDescription = "";

              // Remove image extension from path before adding .json
              $imagePathWithoutExt = pathinfo($image['path'], PATHINFO_FILENAME);
              $imageDir = pathinfo($image['path'], PATHINFO_DIRNAME);
              $jsonPath = ($imageDir !== '.') ? $imageDir . '/' . $imagePathWithoutExt : $imagePathWithoutExt;
              $jsonFile = __DIR__ . '/' . $jsonPath . '.json';
              if (file_exists($jsonFile)) {
                $jsonData = file_get_contents($jsonFile);
                $metadata = json_decode($jsonData, true);

                if ($metadata && !empty($metadata['name'])) {
                  $imageName = $metadata['name'];
                }
                if ($metadata && !empty($metadata['description'])) {
                  $imageDescription = $metadata['description'];
                }
              }
              ?>
              <a href="<?= $viewUrl ?>">
                <img
                  src="<?= $thumbUrl ?>"
                  alt="<?= htmlspecialchars($imageName) ?>"
                  width="<?= htmlspecialchars($thumbSize) ?>"
                  height="<?= htmlspecialchars($thumbSize) ?>"
                  loading="lazy"
                />
                <div class="image-name"><?= htmlspecialchars($imageName) ?></div>
                <? if ($imageDescription): ?>
                <div class="image-description"><?= htmlspecialchars($imageDescription) ?></div>
                <? endif ?>
              </a>
            </div>
          <? endforeach ?>
        </div>
      </div>
    <? endforeach ?>
  <? endif ?>
</body>
</html>
