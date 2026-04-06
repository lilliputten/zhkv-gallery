# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added OpenGraph meta tags to `index.php` and `view.php` for social media sharing
- Implemented support for `maxHeightRatio` parameter in OpenGraph image height calculations
- Added Twitter Card meta tags for enhanced Twitter sharing support

### Fixed
- Fixed escaped slashes in OpenGraph URLs by correcting backslash replacement logic
- Improved URL generation to prevent double-escaping of forward slashes
- Enhanced OpenGraph image height calculation to respect `maxHeightRatio` constraints

### Technical
- Refactored URL construction in OpenGraph tags to use pre-built variables
- Improved consistency between thumbnail generation and OpenGraph preview dimensions
- Unified social media meta tag implementation across gallery and image view pages