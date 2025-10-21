#!/bin/bash

echo "🚀 AltText AI Phase 2 Master Deployment"
echo "======================================="

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

echo ""
echo "This script will deploy Phase 2 of AltText AI with:"
echo "✅ User authentication (JWT)"
echo "✅ PostgreSQL database"
echo "✅ Stripe billing integration"
echo "✅ Subscription plans"
echo "✅ Credit system"
echo ""

read -p "Do you want to continue? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_status "Deployment cancelled"
    exit 1
fi

# Step 1: Deploy Backend
print_status "Step 1: Deploying Backend..."
cd backend

if [ -f "auto-deploy-phase2.sh" ]; then
    ./auto-deploy-phase2.sh
    if [ $? -eq 0 ]; then
        print_success "Backend deployment completed"
    else
        print_error "Backend deployment failed"
        exit 1
    fi
else
    print_error "auto-deploy-phase2.sh not found"
    exit 1
fi

cd ..

# Step 2: Update WordPress Plugin
print_status "Step 2: Updating WordPress Plugin..."
if [ -f "update-plugin-phase2.sh" ]; then
    ./update-plugin-phase2.sh
    if [ $? -eq 0 ]; then
        print_success "Plugin update completed"
    else
        print_error "Plugin update failed"
        exit 1
    fi
else
    print_error "update-plugin-phase2.sh not found"
    exit 1
fi

# Step 3: Create final deployment summary
print_status "Step 3: Creating final deployment summary..."
cat > PHASE-2-DEPLOYMENT-COMPLETE.md << EOF
# Phase 2 Deployment Complete! 🎉

## ✅ What Was Deployed

### Backend (Node.js + PostgreSQL + Stripe)
- [x] JWT authentication system
- [x] PostgreSQL database with Prisma
- [x] Stripe products and pricing
- [x] Webhook handling
- [x] Usage tracking
- [x] Migration from Phase 1

### WordPress Plugin
- [x] User authentication UI
- [x] JWT token management
- [x] Stripe checkout integration
- [x] Upgrade modals
- [x] Modern SaaS dashboard

## 🔧 Manual Steps Required

### 1. Create PostgreSQL Database on Render
1. Go to [Render Dashboard](https://dashboard.render.com)
2. Create new PostgreSQL database
3. Copy the connection string
4. Update backend/.env with DATABASE_URL

### 2. Set Up Stripe
1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Get your secret key
3. Create webhook endpoint: \`https://your-backend-url.com/billing/webhook\`
4. Update backend/.env with Stripe keys

### 3. Deploy Backend to Render
1. Update your Render service environment variables
2. Change start command to: \`node server-v2.js\`
3. Deploy

### 4. Update WordPress Plugin
1. Update API URL in WordPress admin settings
2. Test authentication flow
3. Test alt text generation
4. Test upgrade flow

## 🧪 Testing Checklist

### Backend Testing
- [ ] Health check: \`GET /health\`
- [ ] User registration: \`POST /auth/register\`
- [ ] User login: \`POST /auth/login\`
- [ ] Alt text generation: \`POST /api/generate\`
- [ ] Stripe checkout: \`POST /billing/checkout\`

### WordPress Plugin Testing
- [ ] Authentication modal appears
- [ ] Registration works
- [ ] Login works
- [ ] Dashboard shows user info
- [ ] Alt text generation works
- [ ] Upgrade modals work
- [ ] Stripe checkout redirects work

## 📊 New Features

### User Authentication
- User registration and login
- JWT token management
- Secure password hashing
- Session persistence

### Billing & Subscriptions
- **Free Plan**: 10 images/month
- **Pro Plan**: 1000 images/month at £12.99
- **Agency Plan**: 10000 images/month at £49.99
- **Credit Packs**: 100 images for £9.99

### Modern UI/UX
- Clean, professional design
- Authentication modals
- Upgrade prompts
- Responsive layout

## 🔄 Rollback Plan

If something goes wrong:

### Backend Rollback
\`\`\`bash
cd backend
# Restore original server
cp server.js server-v2.js
# Restore original .env
cp .env.backup .env
\`\`\`

### WordPress Plugin Rollback
\`\`\`bash
# Restore original plugin
cp ai-alt-gpt-v1-backup.php ai-alt-gpt.php
\`\`\`

## 📈 Expected Results

### User Experience
- Users must create accounts (no more anonymous usage)
- Free users get 10 images/month (down from 50)
- Pro users get 1000 images/month at £12.99
- Agency users get 10000 images/month at £49.99
- Credit packs available for £9.99

### Business Impact
- Recurring revenue from subscriptions
- Better user tracking and analytics
- Professional SaaS experience
- Scalable infrastructure

## 🎯 Next Steps

1. **Monitor user registrations**
2. **Test the complete flow** end-to-end
3. **Update any hardcoded URLs** in the plugin
4. **Announce the new features** to existing users
5. **Plan marketing** for the new subscription model

## 📚 Documentation

- \`backend/PHASE-2-README.md\` - Backend documentation
- \`PHASE-2-DEPLOYMENT.md\` - Detailed deployment guide
- \`backend/deployment-summary.md\` - Backend deployment summary
- \`plugin-update-summary.md\` - Plugin update summary

---

**Phase 2 deployment is ready! 🚀**

**Total time to complete manual steps: ~30 minutes**
**Total time to test everything: ~15 minutes**

**You're ready to launch your SaaS platform! 🎉**
EOF

print_success "Phase 2 deployment preparation completed!"
print_status "See PHASE-2-DEPLOYMENT-COMPLETE.md for next steps"

echo ""
echo "🎉 Phase 2 is ready for deployment!"
echo ""
echo "📋 Manual steps required:"
echo "1. Create PostgreSQL database on Render"
echo "2. Set up Stripe products and webhooks"
echo "3. Deploy backend to Render"
echo "4. Update WordPress plugin settings"
echo "5. Test everything"
echo ""
echo "⏱️  Estimated time: ~30 minutes"
echo "📚 See PHASE-2-DEPLOYMENT-COMPLETE.md for complete instructions"
