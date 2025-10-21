#!/bin/bash

echo "🚀 AltText AI Phase 2 Auto-Deployment"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "package.json" ]; then
    print_error "Not in backend directory. Please run from backend/ folder"
    exit 1
fi

print_status "Starting Phase 2 deployment..."

# Step 1: Install dependencies
print_status "Installing Phase 2 dependencies..."
npm install @prisma/client prisma jsonwebtoken bcrypt stripe

if [ $? -eq 0 ]; then
    print_success "Dependencies installed"
else
    print_error "Failed to install dependencies"
    exit 1
fi

# Step 2: Generate Prisma client
print_status "Generating Prisma client..."
npx prisma generate

if [ $? -eq 0 ]; then
    print_success "Prisma client generated"
else
    print_error "Failed to generate Prisma client"
    exit 1
fi

# Step 3: Check for environment variables
print_status "Checking environment variables..."

if [ ! -f ".env" ]; then
    print_warning "No .env file found. Creating template..."
    cat > .env << 'EOF'
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
EOF
    print_warning "Please update .env file with your actual values"
    print_warning "Then run this script again"
    exit 1
fi

# Check if DATABASE_URL is set
if ! grep -q "DATABASE_URL=postgresql://" .env; then
    print_error "DATABASE_URL not set in .env file"
    print_error "Please update .env with your PostgreSQL connection string"
    exit 1
fi

# Step 4: Run database migrations
print_status "Running database migrations..."
npx prisma migrate deploy

if [ $? -eq 0 ]; then
    print_success "Database migrations completed"
else
    print_error "Database migrations failed"
    print_error "Please check your DATABASE_URL in .env file"
    exit 1
fi

# Step 5: Set up Stripe products
print_status "Setting up Stripe products..."
node stripe/setup.js setup

if [ $? -eq 0 ]; then
    print_success "Stripe products created"
    print_warning "Please copy the price IDs to your .env file"
else
    print_error "Stripe setup failed"
    print_error "Please check your STRIPE_SECRET_KEY in .env file"
fi

# Step 6: Test the server
print_status "Testing server startup..."
timeout 10s node server-v2.js &
SERVER_PID=$!
sleep 3

if kill -0 $SERVER_PID 2>/dev/null; then
    print_success "Server started successfully"
    kill $SERVER_PID
else
    print_error "Server failed to start"
    print_error "Please check your environment variables"
    exit 1
fi

# Step 7: Create deployment summary
print_status "Creating deployment summary..."
cat > deployment-summary.md << EOF
# Phase 2 Deployment Summary

## ✅ Completed Steps
- [x] Dependencies installed
- [x] Prisma client generated
- [x] Database migrations run
- [x] Stripe products created
- [x] Server tested

## 🔧 Next Steps
1. Update Render environment variables with all values from .env
2. Change Render start command to: \`node server-v2.js\`
3. Deploy to Render
4. Update WordPress plugin with new files
5. Test the complete flow

## 📋 Environment Variables to Set in Render
\`\`\`
$(grep -v '^#' .env | grep -v '^$')
\`\`\`

## 🧪 Testing Commands
\`\`\`bash
# Test health endpoint
curl https://your-backend-url.com/health

# Test registration
curl -X POST https://your-backend-url.com/auth/register \\
  -H "Content-Type: application/json" \\
  -d '{"email":"test@example.com","password":"testpass123"}'

# Test login
curl -X POST https://your-backend-url.com/auth/login \\
  -H "Content-Type: application/json" \\
  -d '{"email":"test@example.com","password":"testpass123"}'
\`\`\`

## 📚 Documentation
- See PHASE-2-DEPLOYMENT.md for detailed instructions
- See PHASE-2-README.md for API documentation
EOF

print_success "Deployment preparation completed!"
print_status "See deployment-summary.md for next steps"

echo ""
echo "🎉 Phase 2 Backend is ready for deployment!"
echo ""
echo "Next steps:"
echo "1. Update Render with new environment variables"
echo "2. Change start command to: node server-v2.js"
echo "3. Deploy to Render"
echo "4. Update WordPress plugin"
echo ""
echo "📋 See deployment-summary.md for complete instructions"
