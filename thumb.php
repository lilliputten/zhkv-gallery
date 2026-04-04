<?php

// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['show']) ? $_GET['show'] : '';
$full = isset($_GET['mode']) ? $_GET['mode'] === 'full' : false;

$config = loadConfig();
$thumbSize = isset($_GET['size']) ? (int)$_GET['size'] : (isset($config['thumbSize']) ? $config['thumbSize'] : 150);
$previewSize = isset($config['previewSize']) ? $config['previewSize'] : 800;
$maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : null;
$thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.thumbs.cache';

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

// Check if file exists
if (!file_exists($fullPath)) {
    die('Image not found: ' . htmlspecialchars($imagePath));
}

// Get image info
$imageInfo = getimagesize($fullPath);
if ($imageInfo === false) {
    die('Not a valid image file');
}

$mimeType = $imageInfo['mime'];
$originalWidth = $imageInfo[0];
$originalHeight = $imageInfo[1];

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

if ($full) {
    // Scale so the minimum dimension equals $previewSize
    $scale = max($previewSize / $originalWidth, $previewSize / $originalHeight);
    $newWidth = floor($originalWidth * $scale);
    $newHeight = floor($originalHeight * $scale);
    // Crop height from the top if it exceeds $maxThumbHeight
    $maxThumbHeight = $maxHeightRatio ? $newWidth * $maxHeightRatio : null;
    if ($maxThumbHeight && $newHeight > $maxThumbHeight) {
        $newHeight = (int)$maxThumbHeight;
    }
} else {
    $newWidth = $thumbSize;
    $newHeight = $thumbSize;
}

$cacheFilename = $originalName . '-' . $currentHash . '-' . $newWidth . '.webp';
$cachePath = $thumbsPath . '/' . $cacheFilename;

// Check if cached thumbnail exists (hash in filename guarantees freshness)
if (file_exists($cachePath)) {
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=86400');
    readfile($cachePath);
    exit;
}

// Try GD library first
if (extension_loaded('gd')) {
    // Create image resource based on type
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($fullPath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($fullPath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($fullPath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($fullPath);
            break;
        default:
            die('Unsupported image format');
    }

    if (!$sourceImage) {
        die('Failed to create image resource');
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

    if ($full) {
        $cropX = 0;
        $cropY = 0;
        $cropWidth = $originalWidth;
        $cropHeight = min($originalHeight, (int)floor($newHeight / $scale));
    } else {
        // Crop to maintain aspect ratio only for non-full mode
        if ($srcRatio > $dstRatio) {
            // Image is wider than square - crop width
            $cropWidth = $originalHeight;
            $cropHeight = $originalHeight;
            $cropX = floor(($originalWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            // Image is taller than square - crop height
            $cropWidth = $originalWidth;
            $cropHeight = $originalWidth;
            $cropX = 0;
            $cropY = 0; // Crop to the top
            // $cropY = floor(($originalHeight - $cropHeight) / 2); // Crop to the center
        }
    }

    // Resize the image
    imagecopyresampled(
        $thumbnail,
        $sourceImage,
        0, 0, // destination point
        $cropX, $cropY, // source point
        $newWidth, // Destination width
        $newHeight, // Destination height
        $cropWidth, // Source width
        $cropHeight // Source height
    );

    // Save thumbnail to cache and output
    imagewebp($thumbnail, $cachePath, 85);
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=86400');
    imagewebp($thumbnail, null, 85);

    // Free memory
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
}
// Try ImageMagick as fallback
elseif (class_exists('Imagick')) {
    try {
        $imagick = new Imagick($fullPath);

        // Scale or crop based on mode
        if ($full) {
            // Scale proportionally without cropping
            $imagick->thumbnailImage($newWidth, $newHeight, true);
        } else {
            // Crop to square from center
            $imagick->cropThumbnailImage($thumbSize, $thumbSize);
        }
        $imagick->setImagePage($thumbSize, $thumbSize, 0, 0);
        $imagick->setImageFormat('webp');

        // Save to cache
        $imagick->writeImage($cachePath);

        // Output the thumbnail
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=86400');
        echo $imagick->getImageBlob();
        $imagick->destroy();
    } catch (Exception $e) {
        die('ImageMagick error: ' . $e->getMessage());
    }
}

// No image library available
else {
    die('Error: No image processing library available. Please enable either GD extension or ImageMagick in your PHP configuration.<br><br>' .
        'To enable GD:<br>' .
        '1. Open your php.ini file<br>' .
        '2. Find the line: ;extension=gd<br>' .
        '3. Remove the semicolon: extension=gd<br>' .
        '4. Restart your web server');
}
?>
