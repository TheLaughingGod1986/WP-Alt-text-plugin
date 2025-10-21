#!/bin/bash

# Create WordPress.org assets from HTML templates and SVG files
# This script requires: wkhtmltopdf, ImageMagick, and a web browser

echo "Creating WordPress.org assets..."

# Create screenshots directory
mkdir -p screenshots

# Check if wkhtmltoimage is available
if command -v wkhtmltoimage &> /dev/null; then
    echo "Using wkhtmltoimage to create screenshots..."
    
    # Create screenshots from HTML files
    wkhtmltoimage --width 1200 --height 800 screenshot-1.html screenshots/screenshot-1.png
    wkhtmltoimage --width 1200 --height 800 screenshot-2.html screenshots/screenshot-2.png
    wkhtmltoimage --width 1200 --height 800 screenshot-3.html screenshots/screenshot-3.png
    wkhtmltoimage --width 1200 --height 800 screenshot-4.html screenshots/screenshot-4.png
    wkhtmltoimage --width 1200 --height 800 screenshot-5.html screenshots/screenshot-5.png
    wkhtmltoimage --width 1200 --height 800 screenshot-6.html screenshots/screenshot-6.png
    
elif command -v google-chrome &> /dev/null; then
    echo "Using Chrome to create screenshots..."
    
    # Create screenshots using Chrome headless
    google-chrome --headless --disable-gpu --window-size=1200,800 --screenshot=screenshots/screenshot-1.png file://$(pwd)/screenshot-1.html
    google-chrome --headless --disable-gpu --window-size=1200,800 --screenshot=screenshots/screenshot-2.png file://$(pwd)/screenshot-2.html
    google-chrome --headless --disable-gpu --window-size=1200,800 --screenshot=screenshots/screenshot-3.png file://$(pwd)/screenshot-3.html
    google-chrome --headless --disable-gpu --window-size=1200,800 --screenshot=screenshots/screenshot-4.png file://$(pwd)/screenshot-4.html
    google-chrome --headless --disable-gpu --window-size=1200,800 --screenshot=screenshots/screenshot-5.png file://$(pwd)/screenshot-5.html
    google-chrome --headless --disable-gpu --window-size=1200,800 --screenshot=screenshots/screenshot-6.png file://$(pwd)/screenshot-6.html
    
else
    echo "No suitable tool found for creating screenshots."
    echo "Please install wkhtmltoimage or Chrome, or create screenshots manually."
    echo "Required files:"
    echo "  - screenshot-1.png (Dashboard)"
    echo "  - screenshot-2.png (ALT Library)"
    echo "  - screenshot-3.png (Settings)"
    echo "  - screenshot-4.png (Media Library)"
    echo "  - screenshot-5.png (Toast Notifications)"
    echo "  - screenshot-6.png (Upgrade Modal)"
fi

# Convert SVG to PNG using ImageMagick
if command -v convert &> /dev/null; then
    echo "Converting SVG assets to PNG..."
    
    # Create banner assets
    convert banner.svg -resize 772x250 banner-772x250.png
    convert banner.svg -resize 1544x500 banner-1544x500.png
    
    # Create icon assets
    convert icon.svg -resize 128x128 icon-128x128.png
    convert icon.svg -resize 256x256 icon-256x256.png
    
    echo "Assets created successfully!"
    echo ""
    echo "Created files:"
    echo "  - banner-772x250.png"
    echo "  - banner-1544x500.png"
    echo "  - icon-128x128.png"
    echo "  - icon-256x256.png"
    echo "  - screenshots/screenshot-1.png through screenshot-6.png"
    
else
    echo "ImageMagick not found. Please install it or convert SVG files manually."
    echo "Required PNG files:"
    echo "  - banner-772x250.png"
    echo "  - banner-1544x500.png"
    echo "  - icon-128x128.png"
    echo "  - icon-256x256.png"
fi

echo ""
echo "WordPress.org assets creation complete!"
echo "You can now submit your plugin to WordPress.org with these assets."
