#!/bin/bash

echo "🚀 Guided Render Deployment"
echo "==========================="
echo ""

# Check if render-env-vars.txt exists
if [ ! -f "render-env-vars.txt" ]; then
    echo "❌ render-env-vars.txt not found. Please run configure-stripe-keys.sh first."
    exit 1
fi

echo "📋 Your Environment Variables:"
echo "==============================="
cat render-env-vars.txt
echo ""

echo "🎯 Step-by-Step Deployment Guide:"
echo "=================================="
echo ""

echo "1️⃣  Go to Render Dashboard"
echo "   👉 https://dashboard.render.com"
echo ""

echo "2️⃣  Find Your Service"
echo "   👉 Look for 'alttext-ai-backend' or similar service name"
echo "   👉 Click on the service name"
echo ""

echo "3️⃣  Add Environment Variables"
echo "   👉 Go to 'Environment' tab"
echo "   👉 Click 'Add Environment Variable' for each one below:"
echo ""

# Display environment variables in a copy-friendly format
grep "^STRIPE_" render-env-vars.txt | while IFS='=' read -r key value; do
    echo "   📝 $key = $value"
done

echo ""

echo "4️⃣  Update Build Commands"
echo "   👉 Go to 'Settings' tab"
echo "   👉 Update Build Command to:"
echo "      cd backend && npm install && npx prisma generate && npx prisma db push"
echo "   👉 Update Start Command to:"
echo "      cd backend && node server-v2.js"
echo ""

echo "5️⃣  Deploy"
echo "   👉 Go to 'Manual Deploy' tab"
echo "   👉 Click 'Deploy latest commit'"
echo "   👉 Wait 5-10 minutes for deployment"
echo ""

echo "🧪 Test Your Deployment:"
echo "========================"
echo ""

# Get backend URL from the environment variables
BACKEND_URL=$(grep "Backend URL" DEPLOYMENT-INSTRUCTIONS.md | cut -d'`' -f2 | head -1)

if [ -n "$BACKEND_URL" ]; then
    echo "Health Check:"
    echo "curl $BACKEND_URL/health"
    echo ""
    echo "Registration Test:"
    echo "curl -X POST $BACKEND_URL/auth/register \\"
    echo "  -H 'Content-Type: application/json' \\"
    echo "  -d '{\"email\":\"test@example.com\",\"password\":\"testpass123\"}'"
else
    echo "Health Check:"
    echo "curl https://your-service-url.onrender.com/health"
    echo ""
    echo "Registration Test:"
    echo "curl -X POST https://your-service-url.onrender.com/auth/register \\"
    echo "  -H 'Content-Type: application/json' \\"
    echo "  -d '{\"email\":\"test@example.com\",\"password\":\"testpass123\"}'"
fi

echo ""
echo "📋 Copy-Paste Commands:"
echo "======================="
echo ""
echo "Build Command:"
echo "cd backend && npm install && npx prisma generate && npx prisma db push"
echo ""
echo "Start Command:"
echo "cd backend && node server-v2.js"
echo ""

echo "✅ Follow these steps and your AltText AI Phase 2 will be deployed!"
echo ""
echo "⏱️  Total time needed: ~10 minutes"
echo "🎯 Difficulty: Easy (just copy/paste)"
echo "🚀 Result: Fully functional Phase 2 system!"
