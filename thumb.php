<?php

// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['image']) ? $_GET['image'] : '';
$preview = isset($_GET['mode']) ? $_GET['mode'] === 'preview' : false;
$full = isset($_GET['mode']) ? $_GET['mode'] === 'full' : false;

$config = loadConfig();
$thumbSize = isset($_GET['size']) ? (int) $_GET['size'] : (isset($config['thumbSize']) ? $config['thumbSize'] : 150);
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 300;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : null;
$thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.cache.thumbs';

// Security: Validate the path to prevent directory traversal attacks
if (empty($imagePath)) {
  dieWithError('No image specified');
}

// Decode URL encoding (e.g., %20 becomes space)
$imagePath = urldecode($imagePath);

// Prevent directory traversal attacks
$basePath = realpath(__DIR__);
$fullPath = realpath(__DIR__ . '/' . $imagePath);

if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
  dieWithError('Invalid image path', 403);
}

// Check if file exists
if (!file_exists($fullPath)) {
  dieWithError('Image not found: ' . $imagePath, 404);
}

// Get image info
$imageInfo = getimagesize($fullPath);
if ($imageInfo === false) {
  dieWithError('Not a valid image file: ' . $imagePath, 422);
}

$mimeType = $imageInfo['mime'];
$originalWidth = $imageInfo[0];
$originalHeight = $imageInfo[1];

// Validate image dimensions
if ($originalWidth <= 0 || $originalHeight <= 0) {
  dieWithError('Invalid image dimensions: ' . $originalWidth . 'x' . $originalHeight, 422);
}

// Define thumbnail dimensions (square) - using config value
// $thumbSize is already set from config above

// Create thumbs directory if it doesn't exist
$thumbsPath = __DIR__ . '/' . $thumbsDir;
if (!file_exists($thumbsPath)) {
  mkdir($thumbsPath, 0755, true);
}

// Generate cache filename based on original image name and size
$pathInfo = pathinfo($imagePath);
$originalName = $pathInfo['filename'];
$fileExt = strtolower($pathInfo['extension']);
$currentHash = substr(md5_file($fullPath), 0, 8);

// Calculate dimensions
$srcRatio = $originalWidth / $originalHeight;

if ($preview || $full) {
  // Scale so the minimum dimension equals $previewSize
  $scale = max($previewSize / $originalWidth, $previewSize / $originalHeight);
  $newWidth = floor($originalWidth * $scale);
  $newHeight = floor($originalHeight * $scale);
  // Crop height from the top if it exceeds $maxThumbHeight (preview mode only, not full)
  $maxThumbHeight = (!$full && $maxHeightRatio) ? $newWidth * $maxHeightRatio : null;
  if ($maxThumbHeight && $newHeight > $maxThumbHeight) {
    $newHeight = (int) $maxThumbHeight;
  }
} else {
  $newWidth = $thumbSize;
  $newHeight = $thumbSize;
}

// Determine output format and extension based on original image format
$outputFormat = $fileExt;
$outputMimeType = $mimeType;
switch ($mimeType) {
  case 'image/jpeg':
    $outputFormat = 'jpg';
    break;
  case 'image/png':
    $outputFormat = 'png';
    break;
  case 'image/gif':
    $outputFormat = 'gif';
    break;
  case 'image/webp':
    $outputFormat = 'webp';
    break;
}

$cacheFilename = $originalName . '-' . $currentHash . '-' . ($preview ? ($full ? 'full' : 'preview') : 'thumb') . '-' . $newWidth . 'x' . $newHeight . '.' . $outputFormat;
$cachePath = $thumbsPath . '/' . $cacheFilename;

// Check if cached thumbnail exists (hash in filename guarantees freshness)
if (!file_exists($cachePath)) {
  // Cache doesn't exist, generate it

  // Try GD library first
  if (extension_loaded('gd')) {
    // Create image resource based on type
    switch ($mimeType) {
      case 'image/jpeg':
        $sourceImage = @imagecreatefromjpeg($fullPath);
        break;
      case 'image/png':
        $sourceImage = @imagecreatefrompng($fullPath);
        break;
      case 'image/gif':
        $sourceImage = @imagecreatefromgif($fullPath);
        break;
      case 'image/webp':
        $sourceImage = @imagecreatefromwebp($fullPath);
        break;
      default:
        dieWithError('Unsupported image format: ' . $mimeType, 415);
    }

    if (!$sourceImage) {
      dieWithError('Failed to create image resource from: ' . $imagePath . ' (format: ' . $mimeType . ')', 500);
    }

    // Create square thumbnail image
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
      imagealphablending($thumbnail, false);
      imagesavealpha($thumbnail, true);
      $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
      imagefill($thumbnail, 0, 0, $transparent);
    }

    if ($preview || $full) {
      $cropX = 0;
      $cropY = 0;
      $cropWidth = $originalWidth;
      $cropHeight = min($originalHeight, (int) floor($newHeight / $scale));
    } else {
      // Square crop for thumb mode
      if ($srcRatio > 1) {
        // Wider than tall: crop sides
        $cropHeight = $originalHeight;
        $cropWidth = $originalHeight;
        $cropX = (int) floor(($originalWidth - $cropWidth) / 2);
        $cropY = 0;
      } else {
        // Taller than wide: crop bottom
        $cropWidth = $originalWidth;
        $cropHeight = $originalWidth;
        $cropX = 0;
        $cropY = 0;
      }
    }

    // Resize the image
    imagecopyresampled(
      $thumbnail,
      $sourceImage,
      0,
      0, // destination point
      $cropX,
      $cropY, // source point
      $newWidth, // Destination width
      $newHeight, // Destination height
      $cropWidth, // Source width
      $cropHeight // Source height
    );

    // Save thumbnail to cache based on original format
    switch ($mimeType) {
      case 'image/png':
        imagepng($thumbnail, $cachePath, 6); // Compression level 0-9, 6 is a good balance
        break;
      case 'image/gif':
        imagegif($thumbnail, $cachePath);
        break;
      case 'image/webp':
        imagewebp($thumbnail, $cachePath, 85); // Quality 0-100
        break;
      case 'image/jpeg':
      default:
        imagejpeg($thumbnail, $cachePath, 85); // Quality 0-100
        break;
    }

    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
  }
  // Try ImageMagick as fallback
  elseif (class_exists('Imagick')) {
    try {
      $imagick = new Imagick($fullPath);

      // Scale or crop based on mode
      if ($preview || $full) {
        // Scale proportionally without cropping
        $imagick->thumbnailImage($newWidth, $newHeight, true);
      } else {
        // Crop to square from center
        $imagick->cropThumbnailImage($thumbSize, $thumbSize);
      }
      $imagick->setImagePage($thumbSize, $thumbSize, 0, 0);

      // Set output format based on original image
      switch ($mimeType) {
        case 'image/png':
          $imagick->setImageFormat('png');
          break;
        case 'image/gif':
          $imagick->setImageFormat('gif');
          break;
        case 'image/webp':
          $imagick->setImageFormat('webp');
          break;
        case 'image/jpeg':
        default:
          $imagick->setImageFormat('jpeg');
          break;
      }

      // Save to cache
      $imagick->writeImage($cachePath);
      $imagick->destroy();
    } catch (Exception $e) {
      dieWithError('ImageMagick error: ' . $e->getMessage(), 500);
    }
  }
  // No image library available
  else {
    dieWithError('No image processing library available. Enable GD or ImageMagick in php.ini.', 500);
  }

  // Verify cache was created successfully
  if (!file_exists($cachePath)) {
    dieWithError('Failed to generate thumbnail cache', 500);
  }
}

// Cache exists (either already existed or just created), redirect to it
$resolvedCache = realpath($cachePath);
if ($resolvedCache === false || strpos($resolvedCache, realpath($thumbsPath)) !== 0) {
  dieWithError('Invalid cache path', 403);
}

// Build the public URL to the cache file
$cacheUrl = $thumbsDir . '/' . $cacheFilename;

// Redirect to the cached thumbnail
header('Location: ' . $cacheUrl, true, 302);
header('Cache-Control: no-cache, must-revalidate');
exit;
