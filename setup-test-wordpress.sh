#!/bin/bash
#
# Quick WordPress Test Setup Script
# Sets up a local WordPress instance for UI testing
#

set -e

echo "======================================"
echo "WordPress Test Environment Setup"
echo "======================================"
echo ""

# Configuration
WP_DIR="$HOME/wp-test"
WP_VERSION="6.8"
DB_NAME="wp_test"
DB_USER="root"
DB_PASS="password"
DB_HOST="localhost"
SITE_URL="http://localhost:8080"
ADMIN_USER="admin"
ADMIN_PASS="admin123"
ADMIN_EMAIL="admin@test.local"
PLUGIN_ZIP="$(pwd)/dist/beepbeep-ai-alt-text-generator.4.2.3.zip"

echo "Configuration:"
echo "  WordPress Directory: $WP_DIR"
echo "  Site URL: $SITE_URL"
echo "  Admin User: $ADMIN_USER"
echo "  Admin Pass: $ADMIN_PASS"
echo "  Plugin: $(basename $PLUGIN_ZIP)"
echo ""

# Check if WordPress directory already exists
if [ -d "$WP_DIR" ]; then
    echo "⚠️  WordPress directory already exists at: $WP_DIR"
    read -p "Delete and reinstall? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -rf "$WP_DIR"
        echo "✓ Removed existing WordPress installation"
    else
        echo "❌ Setup cancelled"
        exit 1
    fi
fi

# Create WordPress directory
echo "→ Creating WordPress directory..."
mkdir -p "$WP_DIR"
cd "$WP_DIR"

# Download WordPress
echo "→ Downloading WordPress $WP_VERSION..."
if command -v wget &> /dev/null; then
    wget -q "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" -O wordpress.tar.gz
elif command -v curl &> /dev/null; then
    curl -sL "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" -o wordpress.tar.gz
else
    echo "❌ Error: wget or curl is required"
    exit 1
fi

# Extract WordPress
echo "→ Extracting WordPress..."
tar -xzf wordpress.tar.gz --strip-components=1
rm wordpress.tar.gz

# Create wp-config.php
echo "→ Creating wp-config.php..."
cat > wp-config.php << 'EOF'
<?php
define( 'DB_NAME', getenv('DB_NAME') ?: 'wp_test' );
define( 'DB_USER', getenv('DB_USER') ?: 'root' );
define( 'DB_PASSWORD', getenv('DB_PASS') ?: 'password' );
define( 'DB_HOST', getenv('DB_HOST') ?: 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
EOF

# Install plugin
echo "→ Installing BeepBeep AI plugin..."
if [ -f "$PLUGIN_ZIP" ]; then
    unzip -q "$PLUGIN_ZIP" -d wp-content/plugins/
    echo "✓ Plugin installed"
else
    echo "⚠️  Plugin ZIP not found at: $PLUGIN_ZIP"
    echo "   You can install it manually later"
fi

# Create database setup script
echo "→ Creating database setup script..."
cat > setup-db.php << 'EOPHP'
<?php
// WordPress Database Setup Script
define('WP_INSTALLING', true);
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

global $wpdb;

// Check if already installed
if (!is_blog_installed()) {
    echo "Installing WordPress...\n";

    wp_install(
        'BeepBeep AI Test',           // Site title
        'admin',                       // Admin username
        'admin@test.local',           // Admin email
        true,                         // Public
        '',                           // Deprecated
        'admin123'                    // Admin password
    );

    echo "✓ WordPress installed successfully\n";
    echo "  Admin User: admin\n";
    echo "  Admin Pass: admin123\n";

    // Activate plugin
    $plugin = 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
    if (file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
        activate_plugin($plugin);
        echo "✓ BeepBeep AI plugin activated\n";
    }
} else {
    echo "WordPress is already installed\n";
}

echo "\nReady to test!\n";
echo "Visit: http://localhost:8080/wp-admin\n";
echo "Login: admin / admin123\n";
EOPHP

# Create startup script
cat > start-server.sh << 'EOSCRIPT'
#!/bin/bash
echo "Starting WordPress development server..."
echo "URL: http://localhost:8080"
echo "Admin: http://localhost:8080/wp-admin"
echo "Login: admin / admin123"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""
php -S localhost:8080 -t .
EOSCRIPT
chmod +x start-server.sh

# Create quick test script
cat > test-ui.sh << 'EOTEST'
#!/bin/bash
echo "======================================"
echo "BeepBeep AI UI Test Checklist"
echo "======================================"
echo ""
echo "1. Open: http://localhost:8080/wp-admin"
echo "2. Login: admin / admin123"
echo "3. Navigate to: Media > BeepBeep AI"
echo ""
echo "UI Tests to Perform:"
echo "  [ ] Click Login button"
echo "  [ ] Test registration form"
echo "  [ ] Navigate all tabs (Dashboard, Library, Usage, etc.)"
echo "  [ ] Upload an image"
echo "  [ ] Generate alt text"
echo "  [ ] Test upgrade button"
echo "  [ ] Check responsive design (resize browser)"
echo "  [ ] Test all links and buttons"
echo "  [ ] Verify error messages display"
echo "  [ ] Test logout/disconnect"
echo ""
echo "Check UI_ANALYSIS_SUMMARY.md for detailed test steps"
echo ""
EOTEST
chmod +x test-ui.sh

echo ""
echo "======================================"
echo "✅ Setup Complete!"
echo "======================================"
echo ""
echo "Next Steps:"
echo ""
echo "1. Setup database (if using MySQL):"
echo "   mysql -u root -p -e \"CREATE DATABASE wp_test;\""
echo ""
echo "2. Initialize WordPress:"
echo "   cd $WP_DIR"
echo "   php setup-db.php"
echo ""
echo "3. Start the server:"
echo "   ./start-server.sh"
echo ""
echo "4. Access WordPress:"
echo "   URL: http://localhost:8080/wp-admin"
echo "   Login: admin / admin123"
echo ""
echo "5. Test the plugin:"
echo "   Navigate to: Media > BeepBeep AI"
echo "   Run: ./test-ui.sh (for checklist)"
echo ""
echo "WordPress installed at: $WP_DIR"
echo ""
