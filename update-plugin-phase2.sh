#!/bin/bash

echo "🔄 Updating WordPress Plugin for Phase 2"
echo "========================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "ai-alt-gpt.php" ]; then
    print_error "Not in WordPress plugin directory. Please run from plugin root folder"
    exit 1
fi

# Backup current plugin
print_status "Creating backup of current plugin..."
cp ai-alt-gpt.php ai-alt-gpt-v1-backup.php
print_success "Backup created: ai-alt-gpt-v1-backup.php"

# Update main plugin file
print_status "Updating main plugin file..."
if [ -f "ai-alt-gpt-v2.php" ]; then
    cp ai-alt-gpt-v2.php ai-alt-gpt.php
    print_success "Main plugin file updated"
else
    print_error "ai-alt-gpt-v2.php not found"
    exit 1
fi

# Check if API client v2 exists
if [ -f "includes/class-api-client-v2.php" ]; then
    print_success "API client v2 found"
else
    print_warning "API client v2 not found - you may need to create it"
fi

# Check if auth modal exists
if [ -f "assets/auth-modal.js" ]; then
    print_success "Auth modal found"
else
    print_warning "Auth modal not found - you may need to create it"
fi

# Create version update script
print_status "Creating version update script..."
cat > update-version.sh << 'EOF'
#!/bin/bash

# Update plugin version in main file
sed -i.bak 's/Version: 3.1.0/Version: 4.0.0/' ai-alt-gpt.php
sed -i.bak 's/Version: 3.1.0/Version: 4.0.0/' readme.txt

echo "✅ Plugin version updated to 4.0.0"
EOF

chmod +x update-version.sh

# Create testing script
print_status "Creating testing script..."
cat > test-phase2.sh << 'EOF'
#!/bin/bash

echo "🧪 Testing Phase 2 Plugin"
echo "========================"

# Check if files exist
echo "Checking required files..."

files=(
    "ai-alt-gpt.php"
    "includes/class-api-client-v2.php"
    "assets/auth-modal.js"
    "assets/auth-modal.css"
    "assets/ai-alt-dashboard.js"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file - MISSING"
    fi
done

echo ""
echo "🔍 Checking for Phase 2 features in main plugin file..."

# Check for Phase 2 features
if grep -q "AltText_AI_API_Client_V2" ai-alt-gpt.php; then
    echo "✅ API Client V2 found"
else
    echo "❌ API Client V2 not found"
fi

if grep -q "alttextai_register" ai-alt-gpt.php; then
    echo "✅ Registration AJAX found"
else
    echo "❌ Registration AJAX not found"
fi

if grep -q "alttextai_login" ai-alt-gpt.php; then
    echo "✅ Login AJAX found"
else
    echo "❌ Login AJAX not found"
fi

if grep -q "auth-modal" ai-alt-gpt.php; then
    echo "✅ Auth modal found"
else
    echo "❌ Auth modal not found"
fi

echo ""
echo "📋 Next steps:"
echo "1. Update API URL in plugin settings"
echo "2. Test user registration"
echo "3. Test user login"
echo "4. Test alt text generation"
echo "5. Test upgrade flow"
EOF

chmod +x test-phase2.sh

# Create deployment summary
print_status "Creating plugin update summary..."
cat > plugin-update-summary.md << EOF
# WordPress Plugin Phase 2 Update Summary

## ✅ Completed Steps
- [x] Main plugin file updated (ai-alt-gpt.php)
- [x] Backup created (ai-alt-gpt-v1-backup.php)
- [x] Version update script created
- [x] Testing script created

## 🔧 Next Steps
1. Update API URL in WordPress admin settings
2. Test the authentication flow
3. Test alt text generation
4. Test upgrade modals
5. Update version to 4.0.0

## 🧪 Testing Commands
\`\`\`bash
# Run the testing script
./test-phase2.sh

# Update version
./update-version.sh
\`\`\`

## 📋 Manual Steps Required
1. **Update API URL**: Go to WordPress Admin → AI Alt Text Generation → Settings
2. **Change API URL** to your new Phase 2 backend URL
3. **Test registration**: Try creating a new account
4. **Test login**: Try logging in with the account
5. **Test generation**: Try generating alt text
6. **Test upgrade**: Try the upgrade flow

## 🔄 Rollback Instructions
If something goes wrong:
\`\`\`bash
# Restore original plugin
cp ai-alt-gpt-v1-backup.php ai-alt-gpt.php
\`\`\`

## 📚 Documentation
- See PHASE-2-DEPLOYMENT.md for complete instructions
- See PHASE-2-README.md for backend documentation
EOF

print_success "Plugin update completed!"
print_status "See plugin-update-summary.md for next steps"

echo ""
echo "🎉 WordPress Plugin is ready for Phase 2!"
echo ""
echo "Next steps:"
echo "1. Update API URL in WordPress admin"
echo "2. Test the authentication flow"
echo "3. Test alt text generation"
echo "4. Test upgrade modals"
echo ""
echo "📋 See plugin-update-summary.md for complete instructions"
