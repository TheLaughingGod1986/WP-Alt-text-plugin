# 🚀 Render Deployment Guide for AltText AI Phase 2

## Step 1: Deploy to Render

### 1. Go to Render Dashboard
- Visit: https://dashboard.render.com
- Find your existing 'alttext-ai-backend' service
- Click on the service name

### 2. Update Build & Start Commands
- Click "Settings" tab
- Update **Build Command**:
  ```
  cd backend && npm install && npx prisma generate && npx prisma db push
  ```
- Update **Start Command**:
  ```
  cd backend && node server-v2.js
  ```

### 3. Deploy
- Click "Manual Deploy" → "Deploy latest commit"
- Wait for deployment to complete (5-10 minutes)

## Step 2: Get Stripe Keys

### 1. Go to Stripe Dashboard
- Visit: https://dashboard.stripe.com
- Sign in to your account

### 2. Get API Keys
- Go to **Developers** → **API Keys**
- Copy your **Secret Key** (starts with `sk_test_` or `sk_live_`)

### 3. Create Webhook
- Go to **Developers** → **Webhooks**
- Click **"Add endpoint"**
- Endpoint URL: `https://your-backend-url.onrender.com/billing/webhook`
- Select events: `checkout.session.completed`, `invoice.paid`, `customer.subscription.*`
- Copy the **Signing Secret** (starts with `whsec_`)

### 4. Create Products & Prices
- Go to **Products** → **Add product**
- Create these products:
  - **Free Plan**: 10 AI Alt Text Generations per month
  - **Pro Plan**: 1000 AI Alt Text Generations per month (£12.99/month)
  - **Agency Plan**: 10000 AI Alt Text Generations per month (£49.99/month)
  - **AltText AI Credits**: 100 AI Alt Text Generations (£9.99 one-time)

## Step 3: Update Environment Variables

### 1. Go to Render Service Settings
- In your Render service, go to **Environment** tab

### 2. Add/Update These Variables:
```
# Database (already set)
DATABASE_URL=postgresql://...

# JWT (already set)
JWT_SECRET=your-jwt-secret

# OpenAI (already set)
OPENAI_API_KEY=sk-your-openai-key

# Stripe Keys (ADD THESE)
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# Stripe Price IDs (ADD THESE - get from Stripe dashboard)
STRIPE_PRICE_ID_PRO_MONTHLY=price_xxxxxxxxxxxxxx
STRIPE_PRICE_ID_AGENCY_MONTHLY=price_xxxxxxxxxxxxxx
STRIPE_PRICE_ID_CREDITS_ONE_TIME=price_xxxxxxxxxxxxxx

# Application
PORT=3000
NODE_ENV=production
FRONTEND_URL=https://yourdomain.com
```

### 3. Save and Redeploy
- Click **"Save Changes"**
- The service will automatically redeploy

## Step 4: Test the System

### 1. Test Backend Health
- Visit: `https://your-backend-url.onrender.com/health`
- Should return: `{"status":"ok","version":"2.0.0","phase":"monetization"}`

### 2. Test Authentication
- Visit: `https://your-backend-url.onrender.com/auth/register`
- Should show registration endpoint

### 3. Test WordPress Plugin
- Go to your WordPress admin
- Navigate to **Media** → **AI Alt Text Generation**
- Should show authentication forms
- Try registering a test account

## 🎉 Success!

Once deployed, your system will have:
- ✅ User authentication with JWT
- ✅ PostgreSQL database with user accounts
- ✅ Stripe billing integration
- ✅ Usage tracking per user
- ✅ Alt text generation with authentication

## 🔧 Troubleshooting

### If deployment fails:
1. Check the **Logs** tab in Render
2. Ensure all environment variables are set
3. Verify the build command completed successfully

### If authentication doesn't work:
1. Check browser console for JavaScript errors
2. Verify the backend URL is correct in WordPress settings
3. Test the backend endpoints directly

### If Stripe integration doesn't work:
1. Verify all Stripe environment variables are set
2. Check that webhook endpoint is accessible
3. Test with Stripe test mode first
