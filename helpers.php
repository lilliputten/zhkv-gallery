<?php

// Set isDev based on environment - true if port is 8000
$serverPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
$isDev = ($serverPort === 8000);

$basePath = str_replace('\\', '/', __DIR__);

$projectTag = !$isDev ? 'v1.0.2' : '';
$vTag = isset($config['vTag']) ? $config['vTag'] : $projectTag;
$projectTagPostfix = !$isDev && !empty($vTag) ? '?v=' . $vTag : '';

$vTagPostfix = ''; // isset($vTag) ? '?v=' . $vTag : '';
$vTagPostfixPlus = ''; // isset($vTag) ? ($useRedirectMode ? '&v=' . $vTag : $vTagPostfix) : '';

/**
 * Load configuration from a folder by merging gallery.json and gallery.local.json files
 * This function accepts the config array by reference and extends it with folder-specific config
 *
 * @param string $folderPath Path to the folder containing config files
 * @param array &$config Reference to the config array to be extended
 * @return void
 */
function loadFolderConfig($folderPath, &$config, $configName = 'gallery')
{
  // Load folder config files
  $configFile = $folderPath . '/' . $configName . '.json';
  $localConfigFile = $folderPath . '/' . $configName . '.local.json';

  // Load main config file
  if (file_exists($configFile)) {
    $configData = file_get_contents($configFile);
    $folderConfig = json_decode($configData, true);
    if (is_array($folderConfig)) {
      $config = array_merge($config, $folderConfig);
    }
  }

  // Load and merge local config file (higher priority)
  if (file_exists($localConfigFile)) {
    $localConfigData = file_get_contents($localConfigFile);
    $localConfig = json_decode($localConfigData, true);
    if (is_array($localConfig)) {
      $config = array_merge($config, $localConfig);
    }
  }
}

/**
 * Parse PHP size string (e.g., '128M', '1G') to bytes
 *
 * @param string $size Size string from php.ini
 * @return int Size in bytes, or -1 if unlimited
 */
function parseSizeToBytes($size) {
  $size = trim($size);

  // Handle empty or invalid input
  if (empty($size)) {
    return -1;
  }

  $last = strtolower($size[strlen($size) - 1]);

  // Check if the last character is a letter (suffix)
  if (ctype_alpha($last)) {
    // Remove the suffix for numeric conversion
    $numericPart = substr($size, 0, -1);

    // Validate that the numeric part is actually numeric
    if (!is_numeric($numericPart)) {
      return -1;
    }

    $size = (float)$numericPart;

    switch ($last) {
      case 'g':
        $size *= 1024;
      case 'm':
        $size *= 1024;
      case 'k':
        $size *= 1024;
    }
  } else {
    // No suffix, ensure it's numeric
    if (!is_numeric($size)) {
      return -1;
    }
    $size = (float)$size;
  }

  return (int)$size;
}

function getCurrentUrlBase()
{
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http');
  return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

function getCurrentUrlPath()
{
  return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}

function getCurrentUrlPrefix()
{
  return getCurrentUrlBase() . getCurrentUrlPath() . '/';
}

function getCurrentUrl()
{
  return getCurrentUrlBase() . $_SERVER['REQUEST_URI'];
}

function faviconTag()
{
  if (file_exists(__DIR__ . '/favicon.ico')) {
    $baseUrl = getCurrentUrlPrefix();
    echo '  <link rel="icon" href="' . $baseUrl . 'favicon.ico" type="image/x-icon" />' . "\n";
  }
}

function loadConfig($imagePath = null)
{
  $configFile = __DIR__ . '/gallery.json';
  $localConfigFile = __DIR__ . '/gallery.local.json';

  // Load base config using loadFolderConfig
  $config = [];
  loadFolderConfig(__DIR__, $config);

  // Load folder-specific and image-specific config if image path is provided
  if ($imagePath && is_string($imagePath)) {
    // Extract the directory from the image path
    $imageDir = dirname($imagePath);

    // Load config from the image's parent folder (if not root directory)
    if ($imageDir !== '.' && $imageDir !== __DIR__) {
      $folderPath = __DIR__ . '/' . $imageDir;

      // Load config from the image's parent folder
      if (is_dir($folderPath)) {
        loadFolderConfig($folderPath, $config);
      }
    }

    // Load image-specific config (using the image filename without extension as config name)
    $pathInfo = pathinfo($imagePath);
    $imageFilename = $pathInfo['filename']; // filename without extension

    // Try to load image-specific config from the same directory
    if (isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.') {
      $imageDirPath = __DIR__ . '/' . $pathInfo['dirname'];
      if (is_dir($imageDirPath)) {
        loadFolderConfig($imageDirPath, $config, $imageFilename);
      }
    }
  }

  return $config;
}

function removeFileExtension($file)
{
  $filePathWithoutExt = pathinfo($file, PATHINFO_FILENAME);
  $fileDir = pathinfo($file, PATHINFO_DIRNAME);
  return ($fileDir !== '.') ? $fileDir . '/' . $filePathWithoutExt : $filePathWithoutExt;
}

/**
 * Read image title and description from {IMAGE}.md file
 * Expected format:
 * ## {title}
 *
 * {description}
 *
 * @param string $imagePath Path to the image file
 * @return array Array with 'title' and 'description' keys (may be empty strings if not found)
 */
function loadImageMetadataFromMarkdown($imagePath)
{
  global $basePath;

  $result = [
    'title' => '',
    'description' => ''
  ];

  // Remove image extension from path before adding .md
  $mdFile = $basePath . '/' . removeFileExtension($imagePath) . '.md';

  if (!file_exists($mdFile)) {
    return $result;
  }

  $content = file_get_contents($mdFile);

  // Match the pattern: ## {title}\n\n{description} or just ## {title}
  // The description is everything after ## {title} and the blank line (optional)
  if (preg_match('/^#+\s+(.+?)(?:\s*\n\s*\n(.*))?$/s', $content, $matches)) {
    $result['title'] = trim($matches[1]);
    $result['description'] = isset($matches[2]) ? trim($matches[2]) : '';
  }

  return $result;
}

/**
 * Get cached image list or create cache if needed
 * Returns a flattened list of all images for navigation
 *
 * @param array $config Configuration array
 * @param string|null $currentImagePath Optional current image path for navigation
 * @return array Array containing image list and navigation info
 */
function getImageList($config, $currentImagePath = null)
{
  global $isDev;

  $indexCache = isset($config['indexCache']) ? $config['indexCache'] : '.cache.index';
  $indexCacheValidMins = isset($config['indexCacheValidMins']) ? $config['indexCacheValidMins'] : 30;

  $cacheFile = $indexCache ? __DIR__ . '/' . $indexCache : '';
  $cacheExpired = $cacheFile && file_exists($cacheFile)
    && $indexCacheValidMins
    && (time() - filemtime($cacheFile)) > $indexCacheValidMins * 60;

  $scanResults = [];

  // Check if cache file exists and is still valid
  if (!$isDev && file_exists($cacheFile) && !$cacheExpired) {
    // Read from cache
    $cachedData = file_get_contents($cacheFile);
    $scanResults = json_decode($cachedData, true);
  } else {
    // Scan directories for images
    $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    $baseDir = __DIR__;

    // Get all subdirectories
    $items = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
      if ($item->isDir()) {
        $dirPath = $item->getPathname();
        $dirName = $item->getFilename();

        // Skip hidden directories
        if (strpos($dirName, '.') === 0) {
          continue;
        }

        // Skip thumbs directory
        $thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.cache.thumbs';
        if ($dirName === $thumbsDir) {
          continue;
        }

        // Get relative path from base directory
        $relativePath = str_replace('\\', '/', str_replace($baseDir . DIRECTORY_SEPARATOR, '', $dirPath));

        // Scan for images in this directory
        $images = [];
        $dirFiles = scandir($dirPath);

        if ($dirFiles !== false) {
          foreach ($dirFiles as $file) {
            if ($file === '.' || $file === '..') {
              continue;
            }

            $filePath = $dirPath . '/' . $file;

            // Check if it's a file (not directory)
            if (!is_file($filePath)) {
              continue;
            }

            // Check file extension
            $pathInfo = pathinfo($file);
            $extension = strtolower(isset($pathInfo['extension']) ? $pathInfo['extension'] : '');

            if (in_array($extension, $supportedExtensions)) {
              $imageName = str_replace('-', ' ', isset($pathInfo['filename']) ? $pathInfo['filename'] : $file);
              $imageRelativePath = str_replace('\\', '/', str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath));

              // Load image metadata from Markdown file
              $imageMetadata = loadImageMetadataFromMarkdown($imageRelativePath);

              $images[] = [
                'name' => $imageName,
                'path' => $imageRelativePath,
                'filename' => $file,
                'title' => $imageMetadata['title'],
                'description' => $imageMetadata['description']
              ];
            }
          }
        }

        // Only add directories that have images
        if (!empty($images)) {
          // Sort images alphabetically by name (case-insensitive)
          usort($images, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
          });

          // Load folder metadata from gallery.md if exists
          $folderMetadata = loadImageMetadataFromMarkdown($relativePath . '/gallery');

          $scanResults[$relativePath] = [
            'name' => str_replace('-', ' ', $dirName),
            'title' => $folderMetadata['title'],
            'description' => $folderMetadata['description'],
            'images' => $images
          ];
        }
      }
    }

    // Sort folders - date-tagged folders (YYMMDD) in reverse, others alphabetically
    uasort($scanResults, function ($a, $b) {
      // Check if both folder names start with a date tag (YYMMDD)
      $aIsNumbered = preg_match('/^\d{6}\b/', $a['name']);
      $bIsNumbered = preg_match('/^\d{6}\b/', $b['name']);

      // If both have date tags, sort in reverse order (newer dates first)
      if ($aIsNumbered && $bIsNumbered) {
        return strcasecmp($b['name'], $a['name']);
      }

      // If only A has date tag, it should come before B
      if ($aIsNumbered && !$bIsNumbered) {
        return -1;
      }

      // If only B has date tag, it should come before A
      if (!$aIsNumbered && $bIsNumbered) {
        return 1;
      }

      // If neither have date tags, sort alphabetically
      return strcasecmp($a['name'], $b['name']);
    });

    // Load root gallery metadata from gallery.md if exists
    $rootMetadata = loadImageMetadataFromMarkdown('gallery');

    // Build final cache structure with root metadata
    $cacheData = [
      'root' => [
        'title' => $rootMetadata['title'],
        'description' => $rootMetadata['description']
      ],
      'folders' => $scanResults
    ];

    // Save to cache
    if ($cacheFile) {
      file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // Set rootMetadata for newly generated cache
    $rootMetadata = $cacheData['root'];
    $folders = $cacheData['folders'];
  }

  // If cache was loaded (not generated), extract folders from the structure
  if (file_exists($cacheFile) && !$cacheExpired) {
    // Cache was loaded, extract folders from the new structure or use old structure for backward compatibility
    if (isset($scanResults['root']) && isset($scanResults['folders'])) {
      // New structure with root metadata
      $rootMetadata = $scanResults['root'];
      $folders = $scanResults['folders'];
    } else {
      // Old structure without root metadata (backward compatibility)
      $folders = $scanResults;
      $rootMetadata = [
        'title' => '',
        'description' => ''
      ];
    }
  }

  // Use folders for further processing
  $folderedData = $folders;

  // Flatten the array to get a simple list of images for navigation
  $flatImageList = [];
  foreach ($folderedData as $folder => $folderData) {
    foreach ($folderData['images'] as $image) {
      $flatImageList[] = $image['path'];
    }
  }

  // Find current image position and get nav info if specified
  $navInfo = [
    'prev' => null,
    'next' => null,
    'currentIndex' => -1,
    'totalImages' => count($flatImageList)
  ];

  if ($currentImagePath) {
    $currentIndex = array_search($currentImagePath, $flatImageList);
    if ($currentIndex !== false) {
      $navInfo['currentIndex'] = $currentIndex;

      // Get previous image
      if ($currentIndex > 0) {
        $navInfo['prev'] = $flatImageList[$currentIndex - 1];
      }

      // Get next image
      if ($currentIndex < count($flatImageList) - 1) {
        $navInfo['next'] = $flatImageList[$currentIndex + 1];
      }
    }
  }

  return [
    'root' => $rootMetadata,         // Root gallery metadata (title, description)
    'foldered' => $folderedData,     // Original foldered structure for index.php
    'flat' => $flatImageList,        // Flat list for navigation
    'navInfo' => $navInfo            // Navigation information if current image specified
  ];
}

function dieWithError($message, $code = 400)
{
  http_response_code($code);
  header('Content-Type: text/plain');
  exit($message);
}

/**
 * @param string|null $description
 * @param [boolean] $specialChars
 * @return string
 */
function prepareRichText($description, $specialChars = true) {
  if (!isset($description)) {
    return '';
  }
  $description = trim($description);
  if (empty($description)) {
    return '';
  }
  return preg_replace('/\s+/', ' ', $description);
}

/**
 * Generate or retrieve a cached thumbnail/preview image
 *
 * This function checks if a thumbnail already exists in cache and generates it if not.
 * It supports multiple output formats: original format, config-specified format, or parameter-specified format.
 *
 * @param string $imagePath Relative path to the source image from project root
 * @param string $mode Thumbnail mode: 'thumb' (square), 'preview' (scaled), or 'full' (scaled without height limit)
 * @param int $size Size for the thumbnail/preview (width for thumb, min dimension for preview/full)
 * @param array $config Configuration array (optional, will load if not provided)
 * @param string|null $outputFormat Optional output format override ('jpg', 'png', 'gif', 'webp')
 * @return array Dataset containing thumbnail information:
 *   - filename: Cache filename
 *   - path: Full server path to cache file
 *   - url: Public URL to cache file (relative)
 *   - width: Thumbnail width
 *   - height: Thumbnail height
 *   - exists: Boolean indicating if cache was generated or already existed
 * @throws Exception If image processing fails
 */
function generateThumbnail($imagePath, $mode = 'thumb', $size = 150, $config = null, $outputFormat = null) {
  global $isDev;

  // Load config if not provided
  if ($config === null) {
    $config = loadConfig($imagePath);
  }

  // Get configuration values
  $thumbsDir = isset($config['thumbsDir']) ? $config['thumbsDir'] : '.cache.thumbs';
  $maxHeightRatio = isset($config['maxHeightRatio']) ? $config['maxHeightRatio'] : null;
  $configImageFormat = isset($config['imageFormat']) ? $config['imageFormat'] : null;

  /* // UNSED: Temporarily increase memory limit for image processing if needed
   * $originalMemoryLimit = ini_get('memory_limit');
   * $currentMemoryLimit = parseSizeToBytes($originalMemoryLimit);
   *
   * // If current limit is less than 256MB, temporarily increase it
   * if ($currentMemoryLimit > 0 && $currentMemoryLimit < 256 * 1024 * 1024) {
   *   ini_set('memory_limit', '256M');
   * }
   */

  try {
    // Security: Validate the path to prevent directory traversal attacks
    if (empty($imagePath)) {
      throw new Exception('No image specified');
    }

    // Decode URL encoding
    $imagePath = urldecode($imagePath);

    // Prevent directory traversal attacks
    $basePath = realpath(__DIR__);
    $fullPath = realpath(__DIR__ . '/' . $imagePath);

    if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
      throw new Exception('Invalid image path', 403);
    }

    // Check if file exists
    if (!file_exists($fullPath)) {
      throw new Exception('Image not found: ' . $imagePath, 404);
    }

    // Get image info
    $imageInfo = getimagesize($fullPath);
    if ($imageInfo === false) {
      throw new Exception('Not a valid image file: ' . $imagePath, 422);
    }

    $mimeType = $imageInfo['mime'];
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];

    // Validate image dimensions
    if ($originalWidth <= 0 || $originalHeight <= 0) {
      throw new Exception('Invalid image dimensions: ' . $originalWidth . 'x' . $originalHeight, 422);
    }

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

    // Calculate dimensions based on mode
    $srcRatio = $originalWidth / $originalHeight;

    if ($mode === 'preview' || $mode === 'full') {
      // Scale so the minimum dimension equals $size
      $scale = max($size / $originalWidth, $size / $originalHeight);
      $newWidth = floor($originalWidth * $scale);
      $newHeight = floor($originalHeight * $scale);

      // Crop height from the top if it exceeds maxHeightRatio (preview mode only, not full)
      if ($mode === 'preview' && $maxHeightRatio) {
        $maxThumbHeight = $newWidth * $maxHeightRatio;
        if ($newHeight > $maxThumbHeight) {
          $newHeight = (int) $maxThumbHeight;
        }
      }
    } else {
      // Square thumbnail
      $newWidth = $size;
      $newHeight = $size;
    }

    // Determine output format
    // Priority: parameter > config > original format
    $finalFormat = $outputFormat ?: $configImageFormat ?: $fileExt;

    // Map MIME type to extension if using original format
    if (!$outputFormat && !$configImageFormat) {
      switch ($mimeType) {
        case 'image/jpeg':
          $finalFormat = 'jpg';
          break;
        case 'image/png':
          $finalFormat = 'png';
          break;
        case 'image/gif':
          $finalFormat = 'gif';
          break;
        case 'image/webp':
          $finalFormat = 'webp';
          break;
      }
    }

    // Build cache filename
    $modeSuffix = ($mode === 'full') ? 'full' : (($mode === 'preview') ? 'preview' : 'thumb');
    $cacheFilename = $originalName . '-' . $currentHash . '-' . $modeSuffix . '-' . $newWidth . 'x' . $newHeight . '.' . $finalFormat;
    $cachePath = $thumbsPath . '/' . $cacheFilename;

    // Check if cached thumbnail exists
    $cacheExists = file_exists($cachePath);

    if (!$cacheExists) {
      // Cache doesn't exist, generate it

      // Try GD library first
      if (extension_loaded('gd')) {
        $sourceImage = null;
        $thumbnail = null;

        try {
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
              throw new Exception('Unsupported image format: ' . $mimeType, 415);
          }

          if (!$sourceImage) {
            throw new Exception('Failed to create image resource from: ' . $imagePath . ' (format: ' . $mimeType . ')', 500);
          }

          // Create thumbnail image
          $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

          // Preserve transparency for PNG and GIF
          if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
          }

          // Calculate crop coordinates
          if ($mode === 'preview' || $mode === 'full') {
            $cropX = 0;
            $cropY = 0;
            $cropWidth = $originalWidth;
            $cropHeight = min($originalHeight, (int) floor($newHeight / max($size / $originalWidth, $size / $originalHeight)));
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
            0, 0, // destination point
            $cropX, $cropY, // source point
            $newWidth, $newHeight, // Destination dimensions
            $cropWidth, $cropHeight // Source dimensions
          );

          // Save thumbnail to cache based on output format
          switch ($finalFormat) {
            case 'png':
              imagepng($thumbnail, $cachePath, 6); // Compression level 0-9, 6 is a good balance
              break;
            case 'gif':
              imagegif($thumbnail, $cachePath);
              break;
            case 'webp':
              imagewebp($thumbnail, $cachePath, 85); // Quality 0-100
              break;
            case 'jpg':
            default:
              imagejpeg($thumbnail, $cachePath, 85); // Quality 0-100
              break;
          }

        } finally {
          // Free up memory associated with the image resources
          // imagedestroy() is deprecated since PHP 8.5 as resources are automatically managed
          if (PHP_VERSION_ID < 80500) {
            if (is_resource($sourceImage)) {
              imagedestroy($sourceImage);
            }
            if (is_resource($thumbnail)) {
              imagedestroy($thumbnail);
            }
          }
        }
      }
      // Try ImageMagick as fallback
      elseif (class_exists('Imagick')) {
        $imagick = new Imagick($fullPath);
        try {
          // Scale or crop based on mode
          if ($mode === 'preview' || $mode === 'full') {
            // Scale proportionally without cropping
            $imagick->thumbnailImage($newWidth, $newHeight, true);
          } else {
            // Crop to square from center
            $imagick->cropThumbnailImage($size, $size);
          }
          $imagick->setImagePage($size, $size, 0, 0);

          // Set output format
          switch ($finalFormat) {
            case 'png':
              $imagick->setImageFormat('png');
              break;
            case 'gif':
              $imagick->setImageFormat('gif');
              break;
            case 'webp':
              $imagick->setImageFormat('webp');
              break;
            case 'jpg':
            default:
              $imagick->setImageFormat('jpeg');
              break;
          }

          // Save to cache
          $imagick->writeImage($cachePath);
        } finally {
          $imagick->destroy();
        }
      }
      // No image library available
      else {
        throw new Exception('No image processing library available. Enable GD or ImageMagick in php.ini.', 500);
      }

      // Verify cache was created successfully
      if (!file_exists($cachePath)) {
        throw new Exception('Failed to generate thumbnail cache', 500);
      }
    }

    // Verify cache path is valid
    $resolvedCache = realpath($cachePath);
    if ($resolvedCache === false || strpos($resolvedCache, realpath($thumbsPath)) !== 0) {
      throw new Exception('Invalid cache path', 403);
    }

    // Return dataset with thumbnail information
    return [
      'filename' => $cacheFilename,
      'path' => $cachePath,
      'url' => $thumbsDir . '/' . $cacheFilename,
      'width' => $newWidth,
      'height' => $newHeight,
      'exists' => $cacheExists,
      'format' => $finalFormat,
      'mode' => $mode,
      'size' => $size
    ];
  } finally {
    // Note: Not restoring memory limit - letting it stay increased for subsequent operations
    // ini_set('memory_limit', $originalMemoryLimit);

    // Log if we encountered memory pressure during processing
    $peakMemory = memory_get_peak_usage(true);
    $currentMemory = memory_get_usage(true);
    $limitBytes = parseSizeToBytes(ini_get('memory_limit'));

    if ($limitBytes > 0) {
      $usagePercent = ($currentMemory / $limitBytes) * 100;
      if ($usagePercent > 80) {
        error_log(sprintf(
          '[Image Processing] High memory usage detected: %.1f%% (%.2f MB / %.2f MB), Peak: %.2f MB',
          $usagePercent,
          $currentMemory / (1024 * 1024),
          $limitBytes / (1024 * 1024),
          $peakMemory / (1024 * 1024)
        ));
      }
    }
  }
}

/**
 * Generate a base64-encoded data URI for an image thumbnail (useful for LQIP)
 * This function generates the thumbnail if it doesn't exist, reads the file,
 * and encodes it as a base64 data URI for inline embedding in HTML.
 *
 * @param string $imagePath Path to the original image
 * @param string $mode Thumbnail mode: 'thumb', 'preview', or 'full'
 * @param int $size Size parameter for thumbnail generation
 * @param array $config Configuration array
 * @return string|null Base64 data URI (e.g., 'data:image/jpeg;base64,...') or null on failure
 */
function generateBase64Thumbnail($imagePath, $mode = 'thumb', $size = 20, $config = null)
{
  try {
    // Generate thumbnail using existing function (cached)
    $thumbnailInfo = generateThumbnail($imagePath, $mode, $size, $config);
    $thumbnailFilePath = __DIR__ . '/' . $thumbnailInfo['url'];

    // Check if thumbnail file exists
    if (!file_exists($thumbnailFilePath)) {
      return null;
    }

    // Get file size to avoid loading very large files
    $fileSize = filesize($thumbnailFilePath);
    if ($fileSize === false || $fileSize > 1024 * 1024) { // Skip files larger than 1MB
      return null;
    }

    // Read the thumbnail file
    $imageData = file_get_contents($thumbnailFilePath);
    if ($imageData === false || empty($imageData)) {
      return null;
    }

    // Encode to base64
    $base64 = base64_encode($imageData);

    // Immediately free the raw image data to reduce memory pressure
    unset($imageData);

    // Determine MIME type based on format
    $mimeType = 'image/jpeg'; // default
    switch ($thumbnailInfo['format']) {
      case 'png':
        $mimeType = 'image/png';
        break;
      case 'gif':
        $mimeType = 'image/gif';
        break;
      case 'webp':
        $mimeType = 'image/webp';
        break;
    }

    // Build and return data URI
    $dataUri = 'data:' . $mimeType . ';base64,' . $base64;

    // Free the base64 string (it's now part of the return value)
    unset($base64);

    return $dataUri;
  } catch (Exception $e) {
    // If anything fails, return null
    return null;
  }
}
