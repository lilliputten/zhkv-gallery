<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

// Urls
// $urlPath = getCurrentUrlPath();
$baseUrl = getCurrentUrlPrefix();
$currentUrl = getCurrentUrl();

$config = loadConfig();
$vTag = isset($config['vTag']) ? $config['vTag'] : $projectTag;
$galleryTitle = isset($config['title']) ? $config['title'] : 'Image Gallery';
$galleryDescription = isset($config['galleryDescription']) ? $config['galleryDescription'] : 'Image gallery with thumbnails and viewer';
$thumbSize = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$lqipThumbSize = isset($config['lqipThumbSize']) ? $config['lqipThumbSize'] : 20; // Extra-small for LQIP
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : Null;
$useRedirectMode = !$isDev && isset($config['useRedirectMode']) ? $config['useRedirectMode'] : false;
// $thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.thumbs';
// $indexCacheValidMins = isset($config['indexCacheValidMins']) ? $config['indexCacheValidMins'] : 30;
// $indexCache = isset($config['indexCache']) ? $config['indexCache'] : '.cache.index';

// Get list parameter if provided
$listParam = isset($_GET['list']) ? $_GET['list'] : null;

// Prepare gallery home URL (with optional anchor for current folder)
$galleryHomeUrl = $baseUrl;

// Get image list using the shared cache function
$imageData = getImageList($config);
$scanResults = $imageData['foldered'];

// Filter by list parameter if provided
if ($listParam !== null) {
  // Decode URL encoding
  $listParam = urldecode($listParam);

  // Add anchor to gallery home URL to return to this folder section
  $galleryHomeUrl .= '#' . $listParam;

  // Check if the list parameter matches a folder path
  if (isset($scanResults[$listParam])) {
    // Filter to show only this folder
    $scanResults = [$listParam => $scanResults[$listParam]];
  } else {
    // If no match, show empty results
    $scanResults = [];
  }
}

// Get gallery metadata from cache (loaded during cache build)
$imageData = getImageList($config);
$rootMetadata = isset($imageData['root']) ? $imageData['root'] : ['title' => '', 'description' => ''];

if (!empty($rootMetadata['title'])) {
  $galleryTitle = $rootMetadata['title'];
}
if (!empty($rootMetadata['description'])) {
  $galleryDescription = $rootMetadata['description'];
}

// Use foldered data for display (already filtered by list parameter if provided)
// Note: $scanResults was already set above with optional filtering applied

// Calculate folder navigation (prev/next/home)
$folderNav = [
  'prev' => null,
  'next' => null,
  'hasRoot' => !empty($listParam) // Show home button if we're in a list view
];

// Only calculate navigation if we're in list mode (viewing a specific folder)
if (!empty($listParam) && !empty($scanResults)) {
  // Get all folder keys from the full image list
  $fullImageData = getImageList($config);
  $allFolders = array_keys($fullImageData['foldered']);

  // Find current folder position
  $currentFolderKey = null;
  foreach ($scanResults as $folderKey => $folderData) {
    $currentFolderKey = $folderKey;
    break; // Get the first (and likely only) folder key
  }

  if ($currentFolderKey !== null) {
    $currentIndex = array_search($currentFolderKey, $allFolders);

    if ($currentIndex !== false) {
      // Get previous folder
      if ($currentIndex > 0) {
        $prevFolderKey = $allFolders[$currentIndex - 1];
        $encodedPrevFolder = str_replace('%2F', '/', rawurlencode($prevFolderKey));
        $folderNav['prev'] = $baseUrl . 'index.php?list=' . $encodedPrevFolder;
        if ($useRedirectMode) {
          $folderNav['prev'] = $baseUrl . 'list/' . $encodedPrevFolder;
        }
      }

      // Get next folder
      if ($currentIndex < count($allFolders) - 1) {
        $nextFolderKey = $allFolders[$currentIndex + 1];
        $encodedNextFolder = str_replace('%2F', '/', rawurlencode($nextFolderKey));
        $folderNav['next'] = $baseUrl . 'index.php?list=' . $encodedNextFolder;
        if ($useRedirectMode) {
          $folderNav['next'] = $baseUrl . 'list/' . $encodedNextFolder;
        }
      }
    }
  }
}

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

$pageTitle = !empty($listParam) && !empty($folderData['title']) ? $folderData['title'] : $galleryTitle;
$pageDescription = !empty($listParam) && !empty($folderData['description']) ? $folderData['description'] : $galleryDescription;

?>
<!DOCTYPE html>
<html>

<head>
  <title><?= prepareRichText($pageTitle) ?></title>
  <!-- OpenGraph & Twitter Card Meta Tags -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= prepareRichText($pageTitle) ?>" />
  <meta property="og:description" content="<?= prepareRichText($pageDescription) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= prepareRichText($pageTitle) ?>" />
  <meta name="twitter:description" content="<?= prepareRichText($pageDescription) ?>" />
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
  <link rel="stylesheet" href="<?= $baseUrl ?>index.css<?= $projectTagPostfix ?>" />
  <style>
    .image-grid {
      grid-template-columns: repeat(auto-fill, minmax(<?= $thumbSize ?>px, 1fr));
    }
    /* Adaptive sizes */
    @media (max-width: <?= $thumbSize * 2 + 200 ?>px) {
      .image-grid {
        grid-template-columns: 1fr 1fr;
      }
    }
    @media (max-width: <?= $thumbSize * 1 + 100 ?>px) {
      .image-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <h1 class="title">
<? if (!empty($listParam)): ?>
    <a href="<?= $baseUrl ?>" class="title-link">
<? endif; ?>
    <?= prepareRichText($galleryTitle) ?>
<? if (!empty($listParam)): ?>
    </a>
<? endif; ?>
  </h1>
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
        <div class="image-grid">
<? foreach ($folderData['images'] as $image): ?>
            <div class="image-item">
<?
              $encodedPath = str_replace('%2F', '/', rawurlencode($image['path']));
              $thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.cache.thumbs';

              // Determine which approach to use for thumbnail URLs
              $useThumbRedirects = isset($config['useThumbRedirects']) ? $config['useThumbRedirects'] : false;

              if ($useThumbRedirects) {
                // Old approach: Use thumb.php endpoint (dynamic processing)
                $previewUrl = $baseUrl . 'thumb.php?mode=preview&image=' . $encodedPath;
                $thumbUrl = $baseUrl . 'thumb.php?image=' . $encodedPath;

                if ($useRedirectMode) {
                  $previewUrl = $baseUrl . 'preview/' . $encodedPath;
                  $thumbUrl = $baseUrl . 'thumb/' . $encodedPath;
                }
              } else {
                // New approach: Use direct cached file URLs (better performance)
                try {
                  $previewThumbInfo = generateThumbnail($image['path'], 'preview', $previewSize, $config);
                  $previewUrl = $baseUrl . $thumbsDir . '/' . $previewThumbInfo['filename'];

                  $thumbInfo = generateThumbnail($image['path'], 'thumb', $thumbSize, $config);
                  $thumbUrl = $baseUrl . $thumbsDir . '/' . $thumbInfo['filename'];
                } catch (Exception $e) {
                  // Fallback to thumb.php if thumbnail generation fails
                  $previewUrl = $baseUrl . 'thumb.php?mode=preview&image=' . $encodedPath;
                  $thumbUrl = $baseUrl . 'thumb.php?image=' . $encodedPath;
                }
              }

              $viewUrl = $baseUrl . 'view.php?image=' . $encodedPath;
              if ($useRedirectMode) {
                $viewUrl = $baseUrl . 'view/' . $encodedPath;
              }

              // Use cached metadata from scanResults (loaded from .md files during cache build)
              $imageTitle = !empty($image['title']) ? $image['title'] : $image['name'];
              $imageDescription = isset($image['description']) ? $image['description'] : "";

              // Generate LQIP as base64-encoded data URI using helper function
              $lqipDataUri = generateBase64Thumbnail($image['path'], 'thumb', $lqipThumbSize, $config);
?>
              <a href="<?= $viewUrl ?>">
                <div class="image-wrapper"<?= $lqipDataUri ? ' data-lqip="' . htmlspecialchars($lqipDataUri) . '"' : '' ?>>
                  <img
                    src="<?= $thumbUrl ?>"
                    alt="<?= htmlspecialchars($imageTitle) ?>"
                    width="<?= htmlspecialchars($thumbSize) ?>"
                    height="<?= htmlspecialchars($thumbSize) ?>"
                    loading="lazy"
                    class="image-thumb"
                   />
<? if ($lqipDataUri): ?>
                  <img
                    src="<?= $lqipDataUri ?>"
                    alt="" aria-hidden="true"
                    width="<?= htmlspecialchars($thumbSize) ?>"
                    height="<?= htmlspecialchars($thumbSize) ?>"
                    class="image-lqip"
                   />
<? endif; ?>
                </div>
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

  <!-- Folder Navigation Buttons (Floating) -->
<? if ($folderNav['prev'] || $folderNav['next'] || $folderNav['hasRoot']): ?>
  <div class="float-panel left bottom">
<? if ($folderNav['prev']): ?>
      <a href="<?= $folderNav['prev'] ?>" class="nav-button" title="Previous folder"><i data-lucide="arrow-left"></i></a>
<? endif ?>
<? if ($folderNav['hasRoot']): ?>
    <a href="<?= $galleryHomeUrl ?>" class="nav-button" title="Back to gallery home"><i data-lucide="home"></i></a>
<? endif ?>
<? if ($folderNav['next']): ?>
      <a href="<?= $folderNav['next'] ?>" class="nav-button" title="Next folder"><i data-lucide="arrow-right"></i></a>
<? endif ?>
  </div>
<? endif ?>

  <!-- Shared scripts -->
<? include('common-html-footer.php') ?>
</body>

</html>
