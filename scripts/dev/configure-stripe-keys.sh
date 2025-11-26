#!/bin/bash

echo "🔑 Configure Stripe Keys for AltText AI"
echo "======================================="
echo ""

echo "This script will help you add your Stripe keys to the correct configuration files."
echo ""

# Get Stripe keys from user
echo "📋 Please provide your Stripe configuration:"
echo ""

read -p "Stripe Secret Key (starts with sk_test_ or sk_live_): " STRIPE_SECRET_KEY
read -p "Stripe Webhook Secret (starts with whsec_): " STRIPE_WEBHOOK_SECRET
read -p "Pro Plan Price ID (starts with price_): " PRO_PRICE_ID
read -p "Agency Plan Price ID (starts with price_): " AGENCY_PRICE_ID
read -p "Credits Price ID (starts with price_): " CREDITS_PRICE_ID
read -p "Backend URL (e.g., https://alttext-ai-backend.onrender.com): " BACKEND_URL

echo ""
echo "🔧 Configuring files..."

# Update backend .env file
if [ -f "backend/.env" ]; then
    echo "📝 Updating backend/.env..."
    
    # Remove existing Stripe variables
    sed -i.bak '/^STRIPE_/d' backend/.env
    
    # Add new Stripe variables
    cat >> backend/.env << EOF

# Stripe Configuration
STRIPE_SECRET_KEY=$STRIPE_SECRET_KEY
STRIPE_WEBHOOK_SECRET=$STRIPE_WEBHOOK_SECRET
STRIPE_PRICE_ID_PRO_MONTHLY=$PRO_PRICE_ID
STRIPE_PRICE_ID_AGENCY_MONTHLY=$AGENCY_PRICE_ID
STRIPE_PRICE_ID_CREDITS_ONE_TIME=$CREDITS_PRICE_ID
EOF
    
    echo "✅ Updated backend/.env"
else
    echo "❌ backend/.env not found"
fi

# Create environment template for Render
echo "📝 Creating Render environment template..."
cat > render-env-vars.txt << EOF
# Add these environment variables to your Render service:

STRIPE_SECRET_KEY=$STRIPE_SECRET_KEY
STRIPE_WEBHOOK_SECRET=$STRIPE_WEBHOOK_SECRET
STRIPE_PRICE_ID_PRO_MONTHLY=$PRO_PRICE_ID
STRIPE_PRICE_ID_AGENCY_MONTHLY=$AGENCY_PRICE_ID
STRIPE_PRICE_ID_CREDITS_ONE_TIME=$CREDITS_PRICE_ID
EOF

echo "✅ Created render-env-vars.txt"

# Update WordPress plugin settings if needed
if [ -f "opptiai-alt.php" ]; then
    echo "📝 Checking WordPress plugin configuration..."
    
    # Check if API URL needs updating
    if grep -q "alttext-ai-backend.onrender.com" opptiai-alt.php; then
        echo "🔧 WordPress plugin is already configured for Render"
    else
        echo "⚠️  You may need to update the API URL in WordPress settings"
    fi
fi

# Create deployment instructions
echo "📝 Creating deployment instructions..."
cat > DEPLOYMENT-INSTRUCTIONS.md << EOF
# 🚀 AltText AI Phase 2 Deployment Instructions

## ✅ Stripe Keys Configured
Your Stripe keys have been added to the configuration files.

## 📋 Environment Variables for Render
Copy these environment variables to your Render service:

\`\`\`
STRIPE_SECRET_KEY=$STRIPE_SECRET_KEY
STRIPE_WEBHOOK_SECRET=$STRIPE_WEBHOOK_SECRET
STRIPE_PRICE_ID_PRO_MONTHLY=$PRO_PRICE_ID
STRIPE_PRICE_ID_AGENCY_MONTHLY=$AGENCY_PRICE_ID
STRIPE_PRICE_ID_CREDITS_ONE_TIME=$CREDITS_PRICE_ID
\`\`\`

## 🚀 Deploy to Render
1. Go to https://dashboard.render.com
2. Find your 'alttext-ai-backend' service
3. Go to Environment tab
4. Add the environment variables above
5. Update Build Command: \`cd backend && npm install && npx prisma generate && npx prisma db push\`
6. Update Start Command: \`cd backend && node server-v2.js\`
7. Click 'Manual Deploy' → 'Deploy latest commit'

## 🧪 Test Your Deployment
After deployment, test these endpoints:
- Health: \`$BACKEND_URL/health\`
- Registration: \`curl -X POST $BACKEND_URL/auth/register -H "Content-Type: application/json" -d '{"email":"test@example.com","password":"testpass123"}'\`

## ✅ Success!
Your AltText AI Phase 2 will be fully operational with:
- User authentication with JWT
- PostgreSQL database with user accounts  
- Stripe billing integration
- Usage tracking per user
- Alt text generation with authentication
EOF

echo "✅ Created DEPLOYMENT-INSTRUCTIONS.md"

echo ""
echo "🎉 Configuration Complete!"
echo ""
echo "📋 Summary:"
echo "- ✅ Updated backend/.env with Stripe keys"
echo "- ✅ Created render-env-vars.txt for easy copying"
echo "- ✅ Created DEPLOYMENT-INSTRUCTIONS.md with next steps"
echo ""
echo "🎯 Next Steps:"
echo "1. Review the files created above"
echo "2. Follow DEPLOYMENT-INSTRUCTIONS.md"
echo "3. Deploy to Render with the environment variables"
echo ""
echo "✅ Your AltText AI Phase 2 is ready to deploy!"
