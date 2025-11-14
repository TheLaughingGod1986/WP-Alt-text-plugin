# Backend API Key Configuration Fix

## Issue
The backend is returning errors: **"Incorrect API key provided: sk-proj-************************************************"**

This means the backend's OpenAI API key is either:
1. Missing from environment variables
2. Invalid or expired
3. Not properly configured

## Solution

### Step 1: Check Backend Environment Variables

1. Go to **Render Dashboard**: https://dashboard.render.com
2. Find your backend service (likely `alttext-ai-backend`)
3. Click on the service
4. Go to **"Environment"** tab
5. Look for one of these environment variables:
   - `ALTTEXT_OPENAI_API_KEY`
   - `OPENAI_API_KEY`
   - `OPENAI_KEY`

### Step 2: Update or Add the API Key

If the key is missing or incorrect:

1. **Get a valid OpenAI API key** from: https://platform.openai.com/api-keys
2. **Add or update** the environment variable:
   - **Variable Name**: `ALTTEXT_OPENAI_API_KEY` (or `OPENAI_API_KEY` if that's what the backend expects)
   - **Value**: Your OpenAI API key (starts with `sk-proj-...` or `sk-...`)
3. **Save** the changes

### Step 3: Restart the Backend Service

After updating the environment variable:

1. Go to **"Manual Deploy"** → **"Restart"**
2. Wait for the service to restart (~1-2 minutes)
3. Or click the **"Restart"** button

### Step 4: Verify It's Working

1. Go to WordPress admin → **Media Library** → **AI Alt Text**
2. Try generating alt text for an image
3. The error should be gone if the API key is correct

## Important Notes

- **The frontend plugin does NOT send OpenAI API keys** - it only sends authentication tokens (JWT) or license keys
- **The backend is responsible for** calling OpenAI with its own API key
- **This is a backend configuration issue**, not a frontend plugin issue
- **The API key must be valid** and have credits/quota available

## Troubleshooting

If the error persists after updating the API key:

1. **Check the API key is valid**:
   - Go to https://platform.openai.com/api-keys
   - Verify the key is active and has credits
   
2. **Check the environment variable name**:
   - The backend might expect a different variable name
   - Check the backend code or documentation for the exact variable name
   
3. **Check the backend logs**:
   - Go to Render Dashboard → Your service → "Logs"
   - Look for error messages about the API key
   
4. **Verify the backend restarted**:
   - The environment variable change only takes effect after restart
   - Make sure the service fully restarted

## Current Status

The frontend plugin is correctly configured and sending requests to the backend. The issue is on the backend side where the OpenAI API key needs to be configured or updated.

