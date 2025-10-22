# ✅ Code Pushed! Next: Create Render Service

**Status:** Phase 2 backend code successfully pushed to GitHub!
**Repository:** https://github.com/TheLaughingGod1986/alttext-ai-backend
**Commit:** ed52953 (feat: Add Phase 2 monetization backend)

---

## 🎯 Next Step: Create Web Service on Render

Since the code is now in GitHub, Render can deploy it automatically.

### Quick Deploy (5 minutes)

**1. Open Render Dashboard**
```
https://dashboard.render.com
```
You're already logged in as: **benoats@gmail.com**

**2. Create New Web Service**
- Click **"New +"** button (top right)
- Select **"Web Service"**
- Choose **"Build and deploy from a Git repository"**

**3. Connect Repository**
- Find: **alttext-ai-backend** (TheLaughingGod1986)
- Click **"Connect"**

**4. Configure Service** (Render will auto-detect render.yaml!)

Render should auto-fill most settings from `render.yaml`, but verify:

| Setting | Value |
|---------|-------|
| **Name** | alttext-ai-phase2 |
| **Region** | Oregon (US West) |
| **Branch** | main |
| **Root Directory** | (leave blank or set to `/`) |
| **Runtime** | Node |
| **Build Command** | `cd backend && npm install && npx prisma generate` |
| **Start Command** | `cd backend && npm start` |
| **Plan** | Starter ($7/mo) or Free |

**5. Add Environment Variables**

⚠️ **CRITICAL:** Add these before deploying!

Click **"Advanced"** → **"Add Environment Variable"**

```bash
# Database
DATABASE_URL=postgresql://alttext_ai_db_user:eXV1unHzLvuv4NsOfHZBFNtWcwJmzlQM@dpg-d3rnbdndiees73bsa8e0-a.oregon-postgres.render.com/alttext_ai_db

# JWT (Click "Generate" for JWT_SECRET)
JWT_SECRET=[Click Generate Button]
JWT_EXPIRES_IN=7d

# OpenAI
OPENAI_API_KEY=OPENAI_API_KEY_REDACTED
OPENAI_MODEL=gpt-4o-mini
OPENAI_REVIEW_API_KEY=OPENAI_API_KEY_REDACTED
OPENAI_REVIEW_MODEL=gpt-4o-mini

# Stripe (Test mode)
STRIPE_SECRET_KEY=sk_test_placeholder_key_for_testing
STRIPE_WEBHOOK_SECRET=whsec_afe6685be32f1b21c346ac82861593e1fa8ea4d5d2e412a9bcf74a6b938e2588
STRIPE_PRICE_PRO=price_1SKgtuJl9Rm418cMtcxOZRCR
STRIPE_PRICE_AGENCY=price_1SKgu1Jl9Rm418cM8MedRfqr
STRIPE_PRICE_CREDITS=price_1SKgu2Jl9Rm418cM3b1Z9tUW

# Application
NODE_ENV=production
PORT=3001
FRONTEND_URL=https://your-wordpress-site.com
```

**6. Create Web Service**
- Click **"Create Web Service"** button
- Wait 2-3 minutes for deployment

**7. Monitor Deployment**
Watch the logs. Success looks like:
```
==> Building...
npm install && npx prisma generate
✔ Generated Prisma Client

==> Starting service...
🚀 AltText AI Phase 2 API running on port 3001
📅 Version: 2.0.0 (Monetization)
🔒 Environment: production
```

**8. Copy Service URL**
Your service URL will be:
```
https://alttext-ai-phase2.onrender.com
```
(Or similar)

---

## ✅ Verification

Once deployed, test:

```bash
# Health check
curl https://your-service.onrender.com/health

# Expected response:
{
  "status": "ok",
  "timestamp": "2025-10-21T...",
  "version": "2.0.0",
  "phase": "monetization"
}
```

---

## 🔄 Update WordPress Plugin

After deployment succeeds:

1. **Copy your Render service URL**
2. **Login to WordPress admin**
3. **Go to:** Media → AI Alt Text Generation → Settings
4. **Update API URL** to: `https://your-service.onrender.com`
5. **Save Changes**

---

## 📋 Deployment Checklist

- [x] ✅ Code committed to git
- [x] ✅ Code pushed to GitHub
- [ ] 🔲 Create Render web service (YOU ARE HERE)
- [ ] 🔲 Add environment variables
- [ ] 🔲 Deploy service
- [ ] 🔲 Verify health endpoint
- [ ] 🔲 Update WordPress API URL
- [ ] 🔲 Test authentication flow

---

## 🆘 If Something Goes Wrong

### Issue: Build Fails
**Check:** Build logs for specific error
**Fix:** Update build command if needed

### Issue: Service Crashes
**Check:** Are all environment variables set?
**Fix:** Add missing DATABASE_URL, JWT_SECRET, etc.

### Issue: Can't Connect from WordPress
**Check:** CORS/FRONTEND_URL setting
**Fix:** Set FRONTEND_URL to your WordPress site URL

---

## 📚 Full Documentation

- **Deployment Guide:** [backend/RENDER-DEPLOYMENT-GUIDE.md](backend/RENDER-DEPLOYMENT-GUIDE.md)
- **Phase 2 Complete:** [PHASE-2-COMPLETION-REPORT.md](backend/PHASE-2-COMPLETION-REPORT.md)
- **Quick Deploy:** [DEPLOY-NOW.md](DEPLOY-NOW.md)

---

## ⏭️ After Deployment

Once Render deployment succeeds, we'll move to:
- **Option 2:** Local Integration Testing
- **Option 3:** Stripe Live Configuration
- **Option 4:** Phase 3 Planning

---

**Ready to deploy?** Go to https://dashboard.render.com now! 🚀
