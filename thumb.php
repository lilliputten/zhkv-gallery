<?php

// Load helpers
require_once __DIR__ . '/helpers.php';

// Get the image path from the URL parameter
$imagePath = isset($_GET['image']) ? $_GET['image'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'thumb'; // 'thumb', 'preview', or 'full'
$size = isset($_GET['size']) ? (int) $_GET['size'] : null;

// Validate mode parameter
if (!in_array($mode, ['thumb', 'preview', 'full'])) {
  dieWithError('Invalid mode: ' . $mode, 400);
}

// Load configuration
$config = loadConfig();

// Determine size based on mode
if ($size === null) {
  if ($mode === 'thumb') {
    $size = isset($config['thumbSize']) ? $config['thumbSize'] : 150;
  } else {
    // preview or full mode
    $size = isset($config['previewSize']) ? $config['previewSize'] : 300;
  }
}

try {
  // Generate or retrieve cached thumbnail
  $thumbData = generateThumbnail($imagePath, $mode, $size, $config);

  // Build FQDN URL to the cached file
  $baseUrl = getCurrentUrlPrefix();
  $cacheUrl = $baseUrl . $thumbData['url'];

  // Redirect to the cached thumbnail with FQDN URL
  header('Location: ' . $cacheUrl, true, 302);
  header('Cache-Control: no-cache, must-revalidate');
  exit;

} catch (Exception $e) {
  // Map exception codes to HTTP status codes
  $statusCode = $e->getCode();
  if ($statusCode < 400 || $statusCode > 599) {
    $statusCode = 500; // Default to 500 if invalid code
  }
  dieWithError($e->getMessage(), $statusCode);
}
