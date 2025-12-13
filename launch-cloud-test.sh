#!/bin/bash
#
# Automated Cloud WordPress Test Launcher
# Provides multiple CLI/API options for cloud testing
#

set -e

echo "======================================"
echo "Cloud WordPress Test Options"
echo "======================================"
echo ""

PLUGIN_ZIP="$(pwd)/dist/beepbeep-ai-alt-text-generator.4.2.3.zip"
PLUGIN_NAME="BeepBeep AI Alt Text Generator"

if [ ! -f "$PLUGIN_ZIP" ]; then
    echo "❌ Plugin ZIP not found: $PLUGIN_ZIP"
    exit 1
fi

echo "Plugin: $PLUGIN_NAME"
echo "File: $(basename $PLUGIN_ZIP) ($(du -h $PLUGIN_ZIP | cut -f1))"
echo ""

# Function to encode blueprint for URL
urlencode() {
    python3 -c "import urllib.parse; print(urllib.parse.quote('''$1'''))"
}

echo "Choose a testing method:"
echo ""
echo "1. WordPress Playground (Browser-based, Instant)"
echo "2. InstaWP (Manual upload, Free)"
echo "3. TasteWP (Manual upload, Free)"
echo "4. Local PHP Server (Port 8080)"
echo ""
read -p "Select option (1-4): " choice

case $choice in
    1)
        echo ""
        echo "→ WordPress Playground (Automated)"
        echo ""

        # Check if blueprint exists
        if [ -f "playground-blueprint.json" ]; then
            echo "✓ Blueprint found: playground-blueprint.json"
            echo ""
            echo "Note: WordPress Playground requires the plugin ZIP to be publicly accessible."
            echo ""
            echo "Option A - Use Playground with manual upload:"
            echo "  1. Visit: https://playground.wordpress.net"
            echo "  2. Drag and drop your plugin ZIP into the browser"
            echo "  3. Plugin will auto-install and activate"
            echo ""
            echo "Option B - Upload ZIP to GitHub first:"
            echo "  1. Push plugin ZIP to your GitHub repo"
            echo "  2. Update playground-blueprint.json with the raw GitHub URL"
            echo "  3. Visit: https://playground.wordpress.net/#{blueprint_url}"
            echo ""

            # Try to open in browser if available
            if command -v xdg-open &> /dev/null; then
                read -p "Open WordPress Playground now? (y/N): " -n 1 -r
                echo
                if [[ $REPLY =~ ^[Yy]$ ]]; then
                    xdg-open "https://playground.wordpress.net" &
                fi
            fi
        else
            echo "⚠️  Blueprint not found. Opening WordPress Playground..."
            echo "   Upload your plugin ZIP manually when it loads."
            echo ""
            echo "Visit: https://playground.wordpress.net"
        fi
        ;;

    2)
        echo ""
        echo "→ InstaWP (Manual Upload)"
        echo ""
        echo "Steps:"
        echo "  1. Visit: https://instawp.com"
        echo "  2. Click 'Create Instance' (no signup needed)"
        echo "  3. Wait 30 seconds"
        echo "  4. Click 'Go to WordPress Admin'"
        echo "  5. Login with provided credentials"
        echo "  6. Go to: Plugins > Add New > Upload Plugin"
        echo "  7. Upload: $PLUGIN_ZIP"
        echo "  8. Activate plugin"
        echo "  9. Navigate to: Media > BeepBeep AI"
        echo ""
        echo "✓ InstaWP provides a 48-hour test instance"
        echo ""

        if command -v xdg-open &> /dev/null; then
            read -p "Open InstaWP now? (y/N): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                xdg-open "https://instawp.com" &
            fi
        fi
        ;;

    3)
        echo ""
        echo "→ TasteWP (Manual Upload)"
        echo ""
        echo "Steps:"
        echo "  1. Visit: https://tastewp.com"
        echo "  2. Click 'Create Instance'"
        echo "  3. Wait ~20 seconds"
        echo "  4. Upload plugin via Plugins > Add New"
        echo "  5. Navigate to: Media > BeepBeep AI"
        echo ""
        echo "✓ TasteWP provides a 48-hour test instance"
        echo ""

        if command -v xdg-open &> /dev/null; then
            read -p "Open TasteWP now? (y/N): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                xdg-open "https://tastewp.com" &
            fi
        fi
        ;;

    4)
        echo ""
        echo "→ Local PHP Server"
        echo ""

        # Check if PHP is available
        if ! command -v php &> /dev/null; then
            echo "❌ PHP not found. Install PHP to use this option."
            exit 1
        fi

        echo "This requires WordPress to be set up first."
        echo "Use ./setup-test-wordpress.sh to create a local WordPress instance."
        echo ""

        if [ -d "$HOME/wp-test" ]; then
            echo "✓ Found WordPress installation at: ~/wp-test"
            read -p "Start server on port 8080? (y/N): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                cd "$HOME/wp-test"
                echo ""
                echo "Starting WordPress server..."
                echo "URL: http://localhost:8080"
                echo "Admin: http://localhost:8080/wp-admin"
                echo "Login: admin / admin123"
                echo ""
                echo "Press Ctrl+C to stop"
                echo ""
                php -S localhost:8080 -t .
            fi
        else
            echo "⚠️  WordPress not found. Run setup first:"
            echo "   ./setup-test-wordpress.sh"
        fi
        ;;

    *)
        echo "Invalid option"
        exit 1
        ;;
esac

echo ""
echo "======================================"
echo "UI Testing Checklist"
echo "======================================"
echo ""
echo "Once WordPress loads, test these features:"
echo ""
echo "  [ ] Login/Registration forms"
echo "  [ ] Dashboard stats display"
echo "  [ ] Tab navigation (Dashboard, Library, Usage, How to)"
echo "  [ ] Upload image"
echo "  [ ] Generate alt text"
echo "  [ ] ALT Library page"
echo "  [ ] Credit Usage page"
echo "  [ ] Upgrade button/modal"
echo "  [ ] Responsive design (resize browser)"
echo "  [ ] All links and buttons"
echo "  [ ] Error handling (invalid inputs)"
echo "  [ ] Logout/disconnect"
echo ""
echo "See UI_ANALYSIS_SUMMARY.md for detailed test steps."
echo ""
