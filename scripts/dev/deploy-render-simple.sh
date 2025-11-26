#!/bin/bash

echo "🚀 Simple Render Deployment Helper"
echo "=================================="
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

echo "🎯 Manual Deployment Steps:"
echo "=========================="
echo ""
echo "1. Go to: https://dashboard.render.com"
echo "2. Find your 'alttext-ai-backend' service"
echo "3. Click on the service name"
echo "4. Go to 'Environment' tab"
echo "5. Add each environment variable from above"
echo "6. Go to 'Settings' tab"
echo "7. Update Build Command: cd backend && npm install && npx prisma generate && npx prisma db push"
echo "8. Update Start Command: cd backend && node server-v2.js"
echo "9. Go to 'Manual Deploy' tab"
echo "10. Click 'Deploy latest commit'"
echo ""

echo "🧪 After deployment, test these endpoints:"
echo "=========================================="
echo ""
echo "Health Check:"
echo "curl https://your-service-url.onrender.com/health"
echo ""
echo "Registration Test:"
echo "curl -X POST https://your-service-url.onrender.com/auth/register \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"email\":\"test@example.com\",\"password\":\"testpass123\"}'"
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
