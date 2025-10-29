# 🔐 Password Reset & Account Management Deployment Guide

## Current Status

✅ **Frontend**: Ready in ZIP (`dist/ai-alt-text-generator-4.2.0.zip`)  
❌ **Backend**: Needs deployment to Render

## Step-by-Step Deployment

### Step 1: Deploy Backend Database Changes

The backend needs the new `PasswordResetToken` model. You have two options:

#### Option A: If you have Render CLI access:
```bash
cd backend
npx prisma generate
npx prisma db push
```

#### Option B: Via Render Dashboard:
1. Go to your Render PostgreSQL database dashboard
2. Go to "Migrations" or "Shell" tab
3. Run Prisma migrations (or manually create the table - see schema below)

**Manual SQL (if Prisma isn't available):**
```sql
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id SERIAL PRIMARY KEY,
  "userId" INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token TEXT NOT NULL UNIQUE,
  "expiresAt" TIMESTAMP NOT NULL,
  used BOOLEAN NOT NULL DEFAULT false,
  "createdAt" TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS password_reset_tokens_token_idx ON password_reset_tokens(token);
CREATE INDEX IF NOT EXISTS password_reset_tokens_user_id_idx ON password_reset_tokens("userId");
```

### Step 2: Commit & Push Backend Changes

The backend files that need to be committed:
- `backend/prisma/schema.prisma` (updated with PasswordResetToken)
- `backend/auth/routes.js` (added forgot-password & reset-password endpoints)
- `backend/auth/email.js` (new email service)
- `backend/routes/billing.js` (added /subscription endpoint)
- `backend/env.example` (updated with email service config)

**Commands:**
```bash
cd backend
git add prisma/schema.prisma auth/routes.js auth/email.js routes/billing.js env.example PASSWORD_RESET_SETUP.md
git commit -m "Add password reset and subscription info endpoints"
git push origin main
```

### Step 3: Deploy to Render

Once pushed, Render should auto-deploy. If not:
1. Go to [Render Dashboard](https://dashboard.render.com)
2. Find your `alttext-ai-backend` service
3. Click "Manual Deploy" → "Deploy latest commit"
4. Wait for deployment to complete (~5-10 minutes)

### Step 4: Set Environment Variables in Render

In your Render service environment variables, ensure you have:

**Required:**
```
FRONTEND_URL=https://your-wordpress-site.com/wp-admin/upload.php?page=ai-alt-gpt
```

**Optional (for email service - currently logs to console):**
```
# If you want real emails, add one of:
SENDGRID_API_KEY=your_key
# OR
RESEND_API_KEY=re_xxx
# OR AWS SES credentials
```

### Step 5: Verify Backend Endpoints

After deployment, test the endpoints:

**Test Forgot Password:**
```bash
curl -X POST https://alttext-ai-backend.onrender.com/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'
```

Expected: `{"success":true,"message":"..."}`

**Test Subscription Info (requires auth token):**
```bash
curl -X GET https://alttext-ai-backend.onrender.com/billing/subscription \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Step 6: Upload Frontend Plugin

The ZIP is already built:
- **Location**: `dist/ai-alt-text-generator-4.2.0.zip`
- **Size**: 160K
- **Version**: 4.2.0

**Upload to WordPress:**
1. WordPress Admin → Plugins → Add New
2. Click "Upload Plugin"
3. Select `ai-alt-text-generator-4.2.0.zip`
4. Click "Install Now"
5. Activate if prompted

### Step 7: Test Password Reset Flow

1. Go to WordPress Media Library → AI Alt Text
2. Click "Sign In" (if not logged in)
3. Click "Forgot password?"
4. Enter email address
5. **Check Render logs** for reset link (currently logs to console)
6. Copy the reset link from logs
7. Open reset link in browser
8. Set new password
9. Try logging in with new password

### Step 8: Test Account Management

1. Go to Settings tab (must be logged in)
2. Scroll to "Account Management" section
3. Should display:
   - Current plan (free/pro/agency)
   - Subscription status
   - Next billing date (if subscribed)
   - Payment method (if subscribed)
   - "Manage Subscription" button (opens Stripe portal)

## Quick Deployment Checklist

- [ ] Deploy database schema changes (`npx prisma db push`)
- [ ] Commit backend changes to Git
- [ ] Push to repository
- [ ] Wait for Render auto-deploy (or manually trigger)
- [ ] Set `FRONTEND_URL` in Render environment
- [ ] Test `/auth/forgot-password` endpoint
- [ ] Test `/billing/subscription` endpoint
- [ ] Upload new plugin ZIP to WordPress
- [ ] Test password reset from WordPress
- [ ] Test Account Management display

## Troubleshooting

### "Password reset is currently being set up" error
- **Cause**: Backend endpoint not deployed yet
- **Fix**: Complete Step 2-3 above

### "Failed to send password reset email"
- **Cause**: Email service not configured (currently logs to console)
- **Fix**: Check Render logs for reset link, or configure real email service

### Subscription info not loading
- **Cause**: `/billing/subscription` endpoint not deployed
- **Fix**: Ensure `backend/routes/billing.js` changes are deployed

### Database errors
- **Cause**: `PasswordResetToken` table doesn't exist
- **Fix**: Run `npx prisma db push` or create table manually (see Step 1)

## Next Steps After Basic Deployment

1. **Integrate Real Email Service** (Optional but Recommended):
   - Choose: SendGrid (easiest), Resend (modern), AWS SES, or Mailgun
   - Update `backend/auth/email.js` with real email sending
   - Add API keys to Render environment

2. **Custom Reset Password Page** (Optional):
   - Currently uses WordPress admin URL
   - Could create custom page at `/reset-password`
   - Update `FRONTEND_URL` in backend

3. **Email Templates** (Optional):
   - Add HTML email templates
   - Include branding
   - Better user experience

