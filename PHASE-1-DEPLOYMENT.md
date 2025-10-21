# Phase 1 MVP - Deployment Guide

## Overview

This guide covers deploying the proxy API backend and preparing the WordPress plugin for submission to WordPress.org.

## Part 1: Deploy Backend API

### Prerequisites

- Railway or Render account (or similar Node.js hosting)
- OpenAI API key
- Git repository (optional but recommended)

### Option A: Deploy to Railway

1. **Create Railway Account**
   - Sign up at [railway.app](https://railway.app)
   - Connect your GitHub account (optional)

2. **Create New Project**
   ```bash
   # From your local machine
   cd backend
   railway init
   railway up
   ```

3. **Set Environment Variables**
   - Go to your project settings in Railway dashboard
   - Add the following variables:
   ```
   OPENAI_API_KEY=sk-...your-key...
   OPENAI_MODEL=gpt-4o-mini
   PORT=3000
   NODE_ENV=production
   FREE_MONTHLY_LIMIT=50
   PRO_MONTHLY_LIMIT=1000
   API_SECRET=your-random-secret-here
   WEBHOOK_SECRET=your-webhook-secret-here
   ```

4. **Deploy**
   - Railway will automatically detect Node.js and deploy
   - Note your deployment URL (e.g., `https://your-app.railway.app`)

### Option B: Deploy to Render

1. **Create Render Account**
   - Sign up at [render.com](https://render.com)

2. **Create New Web Service**
   - Click "New +" → "Web Service"
   - Connect your Git repository or upload manually
   - Choose "Node" environment

3. **Configure Build Settings**
   - Build Command: `npm install`
   - Start Command: `npm start`
   - Choose "Free" plan (or paid for better performance)

4. **Set Environment Variables**
   - In your service settings, add:
   ```
   OPENAI_API_KEY=sk-...your-key...
   OPENAI_MODEL=gpt-4o-mini
   NODE_ENV=production
   FREE_MONTHLY_LIMIT=50
   PRO_MONTHLY_LIMIT=1000
   API_SECRET=your-random-secret-here
   WEBHOOK_SECRET=your-webhook-secret-here
   ```

5. **Deploy**
   - Render will build and deploy automatically
   - Note your deployment URL (e.g., `https://your-app.onrender.com`)

### Testing the API

```bash
# Health check
curl https://your-api-url.com/health

# Test generation (should work)
curl -X POST https://your-api-url.com/api/generate \
  -H "Content-Type: application/json" \
  -d '{
    "domain": "test.com",
    "context": {
      "filename": "test.jpg",
      "title": "Sunset at the beach"
    }
  }'

# Check usage
curl https://your-api-url.com/api/usage/test.com
```

### Setup Monthly Reset Cron Job

Use a free cron service like [cron-job.org](https://cron-job.org) or [EasyCron](https://www.easycron.com):

1. Create a new cron job
2. Set URL: `https://your-api-url.com/api/webhook/reset`
3. Method: POST
4. Body: `{"secret": "your-webhook-secret"}`
5. Schedule: First day of each month at 00:00 UTC

## Part 2: Update WordPress Plugin

### 1. Update API URL

The plugin now defaults to `https://api.alttextai.com`, but you need to update it to your deployed API URL:

**Option A: Hardcode your URL (for initial testing)**

Edit `includes/class-api-client.php`:
```php
$this->api_url = $options['api_url'] ?? 'https://your-actual-api-url.com';
```

**Option B: Let users configure (recommended)**

Users can set it in Settings → API Configuration → API URL field.

### 2. Update Plugin Version

Edit `ai-alt-gpt.php` header:
```php
* Version: 3.1.0
```

### 3. Test Locally

1. Install plugin in WordPress test site
2. Activate plugin
3. Go to Media → AI Alt Text
4. Try generating alt text for an image
5. Check usage counter updates
6. Test approaching limit (80%)
7. Test at limit (100%)

## Part 3: Prepare for WordPress.org Submission

### Required Files

- [x] `readme.txt` (created)
- [x] `ai-alt-gpt.php` (updated)
- [ ] `screenshot-1.png` - Dashboard view
- [ ] `screenshot-2.png` - Usage widget
- [ ] `screenshot-3.png` - ALT Library
- [ ] `screenshot-4.png` - Media Library integration
- [ ] `screenshot-5.png` - Upgrade modal
- [ ] `screenshot-6.png` - Settings page
- [ ] `assets/banner-772x250.png` - Plugin directory banner
- [ ] `assets/banner-1544x500.png` - Retina banner (optional)
- [ ] `assets/icon-128x128.png` - Plugin icon
- [ ] `assets/icon-256x256.png` - Retina icon

### Screenshot Guidelines

Screenshots should be:
- PNG or JPEG format
- Named `screenshot-1.png`, `screenshot-2.png`, etc.
- Placed in plugin root directory
- Representative of actual plugin functionality
- Clean, professional appearance
- No Lorem Ipsum or placeholder content

### Banner & Icon Guidelines

**Banner (772x250px)**
- Professional design
- Plugin name clearly visible
- Brand colors matching UI
- Clean, not cluttered

**Icon (256x256px)**
- Simple, recognizable symbol
- Works at small sizes
- Consistent with banner

### Create Plugin ZIP

```bash
# From plugin root directory
cd ..
zip -r ai-alt-text-generator.zip WP-Alt-text-plugin \
  -x "*.git*" \
  -x "*node_modules*" \
  -x "*backend*" \
  -x "*.DS_Store" \
  -x "*landing-page*" \
  -x "*DEMO.html" \
  -x "*phase-1*"
```

### WordPress.org Submission Process

1. **Create WordPress.org Account**
   - Sign up at [wordpress.org](https://wordpress.org)
   - Verify email

2. **Submit Plugin**
   - Go to [Plugin Developer](https://wordpress.org/plugins/developers/)
   - Click "Add Your Plugin"
   - Upload ZIP file
   - Wait for review (typically 2-14 days)

3. **During Review**
   - Check email for questions from reviewers
   - Be prepared to make changes if requested
   - Common review points:
     * Security (sanitization, nonces, capabilities)
     * Licensing (GPL compatible)
     * No obfuscated code
     * Proper internationalization
     * Data escaping

4. **After Approval**
   - You'll receive SVN access
   - Commit your plugin to SVN repository
   - Add screenshots to `/assets/` directory in SVN

### SVN Workflow (After Approval)

```bash
# Checkout your plugin SVN
svn co https://plugins.svn.wordpress.org/your-plugin-slug

# Add plugin files to trunk
cp -r /path/to/plugin/* your-plugin-slug/trunk/

# Add assets (banner, icon, screenshots)
cp banner-772x250.png your-plugin-slug/assets/
cp icon-256x256.png your-plugin-slug/assets/

# Commit to SVN
cd your-plugin-slug
svn add trunk/* assets/*
svn ci -m "Initial commit v3.1.0"

# Tag the release
svn cp trunk tags/3.1.0
svn ci -m "Tagging version 3.1.0"
```

## Part 4: Setup Upgrade/Payment System

### Quick Option: Use Gumroad or Lemon Squeezy

**Gumroad Setup:**
1. Create account at [gumroad.com](https://gumroad.com)
2. Create product "AltText AI Pro"
3. Set price (e.g., $9/month or $49/year)
4. Add product description and benefits
5. Get product URL
6. Update `includes/class-usage-tracker.php`:
   ```php
   public static function get_upgrade_url() {
       return 'https://yourusername.gumroad.com/l/alttextai-pro';
   }
   ```

**Lemon Squeezy Setup:**
1. Create account at [lemonsqueezy.com](https://lemonsqueezy.com)
2. Create subscription product
3. Configure pricing tiers
4. Get checkout URL
5. Update plugin with your URL

### Handling Pro Users (Manual for MVP)

When someone purchases Pro:

1. They provide their domain name
2. You manually upgrade them via API:

```bash
curl -X POST https://your-api-url.com/api/admin/upgrade \
  -H "Content-Type: application/json" \
  -d '{
    "domain": "customer-domain.com",
    "secret": "your-api-secret",
    "plan": "pro"
  }'
```

3. Send them confirmation email
4. Their WordPress site will automatically show Pro features on next page load

## Testing Checklist

- [ ] Backend API deploys successfully
- [ ] Health endpoint returns OK
- [ ] Generate endpoint works with test domain
- [ ] Usage tracking increments correctly
- [ ] Monthly limit enforced at 50 for free users
- [ ] Plugin connects to API successfully
- [ ] Alt text generation works in WordPress
- [ ] Usage widget displays correctly
- [ ] Upgrade modal appears at 80% usage
- [ ] Blocker modal appears at 100% usage
- [ ] Upgrade URL works
- [ ] Manual Pro upgrade via API works
- [ ] Pro users see increased limit (1000)

## Monitoring & Maintenance

### Monitor API Health

- Set up uptime monitoring (UptimeRobot, Pingdom, etc.)
- Monitor endpoint: `https://your-api-url.com/health`

### Check Logs

**Railway:**
```bash
railway logs
```

**Render:**
View logs in Render dashboard under your service

### Database Backup

The `db.json` file stores usage data. For production, you should:

1. Regularly backup this file
2. Consider migrating to proper database (PostgreSQL, MongoDB)
3. Archive old monthly data

```bash
# Backup db.json (on your server)
cp backend/db.json backend/db.backup.$(date +%Y%m%d).json
```

## Troubleshooting

### API Not Responding

1. Check logs in hosting platform
2. Verify environment variables are set
3. Test health endpoint
4. Check OpenAI API key is valid

### WordPress Plugin Can't Connect

1. Check API URL in Settings
2. Check for CORS errors (shouldn't happen with proper setup)
3. Test API URL directly in browser/Postman
4. Check WordPress PHP error logs

### Usage Not Tracking

1. Check `db.json` file exists and is writable
2. Verify domain is being sent correctly
3. Check API logs for errors
4. Test usage endpoint manually

### Monthly Reset Not Working

1. Verify cron job is configured correctly
2. Check webhook secret matches
3. Test reset endpoint manually
4. Check cron job logs

## Next Steps (Phase 2)

After MVP is live and you have initial users:

1. Migrate to proper database (PostgreSQL)
2. Add Stripe integration for automated billing
3. Implement license key system
4. Add user accounts/dashboard
5. Build admin panel for managing users
6. Add email notifications (welcome, limit reached, renewal)
7. Implement Agency plan with multiple domains

## Support

For issues during deployment:
- Backend API: Check server logs
- WordPress Plugin: Check WP debug logs
- OpenAI: Check OpenAI API status page

## Success Metrics

Track these to measure MVP success:

- Number of installations from WordPress.org
- Number of alt texts generated
- Percentage of users hitting free limit
- Number of Pro upgrades
- Average time to upgrade
- User feedback and ratings

Good luck with your launch! 🚀


