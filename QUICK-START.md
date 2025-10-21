# Quick Start Guide - Phase 1 MVP

## What We've Built

A **free-tier WordPress plugin** that generates alt text using AI, with:
- ✅ 50 free generations per month per domain
- ✅ Secure proxy API (users don't need their own OpenAI key)
- ✅ Usage tracking and quota display
- ✅ Upgrade prompts when approaching limit
- ✅ Beautiful, gamified UI
- ✅ Ready for WordPress.org submission

## Quick Deploy (30 minutes)

### 1. Deploy Backend API (10 min)

```bash
# Navigate to backend folder
cd backend

# Install dependencies
npm install

# Create .env file
cat > .env << EOF
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_MODEL=gpt-4o-mini
PORT=3000
NODE_ENV=production
FREE_MONTHLY_LIMIT=50
PRO_MONTHLY_LIMIT=1000
API_SECRET=change-this-to-random-string
WEBHOOK_SECRET=change-this-to-another-random-string
EOF

# Test locally
npm run dev

# Visit http://localhost:3000/health - should return {"status":"ok"}
```

**Deploy to Railway:**
```bash
# Install Railway CLI
npm install -g @railway/cli

# Login and deploy
railway login
railway init
railway up

# Add environment variables in Railway dashboard
# Get your public URL from Railway
```

**OR Deploy to Render:**
1. Push code to GitHub
2. Go to render.com
3. Create New Web Service
4. Connect your repo
5. Add environment variables
6. Deploy!

### 2. Update Plugin (5 min)

```bash
# Update API URL in plugin (if not using default)
# Edit: includes/class-api-client.php line 12
$this->api_url = $options['api_url'] ?? 'https://YOUR-API-URL.railway.app';

# Update Upgrade URL
# Edit: includes/class-usage-tracker.php line 80
return 'https://your-gumroad-or-payment-url.com/pricing';
```

### 3. Test Plugin (10 min)

1. Install plugin in WordPress test site
2. Activate plugin  
3. Go to **Media > AI Alt Text**
4. See usage widget showing "0 of 50 free generations used"
5. Click "Fill Coverage Gaps" button
6. Confirm alt text generation works
7. Check usage counter increments

### 4. Setup Monthly Reset Cron (5 min)

Go to [cron-job.org](https://cron-job.org):
1. Create free account
2. Add new cron job:
   - **URL**: `https://YOUR-API-URL/api/webhook/reset`
   - **Method**: POST
   - **Body**: `{"secret":"your-webhook-secret"}`
   - **Schedule**: Every 1st of month, 00:00 UTC

## Test Full User Flow

1. **New User Installs Plugin**
   - Usage shows 0/50
   - Can generate alt text immediately

2. **User Generates 40 Alt Texts**
   - Usage shows 40/50
   - No warnings yet

3. **User Hits 45/50 (90%)**
   - Warning banner appears
   - "Approaching your free limit" message
   - Upgrade button visible

4. **User Hits 50/50 (100%)**
   - Blocker modal appears
   - Cannot generate more alt text
   - "Upgrade to Pro" is main CTA
   - Also shows: "Resets on November 1"

5. **User Clicks Upgrade**
   - Taken to your payment page (Gumroad, etc.)

6. **After Purchase**
   - User emails you their domain
   - You run upgrade command:
   ```bash
   curl -X POST https://YOUR-API-URL/api/admin/upgrade \
     -H "Content-Type: application/json" \
     -d '{"domain":"customer-site.com","secret":"your-api-secret","plan":"pro"}'
   ```
   - Their limit increases to 1,000/month

## Submit to WordPress.org

### Before Submission Checklist

- [ ] Backend API is deployed and working
- [ ] Plugin tested with backend API
- [ ] Screenshots created (6 images)
- [ ] Banner created (772x250px)
- [ ] Icon created (256x256px)
- [ ] readme.txt is complete
- [ ] Version number updated to 3.1.0
- [ ] Author name updated
- [ ] All upgrade URLs point to correct destination

### Create Screenshots

Take screenshots of:
1. **Dashboard** - Showing coverage percentage, usage widget, and action cards
2. **Usage Widget** - Close-up of monthly usage meter
3. **ALT Library** - Table view with quality scores
4. **Media Library** - Row action "Generate Alt Text (AI)"
5. **Upgrade Modal** - Warning or blocker modal
6. **Settings** - API Configuration section

Save as: `screenshot-1.png` through `screenshot-6.png`

### Create Banner & Icon

**Tools:**
- Figma (free)
- Canva (free tier)
- Photoshop
- Or hire on Fiverr ($5-20)

**Design Tips:**
- Use gradient colors from plugin UI (#667eea to #764ba2)
- Include icon/logo
- Plugin name clearly visible
- Professional, clean design

### Submit Plugin

1. Go to: https://wordpress.org/plugins/developers/add/
2. Upload plugin ZIP:
   ```bash
   # Create clean ZIP
   cd /path/to/plugin/parent
   zip -r ai-alt-text-generator.zip WP-Alt-text-plugin \
     -x "*.git*" "*node_modules*" "*backend*" "*.DS_Store" "*DEMO*"
   ```
3. Fill out submission form
4. Wait for review (2-14 days typically)
5. Respond to any reviewer questions promptly
6. After approval, commit to SVN

## Pricing Your Pro Plan

### Suggested Pricing

**Monthly:**
- $9/month - Easy entry point, covers costs + margin

**Annual:**
- $49/year - ~$4/month, 45% discount encourages annual
- Or $79/year - higher margin

**Agency (Phase 2):**
- $199/year - Multiple domains

### What Your Costs Look Like

**OpenAI Costs (GPT-4o-mini):**
- Input: ~$0.15 per 1M tokens
- Output: ~$0.60 per 1M tokens
- Average alt text generation: ~500 input + 100 output tokens = $0.00015
- **1,000 generations = ~$0.15 in OpenAI costs**

**Hosting:**
- Railway/Render: $0-5/month (free tier to start)

**Your margin on Pro ($9/month):**
- Revenue: $9
- OpenAI cost: ~$0.15
- Hosting: ~$1
- **Profit: ~$7.85 per Pro user per month**

With just 10 Pro users = $78.50/month profit
With 100 Pro users = $785/month profit

## Growth Strategy

### Week 1: Soft Launch
- Submit to WordPress.org
- Share in WordPress Facebook groups
- Post on Reddit (r/WordPress, r/webdev)
- Share on Twitter/LinkedIn

### Week 2-4: While Waiting for Approval
- Create demo video (screen recording)
- Write blog post about accessibility
- Reach out to WordPress influencers
- Create Product Hunt launch page (draft)

### After WordPress.org Approval
- Launch on Product Hunt
- Post in WordPress Tavern
- Email WordPress newsletter sites
- Offer launch discount (30% off for 1st month)

### Content Marketing
- "The Ultimate Guide to Image Accessibility"
- "How We Generated Alt Text for 10,000 Images"
- "WordPress Accessibility Checklist"
- Share on dev.to, Medium, your blog

## Monitoring Success

### Key Metrics to Track

1. **Installations**: Check WordPress.org plugin page
2. **Active Installations**: Track in stats
3. **Generations per Day**: Monitor API `db.json`
4. **Conversion Rate**: % of users who hit limit and upgrade
5. **Revenue**: Track via Gumroad/payment platform

### API Monitoring

```bash
# Check total usage across all users
ssh into your server
cd backend
cat db.json | grep -o '"count":[0-9]*' | wc -l
```

### Set Up Alerts

- **Uptime**: UptimeRobot for API health checks
- **Errors**: Check logs daily for first week
- **Revenue**: Gumroad email notifications

## Support Plan

### Free Users
- Direct to WordPress.org support forum
- You answer 1-2x per week
- Update FAQ as common questions arise

### Pro Users
- Priority email support
- Aim for 24-hour response time
- Consider Intercom/Help Scout when > 50 Pro users

## Common Issues & Solutions

### "API not responding"
→ Check Railway/Render logs, verify API key is set

### "Monthly limit reached" 
→ Check if it's actually end of month, offer Pro upgrade

### "Alt text is low quality"
→ Explain you can regenerate, or edit manually

### "Can I use my own OpenAI key?"
→ "Pro plan uses advanced GPT-4 model for better quality"

## Next Phase Plans

Once you have **20-50 Pro users**, consider:

1. **User Dashboard**: Let users manage their own account
2. **Stripe Integration**: Automated billing
3. **License Keys**: Self-service upgrades
4. **Agency Plan**: Multiple domains
5. **API Rate Limiting**: Per-user limits
6. **Email Automation**: Welcome, renewal reminders
7. **Advanced Analytics**: Usage graphs, quality trends

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress.org Review Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [OpenAI Pricing](https://openai.com/pricing)
- [Railway Docs](https://docs.railway.app/)
- [Render Docs](https://render.com/docs)

## Getting Help

**Technical Issues:**
- Review PHASE-1-DEPLOYMENT.md for detailed troubleshooting
- Check plugin error logs: `WP_DEBUG_LOG`
- Check API logs in hosting dashboard

**Business Questions:**
- Pricing strategy
- Growth tactics
- When to add features

Start small, iterate based on user feedback, and grow sustainably!

---

**Ready to launch?** Follow the steps above and you'll have your MVP live in under an hour. 🚀

Good luck! You've got this! 💪


