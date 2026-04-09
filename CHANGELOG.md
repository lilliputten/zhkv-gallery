# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Renamed configuration files from `.config.json` and `.config.local.json` to `gallery.json` and `gallery.local.json`
- Added `gallery.local.json.EXAMPLE` template file for easy local configuration setup
- Updated all documentation references to use new configuration file names

## [1.0.1] - 2026-04-08

### Added
- Added prev/next navigation with FontAwesome icons in `view.php`
- Implemented image metadata (name/description) support via JSON files
- Added UTF-8 encoding meta tags to all pages
- Created image information popup in `view.php` with toggle functionality
- Added cache-based image list management in `helpers.php` for navigation
- Enhanced configuration handling with `maxWidth` fallback to image dimensions
- Added caching system with automatic revalidation (30 minutes default)
- FontAwesome integration for navigation and info icons

### Changed
- Refactored cache logic from `index.php` to reusable `getImageList()` function in `helpers.php`
- Improved URL encoding to preserve forward slashes in image paths
- Consolidated navigation buttons styling using `nav-button` class
- Moved info button into main navigation panel for better UI consistency
- Updated styling architecture with consolidated CSS in head section
- Enhanced folder sorting: date-tagged folders (YYMMDD) sorted chronologically with newest first, regular folders alphabetically

### Fixed
- Fixed slash encoding in URLs to prevent `%2F` escapes
- Improved URL consistency between `index.php` and `view.php` redirect modes
- Enhanced JSON file path generation (proper extension removal)
- Eliminated redundant JSON file reading in `view.php` when config data available

## [1.0.0] - 2026-03-30

### Added
- Initial release of PHP image gallery system
- Automatic directory scanning and thumbnail generation
- OpenGraph and Twitter Card meta tags for social sharing
- URL rewriting support for clean URLs
- JSON-based configuration system with local overrides
- GD and ImageMagick thumbnail generation support
- Comprehensive documentation and installation guide