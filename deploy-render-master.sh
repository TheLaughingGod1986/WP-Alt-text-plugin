#!/bin/bash

echo "🚀 Render Deployment Options"
echo "============================"
echo ""

echo "Choose your deployment method:"
echo "1. Render CLI (automated - requires Render CLI)"
echo "2. Manual deployment (step-by-step guide)"
echo "3. Show environment variables only"
echo ""

read -p "Enter your choice (1-3): " CHOICE

case $CHOICE in
    1)
        echo "🚀 Using Render CLI method..."
        ./deploy-to-render-cli.sh
        ;;
    2)
        echo "📋 Using manual deployment guide..."
        ./deploy-render-simple.sh
        ;;
    3)
        echo "📋 Environment Variables:"
        echo "========================"
        if [ -f "render-env-vars.txt" ]; then
            cat render-env-vars.txt
        else
            echo "❌ render-env-vars.txt not found. Run configure-stripe-keys.sh first."
        fi
        ;;
    *)
        echo "❌ Invalid choice. Please run the script again."
        exit 1
        ;;
esac

echo ""
echo "✅ Deployment process complete!"
