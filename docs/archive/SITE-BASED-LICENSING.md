# Site-Based Licensing Implementation

## 🎯 Overview
The OpptiAI Alt Text Generator uses **site-based licensing** instead of user-based licensing. This means:

- ✅ Quotas are tied to the **WordPress installation**, not individual users
- ✅ All users on a WordPress site share the same generation quota
- ✅ Any admin can authenticate and manage the subscription
- ✅ Pro/Credits = Single site only
- ✅ Agency = Can be used across multiple sites (multi-site support)

---

## 🔑 Technical Implementation

### Site Identification
Every API request includes these headers:

```http
X-Site-ID: {unique_site_hash}
X-Site-URL: https://example.com
Authorization: Bearer {jwt_token}
```

#### `X-Site-ID`
- **What**: A unique MD5 hash generated once per WordPress installation
- **Format**: 32-character MD5 hash
- **Storage**: `wp_options` table as `opptiai_alt_site_id`
- **Generation**: `md5(site_url + timestamp + random_string)` on first use
- **Purpose**: Ensures quotas are tracked per WordPress site, not per user account

#### `X-Site-URL`
- **What**: The WordPress site URL
- **Format**: Full URL (e.g., `https://example.com`)
- **Purpose**: Human-readable reference for support and multi-site validation

---

## 📊 Usage Tracking

### Backend Behavior (Expected)
1. **Identify Site**: Use `X-Site-ID` to determine which WordPress installation is making the request
2. **Track Quota by Site**: Associate usage quotas with `X-Site-ID`, not the user account
3. **Allow Multiple Admins**: Multiple user accounts can authenticate, but they all manage the same site's subscription
4. **Multi-Site Support (Agency Only)**: Agency plans should allow multiple `X-Site-ID` values under one account

### Frontend Behavior
- JWT token stored site-wide in `wp_options` (not per-user)
- Usage cache stored site-wide in transients
- All WordPress users see the same quota/usage data
- Any admin can click "Manage Subscription" to access Stripe customer portal

---

## 🏢 Plan Types

### Free Plan
- ❌ No authentication required (site ID still sent for analytics)
- ✅ Limited to X generations per month per site
- ✅ Auto-resets monthly

### Pro Plan (Single Site)
- ✅ One WordPress site can use the quota
- ❌ Multiple sites with same Pro account should NOT share quota
- ✅ Backend should validate that the `X-Site-ID` matches the original site

### Agency Plan (Multi-Site)
- ✅ Multiple WordPress sites can share the quota
- ✅ Backend should allow multiple `X-Site-ID` values per account
- ✅ Total usage is tracked across all sites

### Credits/Add-Ons
- ✅ Credits are purchased for a specific site (tied to `X-Site-ID`)
- ❌ Cannot be transferred between sites

---

## 🔄 Authentication Flow

### Login Flow
```
1. Admin clicks "Login" on WordPress site A (X-Site-ID: abc123)
2. WordPress sends login request with X-Site-ID: abc123
3. Backend returns JWT token
4. Token stored in wp_options (site-wide)
5. All users on site A now see "logged in" status
```

### Multi-Site Scenario (Agency Plan)
```
1. Site A (X-Site-ID: abc123) authenticates with email@example.com
2. Site B (X-Site-ID: xyz789) authenticates with same email@example.com
3. Backend recognizes this is an Agency account
4. Both sites share the same Agency quota
5. Usage is tracked: Site A used 50, Site B used 30 = 80 total
```

### Single-Site Protection (Pro Plan)
```
1. Site A (X-Site-ID: abc123) authenticates with Pro account
2. Site B (X-Site-ID: xyz789) tries to authenticate with same Pro account
3. Backend should either:
   - Reject Site B (recommended)
   - Or migrate the license to Site B and invalidate Site A
```

---

## 🛠 Backend API Endpoints

### Expected Headers
All authenticated requests should include:
```http
Authorization: Bearer {jwt_token}
X-Site-ID: {site_hash}
X-Site-URL: {site_url}
Content-Type: application/json
```

### Usage Endpoint
**GET** `/api/usage`

Response should include:
```json
{
  "used": 45,
  "limit": 500,
  "remaining": 455,
  "plan": "pro",
  "resetDate": "2025-12-01T00:00:00Z",
  "resetTimestamp": 1733011200,
  "siteId": "abc123...",
  "allowedSites": ["abc123..."], // For Agency, multiple site IDs
  "billingPortalUrl": "https://billing.stripe.com/p/session/..."
}
```

### Multi-Site Check (Agency Plan)
When a new site ID is detected:
1. Check if user's plan is "agency"
2. If yes, add the new `X-Site-ID` to `allowedSites[]`
3. If no, reject or prompt for upgrade

---

## 🔐 Security Considerations

### Site ID Validation
- ✅ Site ID is immutable once generated
- ✅ Stored in WordPress database (survives plugin updates)
- ✅ Only deleted on full plugin uninstall
- ⚠️ Backend should validate that JWT token + Site ID combination is authorized

### Token Storage
- ✅ Stored in `wp_options` (not per-user)
- ✅ Cleared on logout (affects all users on the site)
- ✅ Validated periodically (5-minute cache)

### Subscription Changes
- ✅ When user upgrades/downgrades, quota updates site-wide
- ✅ Stripe customer portal URL is site-wide
- ✅ Only admins can access subscription management

---

## 📝 Database Schema Reference

### WordPress Options (Plugin Storage)
```
opptiai_alt_jwt_token        = "eyJhbGciOiJIUzI1NiIs..." (site-wide)
opptiai_alt_user_data        = {email, plan, etc.}      (site-wide)
opptiai_alt_site_id          = "abc123..."              (unique per site)
opptiai_alt_upgrade_url      = "https://..."
opptiai_alt_billing_portal_url = "https://billing.stripe.com/..."
```

### WordPress Transients (Caching)
```
opptiai_alt_usage_cache      = {used, limit, remaining, plan} (5 min TTL)
opptiai_alt_token_last_check = timestamp (5 min TTL)
```

---

## ✅ Testing Checklist

### Single Site (Pro Plan)
- [ ] Admin A logs in → sees usage quota
- [ ] Admin B (same site) → sees same quota (no separate login needed)
- [ ] Admin A generates 10 alt texts → Admin B sees usage increase
- [ ] Admin A clicks "Manage Subscription" → can upgrade/downgrade
- [ ] After upgrade to Pro → all users on site see Pro features

### Multi-Site (Agency Plan)
- [ ] Site A logs in with Agency account → works
- [ ] Site B logs in with same Agency account → works
- [ ] Site A uses 50 generations → Site B sees 50 used in shared quota
- [ ] Total usage across both sites is enforced
- [ ] Both sites can access full Agency features

### Site Migration
- [ ] User moves WordPress to new domain → Site ID persists (same database)
- [ ] User creates new WordPress install → new Site ID generated
- [ ] Pro plan should NOT work on new Site ID (single-site restriction)

---

## 🐛 Common Issues

### Issue: User sees different quota on different browsers
**Cause**: The quota is site-wide, so this shouldn't happen  
**Fix**: Check if there are browser-specific caching issues or if the API is returning different data

### Issue: Two sites sharing Pro plan
**Cause**: Backend not enforcing single-site restriction  
**Fix**: Backend should validate `X-Site-ID` and reject if Pro plan is already assigned to a different site

### Issue: Agency plan not working on second site
**Cause**: Backend not recognizing Agency plan's multi-site capability  
**Fix**: Backend should check if `plan === "agency"` and allow multiple `X-Site-ID` values

---

## 📞 Support Contacts
For backend API questions or licensing issues, contact:
- **Plugin Developer**: Benjamin Oats (benjamin@opptiai.com)
- **Backend API**: https://alttext-ai-backend.onrender.com

---

**Last Updated**: November 12, 2025  
**Plugin Version**: 4.2.2  
**API Version**: v2

