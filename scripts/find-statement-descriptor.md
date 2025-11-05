# Finding Statement Descriptor Prefix in Stripe Dashboard

The statement descriptor prefix appears on customer credit card statements. Here's where to find it:

## Method 1: Settings > Account (Most Common)

1. Go to: https://dashboard.stripe.com/settings/account
2. Scroll down to find **"Card payments"** or **"Payment settings"** section
3. Look for **"Statement descriptor prefix"** or **"Card statement descriptor"**
4. Change from `BMV` to `ALTTEXT`
5. Click **"Save"**

## Method 2: Settings > Branding

1. Go to: https://dashboard.stripe.com/settings/branding
2. Look for **"Statement descriptor"** field (sometimes near the bottom)
3. Change from `BMV` to `ALTTEXT`
4. Click **"Save"**

## Method 3: Via Cards Payment Method

1. Go to: https://dashboard.stripe.com/settings/payment_methods
2. Click on the **"Cards"** row (or click the "..." menu and select "Settings")
3. In the Card payment method settings, look for **"Statement descriptor prefix"**
4. Change from `BMV` to `ALTTEXT`
5. Click **"Save"**

## Important Notes:

- The statement descriptor prefix can be **maximum 22 characters**
- `ALTTEXT` is only 7 characters, so you have room
- Stripe may show it as just "Statement descriptor" or "Card statement descriptor"
- It should appear in a section related to **card payments** or **payment settings**

## Current Status (via API):

The current value is: **`BMV`**

After updating, it should be: **`ALTTEXT`**

---

**Note:** If you still can't find it, it might be that your Stripe account type doesn't allow direct editing, or you need to contact Stripe support to enable this setting.

