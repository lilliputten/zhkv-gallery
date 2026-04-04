<?php
/**
 * Load and merge configuration from .config.json and optional .config.local.json
 *
 * @return array Merged configuration array
 */
function loadConfig() {
    $configFile = __DIR__ . '/.config.json';
    $localConfigFile = __DIR__ . '/.config.local.json';

    // Load default config
    $config = [];
    if (file_exists($configFile)) {
        $configData = file_get_contents($configFile);
        $config = json_decode($configData, true);
    }

    // Load and merge local config (optional)
    if (file_exists($localConfigFile)) {
        $localConfigData = file_get_contents($localConfigFile);
        $localConfig = json_decode($localConfigData, true);
        if (is_array($localConfig)) {
            $config = array_merge($config, $localConfig);
        }
    }

    return $config;
}
