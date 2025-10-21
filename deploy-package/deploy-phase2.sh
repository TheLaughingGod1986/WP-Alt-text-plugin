#!/bin/bash

echo "🚀 Deploying AltText AI Phase 2 Backend"
echo "========================================"

# Check if we're in the right directory
if [ ! -f "package.json" ]; then
    echo "❌ Error: Not in backend directory. Please run from backend/ folder"
    exit 1
fi

echo "📦 Installing Phase 2 dependencies..."
npm install @prisma/client prisma jsonwebtoken bcrypt stripe

echo "🔧 Setting up Prisma..."
npx prisma generate

echo "📋 Environment variables needed:"
echo "================================="
echo "DATABASE_URL=postgresql://username:password@host:port/database"
echo "JWT_SECRET=your-super-secret-jwt-key-change-in-production"
echo "OPENAI_API_KEY=sk-your-openai-api-key"
echo "STRIPE_SECRET_KEY=sk_live_your_stripe_secret_key"
echo "STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret"
echo ""

echo "🗄️  Database setup required:"
echo "1. Create PostgreSQL database on Render"
echo "2. Copy DATABASE_URL to environment variables"
echo "3. Run: npx prisma migrate deploy"
echo ""

echo "💳 Stripe setup required:"
echo "1. Run: node stripe/setup.js setup"
echo "2. Copy price IDs to environment variables"
echo "3. Configure webhook endpoint"
echo ""

echo "🔄 Migration from Phase 1:"
echo "1. Run: node scripts/migrate-domains-to-users.js"
echo ""

echo "✅ Phase 2 deployment checklist:"
echo "- [ ] PostgreSQL database created"
echo "- [ ] Environment variables set"
echo "- [ ] Database migrations run"
echo "- [ ] Stripe products created"
echo "- [ ] Webhook endpoint configured"
echo "- [ ] Phase 1 data migrated"
echo "- [ ] Server deployed with server-v2.js"
echo ""

echo "📚 See PHASE-2-DEPLOYMENT.md for detailed instructions"
