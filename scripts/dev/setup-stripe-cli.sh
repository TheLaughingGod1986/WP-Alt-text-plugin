#!/bin/bash

echo "🔑 Automated Stripe Setup for AltText AI"
echo "=========================================="
echo ""

# Check if Stripe CLI is installed
if ! command -v stripe &> /dev/null; then
    echo "❌ Stripe CLI not found. Installing..."
    
    # Install Stripe CLI based on OS
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        if command -v brew &> /dev/null; then
            brew install stripe/stripe-cli/stripe
        else
            echo "Please install Homebrew first: https://brew.sh"
            exit 1
        fi
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Linux
        curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
        echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
        sudo apt update
        sudo apt install stripe
    else
        echo "❌ Unsupported OS. Please install Stripe CLI manually: https://stripe.com/docs/stripe-cli"
        exit 1
    fi
fi

echo "✅ Stripe CLI found"
echo ""

# Login to Stripe
echo "🔐 Logging into Stripe..."
stripe login

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

# Create products
echo "🛍️ Creating products..."

# Pro Plan
PRO_PRODUCT=$(stripe products create \
    --name "Pro Plan" \
    --description "1000 AI Alt Text Generations per month" \
    --type service \
    --format json)

PRO_PRICE=$(stripe prices create \
    --product "$(echo $PRO_PRODUCT | jq -r '.id')" \
    --unit-amount 1299 \
    --currency gbp \
    --recurring interval=month \
    --format json)

# Agency Plan
AGENCY_PRODUCT=$(stripe products create \
    --name "Agency Plan" \
    --description "10000 AI Alt Text Generations per month" \
    --type service \
    --format json)

AGENCY_PRICE=$(stripe prices create \
    --product "$(echo $AGENCY_PRODUCT | jq -r '.id')" \
    --unit-amount 4999 \
    --currency gbp \
    --recurring interval=month \
    --format json)

# Credits Pack
CREDITS_PRODUCT=$(stripe products create \
    --name "AltText AI Credits" \
    --description "100 AI Alt Text Generations" \
    --type service \
    --format json)

CREDITS_PRICE=$(stripe prices create \
    --product "$(echo $CREDITS_PRODUCT | jq -r '.id')" \
    --unit-amount 999 \
    --currency gbp \
    --format json)

# Create webhook
echo "🔗 Creating webhook..."
WEBHOOK=$(stripe webhook_endpoints create \
    --url "$BACKEND_URL/billing/webhook" \
    --enabled-events checkout.session.completed \
    --enabled-events invoice.paid \
    --enabled-events customer.subscription.created \
    --enabled-events customer.subscription.updated \
    --enabled-events customer.subscription.deleted \
    --format json)

# Get API keys
echo "🔑 Getting API keys..."
SECRET_KEY=$(stripe config --list | grep secret_key | cut -d' ' -f2)
WEBHOOK_SECRET=$(echo $WEBHOOK | jq -r '.secret')

# Extract price IDs
PRO_PRICE_ID=$(echo $PRO_PRICE | jq -r '.id')
AGENCY_PRICE_ID=$(echo $AGENCY_PRICE | jq -r '.id')
CREDITS_PRICE_ID=$(echo $CREDITS_PRICE | jq -r '.id')

echo ""
echo "✅ Stripe setup complete!"
echo ""
echo "📋 Environment Variables for Render:"
echo "====================================="
echo ""
echo "STRIPE_SECRET_KEY=$SECRET_KEY"
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
