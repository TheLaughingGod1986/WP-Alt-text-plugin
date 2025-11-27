# Stripe Checkout Branding Update

## Issue
The Stripe checkout page currently displays "bmv" branding instead of "AltText AI" branding.

## Solution

The WordPress plugin now sends branding parameters to the backend API. The backend needs to apply these when creating Stripe checkout sessions.

## Changes Made

### WordPress Plugin (✅ Complete)
- Updated `class-api-client-v2.php` to send branding parameters:
  - `companyName`: "AltText AI"
  - `branding.name`: "AltText AI"
  - `branding.statementDescriptor`: "ALTTEXT AI"
  - `branding.description`: "AI Alt Text Generator Plugin"

### Backend API (⚠️ Required)
The backend `/api/billing/checkout` endpoint must:

1. **Use the `companyName` parameter** when creating the Stripe checkout session
2. **Apply branding to the Stripe session** using the provided branding object
3. **Set statement descriptor** from `branding.statementDescriptor`

### Stripe Dashboard (⚠️ Required)
Additionally, update these Stripe Dashboard settings:

1. **Settings > Business settings**
   - Business name: **AltText AI**
   - Support phone: (optional)
   - Support email: (optional)

2. **Settings > Branding**
   - Upload AltText AI logo if desired
   - This will appear on checkout pages

## Backend Implementation Example

```javascript
// Node.js/Express example
app.post('/api/billing/checkout', async (req, res) => {
  const { priceId, successUrl, cancelUrl, companyName, branding } = req.body;
  
  // Create Stripe checkout session with branding
  const session = await stripe.checkout.sessions.create({
    payment_method_types: ['card'],
    line_items: [{
      price: priceId,
      quantity: 1,
    }],
    mode: 'subscription', // or 'payment' for one-time
    success_url: successUrl,
    cancel_url: cancelUrl,
    // Use branding parameters
    payment_intent_data: {
      statement_descriptor: branding?.statementDescriptor || 'ALTTEXT AI',
      description: branding?.description || 'AI Alt Text Generator Plugin'
    },
    metadata: {
      company_name: companyName || 'AltText AI',
      plugin_name: 'AltText AI'
    }
  });
  
  res.json({
    success: true,
    data: {
      url: session.url,
      sessionId: session.id
    }
  });
});
```

## Testing

After backend implementation:
1. Create a new checkout session from the WordPress plugin
2. Verify the checkout page shows "AltText AI" instead of "bmv"
3. Verify the statement descriptor shows "ALTTEXT AI" on payment statements

## Notes

- The branding parameters are optional and have defaults, so the backend should handle missing values gracefully
- If branding parameters are not used, the Stripe account's default business name will be displayed
- The statement descriptor must be 22 characters or less (current: "ALTTEXT AI" = 11 chars ✅)

