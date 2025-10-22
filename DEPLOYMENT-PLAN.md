# Complete Deployment & Testing Plan

**Created:** October 21, 2025
**Status:** In Progress

---

## Overview

This document outlines the complete plan to deploy and test Phase 2, covering all 4 options sequentially:

1. ✅ **Option 1:** Production Deployment (Backend to Render)
2. 🔲 **Option 2:** Local Integration Testing
3. 🔲 **Option 3:** Stripe Live Configuration
4. 🔲 **Option 4:** Phase 3 Planning

Each option will be completed fully before moving to the next.

---

## Option 1: Production Deployment ⏳

**Goal:** Deploy Phase 2 backend to Render.com and make it publicly accessible

**Estimated Time:** 1 hour
**Current Status:** In Progress

### Prerequisites ✅
- [x] Backend code complete and tested locally
- [x] Database schema deployed (Render PostgreSQL)
- [x] Environment variables documented
- [x] Health endpoint working

### Deployment Steps

#### Step 1.1: Prepare for Deployment 🔲
- [ ] Create render.yaml configuration
- [ ] Create .dockerignore if using Docker
- [ ] Verify package.json start script
- [ ] Document environment variables
- [ ] Create deployment checklist

#### Step 1.2: Create Render Web Service 🔲
- [ ] Login to Render.com dashboard
- [ ] Create new Web Service
- [ ] Connect GitHub repository
- [ ] Configure build settings:
  - Build Command: `cd backend && npm install`
  - Start Command: `cd backend && node server-v2.js`
  - Environment: `Node`
- [ ] Set environment variables
- [ ] Deploy service

#### Step 1.3: Configure Environment Variables 🔲
Required variables:
```bash
DATABASE_URL=postgresql://alttext_ai_db_user:...@dpg-...oregon-postgres.render.com/alttext_ai_db
JWT_SECRET=<secure-random-secret>
JWT_EXPIRES_IN=7d
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_REVIEW_API_KEY=sk-...
OPENAI_REVIEW_MODEL=gpt-4o-mini
STRIPE_SECRET_KEY=sk_test_... (test for now)
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PRO=price_1SKgtuJl9Rm418cMtcxOZRCR
STRIPE_PRICE_AGENCY=price_1SKgu1Jl9Rm418cM8MedRfqr
STRIPE_PRICE_CREDITS=price_1SKgu2Jl9Rm418cM3b1Z9tUW
PORT=3001
NODE_ENV=production
FRONTEND_URL=<your-wordpress-site-url>
```

#### Step 1.4: Verify Deployment 🔲
- [ ] Check deployment logs for errors
- [ ] Test health endpoint: `curl https://your-app.onrender.com/health`
- [ ] Verify response includes version 2.0.0
- [ ] Test CORS with browser fetch from WordPress domain
- [ ] Check database connectivity

#### Step 1.5: Update WordPress Configuration 🔲
- [ ] Copy production backend URL
- [ ] Update WordPress plugin settings
- [ ] Save and verify configuration

### Success Criteria
- [ ] Backend accessible via HTTPS
- [ ] Health endpoint returns 200 OK
- [ ] Database connection working
- [ ] CORS allows WordPress domain
- [ ] No errors in deployment logs

---

## Option 2: Local Integration Testing 🔲

**Goal:** Verify WordPress plugin works with Phase 2 backend locally

**Estimated Time:** 1-2 hours
**Current Status:** Pending Option 1 completion

### Prerequisites
- [ ] Option 1 completed OR backend running locally
- [ ] WordPress site running
- [ ] Plugin updated to version 4.0.0

### Testing Plan

#### Test 2.1: Authentication Flow 🔲
**Test:** User Registration
- [ ] Open WordPress admin
- [ ] Navigate to Media → AI Alt Text Generation
- [ ] Authentication modal should appear
- [ ] Fill registration form (email + password)
- [ ] Submit registration
- [ ] Verify success message
- [ ] Verify dashboard loads with user info
- [ ] Check browser console for errors

**Test:** User Login
- [ ] Logout from dashboard
- [ ] Navigate back to AI Alt Text
- [ ] Login modal should appear
- [ ] Enter credentials
- [ ] Submit login
- [ ] Verify dashboard loads
- [ ] Check user info displayed correctly

**Test:** Token Persistence
- [ ] Login successfully
- [ ] Refresh browser page
- [ ] Should remain logged in (no modal)
- [ ] User info should display immediately

**Test:** Logout
- [ ] Click logout button
- [ ] Verify redirect or modal appears
- [ ] Try to generate alt text (should fail)
- [ ] Verify authentication required message

#### Test 2.2: Alt Text Generation 🔲
**Test:** Basic Generation
- [ ] Login as authenticated user
- [ ] Upload test image to Media Library
- [ ] Click "Generate Alt Text"
- [ ] Verify alt text appears
- [ ] Check alt text quality
- [ ] Verify usage counter decrements

**Test:** Usage Tracking
- [ ] Note starting usage (e.g., 10/10)
- [ ] Generate alt text for one image
- [ ] Verify usage shows 9/10
- [ ] Refresh page
- [ ] Verify usage persists as 9/10

**Test:** Multiple Images
- [ ] Generate alt text for 5 different images
- [ ] Verify each generation succeeds
- [ ] Verify usage decrements correctly
- [ ] Check all alt texts are saved

#### Test 2.3: Usage Limits 🔲
**Test:** Reaching Limit
- [ ] Start with fresh account (10/10)
- [ ] Generate alt text 10 times
- [ ] Verify usage reaches 0/10
- [ ] Attempt 11th generation
- [ ] Verify limit reached error
- [ ] Check upgrade modal appears

**Test:** Limit Enforcement
- [ ] With 0/10 usage remaining
- [ ] Try to generate alt text
- [ ] Should show "Monthly limit reached"
- [ ] Verify upgrade prompt displays
- [ ] Cannot generate until upgrade

#### Test 2.4: Upgrade & Billing 🔲
**Test:** Upgrade Modal
- [ ] Reach usage limit
- [ ] Verify upgrade modal appears
- [ ] Click "Upgrade to Pro"
- [ ] Verify AJAX request sent
- [ ] Check for redirect to Stripe (test mode)
- [ ] Verify checkout session created

**Test:** Billing Portal (if subscription exists)
- [ ] Login as subscribed user
- [ ] Click "Manage Billing"
- [ ] Verify portal session created
- [ ] Verify redirect to Stripe portal
- [ ] Can view/manage subscription

#### Test 2.5: Error Handling 🔲
**Test:** Backend Offline
- [ ] Stop backend server
- [ ] Try to login
- [ ] Verify friendly error message
- [ ] Try to generate alt text
- [ ] Verify connection error shown

**Test:** Invalid Credentials
- [ ] Enter wrong password
- [ ] Submit login
- [ ] Verify "Invalid credentials" message
- [ ] Try non-existent email
- [ ] Verify appropriate error

**Test:** Network Issues
- [ ] Simulate slow network (browser DevTools)
- [ ] Test all operations
- [ ] Verify loading states
- [ ] Verify timeout handling

### Issues Log
| Issue | Severity | Status | Fix |
|-------|----------|--------|-----|
| (Issues found during testing will be logged here) | | | |

---

## Option 3: Stripe Live Configuration 🔲

**Goal:** Configure Stripe for production with live keys and webhooks

**Estimated Time:** 30 minutes
**Current Status:** Pending Option 2 completion

### Prerequisites
- [ ] Option 2 completed successfully
- [ ] Backend deployed to production
- [ ] Stripe account verified
- [ ] Bank account connected to Stripe

### Configuration Steps

#### Step 3.1: Get Live Stripe Keys 🔲
- [ ] Login to Stripe Dashboard
- [ ] Switch to Live mode (toggle in sidebar)
- [ ] Go to Developers → API Keys
- [ ] Reveal and copy Secret Key (sk_live_...)
- [ ] Store securely (password manager)

#### Step 3.2: Update Backend Environment 🔲
- [ ] Open Render dashboard
- [ ] Navigate to Environment Variables
- [ ] Update `STRIPE_SECRET_KEY=sk_live_...`
- [ ] Save changes
- [ ] Trigger redeployment
- [ ] Verify service restarts successfully

#### Step 3.3: Create Webhook Endpoint 🔲
- [ ] In Stripe Dashboard (Live mode)
- [ ] Go to Developers → Webhooks
- [ ] Click "Add endpoint"
- [ ] Enter URL: `https://your-backend.onrender.com/billing/webhook`
- [ ] Select events to listen to:
  - [x] checkout.session.completed
  - [x] customer.subscription.created
  - [x] customer.subscription.updated
  - [x] customer.subscription.deleted
  - [x] invoice.paid
  - [x] invoice.payment_failed
- [ ] Click "Add endpoint"
- [ ] Copy Signing Secret (whsec_...)

#### Step 3.4: Update Webhook Secret 🔲
- [ ] Open Render dashboard
- [ ] Update `STRIPE_WEBHOOK_SECRET=whsec_...`
- [ ] Save and redeploy
- [ ] Verify service restarts

#### Step 3.5: Test Webhooks 🔲
**Using Stripe CLI:**
```bash
stripe listen --forward-to https://your-backend.onrender.com/billing/webhook
stripe trigger checkout.session.completed
```

**Manual Test:**
- [ ] Create test subscription
- [ ] Verify webhook received
- [ ] Check backend logs
- [ ] Verify user plan updated
- [ ] Test subscription cancellation
- [ ] Verify downgrade works

#### Step 3.6: Verify Payment Processing 🔲
- [ ] Create test subscription with real card
- [ ] Verify charge appears in Stripe
- [ ] Verify user upgraded in database
- [ ] Check usage limit increased
- [ ] Test billing portal access
- [ ] Cancel subscription
- [ ] Verify downgrade after period ends

### Success Criteria
- [ ] Live keys configured
- [ ] Webhooks receiving events
- [ ] Subscriptions creating successfully
- [ ] User plans updating correctly
- [ ] Cancellations working
- [ ] No errors in logs

---

## Option 4: Phase 3 Planning 🔲

**Goal:** Plan next phase of features and enhancements

**Estimated Time:** 1 hour
**Current Status:** Pending Option 3 completion

### Planning Agenda

#### 4.1: Review Phase 2 Performance 🔲
- [ ] Analyze user feedback
- [ ] Review error logs
- [ ] Check usage patterns
- [ ] Identify bottlenecks
- [ ] List improvement areas

#### 4.2: Feature Brainstorming 🔲
**Potential Features:**
- Enhanced analytics dashboard
- Email notifications (welcome, limit reached, etc.)
- Batch processing improvements
- Custom AI prompts per user
- Team/agency features
- White-label options
- API key access for developers
- WordPress multisite support
- Image SEO recommendations
- Performance optimizations

#### 4.3: Prioritize Features 🔲
Use MoSCoW method:
- **Must Have:** Critical for next release
- **Should Have:** Important but not critical
- **Could Have:** Nice to have
- **Won't Have:** Deferred to future

#### 4.4: Technical Planning 🔲
For each selected feature:
- [ ] Define requirements
- [ ] Estimate effort
- [ ] Identify dependencies
- [ ] Plan architecture
- [ ] Create task breakdown

#### 4.5: Create Phase 3 Roadmap 🔲
- [ ] Define timeline
- [ ] Assign priorities
- [ ] Set milestones
- [ ] Document decisions
- [ ] Share with stakeholders

---

## Progress Tracking

### Overall Status
- **Option 1:** 🔄 In Progress (0%)
- **Option 2:** ⏳ Pending
- **Option 3:** ⏳ Pending
- **Option 4:** ⏳ Pending

### Timeline
| Option | Start Date | End Date | Status |
|--------|-----------|----------|--------|
| 1 | Oct 21 | - | In Progress |
| 2 | - | - | Pending |
| 3 | - | - | Pending |
| 4 | - | - | Pending |

---

## Notes & Decisions

### Option 1 Notes
- Using Render.com for deployment
- Existing PostgreSQL database already on Render
- Test Stripe keys initially, switch to live in Option 3

### Issues Encountered
(Will be documented as they occur)

---

## Next Actions

**Current Focus:** Option 1 - Production Deployment

**Immediate Next Steps:**
1. Create render.yaml configuration file
2. Verify package.json scripts
3. Create Render Web Service
4. Configure environment variables
5. Deploy and verify

---

**Last Updated:** October 21, 2025
