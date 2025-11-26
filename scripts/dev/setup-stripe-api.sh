#!/bin/bash

echo "🔑 Automated Stripe Setup via API"
echo "=================================="
echo ""

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "❌ jq not found. Installing..."
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install jq
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        sudo apt-get update && sudo apt-get install -y jq
    else
        echo "Please install jq manually: https://stedolan.github.io/jq/"
        exit 1
    fi
fi

echo "✅ jq found"
echo ""

# Get Stripe secret key
echo "🔐 Enter your Stripe Secret Key (starts with sk_test_ or sk_live_):"
read -p "Secret Key: " STRIPE_SECRET_KEY

if [ -z "$STRIPE_SECRET_KEY" ]; then
    echo "❌ Stripe Secret Key is required"
    exit 1
fi

# Get the backend URL
echo ""
echo "🌐 What's your backend URL? (e.g., https://alttext-ai-backend.onrender.com)"
read -p "Backend URL: " BACKEND_URL

if [ -z "$BACKEND_URL" ]; then
    echo "❌ Backend URL is required"
    exit 1
fi

echo ""
echo "📋 Setting up Stripe products and webhooks..."

# Create products using Stripe API
echo "🛍️ Creating products..."

# Pro Plan
PRO_PRODUCT=$(curl -s -X POST https://api.stripe.com/v1/products \
    -u "$STRIPE_SECRET_KEY:" \
    -d "name=Pro Plan" \
    -d "description=1000 AI Alt Text Generations per month" \
    -d "type=service")

PRO_PRODUCT_ID=$(echo $PRO_PRODUCT | jq -r '.id')

PRO_PRICE=$(curl -s -X POST https://api.stripe.com/v1/prices \
    -u "$STRIPE_SECRET_KEY:" \
    -d "product=$PRO_PRODUCT_ID" \
    -d "unit_amount=1299" \
    -d "currency=gbp" \
    -d "recurring[interval]=month")

PRO_PRICE_ID=$(echo $PRO_PRICE | jq -r '.id')

# Agency Plan
AGENCY_PRODUCT=$(curl -s -X POST https://api.stripe.com/v1/products \
    -u "$STRIPE_SECRET_KEY:" \
    -d "name=Agency Plan" \
    -d "description=10000 AI Alt Text Generations per month" \
    -d "type=service")

AGENCY_PRODUCT_ID=$(echo $AGENCY_PRODUCT | jq -r '.id')

AGENCY_PRICE=$(curl -s -X POST https://api.stripe.com/v1/prices \
    -u "$STRIPE_SECRET_KEY:" \
    -d "product=$AGENCY_PRODUCT_ID" \
    -d "unit_amount=4999" \
    -d "currency=gbp" \
    -d "recurring[interval]=month")

AGENCY_PRICE_ID=$(echo $AGENCY_PRICE | jq -r '.id')

# Credits Pack
CREDITS_PRODUCT=$(curl -s -X POST https://api.stripe.com/v1/products \
    -u "$STRIPE_SECRET_KEY:" \
    -d "name=AltText AI Credits" \
    -d "description=100 AI Alt Text Generations" \
    -d "type=service")

CREDITS_PRODUCT_ID=$(echo $CREDITS_PRODUCT | jq -r '.id')

CREDITS_PRICE=$(curl -s -X POST https://api.stripe.com/v1/prices \
    -u "$STRIPE_SECRET_KEY:" \
    -d "product=$CREDITS_PRODUCT_ID" \
    -d "unit_amount=999" \
    -d "currency=gbp")

CREDITS_PRICE_ID=$(echo $CREDITS_PRICE | jq -r '.id')

# Create webhook
echo "🔗 Creating webhook..."
WEBHOOK=$(curl -s -X POST https://api.stripe.com/v1/webhook_endpoints \
    -u "$STRIPE_SECRET_KEY:" \
    -d "url=$BACKEND_URL/billing/webhook" \
    -d "enabled_events[]=checkout.session.completed" \
    -d "enabled_events[]=invoice.paid" \
    -d "enabled_events[]=customer.subscription.created" \
    -d "enabled_events[]=customer.subscription.updated" \
    -d "enabled_events[]=customer.subscription.deleted")

WEBHOOK_SECRET=$(echo $WEBHOOK | jq -r '.secret')

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
echo "2. Add them to your Render service environment variables"
echo "3. Redeploy your service"
echo ""
echo "🧪 Test your setup:"
echo "curl $BACKEND_URL/health"
echo ""
echo "✅ Your Stripe integration is ready!"
