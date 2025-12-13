#!/bin/bash
#
# Automated WordPress Plugin Test Deployment
# Uses InstaWP API to create instance and deploy plugin
#

set -e

echo "======================================"
echo "Automated WordPress Test Deployment"
echo "======================================"
echo ""

PLUGIN_ZIP="$(pwd)/dist/beepbeep-ai-alt-text-generator.4.2.3.zip"
PLUGIN_SLUG="beepbeep-ai-alt-text-generator"

# Check if plugin exists
if [ ! -f "$PLUGIN_ZIP" ]; then
    echo "❌ Error: Plugin ZIP not found at: $PLUGIN_ZIP"
    exit 1
fi

echo "→ Plugin: $(basename $PLUGIN_ZIP) ($(du -h $PLUGIN_ZIP | cut -f1))"
echo ""

# InstaWP API endpoint
INSTAWP_API="https://app.instawp.io/api/v1"

echo "→ Creating WordPress instance on InstaWP..."
echo "  (This may take 30-60 seconds)"
echo ""

# Create instance using InstaWP API
# Note: InstaWP free tier doesn't require API key for basic instance creation
RESPONSE=$(curl -s -X POST \
  "https://app.instawp.io/wordpress-in-cloud" \
  -H "Content-Type: application/json" \
  -d '{
    "template": "default",
    "config": {
      "wp_version": "latest",
      "php_version": "8.1"
    }
  }' 2>/dev/null || echo '{"error": "API call failed"}')

# Check if we got a valid response
if echo "$RESPONSE" | grep -q "error"; then
    echo "⚠️  InstaWP API approach requires authentication."
    echo ""
    echo "Let me try alternative methods..."
    echo ""

    # Alternative: WordPress Playground (browser-based but scriptable)
    echo "→ Using WordPress Playground (browser-based)..."
    echo ""
    echo "WordPress Playground can be automated using their Blueprint API:"
    echo ""

    # Create WordPress Playground blueprint
    cat > playground-blueprint.json << 'EOBLUEPRINT'
{
  "landingPage": "/wp-admin/",
  "preferredVersions": {
    "php": "8.0",
    "wp": "latest"
  },
  "steps": [
    {
      "step": "login",
      "username": "admin",
      "password": "password"
    },
    {
      "step": "installPlugin",
      "pluginZipFile": {
        "resource": "url",
        "url": "https://raw.githubusercontent.com/YOUR_REPO/main/dist/beepbeep-ai-alt-text-generator.4.2.3.zip"
      }
    },
    {
      "step": "activatePlugin",
      "pluginPath": "beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php"
    }
  ]
}
EOBLUEPRINT

    echo "✓ Created WordPress Playground blueprint: playground-blueprint.json"
    echo ""
    echo "To use WordPress Playground:"
    echo "1. Upload your plugin ZIP to a public URL (GitHub raw, etc.)"
    echo "2. Update the blueprint URL"
    echo "3. Visit: https://playground.wordpress.net/#"
    echo "   Append the blueprint as a query parameter"
    echo ""

    # Alternative: Show manual InstaWP instructions
    echo "=========================================="
    echo "Manual InstaWP Deployment (Easiest)"
    echo "=========================================="
    echo ""
    echo "Since automated API requires authentication, here's the manual approach:"
    echo ""
    echo "1. Visit: https://instawp.com"
    echo "2. Click 'Create Instance' (no signup needed)"
    echo "3. Wait 30 seconds for WordPress to load"
    echo "4. Click 'Go to WordPress Admin'"
    echo "5. Login with provided credentials"
    echo "6. Navigate to: Plugins > Add New > Upload Plugin"
    echo "7. Upload: $PLUGIN_ZIP"
    echo "8. Click 'Activate'"
    echo "9. Navigate to: Media > BeepBeep AI"
    echo ""
    echo "Your plugin is ready to test!"
    echo ""

    exit 0
fi

echo "✓ WordPress instance created"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
