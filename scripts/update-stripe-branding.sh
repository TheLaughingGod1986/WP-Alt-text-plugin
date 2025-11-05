#!/bin/bash
#
# Stripe Branding Update Script
# 
# This script checks current Stripe account settings and provides
# instructions for updating branding to AltText AI.
#

echo "🔍 Checking Stripe Account Settings..."
echo ""

# Get account info
ACCOUNT_INFO=$(stripe get /v1/account --live 2>&1)

# Check if we got valid account data (not an error)
if echo "$ACCOUNT_INFO" | grep -q '"id":'; then
    echo "✅ Connected to Stripe account"
    echo ""
    
    # Extract current settings using Python for better JSON parsing
    CURRENT_BUSINESS_NAME=$(echo "$ACCOUNT_INFO" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('business_profile', {}).get('name', 'Not set'))" 2>/dev/null || echo "bmv")
    CURRENT_DISPLAY_NAME=$(echo "$ACCOUNT_INFO" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('settings', {}).get('dashboard', {}).get('display_name', 'Not set'))" 2>/dev/null || echo "bmv")
    CURRENT_STATEMENT_PREFIX=$(echo "$ACCOUNT_INFO" | python3 -c "import sys, json; data=json.load(sys.stdin); print(data.get('settings', {}).get('card_payments', {}).get('statement_descriptor_prefix', 'Not set'))" 2>/dev/null || echo "BMV")
    
    echo "Current Settings:"
    echo "  Business Name (business_profile.name): '$CURRENT_BUSINESS_NAME'"
    echo "  Dashboard Display Name: '$CURRENT_DISPLAY_NAME'"
    echo "  Statement Descriptor Prefix: '$CURRENT_STATEMENT_PREFIX'"
    echo ""
else
    echo "⚠️  Could not parse account data, but continuing..."
    echo ""
    CURRENT_BUSINESS_NAME="bmv"
    CURRENT_DISPLAY_NAME="bmv"
fi

echo "📋 REQUIRED: Update Stripe Dashboard Settings"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "⚠️  The account-level business name CANNOT be changed via API."
echo "    It must be updated in the Stripe Dashboard."
echo ""
echo "Step 1: Update Business Information"
echo "   URL: https://dashboard.stripe.com/settings/business"
echo "   Changes needed:"
echo "     ✏️  Business name: Change '$CURRENT_BUSINESS_NAME' → 'AltText AI'"
echo "     ✏️  Product description: 'AI Alt Text Generator Plugin'"
echo ""
echo "Step 2: Update Dashboard Display Name"
echo "   URL: https://dashboard.stripe.com/settings/account"
echo "   Changes needed:"
echo "     ✏️  Display name: Change '$CURRENT_DISPLAY_NAME' → 'AltText AI'"
echo ""
echo "Step 3: Update Statement Descriptor"
echo "   URL: https://dashboard.stripe.com/settings/connect/payouts"
echo "   Or search for: 'statement descriptor' in Dashboard"
echo "   Changes needed:"
echo "     ✏️  Statement descriptor prefix: Change 'BMV' → 'ALTTEXT'"
echo ""
echo "Step 4: Update Branding (Optional but Recommended)"
echo "   URL: https://dashboard.stripe.com/settings/branding"
echo "   Changes needed:"
echo "     📸 Upload AltText AI logo"
echo "       Logo files available at:"
echo "       • assets/logo-alttext-ai.svg (transparent background, recommended)"
echo "       • assets/logo-alttext-ai-white-bg.svg (white background variant)"
echo "     🎨 Set primary color: #14b8a6 (teal)"
echo ""

if [ "$CURRENT_BUSINESS_NAME" != "AltText AI" ]; then
    echo "⚠️  ACTION REQUIRED:"
    echo "    Business name is still '$CURRENT_BUSINESS_NAME'"
    echo "    Please update it in the Dashboard (Step 1 above)"
    echo ""
fi

echo "✅ Code Changes (Already Complete):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ WordPress plugin sends branding parameters to backend"
echo "  ✅ Backend receives: companyName='AltText AI', branding data"
echo "  ✅ See: docs/STRIPE_BRANDING_UPDATE.md for backend implementation"
echo ""

echo "🔧 Backend API Update Required:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  The backend /api/billing/checkout endpoint must:"
echo "  1. Use 'companyName' parameter when creating checkout sessions"
echo "  2. Apply 'branding.statementDescriptor' to payment_intent_data"
echo "  3. Set metadata with company_name"
echo ""
echo "  Example backend code is in: docs/STRIPE_BRANDING_UPDATE.md"
echo ""

echo "🧪 Testing Checkout Session:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Test command (replace price_id with actual):"
echo ""
cat << 'EOF'
stripe post /v1/checkout/sessions \
  -d payment_method_types[]=card \
  -d 'line_items[0][price]=price_1SMrxaJl9Rm418cMM4iikjlJ' \
  -d 'line_items[0][quantity]=1' \
  -d mode=subscription \
  -d success_url='https://example.com/success' \
  -d cancel_url='https://example.com/cancel' \
  -d 'payment_intent_data[statement_descriptor]=ALTTEXT AI' \
  -d 'metadata[company_name]=AltText AI' \
  --live
EOF
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

