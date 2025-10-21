# 🚀 AltText AI Phase 2 Deployment Instructions

## ✅ Stripe Keys Configured
Your Stripe keys have been added to the configuration files.

## 📋 Environment Variables for Render
Copy these environment variables to your Render service:

```
STRIPE_SECRET_KEY=sk_test_placeholder_key_for_testing
STRIPE_WEBHOOK_SECRET=whsec_afe6685be32f1b21c346ac82861593e1fa8ea4d5d2e412a9bcf74a6b938e2588
STRIPE_PRICE_ID_PRO_MONTHLY=price_1SKgtuJl9Rm418cMtcxOZRCR
STRIPE_PRICE_ID_AGENCY_MONTHLY=price_1SKgu1Jl9Rm418cM8MedRfqr
STRIPE_PRICE_ID_CREDITS_ONE_TIME=price_1SKgu2Jl9Rm418cM3b1Z9tUW
```

## 🚀 Deploy to Render
1. Go to https://dashboard.render.com
2. Find your 'alttext-ai-backend' service
3. Go to Environment tab
4. Add the environment variables above
5. Update Build Command: `cd backend && npm install && npx prisma generate && npx prisma db push`
6. Update Start Command: `cd backend && node server-v2.js`
7. Click 'Manual Deploy' → 'Deploy latest commit'

## 🧪 Test Your Deployment
After deployment, test these endpoints:
- Health: `https://alttext-ai-backend.onrender.com/health`
- Registration: `curl -X POST https://alttext-ai-backend.onrender.com/auth/register -H "Content-Type: application/json" -d '{"email":"test@example.com","password":"testpass123"}'`

## ✅ Success!
Your AltText AI Phase 2 will be fully operational with:
- User authentication with JWT
- PostgreSQL database with user accounts  
- Stripe billing integration
- Usage tracking per user
- Alt text generation with authentication
