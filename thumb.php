<?php

// Load helpers
require_once __DIR__ . '/helpers.php';

$config = loadConfig();
$thumbSize = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
$thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.thumbs.cache';

// Get the image path from the URL parameter
$imagePath = isset($_GET['show']) ? $_GET['show'] : '';

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
$cacheFilename = $originalName . '_' . $thumbSize . 'x' . $thumbSize . '-' . $currentHash . '.webp';
$cachePath = $thumbsPath . '/' . $cacheFilename;

// Check if cached thumbnail exists (hash in filename guarantees freshness)
if (file_exists($cachePath)) {
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=86400');
    readfile($cachePath);
    exit;
}

// Thumbnail needs to be generated
$newWidth = $thumbSize;
$newHeight = $thumbSize;

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

    // Calculate cropping to make it square (center crop)
    $srcRatio = $originalWidth / $originalHeight;
    $dstRatio = 1; // Square

    if ($srcRatio > $dstRatio) {
        // Image is wider than square - crop width
        $cropWidth = floor($originalHeight);
        $cropHeight = $originalHeight;
        $cropX = floor(($originalWidth - $cropWidth) / 2);
        $cropY = 0;
    } else {
        // Image is taller than square - crop height
        $cropWidth = $originalWidth;
        $cropHeight = floor($originalWidth);
        $cropX = 0;
        $cropY = floor(($originalHeight - $cropHeight) / 2);
    }

    // Resize and crop the image to square
    imagecopyresampled(
        $thumbnail,
        $sourceImage,
        0, 0, $cropX, $cropY,
        $newWidth,
        $newHeight,
        $cropWidth,
        $cropHeight
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

        // Crop to square from center
        $imagick->cropThumbnailImage($thumbSize, $thumbSize);
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
