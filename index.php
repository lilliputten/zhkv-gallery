<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

$config = loadConfig();
$galleryTitle = isset($config['title']) ? $config['title'] : 'Image Gallery';
$galleryDescription = isset($config['galleryDescription']) ? $config['galleryDescription'] : 'Image gallery with thumbnails and viewer';
$thumbSize = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;
$useRedirectMode = !$isDev && isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false;
// $thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.thumbs';
// $indexCacheValidMins = isset($config['indexCacheValidMins']) ? $config['indexCacheValidMins'] : 30;
// $indexCache = isset($config['indexCache']) ? $config['indexCache'] : '.cache.index';

// Get image list using the shared cache function
$imageData = getImageList($config);
$scanResults = $imageData['foldered'];

$mdMetadata = loadImageMetadataFromMarkdown('gallery');
if (!empty($mdMetadata['title'])) {
  $galleryTitle = $mdMetadata['title'];
}
if (!empty($mdMetadata['description'])) {
  $galleryDescription = $mdMetadata['description'];
}

$currentUrl = currentUrl();
?>
<!DOCTYPE html>
<html>

<head>
  <title><?= htmlspecialchars($galleryTitle) ?></title>
  <!-- OpenGraph & Twitter Card Meta Tags -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= htmlspecialchars($galleryTitle) ?>" />
  <meta property="og:description" content="Image gallery with thumbnails and viewer" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= htmlspecialchars($galleryTitle) ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars(preg_replace('/\s+/', ' ', $galleryDescription)) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>" />
  <meta property="og:site_name" content="<?= htmlspecialchars($galleryTitle) ?>" />
<?php
  $baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
  $firstImagePath = null;
  $firstImageInfo = null;

  // Iterate through scanResults to find the first existing image with valid image info
  if (!empty($scanResults)) {
    foreach ($scanResults as $folder) {
      if (!empty($folder['images'])) {
        foreach ($folder['images'] as $image) {
          $candidatePath = $basePath . '/' . $image['path'];
          $candidateInfo = @getimagesize($candidatePath);
          if ($candidateInfo !== false) {
            // Found a valid image
            $firstImagePath = $image['path'];
            $firstImageInfo = $candidateInfo;
            break 2; // Break out of both foreach loops
          }
        }
      }
    }
  }

  if ($firstImagePath && $firstImageInfo) {
    $encodedPath = str_replace('%2F', '/', rawurlencode($firstImagePath));
    $previewUrl = 'thumb.php?mode=preview&image=' . $encodedPath;
    $thumbUrl = 'thumb.php?image=' . $encodedPath;
    if ($useRedirectMode) {
      $previewUrl = 'preview/' . $encodedPath;
      $thumbUrl = 'thumb/' . $encodedPath;
    }
    $aspectRatio = $firstImageInfo[0] / $firstImageInfo[1];
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
<?
  }
?>
  <!-- Shared headers -->
<? include('common-headers-post.php') ?>
  <link rel="stylesheet" href="index.css" />
</head>

<body>
  <h1 class="title"><?= htmlspecialchars($galleryTitle) ?></h1>
  <p class="gallery-description"><?= htmlspecialchars(preg_replace('/\s+/', ' ', $galleryDescription)) ?></p>

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
        <div class="image-grid"
          style="grid-template-columns: repeat(auto-fill, minmax(<?= htmlspecialchars($thumbSize) ?>px, 1fr))">
<? foreach ($folderData['images'] as $image): ?>
            <div class="image-item">
<?php
              $encodedPath = str_replace('%2F', '/', rawurlencode($image['path']));
              $previewUrl = 'thumb.php?mode=preview&image=' . $encodedPath;
              $thumbUrl = 'thumb.php?image=' . $encodedPath;
              $viewUrl = 'view.php?image=' . $encodedPath;
              if ($useRedirectMode) {
                $previewUrl = 'preview/' . $encodedPath;
                $thumbUrl = 'thumb/' . $encodedPath;
                $viewUrl = 'view/' . $encodedPath;
              }

              // Load image metadata from JSON file if exists
              $imageTitle = $image['name'];
              $imageDescription = "";

              // First, try to load from .md file
              $mdMetadata = loadImageMetadataFromMarkdown($image['path']);
              if (!empty($mdMetadata['title'])) {
                $imageTitle = $mdMetadata['title'];
              }
              if (!empty($mdMetadata['description'])) {
                $imageDescription = $mdMetadata['description'];
              }

              // If no .md file or incomplete data, try JSON file as fallback
              if (empty($mdMetadata['title']) || empty($mdMetadata['description'])) {
                // Remove image extension from path before adding .json
                $jsonFile = $basePath . '/' . removeFileExtension($image['path']) . '.json';
                if (file_exists($jsonFile)) {
                  $jsonData = file_get_contents($jsonFile);
                  $metadata = json_decode($jsonData, true);

                  if ($metadata && !empty($metadata['name']) && empty($mdMetadata['title'])) {
                    $imageTitle = $metadata['name'];
                  }
                  if ($metadata && !empty($metadata['description']) && empty($mdMetadata['description'])) {
                    $imageDescription = $metadata['description'];
                  }
                }
              }
              ?>
              <a href="<?= $viewUrl ?>">
                <img src="<?= $thumbUrl ?>" alt="<?= htmlspecialchars($imageTitle) ?>"
                  width="<?= htmlspecialchars($thumbSize) ?>" height="<?= htmlspecialchars($thumbSize) ?>" loading="lazy" />
                <div class="image-name">
                  <?= htmlspecialchars($imageTitle) ?>

                </div>
<? if ($imageDescription): ?>
                <div class="image-description">
                  <?= htmlspecialchars(preg_replace('/\s+/', ' ', $imageDescription)) ?>

                </div>
<? endif ?>
              </a>
            </div>
<? endforeach ?>
        </div>
      </div>
<? endforeach ?>
<? endif ?>

  <!-- Shared scripts -->
<? include('common-html-footer.php') ?>
</body>

</html>
