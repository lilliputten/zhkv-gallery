<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

$config = loadConfig();
$vTag = isset($config['vTag']) ? $config['vTag'] : $projectTag;
$galleryTitle = isset($config['title']) ? $config['title'] : 'Image Gallery';
$galleryDescription = isset($config['galleryDescription']) ? $config['galleryDescription'] : 'Image gallery with thumbnails and viewer';
$thumbSize = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;
$useRedirectMode = !$isDev && isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false;
// $thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.thumbs';
// $indexCacheValidMins = isset($config['indexCacheValidMins']) ? $config['indexCacheValidMins'] : 30;
// $indexCache = isset($config['indexCache']) ? $config['indexCache'] : '.cache.index';

$vTagPostfix = ''; // isset($vTag) ? '?v=' . $vTag : '';
$vTagPostfixPlus = ''; // isset($vTag) ? ($useRedirectMode ? '&v=' . $vTag : $vTagPostfix) : '';

// Get list parameter if provided
$listParam = isset($_GET['list']) ? $_GET['list'] : null;

// Get image list using the shared cache function
$imageData = getImageList($config);
$scanResults = $imageData['foldered'];

// Filter by list parameter if provided
if ($listParam !== null) {
  // Decode URL encoding
  $listParam = urldecode($listParam);

  // Check if the list parameter matches a folder path
  if (isset($scanResults[$listParam])) {
    // Filter to show only this folder
    $scanResults = [$listParam => $scanResults[$listParam]];
  } else {
    // If no match, show empty results
    $scanResults = [];
  }
}

$mdMetadata = loadImageMetadataFromMarkdown('gallery');
if (!empty($mdMetadata['title'])) {
  $galleryTitle = $mdMetadata['title'];
}
if (!empty($mdMetadata['description'])) {
  $galleryDescription = $mdMetadata['description'];
}

// Urls
$baseUrl = getCurrentUrlPrefix();
$currentUrl = getCurrentUrl();

// Try to find thumbnail image...
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

?>
<!DOCTYPE html>
<html>

<head>
  <title><?= prepareRichText($galleryTitle) ?></title>
  <!-- OpenGraph & Twitter Card Meta Tags -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= prepareRichText($galleryTitle) ?>" />
  <meta property="og:description" content="<?= prepareRichText($galleryDescription) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= prepareRichText($galleryTitle) ?>" />
  <meta name="twitter:description" content="<?= prepareRichText($galleryDescription) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>" />
  <meta property="og:site_name" content="<?= prepareRichText($galleryTitle) ?>" />
<?

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
  <meta property="og:image" content="<?= $thumbImageUrl . $vTagPostfixPlus ?>" />
  <meta property="og:image:width" content="<?= $thumbSize ?>" />
  <meta property="og:image:height" content="<?= $thumbSize ?>" />
  <meta name="twitter:image" content="<?= $thumbImageUrl . $vTagPostfixPlus ?>" />
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
  <h1 class="title"><?= prepareRichText($galleryTitle) ?></h1>
  <p class="gallery-description"><?= prepareRichText($galleryDescription) ?></p>

<? if (empty($scanResults)): ?>
    <p style="text-align: center; color: #999;">No images found.</p>
<? else: ?>
<? foreach ($scanResults as $folderPath => $folderData): ?>
      <a name="<?= $folderPath ?>"></a>
      <div class="section">
        <div class="section-header">
          <h2 class="section-title">
<?
            // Generate list URL based on useRedirectMode
            $encodedFolderPath = str_replace('%2F', '/', rawurlencode($folderPath));
            $listUrl = 'index.php?list=' . $encodedFolderPath;
            if ($useRedirectMode) {
              $listUrl = 'list/' . $encodedFolderPath;
            }

            // Check if we're currently viewing this folder
            $isCurrentList = ($listParam === $folderPath);
?>
<? if (!$isCurrentList): ?>
            <a href="<?= $listUrl ?>" class="section-link">
<? endif; ?>
            <?= prepareRichText(!empty($folderData['title']) ? $folderData['title'] : $folderData['name']) ?>
<? if (!$isCurrentList): ?>
            </a>
<? endif; ?>

            <a class="anchor-link" href="#<?= $folderPath ?>" title="Anchor link to the section"><i data-lucide="link"></i></a>
          </h2>
<? if (!empty($folderData['description'])): ?>
          <p class="section-description"><?= prepareRichText($folderData['description']) ?></p>
<? endif; ?>
        </div>
        <div class="image-grid"
          style="grid-template-columns: repeat(auto-fill, minmax(<?= htmlspecialchars($thumbSize) ?>px, 1fr))">
<? foreach ($folderData['images'] as $image): ?>
            <div class="image-item">
<?
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
                  <?= prepareRichText($imageDescription) ?>

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
