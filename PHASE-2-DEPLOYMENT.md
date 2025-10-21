# Phase 2 Deployment Guide

## Overview

This guide covers deploying the Phase 2 monetization backend and updating the WordPress plugin to use the new authentication and billing system.

## Prerequisites

- PostgreSQL database (Railway, Render, Supabase, or self-hosted)
- Stripe account with API keys
- OpenAI API key
- Domain for webhook endpoints

## Backend Deployment

### 1. Database Setup

#### Option A: Railway (Recommended)
1. Go to [Railway.app](https://railway.app)
2. Create new project
3. Add PostgreSQL database
4. Copy the connection string

#### Option B: Render
1. Go to [Render.com](https://render.com)
2. Create new PostgreSQL database
3. Copy the connection string

#### Option C: Supabase
1. Go to [Supabase.com](https://supabase.com)
2. Create new project
3. Go to Settings > Database
4. Copy the connection string

### 2. Environment Variables

Create a `.env` file in the backend directory:

```bash
# Database
DATABASE_URL=postgresql://username:password@host:port/database

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

### 3. Deploy Backend

#### Option A: Railway
1. Connect your GitHub repository to Railway
2. Set environment variables in Railway dashboard
3. Deploy automatically

#### Option B: Render
1. Connect your GitHub repository to Render
2. Set environment variables in Render dashboard
3. Deploy automatically

#### Option C: Manual Deployment
```bash
cd backend
npm install
npx prisma migrate deploy
npm start
```

### 4. Set Up Stripe Products

Run the Stripe setup script:

```bash
cd backend
node stripe/setup.js setup
```

Copy the generated price IDs to your environment variables:

```bash
STRIPE_PRICE_PRO=price_xxxxx
STRIPE_PRICE_AGENCY=price_xxxxx
STRIPE_PRICE_CREDITS=price_xxxxx
```

### 5. Configure Stripe Webhooks

1. Go to Stripe Dashboard > Webhooks
2. Add endpoint: `https://your-backend-url.com/billing/webhook`
3. Select events:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.paid`
   - `invoice.payment_failed`
4. Copy the webhook secret to your environment variables

### 6. Run Database Migration

If you have existing Phase 1 data:

```bash
cd backend
node scripts/migrate-domains-to-users.js
```

## WordPress Plugin Updates

### 1. Update Plugin Files

Replace the main plugin file with the Phase 2 version:

```bash
# Backup current version
cp ai-alt-gpt.php ai-alt-gpt-v1-backup.php

# Replace with Phase 2 version
cp ai-alt-gpt-v2.php ai-alt-gpt.php
```

### 2. Update API Client

The plugin will automatically use the new API client (`class-api-client-v2.php`).

### 3. Configure Plugin Settings

In WordPress admin, go to AI Alt Text Generation settings and update:

- **API URL**: Your new backend URL (e.g., `https://your-backend-url.com`)
- **Authentication**: The plugin will now require user accounts

### 4. Test Authentication Flow

1. Go to AI Alt Text Generation in WordPress admin
2. You should see the authentication required page
3. Click "Create Account or Sign In"
4. Register a new account
5. Verify you can access the dashboard

## Testing Checklist

### Backend Testing

- [ ] Health check endpoint responds: `GET /health`
- [ ] User registration works: `POST /auth/register`
- [ ] User login works: `POST /auth/login`
- [ ] JWT authentication works: `GET /auth/me`
- [ ] Usage tracking works: `GET /usage`
- [ ] Alt text generation works: `POST /api/generate`
- [ ] Stripe checkout works: `POST /billing/checkout`
- [ ] Webhooks are receiving events

### WordPress Plugin Testing

- [ ] Authentication modal appears for new users
- [ ] Registration flow works
- [ ] Login flow works
- [ ] Dashboard displays user info and usage
- [ ] Alt text generation works with JWT auth
- [ ] Upgrade modals work
- [ ] Stripe checkout redirects work
- [ ] Customer portal access works

### Stripe Integration Testing

- [ ] Pro plan subscription works
- [ ] Agency plan subscription works
- [ ] Credit pack purchase works
- [ ] Webhooks update user plans correctly
- [ ] Monthly token resets work
- [ ] Customer portal allows plan management

## Production Deployment

### 1. Database Migration

```bash
# Run Prisma migrations
npx prisma migrate deploy

# Run domain migration (if applicable)
node scripts/migrate-domains-to-users.js
```

### 2. Update Environment Variables

Ensure all production environment variables are set:

- Database URL
- JWT secret (use a strong, random secret)
- OpenAI API key
- Stripe keys (use live keys for production)
- Webhook secrets

### 3. Deploy Backend

Deploy to your chosen platform (Railway, Render, etc.)

### 4. Update WordPress Plugin

1. Update the plugin files
2. Clear any caches
3. Test the authentication flow

### 5. Monitor and Verify

- Check backend logs for errors
- Verify Stripe webhooks are working
- Test the complete user journey
- Monitor database for user registrations

## Rollback Plan

If issues arise, you can rollback:

### Backend Rollback
1. Revert to previous backend version
2. Update environment variables to point to old API
3. Restore from database backup if needed

### WordPress Plugin Rollback
1. Restore the original plugin file
2. Clear any cached data
3. Test that the old system works

## Monitoring and Maintenance

### Key Metrics to Monitor

- User registrations per day
- API request volume
- Stripe webhook success rate
- Database performance
- Error rates

### Regular Maintenance

- Monitor Stripe webhook logs
- Check database performance
- Review user feedback
- Update dependencies regularly

## Support and Troubleshooting

### Common Issues

1. **Authentication failures**: Check JWT secret and token expiration
2. **Stripe webhook failures**: Verify webhook URL and secret
3. **Database connection issues**: Check DATABASE_URL format
4. **API rate limits**: Monitor OpenAI usage

### Debug Mode

Enable debug logging by setting:

```bash
NODE_ENV=development
DEBUG=alttext:*
```

### Log Locations

- Railway: Check Railway dashboard logs
- Render: Check Render dashboard logs
- Self-hosted: Check application logs

## Security Considerations

1. **JWT Secrets**: Use strong, random secrets
2. **Database**: Use connection pooling and SSL
3. **API Keys**: Store securely, never commit to version control
4. **Webhooks**: Verify Stripe webhook signatures
5. **Rate Limiting**: Implement appropriate rate limits

## Performance Optimization

1. **Database Indexing**: Ensure proper indexes on user queries
2. **Connection Pooling**: Use connection pooling for database
3. **Caching**: Implement Redis caching for frequently accessed data
4. **CDN**: Use CDN for static assets

## Next Steps

After successful deployment:

1. Monitor user adoption
2. Gather user feedback
3. Plan additional features
4. Optimize based on usage patterns
5. Consider scaling infrastructure

## Support

For technical support:

- Check the logs first
- Review this deployment guide
- Test each component individually
- Contact support if issues persist
