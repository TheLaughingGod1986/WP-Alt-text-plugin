#!/bin/bash

echo "🔑 Automated Stripe Setup for AltText AI"
echo "=========================================="
echo ""

echo "Choose your setup method:"
echo "1. Stripe CLI (recommended - fastest)"
echo "2. Stripe API (no CLI required)"
echo "3. Manual setup (original method)"
echo ""

read -p "Enter your choice (1-3): " CHOICE

case $CHOICE in
    1)
        echo "🚀 Using Stripe CLI method..."
        ./setup-stripe-cli.sh
        ;;
    2)
        echo "🚀 Using Stripe API method..."
        ./setup-stripe-api.sh
        ;;
    3)
        echo "📋 Manual setup guide..."
        ./setup-stripe.sh
        ;;
    *)
        echo "❌ Invalid choice. Please run the script again."
        exit 1
        ;;
esac

echo ""
echo "✅ Setup complete! Follow the instructions above to add the environment variables to Render."
