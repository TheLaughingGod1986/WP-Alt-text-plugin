# Email Automation - Backend Quick Start

**For Backend Engineers**
**Estimated Time:** 2-3 hours

---

## 🎯 Goal

Build 3 API endpoints and set up Resend.com to enable automated email campaigns for the AltText AI WordPress plugin.

---

## 📋 Prerequisites

- [ ] Resend.com account ([sign up free](https://resend.com))
- [ ] Node.js 18+ (for React Email templates)
- [ ] Access to your backend codebase

---

## Step 1: Get Resend API Key (5 min)

1. Go to [resend.com](https://resend.com) → Sign up
2. Dashboard → API Keys → Create API Key
3. Copy your key: `re_...`
4. Add to `.env`:
   ```bash
   RESEND_API_KEY=re_your_key_here
   ```

---

## Step 2: Create Email Audience (2 min)

```bash
curl -X POST https://api.resend.com/audiences \
  -H "Authorization: Bearer re_your_key_here" \
  -H "Content-Type: application/json" \
  -d '{"name": "AltText AI Users"}'

# Response: {"id": "aud_..."}
```

Add to `.env`:
```bash
RESEND_AUDIENCE_ID=aud_your_audience_id
```

---

## Step 3: Install Dependencies (1 min)

```bash
npm install resend
```

---

## Step 4: Create Email Service (15 min)

**File:** `services/emailService.js`

```javascript
const { Resend } = require('resend');
const resend = new Resend(process.env.RESEND_API_KEY);

class EmailService {
    /**
     * Add subscriber to Resend audience
     */
    async subscribe(data) {
        const { email, name, plan, install_id } = data;

        try {
            const contact = await resend.contacts.create({
                email,
                firstName: name || email.split('@')[0],
                audienceId: process.env.RESEND_AUDIENCE_ID,
                unsubscribed: false
            });

            return {
                success: true,
                contact_id: contact.id,
                audience_id: process.env.RESEND_AUDIENCE_ID
            };
        } catch (error) {
            console.error('[Email] Subscribe failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Send email based on event type
     */
    async triggerEmail(data) {
        const { email, event_type, event_data } = data;

        try {
            const emailConfig = this.getEmailConfig(event_type, event_data);

            const result = await resend.emails.send({
                from: 'AltText AI <noreply@alttextai.com>',
                to: email,
                subject: emailConfig.subject,
                html: emailConfig.html
            });

            return {
                success: true,
                email_id: result.id
            };
        } catch (error) {
            console.error('[Email] Send failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Remove subscriber from audience
     */
    async unsubscribe(email) {
        try {
            await resend.contacts.remove({
                email,
                audienceId: process.env.RESEND_AUDIENCE_ID
            });

            return { success: true };
        } catch (error) {
            console.error('[Email] Unsubscribe failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Get email configuration for event type
     */
    getEmailConfig(eventType, eventData) {
        switch (eventType) {
            case 'welcome':
                return {
                    subject: 'Welcome to AltText AI! 🎉',
                    html: `
                        <h1>Welcome!</h1>
                        <p>Thanks for signing up. Start generating alt text today!</p>
                        <a href="https://alttextai.com/docs">Get Started →</a>
                    `
                };

            case 'usage_70':
                return {
                    subject: `You've used ${eventData.percentage}% of your free tokens`,
                    html: `
                        <h1>Usage Alert</h1>
                        <p>You've used ${eventData.tokens_used} of ${eventData.limit} free tokens.</p>
                        <p>Upgrade to Pro for unlimited generations!</p>
                        <a href="https://alttextai.com/pricing">View Plans →</a>
                    `
                };

            case 'usage_100':
                return {
                    subject: 'You\'re out of free tokens - Upgrade to keep generating!',
                    html: `
                        <h1>Out of Tokens</h1>
                        <p>You've used all ${eventData.limit} free tokens.</p>
                        <p>Upgrade now to continue generating alt text!</p>
                        <a href="https://alttextai.com/pricing">Upgrade Now →</a>
                    `
                };

            case 'inactive_30d':
                return {
                    subject: 'We miss you! Come back to AltText AI',
                    html: `
                        <h1>We Miss You!</h1>
                        <p>It's been ${eventData.days_inactive} days since your last generation.</p>
                        <p>Check out what's new!</p>
                        <a href="${eventData.dashboard_url}">Open Dashboard →</a>
                    `
                };

            default:
                return {
                    subject: 'AltText AI Notification',
                    html: '<p>You have a notification from AltText AI.</p>'
                };
        }
    }
}

module.exports = new EmailService();
```

---

## Step 5: Create API Routes (20 min)

**File:** `routes/email.js`

```javascript
const express = require('express');
const router = express.Router();
const emailService = require('../services/emailService');
const { authenticate } = require('../middleware/auth');

/**
 * Subscribe user to email list
 * POST /api/email/subscribe
 */
router.post('/subscribe', authenticate, async (req, res) => {
    try {
        const result = await emailService.subscribe(req.body);
        res.json(result);
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Trigger email event
 * POST /api/email/trigger
 */
router.post('/trigger', authenticate, async (req, res) => {
    try {
        const result = await emailService.triggerEmail(req.body);
        res.json(result);
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Unsubscribe user
 * POST /api/email/unsubscribe
 */
router.post('/unsubscribe', async (req, res) => {
    try {
        const { email } = req.body;
        const result = await emailService.unsubscribe(email);
        res.json(result);
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

module.exports = router;
```

**Add to your main app:**

```javascript
// app.js or index.js
const emailRoutes = require('./routes/email');
app.use('/api/email', emailRoutes);
```

---

## Step 6: Test the Endpoints (10 min)

### Test 1: Subscribe

```bash
curl -X POST http://localhost:3001/api/email/subscribe \
  -H "Authorization: Bearer your_jwt_token" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "name": "Test User",
    "plan": "free",
    "install_id": "test_install_id"
  }'

# Expected: {"success": true, "contact_id": "...", "audience_id": "..."}
```

### Test 2: Trigger Email

```bash
curl -X POST http://localhost:3001/api/email/trigger \
  -H "Authorization: Bearer your_jwt_token" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "event_type": "welcome",
    "event_data": {},
    "install_id": "test_install_id"
  }'

# Expected: {"success": true, "email_id": "..."}
```

### Test 3: Check Resend Dashboard

1. Go to [resend.com/emails](https://resend.com/emails)
2. You should see your test email
3. Click to view content and delivery status

---

## Step 7: Create Better Email Templates (30 min - Optional)

Use React Email for professional templates:

```bash
npm install @react-email/components
```

**File:** `emails/Welcome.tsx`

```tsx
import {
  Body,
  Button,
  Container,
  Head,
  Heading,
  Html,
  Preview,
  Section,
  Text,
} from '@react-email/components';

interface WelcomeEmailProps {
  userName?: string;
}

export const WelcomeEmail = ({ userName = 'there' }: WelcomeEmailProps) => (
  <Html>
    <Head />
    <Preview>Welcome to AltText AI - Start generating today!</Preview>
    <Body style={main}>
      <Container style={container}>
        <Heading style={h1}>Welcome to AltText AI! 🎉</Heading>
        <Text style={text}>Hi {userName},</Text>
        <Text style={text}>
          Thanks for signing up! You're now ready to generate AI-powered alt text
          for all your images.
        </Text>
        <Section style={section}>
          <Text style={text}>
            <strong>Quick Start:</strong>
          </Text>
          <Text style={text}>
            1. Go to Media Library<br />
            2. Click "Generate Alt Text (AI)"<br />
            3. Watch the magic happen ✨
          </Text>
        </Section>
        <Button style={button} href="https://alttextai.com/docs">
          Get Started
        </Button>
        <Text style={footer}>
          AltText AI - Making the web more accessible
        </Text>
      </Container>
    </Body>
  </Html>
);

const main = {
  backgroundColor: '#f6f9fc',
  fontFamily: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
};

const container = {
  backgroundColor: '#ffffff',
  margin: '0 auto',
  padding: '40px 20px',
  maxWidth: '600px',
};

const h1 = {
  color: '#333',
  fontSize: '24px',
  fontWeight: 'bold',
  margin: '40px 0',
  textAlign: 'center' as const,
};

const text = {
  color: '#333',
  fontSize: '16px',
  lineHeight: '26px',
};

const section = {
  padding: '24px',
  border: '1px solid #dedede',
  borderRadius: '8px',
  margin: '24px 0',
  backgroundColor: '#f8f9fa',
};

const button = {
  backgroundColor: '#14b8a6',
  borderRadius: '8px',
  color: '#fff',
  fontSize: '16px',
  fontWeight: 'bold',
  textDecoration: 'none',
  textAlign: 'center' as const,
  display: 'block',
  padding: '12px 24px',
  margin: '24px auto',
  maxWidth: '200px',
};

const footer = {
  color: '#8898aa',
  fontSize: '12px',
  marginTop: '48px',
  textAlign: 'center' as const,
};
```

**Update emailService.js to use React templates:**

```javascript
const { render } = require('@react-email/render');
const WelcomeEmail = require('./emails/Welcome').WelcomeEmail;

// In triggerEmail method:
const html = await render(WelcomeEmail({ userName: eventData.name }));
```

---

## Step 8: Deploy (10 min)

### Update Environment Variables

```bash
# Production .env
RESEND_API_KEY=re_production_key_here
RESEND_AUDIENCE_ID=aud_production_audience_id
```

### Deploy to Your Platform

```bash
# Render, Railway, etc.
git push origin main
```

### Verify

1. Check logs for startup errors
2. Test `/api/email/subscribe` endpoint
3. Check Resend dashboard for emails

---

## 🎉 Done!

You've successfully integrated Resend email automation!

### What Happens Now

1. User signs up and checks opt-in box
2. WordPress creates subscriber record
3. WordPress calls your `/api/email/subscribe` endpoint
4. You add contact to Resend audience
5. WordPress triggers welcome email via `/api/email/trigger`
6. You send email via Resend
7. When user reaches 70% usage, usage alert email sent
8. When user reaches 100% usage, out-of-tokens email sent

---

## 📊 Monitoring

### Check Email Stats

```bash
curl https://api.resend.com/emails \
  -H "Authorization: Bearer re_..." \
  -H "Content-Type: application/json"
```

### Check Contacts

```bash
curl https://api.resend.com/audiences/${AUDIENCE_ID}/contacts \
  -H "Authorization: Bearer re_..."
```

### Set Up Webhooks (Optional)

Resend can send webhooks for:
- Email delivered
- Email opened
- Email clicked
- Email bounced
- Contact unsubscribed

See: https://resend.com/docs/webhooks

---

## 🐛 Troubleshooting

### Issue: "Unauthorized"

**Problem:** API key invalid

**Solution:**
```bash
# Test API key
curl https://api.resend.com/emails \
  -H "Authorization: Bearer re_your_key" \
  -H "Content-Type: application/json"

# Should return 200, not 401
```

### Issue: "Audience not found"

**Problem:** Audience ID wrong

**Solution:**
```bash
# List all audiences
curl https://api.resend.com/audiences \
  -H "Authorization: Bearer re_your_key"

# Copy correct audience ID
```

### Issue: Emails not sending

**Problem:** Domain not verified

**Solution:**
1. Go to Resend Dashboard → Domains
2. Add your domain (alttextai.com)
3. Add DNS records as instructed
4. Wait for verification (can take up to 48 hours)

For testing, use: `from: 'onboarding@resend.dev'`

---

## 📚 Resources

- **Resend Docs:** https://resend.com/docs
- **React Email:** https://react.email
- **Full Spec:** See EMAIL_AUTOMATION_SPEC.md
- **Implementation Summary:** See EMAIL_AUTOMATION_IMPLEMENTATION_SUMMARY.md

---

## 🚀 Next Steps

After basic emails work:

1. Create professional React Email templates
2. Set up domain verification
3. Add email analytics
4. Implement A/B testing
5. Add personalization
6. Set up webhooks for tracking

---

**Questions?** Check EMAIL_AUTOMATION_SPEC.md or create a GitHub issue.

**Estimated Total Time:** 2-3 hours (including testing)
**Backend Complexity:** Low (just 3 endpoints + Resend)
**Frontend Complexity:** Zero (already done!)
