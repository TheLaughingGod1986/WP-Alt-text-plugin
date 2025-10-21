# ✅ AltText AI Phase 2 Deployment Checklist

## 🚀 Step 1: Deploy to Render
- [ ] Go to https://dashboard.render.com
- [ ] Find your 'alttext-ai-backend' service
- [ ] Click 'Manual Deploy' → 'Deploy latest commit'
- [ ] Wait for deployment to complete (5-10 minutes)

## ⚙️ Step 2: Update Build Commands
- [ ] Go to service Settings tab
- [ ] Update Build Command: `cd backend && npm install && npx prisma generate && npx prisma db push`
- [ ] Update Start Command: `cd backend && node server-v2.js`
- [ ] Save changes

## 🔑 Step 3: Get Stripe Keys
- [ ] Go to https://dashboard.stripe.com
- [ ] Navigate to Developers → API Keys
- [ ] Copy Secret Key (starts with `sk_test_` or `sk_live_`)
- [ ] Go to Developers → Webhooks
- [ ] Add endpoint: `https://your-backend-url.onrender.com/billing/webhook`
- [ ] Select events: `checkout.session.completed`, `invoice.paid`, `customer.subscription.*`
- [ ] Copy Signing Secret (starts with `whsec_`)

## 🛍️ Step 4: Create Stripe Products
- [ ] Go to Products → Add product
- [ ] Create "Pro Plan" - £12.99/month recurring
- [ ] Create "Agency Plan" - £49.99/month recurring  
- [ ] Create "AltText AI Credits" - £9.99 one-time
- [ ] Copy all Price IDs (start with `price_`)

## 🔧 Step 5: Add Environment Variables
Add these to your Render service environment:
- [ ] `STRIPE_SECRET_KEY=sk_test_your_secret_key`
- [ ] `STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret`
- [ ] `STRIPE_PRICE_ID_PRO_MONTHLY=price_your_pro_price_id`
- [ ] `STRIPE_PRICE_ID_AGENCY_MONTHLY=price_your_agency_price_id`
- [ ] `STRIPE_PRICE_ID_CREDITS_ONE_TIME=price_your_credits_price_id`

## 🧪 Step 6: Test Everything
- [ ] Test backend health: `https://your-backend-url.onrender.com/health`
- [ ] Test registration with curl command
- [ ] Test WordPress plugin authentication
- [ ] Try generating alt text with a test account

## 🎉 Success!
Once all steps are complete, you'll have:
- ✅ User authentication with JWT
- ✅ PostgreSQL database with user accounts
- ✅ Stripe billing integration
- ✅ Usage tracking per user
- ✅ Alt text generation with authentication

## 🆘 Need Help?
- Check Render logs if deployment fails
- Verify all environment variables are set
- Test backend endpoints directly
- Check browser console for JavaScript errors
