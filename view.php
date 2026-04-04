<?php
// Load helpers
require_once __DIR__ . '/helpers.php';

$config = loadConfig();
$title = isset($config['title']) ? $config['title'] : 'Image Gallery';

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

// Check if file exists and is an image
if (!file_exists($fullPath)) {
    die('Image not found: ' . htmlspecialchars($imagePath));
}

// Get image info to determine MIME type
$imageInfo = getimagesize($fullPath);
if ($imageInfo === false) {
    die('Not a valid image file');
}

$mimeType = $imageInfo['mime'];
?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($title) . ': ' . htmlspecialchars($imagePath); ?></title>
    <style>
      body {
        padding: 0;
        margin: 0;
      }
      .image {
          width: 100%;
          height: auto;
      }
    </style>
  </head>
  <body>
    <img class="image" src="<?php echo htmlspecialchars($imagePath); ?>" border="0" />
  </body>
</html>
