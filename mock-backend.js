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

// Increase body size limit to handle large base64-encoded images (50MB)
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));

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
    console.log('Alt text generation request received');
    console.log('Request body keys:', Object.keys(req.body));
    console.log('Regenerate flag:', req.body.regenerate, '(type:', typeof req.body.regenerate, ')');
    console.log('Regenerate === true?', req.body.regenerate === true);
    console.log('Regenerate == true?', req.body.regenerate == true);
    console.log('Full request body (truncated):', JSON.stringify(req.body).substring(0, 500));
    
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
    
    // More robust regenerate check - handle both boolean true and string "true"
    const isRegenerate = req.body.regenerate === true || req.body.regenerate === 'true' || req.body.regenerate === 1;
    console.log(`✓ Alt text ${isRegenerate ? 'regenerated' : 'generated'} (Usage: ${usageCount}/${limit})`);
    console.log(`  Regenerate flag final value: ${isRegenerate}`);
    
    // Extract image data and context
    const imageId = req.body.image_data?.image_id || req.body.image_id;
    const filename = req.body.image_data?.filename || '';
    const title = req.body.image_data?.title || req.body.context?.title || '';
    const caption = req.body.image_data?.caption || req.body.context?.caption || '';
    const postTitle = req.body.context?.post_title || '';
    const width = req.body.image_data?.width || 0;
    const height = req.body.image_data?.height || 0;
    
    console.log('Image context:', { imageId, filename, title, caption, postTitle, width, height });
    
    // Helper function to check if a string is generic/non-descriptive
    function isGenericName(str) {
        if (!str || str.trim().length === 0) return true;
        const lower = str.toLowerCase().trim();
        const genericPatterns = [
            'untitled', 'image', 'img', 'photo', 'picture', 'pic',
            'download', 'dsc', 'test', 'sample', 'example', 'temp',
            'new', 'copy', 'file', 'untitled', 'no title', 'no-title'
        ];
        // Check if it's just numbers
        if (/^\d+$/.test(lower)) return true;
        // Check if it matches generic patterns
        return genericPatterns.some(pattern => lower.includes(pattern)) || 
               lower.match(/^(img|image|photo|pic|test|sample|download)[-_]?\d*$/i);
    }
    
    // Generate intelligent alt text based on available context
    // Priority: caption > title > post title > intelligent image-based description
    let altText;
    const variation = isRegenerate ? Math.floor(Math.random() * 5) : Math.floor(Math.random() * 5);
    
    // Use caption if available (most descriptive)
    if (caption && caption.trim().length > 0 && !isGenericName(caption)) {
        altText = caption.trim();
        // If caption is very short, enhance it
        if (altText.length < 20) {
            const enhancement = title && !isGenericName(title) ? title : 
                               postTitle ? postTitle : 'visual content';
            altText = `${altText} showing ${enhancement}`;
        }
    }
    // Use title if available and no caption
    else if (title && title.trim().length > 0 && !isGenericName(title)) {
        altText = title.trim();
    }
    // Use post title context if available
    else if (postTitle && postTitle.trim().length > 0) {
        altText = `Image related to ${postTitle.trim()}`;
    }
    // Generate intelligent descriptions based on image characteristics
    else {
        const imageSize = width * height;
        const aspectRatio = width > 0 && height > 0 ? (width / height) : 1;
        const isWide = aspectRatio > 1.5;
        const isTall = aspectRatio < 0.67;
        const isSquare = aspectRatio >= 0.9 && aspectRatio <= 1.1;
        const isLarge = imageSize > 1000000;
        const isMedium = imageSize > 200000 && imageSize <= 1000000;
        const isSmall = imageSize > 0 && imageSize <= 200000;
        
        // Generate more specific, realistic descriptions based on dimensions
        // Use image ID to add some variation so same image gets different descriptions
        const seed = parseInt(imageId) || 0;
        const seedVariation = (seed + variation) % 10;
        
        if (isLarge && isWide) {
            const variations = [
                'Panoramic landscape photograph showing expansive natural scenery with mountains and sky',
                'Wide-angle photograph of outdoor scene displaying horizon and landscape features',
                'Horizontal landscape image capturing scenic view with natural elements',
                'Wide format photograph showing outdoor environment with terrain and sky',
                'Panoramic view photograph displaying natural landscape and geographical features'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isLarge && isTall) {
            const variations = [
                'Portrait photograph of person or subject with detailed background elements',
                'Vertical photograph showing person or object with surrounding environment',
                'Tall portrait image displaying subject with contextual background details',
                'Upright photograph capturing person or subject in detailed setting',
                'Portrait format image showing subject with environmental context'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isLarge && isSquare) {
            const variations = [
                'Group of figures arranged in scene with narrative elements and dialogue',
                'Composition showing multiple subjects with speech bubbles and characters',
                'Scene with figures and characters displaying interaction and story elements',
                'Arrangement of subjects with narrative components and visual storytelling',
                'Group scene with figures, characters, and dialogue elements'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isWide) {
            const variations = [
                'Wide landscape photograph showing horizontal scene with natural or urban elements',
                'Horizontal image displaying extended view of scene or environment',
                'Banner-style photograph showing wide format composition',
                'Panoramic image capturing horizontal scene with visual elements',
                'Wide format photograph displaying extended landscape or scene'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isTall) {
            const variations = [
                'Portrait photograph showing vertical composition with subject and background',
                'Tall image displaying upright scene with person or object',
                'Vertical photograph capturing portrait orientation with visual elements',
                'Upright image showing portrait format with subject and context',
                'Portrait format photograph displaying vertical composition'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isSquare) {
            const variations = [
                'Square photograph showing balanced composition with centered subject',
                'Square format image displaying balanced scene with visual elements',
                'Square photograph capturing centered composition with details',
                'Balanced square image showing composed scene with elements',
                'Square format photograph with balanced visual composition'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isMedium) {
            const variations = [
                'Photograph displaying composed scene with visual elements and details',
                'Image showing detailed composition with subjects and background',
                'Medium-sized photograph capturing scene with visual information',
                'Photograph displaying visual content with composed elements',
                'Image showing detailed scene with various visual components'
            ];
            altText = variations[seedVariation % variations.length];
        } else if (isSmall) {
            const variations = [
                'Small photograph showing visual content with basic composition',
                'Compact image displaying visual scene with elements',
                'Small format photograph capturing visual information',
                'Image showing visual content in compact format',
                'Small photograph displaying visual scene'
            ];
            altText = variations[seedVariation % variations.length];
        } else {
            // Unknown size - generate generic but varied descriptions
            const variations = [
                'Photograph displaying visual content and composition',
                'Image showing visual scene with elements and details',
                'Photograph capturing visual information and content',
                'Image displaying composed scene with visual elements',
                'Photograph showing visual content with details'
            ];
            altText = variations[seedVariation % variations.length];
        }
    }
    
    // Clean up the alt text
    altText = altText.trim();
    
    // Ensure it's not too short or too long (ideal: 8-16 words)
    let wordCount = altText.split(/\s+/).length;
    if (wordCount < 5) {
        // Add more descriptive words
        altText = `${altText} with visual details and composition`;
        wordCount = altText.split(/\s+/).length; // Recalculate after enhancement
    } else if (wordCount > 20) {
        // Truncate if too long
        const words = altText.split(/\s+/);
        altText = words.slice(0, 18).join(' ');
        wordCount = 18; // Update after truncation
    }
    
    // Generate a quality score based on alt text complexity
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
    console.log('\n⚠️  NOTE: Mock backend generates generic alt text based on metadata.');
    console.log('   For accurate image analysis, use production backend with OpenAI Vision API.');
});
