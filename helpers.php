<?php

// Set isDev based on environment - true if port is 8000
$serverPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
$isDev = ($serverPort === 8000);

$basePath = str_replace('\\', '/', __DIR__);

$projectTag = 'v0.0.1a';

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

function getCurrentUrlBase()
{
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http');
  return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

function getCurrentUrlPrefix()
{
  return getCurrentUrlBase() . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
}

function getCurrentUrl()
{
  return getCurrentUrlBase() . $_SERVER['REQUEST_URI'];
}

function faviconTag()
{
  if (file_exists(__DIR__ . '/favicon.ico')) {
    echo '  <link rel="icon" href="favicon.ico" type="image/x-icon" />' . "\n";
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

              $images[] = [
                'name' => $imageName,
                'path' => $imageRelativePath,
                'filename' => $file
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

    // Save to cache
    if ($cacheFile) {
      file_put_contents($cacheFile, json_encode($scanResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
  }

  // Flatten the array to get a simple list of images for navigation
  $flatImageList = [];
  foreach ($scanResults as $folder => $folderData) {
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
    'foldered' => $scanResults,   // Original foldered structure for index.php
    'flat' => $flatImageList,      // Flat list for navigation
    'navInfo' => $navInfo          // Navigation information if current image specified
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
