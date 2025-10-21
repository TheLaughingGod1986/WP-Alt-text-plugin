const express = require('express');
const cors = require('cors');
const app = express();
const PORT = 3001;

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
    if (req.body.email === 'test@example.com' || req.body.email === 'benoats@gmail.com') {
        return res.status(200).json({
            success: false,
            error: 'User already exists with this email',
            code: 'USER_EXISTS'
        });
    }
    
    res.json({
        success: true,
        token: 'mock-jwt-token-' + Date.now(),
        user: {
            id: 1,
            email: req.body.email,
            plan: 'free'
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
            plan: 'free'
        }
    });
});

app.get('/usage', (req, res) => {
    res.json({
        used: 5,
        remaining: 5,
        limit: 10,
        plan: 'free',
        credits: 0,
        seconds_until_reset: 864000 // 10 days
    });
});

app.post('/api/generate', (req, res) => {
    console.log('Alt text generation:', req.body);
    res.json({
        success: true,
        alt_text: 'Mock alt text for testing purposes',
        usage: {
            used: 6,
            remaining: 4,
            limit: 10
        }
    });
});

app.get('/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

app.listen(PORT, () => {
    console.log(`Mock backend running on http://localhost:${PORT}`);
    console.log('Endpoints available:');
    console.log('- POST /auth/register');
    console.log('- POST /auth/login');
    console.log('- GET /usage');
    console.log('- POST /api/generate');
    console.log('- GET /health');
});
