# Image Gallery Demo Site

A lightweight PHP-based image gallery system that automatically scans directories for images, generates thumbnails, and displays them in an organized gallery interface.

Public link: http://temp.lilliputten.ru/zdkv/

Repository: https://github.com/lilliputten/zhkv-gallery

## Features

- **Automatic Directory Scanning**: Recursively scans subdirectories for image files
- **On-Demand Thumbnail Generation**: Creates square thumbnails with configurable size
- **Smart Caching**: Caches both directory scan results and generated thumbnails for optimal performance
- **Clean URL Support**: Optional URL rewriting for cleaner, more readable URLs
- **Configuration Management**: JSON-based configuration with local override support
- **Multiple Image Formats**: Supports JPEG, PNG, GIF, WebP, and BMP formats
- **Responsive Design**: Mobile-friendly grid layout with hover effects

## Project Structure

```
demo-site/
├── index.php           # Main gallery page - scans and displays images
├── view.php            # Full-size image viewer
├── thumb.php           # Thumbnail generator with caching
├── helpers.php         # Shared utility functions
├── index.css           # Gallery stylesheet
├── .htaccess           # Apache URL rewriting rules
├── .config.json        # Default configuration file
├── .config.local.json  # Optional local configuration overrides (gitignored)
├── .cache.index        # Cached directory scan results (auto-generated, configurable)
├── .thumbs/            # Cached thumbnail images (auto-generated, configurable)
├── example/            # Example HTML template
│   └── index.html
└── [image folders]/    # Subdirectories containing images
    └── *.jpg, *.png, etc.
```

## Modules

### 1. `index.php` - Gallery Index Page

**Purpose**: Main entry point that displays the image gallery organized by folders.

**Key Features**:
- Scans all subdirectories for image files
- Groups images by folder with section headers
- Displays square thumbnails in a responsive grid
- Links to full-size image viewer
- Caches scan results to configurable cache file (default: `.cache.index`) for faster subsequent loads
- Supports toggle between query string and clean URL modes
- Sorts folders and images alphabetically (case-insensitive)

**Configuration Used**:
- `title`: Page title displayed in browser tab and heading
- `thumbSize`: Size of thumbnails (passed to thumb.php)
- `useRedirectMode`: Default URL mode (can be overridden via `?redirect=1`)
- `indexCache`: Filename for directory scan cache

**URL Parameters**:
- `?redirect=1` or `?redirect=true`: Enable clean URL mode
- `?redirect=0` or `?redirect=false`: Disable clean URL mode (default)

**Example Usage**:
```
http://localhost/index.php
http://localhost/index.php?redirect=1
```

---

### 2. `view.php` - Full-Size Image Viewer

**Purpose**: Displays full-size images when clicked from the gallery.

**Key Features**:
- Securely serves full-size images
- Validates file paths to prevent directory traversal attacks
- Supports all common image formats
- Uses page title from configuration

**Security**:
- Prevents directory traversal attacks
- Validates that requested files exist and are valid images
- Only serves files within the project directory

**URL Parameters**:
- `show`: Path to the image file (URL-encoded, but slashes preserved)

**Example Usage**:
```
http://localhost/view.php?show=folder/image.png
http://localhost/view/folder/image.png  (with .htaccess and redirect mode)
```

---

### 3. `thumb.php` - Thumbnail Generator

**Purpose**: Generates and caches square thumbnails on-demand.

**Key Features**:
- Creates square thumbnails with center cropping
- Configurable thumbnail size from config
- Automatic caching in configurable directory (default: `.thumbs/`)
- Supports GD library and ImageMagick (fallback)
- Preserves transparency for PNG/GIF images
- Serves cached thumbnails instantly on subsequent requests

**Caching Strategy**:
- Cache directory: Configurable via `thumbsDir` in config (default: `.thumbs/`)
- Cache filename format: `{original_name}_{size}x{size}.{ext}`
- Example: `header 1_150x150.png`
- Checks cache before generating new thumbnail

**Image Processing**:
- **GD Library** (preferred): Uses `imagecopyresampled()` for high-quality resizing
- **ImageMagick** (fallback): Uses `cropThumbnailImage()` if GD is unavailable
- Center-crops images to maintain square aspect ratio
- Preserves important central content of images

**Requirements**:
- PHP GD extension (recommended) OR ImageMagick extension
- Enable in `php.ini`: `extension=gd` or install Imagick

**URL Parameters**:
- `show`: Path to the source image (URL-encoded, but slashes preserved)

**Example Usage**:
```
http://localhost/thumb.php?show=folder/image.png
```

---

### 4. `helpers.php` - Utility Functions

**Purpose**: Centralized helper functions used across multiple scripts.

**Functions**:

#### `loadConfig()`
Loads and merges configuration from `.config.json` and optional `.config.local.json`.

**Returns**: Array of merged configuration values

**Priority**: Local config overrides default config

**Example**:
```php
require_once 'helpers.php';
$config = loadConfig();
$title = $config['title'];
```

---

### 5. `index.css` - Stylesheet

**Purpose**: Styling for the gallery interface.

**Key Styles**:
- Responsive grid layout using CSS Grid
- Card-based design for image sections
- Hover effects and transitions
- Mobile-friendly breakpoints
- Clean, modern aesthetic

---

### 6. `.htaccess` - Apache Configuration

**Purpose**: URL rewriting for clean URLs.

**Rewrite Rules**:
```
RewriteEngine On
RewriteRule ^view/(.+)$ view.php?show=$1 [QSA,L]
```

**Effect**: Transforms `view/folder/image.png` → `view.php?show=folder/image.png`

**Requirements**:
- Apache web server with `mod_rewrite` enabled
- `AllowOverride All` in Apache configuration

---

## Configuration

### `.config.json` - Default Configuration

```json
{
    "title": "Image Gallery",
    "thumbSize": 150,
    "useRedirectMode": false,
    "thumbsDir": ".thumbs",
    "indexCache": ".cache.index"
}
```

**Parameters**:
- `title` (string): Title displayed in page header and browser tab
- `thumbSize` (integer): Size in pixels for square thumbnails (default: 150)
- `useRedirectMode` (boolean): Default URL mode for image links (default: false)
- `thumbsDir` (string): Directory name for storing cached thumbnails (default: ".thumbs")
- `indexCache` (string): Filename for directory scan cache (default: ".cache.index")

### `.config.local.json` - Local Overrides (Optional)

Create this file to override default settings without modifying `.config.json`. This file should be added to `.gitignore`.

**Example**:
```json
{
    "thumbSize": 200,
    "useRedirectMode": true,
    "thumbsDir": "cache/thumbnails",
    "indexCache": "cache/gallery-index.json"
}
```

**Merge Behavior**: Local config values completely override default config values. Arrays are not deep-merged.

---

## Caching System

### Directory Scan Cache

**Purpose**: Avoids repeated filesystem scans on every page load.

**Configuration**: Filename defined by `indexCache` parameter in config (default: `.cache.index`)

**Format**: JSON file containing structured directory and image data

**Behavior**:
- Created automatically on first run
- Read on subsequent visits instead of scanning
- To refresh: Delete the file and reload the page
- Location can be customized via config

**Structure**:
```json
{
    "folder/path": {
        "name": "Folder Name",
        "images": [
            {
                "name": "Image Name",
                "path": "folder/path/image.png",
                "filename": "image.png"
            }
        ]
    }
}
```

### Thumbnail Cache

**Purpose**: Stores generated thumbnails to avoid repeated image processing.

**Configuration**: Directory defined by `thumbsDir` parameter in config (default: `.thumbs/`)

**Naming Convention**: `{original_name}_{width}x{height}.{extension}`

**Examples**:
- `header 1_150x150.png`
- `Catalog Mobile_150x150.jpg`

**Behavior**:
- Directory created automatically if missing
- Checked before generating new thumbnail
- Served directly if exists (much faster)
- New thumbnails saved after generation

---

## URL Encoding

The system uses a custom URL encoding approach to preserve forward slashes in paths:

**Standard Issue**: `urlencode()` encodes `/` as `%2F`, making URLs hard to read.

**Solution**: Use `rawurlencode()` then replace `%2F` back to `/`.

**Result**:
- ❌ `folder%2Fimage.png` (hard to read)
- ✅ `folder/image.png` (clean and readable)

---

## Requirements

### Server Requirements
- PHP 7.0+ (recommended: PHP 8.0+)
- Apache web server with `mod_rewrite` (for clean URLs)
- Write permissions for cache directory and file (configured in `.config.json`)

### PHP Extensions
- **Required**: None (base PHP)
- **Recommended**: GD extension (`extension=gd` in php.ini)
- **Alternative**: ImageMagick/Imagick extension

### Enabling GD Extension

1. Open `php.ini`
2. Find: `;extension=gd` (or `;extension=php_gd.dll` on Windows)
3. Remove the semicolon: `extension=gd`
4. Restart web server

**Verify**: Visit `info.php` and search for "GD" section.

---

## Installation

1. **Clone/Copy Files**: Place all files in your web server's document root
2. **Enable GD**: Ensure PHP GD extension is enabled (see above)
3. **Set Permissions**: Ensure web server can write to project directory
4. **Add Images**: Create subdirectories and add image files
5. **Access Gallery**: Navigate to `index.php` in your browser

**Optional Steps**:
- Enable `.htaccess` for clean URLs (requires Apache mod_rewrite)
- Create `.config.local.json` for custom settings
- Adjust `thumbSize` in config for different thumbnail dimensions

---

## Usage Examples

### Basic Gallery Access
```
http://localhost/index.php
```

### Enable Clean URLs
```
http://localhost/index.php?redirect=1
```

### View Specific Image
```
http://localhost/view.php?show=260330-Previews-002/header%201.png
http://localhost/view/260330-Previews-002/header%201.png  (with clean URLs)
```

### Generate Thumbnail
```
http://localhost/thumb.php?show=260330-Previews-002/header%201.png
```

---

## Troubleshooting

### Thumbnails Not Generating
**Error**: `Call to undefined function imagecreatefrompng()`

**Solution**: Enable GD extension in php.ini (see Requirements section)

### Clean URLs Not Working
**Issue**: `view/folder/image.png` returns 404

**Solutions**:
1. Ensure `mod_rewrite` is enabled in Apache
2. Verify `.htaccess` file exists in project root
3. Check `AllowOverride All` in Apache config
4. Test with `view.php?show=...` format instead

### Permission Errors
**Issue**: Cannot write to cache directory or file

**Solution**:
```bash
# For default configuration:
chmod 755 .thumbs/
chmod 644 .cache.index
chown www-data:www-data .thumbs/ .cache.index  # Linux

# Or for custom paths defined in config:
chmod 755 <thumbsDir>/
chmod 644 <indexCache>
```

### Images Not Appearing
**Checklist**:
- Images are in subdirectories (not root)
- File extensions are supported (jpg, jpeg, png, gif, webp, bmp)
- Directory names don't start with `.` (hidden directories are skipped)
- Clear cache file (defined in `indexCache` config) and reload to rescan

---

## Performance Tips

1. **Use Caching**: Keep cache file and directory (configured in `.config.json`) intact
2. **Optimize Images**: Compress source images before uploading
3. **Adjust Thumb Size**: Smaller thumbnails = faster generation (configure via `thumbSize`)
4. **Customize Cache Paths**: Use fast storage for cache directories (configure via `thumbsDir` and `indexCache`)
5. **Enable OPcache**: Improves PHP script execution speed
6. **Use CDN**: For production, consider serving static assets via CDN

---

## Security Considerations

- **Directory Traversal Protection**: All scripts validate file paths stay within project directory
- **Input Validation**: Image paths are validated before processing
- **No File Upload**: System only reads existing files, no upload functionality
- **Hidden Files Skipped**: Directories starting with `.` are ignored during scanning

---

## License

This project is provided as-is for demonstration purposes.

---

## Author

Created for demo site image gallery functionality.

---

## Version

1.0.0
