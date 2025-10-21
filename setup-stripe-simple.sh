#!/bin/bash

echo "🔑 Simple Stripe Setup for AltText AI"
echo "======================================"
echo ""

echo "This script will help you get all the Stripe keys and IDs you need."
echo ""

# Get Stripe secret key
echo "🔐 Step 1: Get your Stripe Secret Key"
echo "1. Go to: https://dashboard.stripe.com"
echo "2. Navigate to: Developers → API Keys"
echo "3. Copy your Secret Key (starts with sk_test_ or sk_live_)"
echo ""

read -p "Enter your Stripe Secret Key: " STRIPE_SECRET_KEY

if [ -z "$STRIPE_SECRET_KEY" ]; then
    echo "❌ Stripe Secret Key is required"
    exit 1
fi

# Get backend URL
echo ""
echo "🌐 Step 2: Get your backend URL"
read -p "Enter your backend URL (e.g., https://alttext-ai-backend.onrender.com): " BACKEND_URL

if [ -z "$BACKEND_URL" ]; then
    echo "❌ Backend URL is required"
    exit 1
fi

echo ""
echo "🛍️ Step 3: Creating Stripe products..."

# Create Pro Plan
echo "Creating Pro Plan..."
PRO_PRODUCT=$(curl -s -X POST https://api.stripe.com/v1/products \
    -u "$STRIPE_SECRET_KEY:" \
    -d "name=Pro Plan" \
    -d "description=1000 AI Alt Text Generations per month" \
    -d "type=service")

if echo "$PRO_PRODUCT" | grep -q '"id"'; then
    PRO_PRODUCT_ID=$(echo "$PRO_PRODUCT" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    echo "✅ Pro Plan product created: $PRO_PRODUCT_ID"
    
    PRO_PRICE=$(curl -s -X POST https://api.stripe.com/v1/prices \
        -u "$STRIPE_SECRET_KEY:" \
        -d "product=$PRO_PRODUCT_ID" \
        -d "unit_amount=1299" \
        -d "currency=gbp" \
        -d "recurring[interval]=month")
    
    if echo "$PRO_PRICE" | grep -q '"id"'; then
        PRO_PRICE_ID=$(echo "$PRO_PRICE" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
        echo "✅ Pro Plan price created: $PRO_PRICE_ID"
    else
        echo "❌ Failed to create Pro Plan price"
        PRO_PRICE_ID=""
    fi
else
    echo "❌ Failed to create Pro Plan product"
    PRO_PRICE_ID=""
fi

# Create Agency Plan
echo "Creating Agency Plan..."
AGENCY_PRODUCT=$(curl -s -X POST https://api.stripe.com/v1/products \
    -u "$STRIPE_SECRET_KEY:" \
    -d "name=Agency Plan" \
    -d "description=10000 AI Alt Text Generations per month" \
    -d "type=service")

if echo "$AGENCY_PRODUCT" | grep -q '"id"'; then
    AGENCY_PRODUCT_ID=$(echo "$AGENCY_PRODUCT" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    echo "✅ Agency Plan product created: $AGENCY_PRODUCT_ID"
    
    AGENCY_PRICE=$(curl -s -X POST https://api.stripe.com/v1/prices \
        -u "$STRIPE_SECRET_KEY:" \
        -d "product=$AGENCY_PRODUCT_ID" \
        -d "unit_amount=4999" \
        -d "currency=gbp" \
        -d "recurring[interval]=month")
    
    if echo "$AGENCY_PRICE" | grep -q '"id"'; then
        AGENCY_PRICE_ID=$(echo "$AGENCY_PRICE" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
        echo "✅ Agency Plan price created: $AGENCY_PRICE_ID"
    else
        echo "❌ Failed to create Agency Plan price"
        AGENCY_PRICE_ID=""
    fi
else
    echo "❌ Failed to create Agency Plan product"
    AGENCY_PRICE_ID=""
fi

# Create Credits Pack
echo "Creating Credits Pack..."
CREDITS_PRODUCT=$(curl -s -X POST https://api.stripe.com/v1/products \
    -u "$STRIPE_SECRET_KEY:" \
    -d "name=AltText AI Credits" \
    -d "description=100 AI Alt Text Generations" \
    -d "type=service")

if echo "$CREDITS_PRODUCT" | grep -q '"id"'; then
    CREDITS_PRODUCT_ID=$(echo "$CREDITS_PRODUCT" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
    echo "✅ Credits Pack product created: $CREDITS_PRODUCT_ID"
    
    CREDITS_PRICE=$(curl -s -X POST https://api.stripe.com/v1/prices \
        -u "$STRIPE_SECRET_KEY:" \
        -d "product=$CREDITS_PRODUCT_ID" \
        -d "unit_amount=999" \
        -d "currency=gbp")
    
    if echo "$CREDITS_PRICE" | grep -q '"id"'; then
        CREDITS_PRICE_ID=$(echo "$CREDITS_PRICE" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
        echo "✅ Credits Pack price created: $CREDITS_PRICE_ID"
    else
        echo "❌ Failed to create Credits Pack price"
        CREDITS_PRICE_ID=""
    fi
else
    echo "❌ Failed to create Credits Pack product"
    CREDITS_PRICE_ID=""
fi

# Create webhook
echo ""
echo "🔗 Creating webhook..."
WEBHOOK=$(curl -s -X POST https://api.stripe.com/v1/webhook_endpoints \
    -u "$STRIPE_SECRET_KEY:" \
    -d "url=$BACKEND_URL/billing/webhook" \
    -d "enabled_events[]=checkout.session.completed" \
    -d "enabled_events[]=invoice.paid" \
    -d "enabled_events[]=customer.subscription.created" \
    -d "enabled_events[]=customer.subscription.updated" \
    -d "enabled_events[]=customer.subscription.deleted")

if echo "$WEBHOOK" | grep -q '"secret"'; then
    WEBHOOK_SECRET=$(echo "$WEBHOOK" | grep -o '"secret":"[^"]*"' | cut -d'"' -f4)
    echo "✅ Webhook created: $WEBHOOK_SECRET"
else
    echo "❌ Failed to create webhook"
    WEBHOOK_SECRET=""
fi

echo ""
echo "✅ Stripe setup complete!"
echo ""
echo "📋 Environment Variables for Render:"
echo "====================================="
echo ""
echo "STRIPE_SECRET_KEY=$STRIPE_SECRET_KEY"
echo "STRIPE_WEBHOOK_SECRET=$WEBHOOK_SECRET"
echo "STRIPE_PRICE_ID_PRO_MONTHLY=$PRO_PRICE_ID"
echo "STRIPE_PRICE_ID_AGENCY_MONTHLY=$AGENCY_PRICE_ID"
echo "STRIPE_PRICE_ID_CREDITS_ONE_TIME=$CREDITS_PRICE_ID"
echo ""
echo "🎯 Next Steps:"
echo "1. Copy the environment variables above"
echo "2. Go to your Render service settings"
echo "3. Add these as environment variables"
echo "4. Redeploy your service"
echo ""
echo "🧪 Test your setup:"
echo "curl $BACKEND_URL/health"
echo ""
echo "✅ Your Stripe integration is ready!"
