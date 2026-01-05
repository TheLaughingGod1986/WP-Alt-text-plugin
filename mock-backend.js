const express = require('express');
const cors = require('cors');
const app = express();
const PORT = 3001;

// Simple in-memory usage tracking
let usageCount = 4; // Starting at 4 since we already processed 4 images

// Initialize reset date to 30 days from now (persists across requests)
// This simulates a monthly billing cycle that doesn't change on every request
let resetDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000);

// Enable CORS for localhost
app.use(cors({
    origin: ['http://localhost:8080', 'http://localhost:3000'],
    credentials: true
}));

app.use(express.json());

// Mock endpoints for testing
app.post('/auth/register', (req, res) => {
    console.log('Registration attempt:', req.body);
    
    // Check if user already exists (simple mock)
    if (req.body.email === 'test@example.com') {
        return res.status(409).json({
            error: 'User already exists with this email',
            code: 'USER_EXISTS'
        });
    }
    
    res.status(201).json({
        success: true,
        token: 'mock-jwt-token-' + Date.now(),
        user: {
            id: 1,
            email: req.body.email,
            plan: 'free',
            tokensRemaining: 10,
            credits: 0
        }
    });
});

app.post('/auth/login', (req, res) => {
    console.log('Login attempt:', req.body);
    res.json({
        success: true,
        token: 'mock-jwt-token-' + Date.now(),
        user: {
            id: 1,
            email: req.body.email,
            plan: 'free',
            tokensRemaining: 10,
            credits: 0
        }
    });
});

app.get('/usage', (req, res) => {
    const limit = 10;
    const remaining = Math.max(0, limit - usageCount);
    
    res.json({
        success: true,
        usage: {
            used: usageCount,
            remaining: remaining,
            limit: limit,
            plan: 'free',
            credits: 0,
            resetDate: resetDate.toISOString(),
            nextReset: resetDate.toISOString()
        }
    });
});

app.post('/api/generate', (req, res) => {
    console.log('Alt text generation:', req.body);
    
    // Accept any Bearer token for testing (or no token in local dev)
    const authHeader = req.headers.authorization;
    if (authHeader && !authHeader.startsWith('Bearer ') && authHeader !== '') {
        return res.status(401).json({
            error: 'Access token required',
            code: 'MISSING_TOKEN'
        });
    }
    
    // Increment usage counter
    usageCount++;
    const limit = 10;
    const remaining = Math.max(0, limit - usageCount);
    
    const isRegenerate = req.body.regenerate === true;
    console.log(`✓ Alt text ${isRegenerate ? 'regenerated' : 'generated'} (Usage: ${usageCount}/${limit})`);
    
    // Generate realistic alt text based on image data
    const imageId = req.body.image_data?.image_id;
    const filename = req.body.image_data?.filename || '';
    const width = req.body.image_data?.width || 0;
    const height = req.body.image_data?.height || 0;
    
    // Mock realistic alt text based on image characteristics
    // In production, OpenAI Vision would analyze the actual image
    // For regenerate, add variation to show it's different
    let altText;
    const imageSize = width * height;
    const regenerateSuffix = isRegenerate ? ' (regenerated)' : '';
    const variation = isRegenerate ? Math.floor(Math.random() * 3) : 0;
    
    // Generate contextually appropriate mock descriptions
    if (filename.includes('tiger') || filename.includes('download-1.jpeg')) {
        const variations = [
            'Majestic tiger walking through tall grass in natural habitat',
            'Wild tiger strolling across a grassy savanna landscape',
            'Powerful tiger moving gracefully through tall vegetation'
        ];
        altText = variations[variation] || variations[0];
    } else if (filename.includes('download.jpeg') && imageId === 6) {
        const variations = [
            'Professional woman with curly hair working on laptop at desk',
            'Businesswoman with curly hair focused on laptop computer',
            'Female professional typing on laptop in office setting'
        ];
        altText = variations[variation] || variations[0];
    } else if (filename.includes('download.png') && width > 300) {
        altText = 'Red square logo with white text displaying "tes"';
    } else if (imageSize > 500000) {
        const variations = [
            'Screenshot of WordPress dashboard showing plugin interface',
            'WordPress admin panel displaying plugin management screen',
            'Dashboard view of WordPress plugin configuration'
        ];
        altText = variations[variation] || variations[0];
    } else if (filename.includes('image')) {
        const variations = [
            'Web analytics dashboard displaying user engagement metrics',
            'Data visualization showing website traffic and user statistics',
            'Analytics interface with charts and performance indicators'
        ];
        altText = variations[variation] || variations[0];
    } else {
        const variations = [
            'Stock photo showing business or technology concept',
            'Professional image depicting modern business environment',
            'High-quality photograph representing corporate or tech theme'
        ];
        altText = variations[variation] || variations[0];
    }
    
    // Generate a quality score based on alt text complexity
    const wordCount = altText.split(' ').length;
    let score;
    if (wordCount >= 8 && wordCount <= 16) {
        score = Math.floor(Math.random() * 10) + 90; // 90-100
    } else if (wordCount >= 6) {
        score = Math.floor(Math.random() * 15) + 75; // 75-90
    } else {
        score = Math.floor(Math.random() * 15) + 60; // 60-75
    }
    const grades = {
        90: { grade: 'A+', summary: 'Excellent accessibility and SEO value' },
        85: { grade: 'A', summary: 'Very good, clear and descriptive' },
        80: { grade: 'B+', summary: 'Good quality, minor improvements possible' },
        75: { grade: 'B', summary: 'Acceptable but could be more descriptive' }
    };
    
    const gradeKey = score >= 90 ? 90 : score >= 85 ? 85 : score >= 80 ? 80 : 75;
    const gradeInfo = grades[gradeKey];
    
    res.json({
        success: true,
        alt_text: altText,
        review: {
            score: score,
            grade: gradeInfo.grade,
            status: score >= 85 ? 'excellent' : score >= 75 ? 'good' : 'needs_improvement',
            summary: gradeInfo.summary,
            issues: score < 85 ? ['Could be more specific', 'Add more context'] : [],
            model: 'gpt-4o-mini'
        },
        usage: {
            used: usageCount,
            remaining: remaining,
            limit: limit,
            plan: 'free',
            credits: 0,
            resetDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
        },
        tokens: {
            prompt_tokens: 150,
            completion_tokens: 25,
            total_tokens: 175
        }
    });
});

// Review alt text quality endpoint
app.post('/api/review', (req, res) => {
    console.log('Alt text review request:', req.body);
    
    const altText = req.body.alt_text || '';
    const wordCount = altText.split(' ').filter(w => w.length > 0).length;
    
    // Generate quality score based on alt text characteristics
    let score;
    let grade;
    let summary;
    let issues = [];
    
    if (wordCount >= 8 && wordCount <= 16) {
        score = Math.floor(Math.random() * 10) + 90; // 90-100
        grade = 'A+';
        summary = 'Excellent accessibility and SEO value';
    } else if (wordCount >= 6 && wordCount < 8) {
        score = Math.floor(Math.random() * 10) + 80; // 80-90
        grade = 'A';
        summary = 'Very good, clear and descriptive';
        issues.push('Could be slightly more descriptive');
    } else if (wordCount < 6) {
        score = Math.floor(Math.random() * 15) + 65; // 65-80
        grade = 'B';
        summary = 'Acceptable but could be more descriptive';
        issues.push('Too brief, add more detail');
    } else {
        score = Math.floor(Math.random() * 15) + 70; // 70-85
        grade = 'B+';
        summary = 'Good quality, minor improvements possible';
        issues.push('Slightly verbose, consider condensing');
    }
    
    res.json({
        success: true,
        review: {
            score: score,
            grade: grade,
            status: score >= 85 ? 'excellent' : score >= 75 ? 'good' : 'needs_improvement',
            summary: summary,
            issues: issues,
            model: 'gpt-4o-mini',
            usage: {
                prompt_tokens: 100,
                completion_tokens: 20,
                total_tokens: 120
            }
        }
    });
});

app.get('/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Mock Stripe checkout endpoint
// In production, this would create a real Stripe checkout session
app.post('/api/billing/create-checkout', (req, res) => {
    console.log('Stripe checkout request:', req.body);
    
    const { priceId, successUrl, cancelUrl } = req.body;
    
    if (!priceId) {
        return res.status(400).json({
            success: false,
            error: 'Price ID is required'
        });
    }
    
    // In production, you would call Stripe API here:
    // const session = await stripe.checkout.sessions.create({...})
    
    // For mock, return a simulated Stripe checkout URL
    // In reality, this would be: session.url
    const mockStripeUrl = `https://checkout.stripe.com/c/pay/mock_${priceId}_${Date.now()}#fidkdWxOYHwnPyd1blpxYHZxWjA0T2pwS21MSm1EYUhoM3JAS2NXb2x9cHxJdzJfbklcME4wT0REa2pIVG1ybWQyPGhSZXFVQG9kbjd8NnVDQnxwYktzdUxANz1kNWl8Y2x0VDE8M09VY2F1TH1VMDQyPWQ9d2dicScpJ2hsYXdgaHdgYHdoYGwoJ2pkYGxga2Bxamp2YGBnJ3gl`;
    
    console.log('✓ Mock Stripe checkout URL generated for:', priceId);
    console.log('  Success URL:', successUrl);
    console.log('  Cancel URL:', cancelUrl);
    console.log('  >>> In production, this would redirect to Stripe Checkout');
    console.log('  >>> For now, redirecting to success URL to simulate successful payment');
    
    res.json({
        success: true,
        data: {
            url: successUrl + '&session_id=mock_session_' + Date.now(), // Simulate success
            sessionId: 'mock_session_' + Date.now()
        }
    });
});

// Alternative endpoint for v2 API compatibility
app.post('/billing/checkout', (req, res) => {
    console.log('Stripe checkout request (v2):', req.body);
    
    const { priceId, successUrl, cancelUrl } = req.body;
    
    if (!priceId) {
        return res.status(400).json({
            success: false,
            error: 'Price ID is required'
        });
    }
    
    console.log('✓ Mock Stripe checkout URL generated for:', priceId);
    console.log('  >>> Simulating successful payment');
    
    res.json({
        success: true,
        data: {
            url: successUrl + '&session_id=mock_session_' + Date.now(),
            sessionId: 'mock_session_' + Date.now()
        }
    });
});

// Get billing plans endpoint
app.get('/billing/plans', (req, res) => {
    console.log('Billing plans request');
    
    // Return mock pricing plans
    const plans = [
        {
            id: 'free',
            name: 'Free',
            description: 'Perfect for getting started',
            price: 0,
            price_id: null,
            interval: null,
            features: [
                '50 AI alt texts per month',
                'Basic support',
                'Manual processing'
            ],
            limits: {
                credits: 50,
                sites: 1
            }
        },
        {
            id: 'pro',
            name: 'Pro',
            description: 'For professional websites',
            price: 9.99,
            price_id: 'price_mock_pro_monthly',
            interval: 'month',
            features: [
                '1,000 AI alt texts per month',
                'Priority queue',
                'Bulk processing',
                'Email support'
            ],
            limits: {
                credits: 1000,
                sites: 5
            }
        },
        {
            id: 'agency',
            name: 'Agency',
            description: 'For agencies and teams',
            price: 49.99,
            price_id: 'price_mock_agency_monthly',
            interval: 'month',
            features: [
                '10,000 AI alt texts per month',
                'Priority queue',
                'Bulk processing',
                'Multiple sites',
                'Priority support',
                'API access'
            ],
            limits: {
                credits: 10000,
                sites: 20
            }
        }
    ];
    
    console.log('✓ Billing plans returned:', plans.length, 'plans');
    
    res.json({
        success: true,
        data: {
            plans: plans
        }
    });
});

// Reset usage counter and reset date (for testing)
app.post('/reset-usage', (req, res) => {
    usageCount = 0;
    resetDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000); // New billing cycle starts now
    console.log('Usage counter reset to 0, new reset date:', resetDate.toISOString());
    res.json({ 
        success: true, 
        message: 'Usage counter reset', 
        used: usageCount,
        resetDate: resetDate.toISOString()
    });
});

app.listen(PORT, () => {
    console.log(`Mock backend running on http://localhost:${PORT}`);
    console.log('Endpoints available:');
    console.log('- POST /auth/register');
    console.log('- POST /auth/login');
    console.log('- GET /usage');
    console.log('- POST /api/generate');
    console.log('- POST /api/review');
    console.log('- POST /api/billing/create-checkout (mock Stripe)');
    console.log('- POST /billing/checkout (mock Stripe v2)');
    console.log('- GET /billing/plans (pricing plans)');
    console.log('- GET /health');
    console.log('- POST /reset-usage (reset counter)');
    console.log(`\nCurrent usage: ${usageCount}/10`);
    console.log('\n💳 Stripe Integration: Mock mode (simulates successful payments)');
});
