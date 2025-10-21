# Phase 2 Quick Deployment Guide

## Current Status ✅
- ✅ Backend code is ready (server-v2.js)
- ✅ WordPress plugin code is ready (ai-alt-gpt-v2.php)
- ✅ Stripe integration is ready
- ✅ Database schema is ready (Prisma)
- ✅ Migration script is ready

## What We Need to Do

### 1. Create PostgreSQL Database on Render

1. **Go to [Render Dashboard](https://dashboard.render.com)**
2. **Click "New +" → "PostgreSQL"**
3. **Configure:**
   - Name: `alttext-ai-db`
   - Plan: Free (for now)
   - Region: Choose closest to your users
4. **Click "Create Database"**
5. **Copy the "External Database URL"** (looks like: `postgresql://user:pass@host:port/db`)

### 2. Set Up Environment Variables

Run the setup script:
```bash
cd backend
./setup-env.sh
```

Or manually create `.env` file:
```bash
# Database
DATABASE_URL=postgresql://user:pass@host:port/db

# JWT Authentication  
JWT_SECRET=your-super-secret-jwt-key-change-in-production
JWT_EXPIRES_IN=7d

# OpenAI
OPENAI_API_KEY=sk-your-openai-api-key
OPENAI_MODEL=gpt-4o-mini

# Stripe
STRIPE_SECRET_KEY=sk_live_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# Application
PORT=3000
NODE_ENV=production
FRONTEND_URL=https://yourdomain.com
```

### 3. Run Database Migrations

```bash
cd backend
npx prisma migrate deploy
```

### 4. Set Up Stripe Products

```bash
cd backend
node stripe/setup.js setup
```

This will create:
- Pro Plan: £12.99/month for 1000 images
- Agency Plan: £49.99/month for 10000 images  
- Credit Pack: £9.99 one-time for 100 images

Copy the generated price IDs to your environment variables.

### 5. Configure Stripe Webhooks

1. **Go to [Stripe Dashboard](https://dashboard.stripe.com) → Webhooks**
2. **Add endpoint:** `https://your-backend-url.com/billing/webhook`
3. **Select events:**
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.paid`
   - `invoice.payment_failed`
4. **Copy webhook secret** to environment variables

### 6. Deploy Backend to Render

1. **Go to your existing Render service**
2. **Update environment variables** with all the new ones
3. **Change the start command** to: `node server-v2.js`
4. **Deploy**

### 7. Migrate Phase 1 Data (if you have existing users)

```bash
cd backend
node scripts/migrate-domains-to-users.js
```

### 8. Update WordPress Plugin

1. **Replace main plugin file:**
   ```bash
   cp ai-alt-gpt-v2.php ai-alt-gpt.php
   ```

2. **Update API URL** in plugin settings to your new backend URL

3. **Test the authentication flow**

## Testing Checklist

### Backend Testing
- [ ] Health check: `GET https://your-backend-url.com/health`
- [ ] User registration: `POST /auth/register`
- [ ] User login: `POST /auth/login`
- [ ] Alt text generation: `POST /api/generate`
- [ ] Stripe checkout: `POST /billing/checkout`

### WordPress Plugin Testing
- [ ] Authentication modal appears
- [ ] Registration works
- [ ] Login works
- [ ] Dashboard shows user info
- [ ] Alt text generation works
- [ ] Upgrade modals work
- [ ] Stripe checkout redirects work

## Deployment Commands

```bash
# 1. Set up environment
cd backend
./setup-env.sh

# 2. Run database migrations
npx prisma migrate deploy

# 3. Set up Stripe products
node stripe/setup.js setup

# 4. Migrate existing data (if any)
node scripts/migrate-domains-to-users.js

# 5. Test locally
npm start

# 6. Deploy to Render
# (Update environment variables and start command in Render dashboard)
```

## Expected Timeline

- **Database setup:** 5 minutes
- **Environment configuration:** 10 minutes
- **Stripe setup:** 15 minutes
- **Backend deployment:** 10 minutes
- **Plugin update:** 5 minutes
- **Testing:** 15 minutes

**Total: ~1 hour**

## What Happens After Deployment

1. **Users will need to create accounts** (no more anonymous usage)
2. **Free users get 10 images/month** (down from 50)
3. **Pro users get 1000 images/month** at £12.99
4. **Agency users get 10000 images/month** at £49.99
5. **Credit packs available** for £9.99 (100 images)

## Rollback Plan

If something goes wrong:

1. **Revert backend** to `server.js` (Phase 1)
2. **Revert plugin** to original version
3. **Restore environment variables** to Phase 1 settings
4. **Deploy**

## Support

If you run into issues:

1. **Check the logs** in Render dashboard
2. **Verify environment variables** are set correctly
3. **Test each component** individually
4. **Check Stripe webhook logs**

## Next Steps After Deployment

1. **Monitor user registrations**
2. **Test the complete flow** end-to-end
3. **Update any hardcoded URLs** in the plugin
4. **Announce the new features** to existing users
5. **Plan marketing** for the new subscription model

---

**Ready to deploy? Let's go! 🚀**
