#!/bin/bash

# Create placeholder WordPress.org assets
echo "Creating WordPress.org placeholder assets..."

# Create screenshots directory
mkdir -p screenshots

# Create placeholder PNG files using ImageMagick if available
if command -v convert &> /dev/null; then
    echo "Using ImageMagick to create assets..."
    
    # Create banner assets
    convert -size 772x250 xc:'#667eea' -fill white -pointsize 32 -gravity center -annotate +0+0 "AI Alt Text Generator" banner-772x250.png
    convert -size 1544x500 xc:'#667eea' -fill white -pointsize 64 -gravity center -annotate +0+0 "AI Alt Text Generator" banner-1544x500.png
    
    # Create icon assets
    convert -size 128x128 xc:'#667eea' -fill white -pointsize 32 -gravity center -annotate +0+0 "AI" icon-128x128.png
    convert -size 256x256 xc:'#667eea' -fill white -pointsize 64 -gravity center -annotate +0+0 "AI" icon-256x256.png
    
    # Create screenshot placeholders
    convert -size 1200x800 xc:'#f0f0f1' -fill '#1d2327' -pointsize 24 -gravity center -annotate +0-50 "Dashboard" -fill '#646970' -pointsize 16 -gravity center -annotate +0+20 "Main dashboard with usage stats" screenshots/screenshot-1.png
    convert -size 1200x800 xc:'#f0f0f1' -fill '#1d2327' -pointsize 24 -gravity center -annotate +0-50 "ALT Library" -fill '#646970' -pointsize 16 -gravity center -annotate +0+20 "Image library with alt text" screenshots/screenshot-2.png
    convert -size 1200x800 xc:'#f0f0f1' -fill '#1d2327' -pointsize 24 -gravity center -annotate +0-50 "Settings" -fill '#646970' -pointsize 16 -gravity center -annotate +0+20 "Plugin settings and configuration" screenshots/screenshot-3.png
    convert -size 1200x800 xc:'#f0f0f1' -fill '#1d2327' -pointsize 24 -gravity center -annotate +0-50 "Media Library" -fill '#646970' -pointsize 16 -gravity center -annotate +0+20 "WordPress media library integration" screenshots/screenshot-4.png
    convert -size 1200x800 xc:'#f0f0f1' -fill '#1d2327' -pointsize 24 -gravity center -annotate +0-50 "Notifications" -fill '#646970' -pointsize 16 -gravity center -annotate +0+20 "Toast notifications and feedback" screenshots/screenshot-5.png
    convert -size 1200x800 xc:'#f0f0f1' -fill '#1d2327' -pointsize 24 -gravity center -annotate +0-50 "Upgrade Modal" -fill '#646970' -pointsize 16 -gravity center -annotate +0+20 "Pro upgrade prompt and pricing" screenshots/screenshot-6.png
    
    echo "Assets created successfully with ImageMagick!"
    
else
    echo "ImageMagick not found. Creating simple placeholder files..."
    
    # Create simple placeholder files
    echo "AI Alt Text Generator Banner" > banner-772x250.txt
    echo "AI Alt Text Generator Banner (Retina)" > banner-1544x500.txt
    echo "AI Alt Text Generator Icon" > icon-128x128.txt
    echo "AI Alt Text Generator Icon (Retina)" > icon-256x256.txt
    
    echo "Dashboard Screenshot" > screenshots/screenshot-1.txt
    echo "ALT Library Screenshot" > screenshots/screenshot-2.txt
    echo "Settings Screenshot" > screenshots/screenshot-3.txt
    echo "Media Library Screenshot" > screenshots/screenshot-4.txt
    echo "Notifications Screenshot" > screenshots/screenshot-5.txt
    echo "Upgrade Modal Screenshot" > screenshots/screenshot-6.txt
    
    echo "Placeholder text files created. Please replace with actual PNG images."
fi

echo ""
echo "WordPress.org assets ready!"
echo ""
echo "Next steps:"
echo "1. Replace placeholder files with actual PNG images"
echo "2. Ensure all files are in the correct format:"
echo "   - banner-772x250.png (772x250 pixels)"
echo "   - banner-1544x500.png (1544x500 pixels)"
echo "   - icon-128x128.png (128x128 pixels)"
echo "   - icon-256x256.png (256x256 pixels)"
echo "   - screenshots/screenshot-1.png through screenshot-6.png (1200x800 pixels)"
echo ""
echo "3. Submit to WordPress.org with these assets"
