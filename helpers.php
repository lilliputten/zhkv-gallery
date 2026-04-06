<?php
/**
 * Load configuration from a folder by merging .config.json and .config.local.json files
 * This function accepts the config array by reference and extends it with folder-specific config
 *
 * @param string $folderPath Path to the folder containing config files
 * @param array &$config Reference to the config array to be extended
 * @return void
 */
function loadFolderConfig($folderPath, &$config, $configName = '.config') {
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
 * Load and merge configuration from .config.json and optional .config.local.json
 * Also loads config from the image's parent folder if an image path is provided
 *
 * @param string $imagePath Optional path to an image file to load folder-specific config
 * @return array Merged configuration array
 */
function faviconTag() {
    if (file_exists(__DIR__ . '/favicon.ico')) {
        echo '<link rel="icon" href="favicon.ico" type="image/x-icon" />' . "\n";
    }
}

function loadConfig($imagePath = null) {
    $configFile = __DIR__ . '/.config.json';
    $localConfigFile = __DIR__ . '/.config.local.json';

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
