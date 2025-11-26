#!/bin/bash

echo "🚀 Deploying AltText AI Phase 2 to Render"
echo "========================================"

# Check if we're in the right directory
if [ ! -f "backend/server-v2.js" ]; then
    echo "❌ Error: Please run this script from the project root directory"
    exit 1
fi

echo "📦 Preparing deployment..."

# Create a deployment package
echo "Creating deployment package..."
mkdir -p deploy-package
cp -r backend/* deploy-package/
cp backend/.env.example deploy-package/.env

echo "📋 Deployment Instructions for Render:"
echo "======================================"
echo ""
echo "1. Go to your Render dashboard: https://dashboard.render.com"
echo "2. Find your existing 'alttext-ai-backend' service"
echo "3. Click 'Manual Deploy' → 'Deploy latest commit'"
echo ""
echo "4. Update Environment Variables:"
echo "   - DATABASE_URL: (already set from Phase 1)"
echo "   - JWT_SECRET: (already set from Phase 1)"
echo "   - OPENAI_API_KEY: (already set from Phase 1)"
echo "   - STRIPE_SECRET_KEY: sk_test_placeholder_key_for_testing"
echo "   - STRIPE_WEBHOOK_SECRET: whsec_placeholder_webhook_secret"
echo "   - STRIPE_PRICE_ID_PRO_MONTHLY: price_placeholder_pro"
echo "   - STRIPE_PRICE_ID_AGENCY_MONTHLY: price_placeholder_agency"
echo "   - STRIPE_PRICE_ID_CREDITS_ONE_TIME: price_placeholder_credits"
echo ""
echo "5. Update Build Command:"
echo "   cd backend && npm install && npx prisma generate && npx prisma db push"
echo ""
echo "6. Update Start Command:"
echo "   cd backend && node server-v2.js"
echo ""
echo "7. Click 'Deploy'"
echo ""
echo "✅ After deployment, your backend will be available at:"
echo "   https://alttext-ai-backend.onrender.com"
echo ""
echo "🔧 Next Steps:"
echo "   - Get real Stripe keys from https://dashboard.stripe.com"
echo "   - Update environment variables with real Stripe keys"
echo "   - Test the deployed endpoints"
echo "   - Update WordPress plugin with new API URL"

# Clean up
rm -rf deploy-package

echo ""
echo "🎉 Deployment package ready!"
echo "Follow the instructions above to deploy to Render."
