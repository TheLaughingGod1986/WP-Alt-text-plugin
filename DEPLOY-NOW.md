# Deploy Phase 2 Backend - Quick Start

## Fastest Path to Deployment (5 minutes)

### Step 1: Commit and Push (1 minute)

```bash
cd backend

# Add Phase 2 files
git add .
git commit -m "feat: Add Phase 2 monetization backend with JWT auth and Stripe integration"
git push origin main
```

### Step 2: Create Service on Render (3 minutes)

1. Open https://dashboard.render.com
2. Click **"New +"** → **"Web Service"**
3. Select your repository
4. Render will auto-detect `render.yaml`
5. Click **"Apply"**
6. Verify settings:
   - Name: alttext-ai-phase2
   - Build Command: `npm install && npx prisma generate`
   - Start Command: `npm start`
7. Add missing environment variables:
   - DATABASE_URL: (copy from .env)
   - OPENAI_API_KEY: (copy from .env)
   - STRIPE_SECRET_KEY: (copy from .env)
   - STRIPE_WEBHOOK_SECRET: (copy from .env)
   - FRONTEND_URL: Your WordPress site URL
8. Click **"Create Web Service"**

### Step 3: Wait for Deployment (1-2 minutes)

Watch the logs. You'll see:
```
==> Building...
==> npm install && npx prisma generate
==> Starting service...
🚀 AltText AI Phase 2 API running on port 3001
```

### Step 4: Get Your URL

Copy the URL from Render dashboard:
```
https://alttext-ai-phase2.onrender.com
```

### Step 5: Update WordPress (30 seconds)

1. Go to WordPress admin → Media → AI Alt Text → Settings
2. Update API URL: `https://alttext-ai-phase2.onrender.com`
3. Save

### Step 6: Test (30 seconds)

```bash
curl https://alttext-ai-phase2.onrender.com/health
```

Expected: `{"status":"ok","version":"2.0.0"}`

**DONE!** ✅

---

## OR: Super Quick One-Command Deploy

If you're feeling adventurous:

```bash
# One command does it all (commit + push + instructions)
cd backend && git add . && git commit -m "Add Phase 2 backend" && git push && echo "Now go to dashboard.render.com and create Web Service from render.yaml"
```

Then follow steps 2-6 above.
