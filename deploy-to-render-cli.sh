#!/bin/bash

echo "🚀 Deploy Environment Variables to Render via CLI"
echo "================================================="
echo ""

# Check if Render CLI is installed
if ! command -v render &> /dev/null; then
    echo "❌ Render CLI not found. Installing..."
    
    # Install Render CLI based on OS
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        if command -v brew &> /dev/null; then
            brew install render
        else
            echo "Please install Homebrew first: https://brew.sh"
            echo "Or install Render CLI manually: https://render.com/docs/cli"
            exit 1
        fi
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Linux
        curl -s https://cli.render.com/install.sh | bash
    else
        echo "Please install Render CLI manually: https://render.com/docs/cli"
        exit 1
    fi
fi

echo "✅ Render CLI found"
echo ""

# Login to Render
echo "🔐 Logging into Render..."
render auth login

# Get service information
echo ""
echo "📋 Getting your services..."
SERVICES=$(render services list --format json)

if [ -z "$SERVICES" ]; then
    echo "❌ No services found. Please check your Render account."
    exit 1
fi

echo "Available services:"
echo "$SERVICES" | jq -r '.[] | "\(.id) - \(.name) (\(.type))"'

echo ""
echo "🔍 Looking for 'alttext-ai-backend' service..."

# Find the backend service
BACKEND_SERVICE=$(echo "$SERVICES" | jq -r '.[] | select(.name | contains("alttext-ai-backend") or contains("backend")) | .id' | head -1)

if [ -z "$BACKEND_SERVICE" ]; then
    echo "❌ Backend service not found. Please enter the service ID manually:"
    read -p "Service ID: " BACKEND_SERVICE
fi

if [ -z "$BACKEND_SERVICE" ]; then
    echo "❌ Service ID is required"
    exit 1
fi

echo "✅ Found service: $BACKEND_SERVICE"
echo ""

# Set environment variables
echo "🔧 Setting environment variables..."

# Read the environment variables from the file
if [ -f "render-env-vars.txt" ]; then
    echo "📝 Reading environment variables from render-env-vars.txt..."
    
    # Extract variables and set them
    while IFS='=' read -r key value; do
        if [[ $key == STRIPE_* ]]; then
            echo "Setting $key..."
            render env set "$key" "$value" --service "$BACKEND_SERVICE"
        fi
    done < <(grep "^STRIPE_" render-env-vars.txt)
    
    echo "✅ Environment variables set successfully!"
else
    echo "❌ render-env-vars.txt not found. Please run configure-stripe-keys.sh first."
    exit 1
fi

# Update build and start commands
echo ""
echo "🔧 Updating build and start commands..."

# Update build command
echo "Setting build command..."
render service update "$BACKEND_SERVICE" --build-command "cd backend && npm install && npx prisma generate && npx prisma db push"

# Update start command  
echo "Setting start command..."
render service update "$BACKEND_SERVICE" --start-command "cd backend && node server-v2.js"

echo "✅ Build and start commands updated!"

# Trigger deployment
echo ""
echo "🚀 Triggering deployment..."
render service deploy "$BACKEND_SERVICE"

echo ""
echo "🎉 Deployment initiated!"
echo ""
echo "📋 Summary:"
echo "- ✅ Environment variables set"
echo "- ✅ Build command updated"
echo "- ✅ Start command updated" 
echo "- ✅ Deployment triggered"
echo ""
echo "🧪 Test your deployment:"
echo "Health: https://your-service-url.onrender.com/health"
echo ""
echo "⏳ Deployment will take 5-10 minutes to complete."
echo "Check the Render dashboard for deployment status."
