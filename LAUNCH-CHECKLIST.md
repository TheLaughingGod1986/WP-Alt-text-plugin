# 🚀 Launch Checklist - Phase 1 MVP

## Implementation Status: ✅ COMPLETE

All code is written, tested, and ready. Follow this checklist to go live.

---

## Pre-Launch Checklist

### 1. Backend Deployment ⏳ (30 minutes)

- [ ] **Sign up for hosting**
  - Option A: [Railway.app](https://railway.app) (Recommended - easiest)
  - Option B: [Render.com](https://render.com) (Also great)
  
- [ ] **Deploy backend API**
  ```bash
  cd backend
  npm install
  # Railway: railway init && railway up
  # Render: Connect GitHub repo in dashboard
  ```

- [ ] **Set environment variables**
  ```
  OPENAI_API_KEY=sk-...
  OPENAI_MODEL=gpt-4o-mini
  NODE_ENV=production
  FREE_MONTHLY_LIMIT=50
  PRO_MONTHLY_LIMIT=1000
  API_SECRET=<random-string>
  WEBHOOK_SECRET=<random-string>
  ```

- [ ] **Test API endpoints**
  ```bash
  # Health check
  curl https://your-api-url.com/health
  
  # Test generation
  curl -X POST https://your-api-url.com/api/generate \
    -H "Content-Type: application/json" \
    -d '{"domain":"test.com","context":{"filename":"test.jpg"}}'
  ```

- [ ] **Note your production API URL**: _________________________

### 2. Update Plugin Configuration ⏳ (10 minutes)

- [ ] **Update API URL**
  - File: `includes/class-api-client.php`
  - Line 12: Change default to your URL
  - Or: Let users configure in Settings (already supported)

- [ ] **Set up payment page**
  - [ ] Create Gumroad account at [gumroad.com](https://gumroad.com)
  - [ ] Create product "AltText AI Pro" at $9/month
  - [ ] Get product URL
  - [ ] Update `includes/class-usage-tracker.php` line 80 with your URL

- [ ] **Update plugin author info**
  - File: `readme.txt`
  - Line 2: Change "yourname" to your username
  - Line 2: Add your WordPress.org username when you have one

### 3. Create Visual Assets ⏳ (2-3 hours)

- [ ] **Take 6 Screenshots** (Save as PNG)
  1. `screenshot-1.png` - Dashboard with usage widget
  2. `screenshot-2.png` - Usage meter close-up
  3. `screenshot-3.png` - ALT Library table view
  4. `screenshot-4.png` - Media Library row action
  5. `screenshot-5.png` - Upgrade modal
  6. `screenshot-6.png` - Settings page

  **Screenshot Tips:**
  - Use browser zoom at 100%
  - Hide browser chrome (F11 fullscreen)
  - Show realistic data (not "test test test")
  - Clean, professional appearance

- [ ] **Create Plugin Banner** (772x250px)
  - Use Figma, Canva, or Photoshop
  - Include plugin name clearly
  - Use gradient colors from plugin (#667eea to #764ba2)
  - Professional, clean design
  - Save as: `banner-772x250.png`

- [ ] **Create Plugin Icon** (256x256px)
  - Simple, recognizable symbol
  - Works at small sizes
  - Matches banner style
  - Save as: `icon-256x256.png`

- [ ] **(Optional) Retina assets**
  - `banner-1544x500.png` (2x banner)
  - `icon-512x512.png` (2x icon)

**Can't design?** Hire on Fiverr for $10-30, or use AI tools like Midjourney/DALL-E for inspiration.

### 4. Final Plugin Prep ⏳ (30 minutes)

- [ ] **Test full flow in WordPress**
  - Install plugin in test site
  - Verify dashboard loads
  - Generate 1 alt text (check usage shows 1/50)
  - Generate 40 more (check warning banner appears)
  - Generate 10 more (check blocker modal appears)
  - Click upgrade link (verify it goes to your Gumroad)

- [ ] **Check for errors**
  - Enable WP_DEBUG in wp-config.php
  - Check PHP error log
  - Check browser console
  - Fix any warnings/errors

- [ ] **Final code review**
  - All API URLs updated?
  - All upgrade URLs updated?
  - Author info updated?
  - Version number correct? (3.1.0)

- [ ] **Create plugin ZIP**
  ```bash
  cd /path/to/plugin/parent/directory
  zip -r ai-alt-text-generator.zip WP-Alt-text-plugin \
    -x "*.git*" \
    -x "*node_modules*" \
    -x "*backend*" \
    -x "*.DS_Store" \
    -x "*DEMO*" \
    -x "*landing-page*"
  ```

### 5. WordPress.org Submission ⏳ (30 minutes)

- [ ] **Create WordPress.org account**
  - Go to [wordpress.org](https://wordpress.org)
  - Sign up
  - Verify email

- [ ] **Submit plugin**
  - Go to: [wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/)
  - Upload your ZIP file
  - Fill out submission form
  - Submit for review

- [ ] **Wait for review** (typically 2-14 days)
  - Check email daily
  - Respond promptly to any questions
  - Be patient and professional

### 6. Setup Monthly Reset Cron ⏳ (5 minutes)

- [ ] **Create cron job** at [cron-job.org](https://cron-job.org)
  - URL: `https://your-api-url.com/api/webhook/reset`
  - Method: POST
  - Headers: `Content-Type: application/json`
  - Body: `{"secret":"your-webhook-secret"}`
  - Schedule: 1st of every month at 00:00 UTC

### 7. Setup Monitoring ⏳ (10 minutes)

- [ ] **Uptime monitoring**
  - Sign up at [UptimeRobot.com](https://uptimerobot.com) (free)
  - Monitor: `https://your-api-url.com/health`
  - Get email alerts if down

- [ ] **Error tracking**
  - Check logs daily for first week
  - Railway: `railway logs`
  - Render: View in dashboard

---

## Post-Approval Checklist

### After WordPress.org Approves (Usually 2-14 days)

- [ ] **Receive SVN access**
  - Check email for approval
  - Note your SVN credentials

- [ ] **Commit to SVN**
  ```bash
  # Checkout
  svn co https://plugins.svn.wordpress.org/your-plugin-slug
  
  # Add plugin files
  cp -r /path/to/plugin/* your-plugin-slug/trunk/
  
  # Add screenshots to assets
  mkdir your-plugin-slug/assets
  cp screenshot-*.png your-plugin-slug/assets/
  cp banner-*.png your-plugin-slug/assets/
  cp icon-*.png your-plugin-slug/assets/
  
  # Commit
  cd your-plugin-slug
  svn add trunk/* assets/*
  svn ci -m "Initial commit v3.1.0"
  
  # Tag release
  svn cp trunk tags/3.1.0
  svn ci -m "Tagging version 3.1.0"
  ```

- [ ] **Verify plugin appears**
  - Check WordPress.org plugin directory
  - Install from WordPress.org on test site
  - Verify everything works

---

## Launch Day Checklist 🎉

### Announce Your Plugin

- [ ] **Social Media**
  - [ ] Tweet about launch
  - [ ] Post on LinkedIn
  - [ ] Share in Facebook WordPress groups

- [ ] **Communities**
  - [ ] Post on Reddit r/WordPress
  - [ ] Share in r/webdev
  - [ ] Post in WordPress Slack communities

- [ ] **Content**
  - [ ] Write blog post announcing launch
  - [ ] Create demo video (screen recording)
  - [ ] Submit to Product Hunt (wait 1 week for some reviews first)

- [ ] **Outreach**
  - [ ] Email WordPress newsletter sites
  - [ ] Contact WordPress influencers
  - [ ] Share in accessibility communities

### Monitor First Week

- [ ] **Daily checks**
  - Check WordPress.org plugin stats
  - Monitor API logs for errors
  - Check support forum
  - Respond to reviews

- [ ] **Track metrics**
  - Number of installations
  - Usage API calls per day
  - Any error patterns
  - First Pro upgrade! 🎉

---

## Quick Reference

### Important URLs

**Your Production URLs:**
- API URL: ___________________________________
- Payment URL: ___________________________________
- Plugin on WP.org: https://wordpress.org/plugins/[your-slug]

**Admin Commands:**

Upgrade user to Pro:
```bash
curl -X POST https://your-api-url.com/api/admin/upgrade \
  -H "Content-Type: application/json" \
  -d '{"domain":"customer-domain.com","secret":"YOUR_API_SECRET","plan":"pro"}'
```

Check user's usage:
```bash
curl https://your-api-url.com/api/usage/customer-domain.com
```

### Support Plan

**Free Users:**
- WordPress.org support forum
- Check 2x per week
- Update FAQ as needed

**Pro Users:**
- Email support: your-support-email@example.com
- 24-hour response time
- Priority handling

---

## Troubleshooting Common Issues

### API Not Responding
1. Check hosting logs
2. Verify environment variables set
3. Test health endpoint
4. Check OpenAI API key validity

### Plugin Can't Connect to API
1. Verify API URL in settings
2. Check WordPress PHP error logs
3. Test API URL in browser
4. Check for CORS errors (shouldn't happen)

### Usage Not Tracking
1. Check `db.json` exists and is writable
2. Verify domain hash being sent
3. Test usage endpoint manually
4. Check API logs

### Slow Response Times
1. Check OpenAI API status
2. Monitor hosting performance
3. Check network latency
4. Consider upgrading hosting plan

---

## Success Milestones

### Week 1
- [ ] Plugin live on WordPress.org
- [ ] 10+ installations
- [ ] First generation completed
- [ ] No critical errors

### Month 1
- [ ] 100+ active installations
- [ ] 4-star+ rating
- [ ] First Pro upgrade 🎉
- [ ] 5+ positive reviews

### Month 3
- [ ] 500+ active installations
- [ ] 10+ Pro users
- [ ] Featured on WP.org (trending)
- [ ] Break-even or profitable

### Month 6
- [ ] 1,000+ installations
- [ ] 50+ Pro users
- [ ] Plan Phase 2 development
- [ ] Consider Agency plan

---

## Emergency Contacts

**Hosting Issues:**
- Railway support: help.railway.app
- Render support: render.com/docs

**WordPress.org:**
- Make WordPress Slack: make.wordpress.org/chat
- Support forum: wordpress.org/support

**OpenAI:**
- Status page: status.openai.com
- Support: help.openai.com

---

## Cost Calculator

### Monthly Expenses

**Hosting:**
- Free tier: $0
- Paid tier: $5-20

**OpenAI API:**
- Per 1,000 generations: ~$0.15
- 10,000 generations/month: ~$1.50
- 100,000 generations/month: ~$15

**Other:**
- Domain: ~$1/month (amortized)
- Cron job: $0 (free tier)
- Email (Phase 2): $15/month

**Total: $0-50/month depending on usage**

### Revenue Projections

**At $9/month Pro Plan:**

| Pro Users | Monthly Revenue | Estimated Costs | Net Profit |
|-----------|----------------|-----------------|------------|
| 5         | $45            | ~$10            | ~$35       |
| 10        | $90            | ~$15            | ~$75       |
| 25        | $225           | ~$25            | ~$200      |
| 50        | $450           | ~$50            | ~$400      |
| 100       | $900           | ~$100           | ~$800      |

**Break even: 3-5 Pro users**

---

## Final Pre-Launch Checklist

Before you hit submit:

- [ ] Backend API deployed and tested ✅
- [ ] Plugin tested in WordPress ✅
- [ ] All URLs updated to production ✅
- [ ] 6 screenshots created ✅
- [ ] Banner and icon created ✅
- [ ] Payment page set up ✅
- [ ] Cron job configured ✅
- [ ] Monitoring set up ✅
- [ ] Plugin ZIP created ✅
- [ ] WordPress.org account created ✅
- [ ] Took a deep breath ✅

---

## You're Ready! 🚀

**Everything is built. All code is complete. Time to launch!**

Follow this checklist step by step and you'll be live within a few hours of work.

**Questions?** Review:
- QUICK-START.md for fast setup
- PHASE-1-DEPLOYMENT.md for detailed guide
- PHASE-1-IMPLEMENTATION-COMPLETE.md for what was built

**Need help?** Google, Stack Overflow, WordPress.org forums, and AI assistants are your friends.

---

**Now go make it happen! You've got this! 💪**

*Good luck with your launch!* 🎉


