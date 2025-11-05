# Backend OpenAI API Key Configuration

The backend API should use the environment variable `ALTTEXT_OPENAI_API_KEY` for the OpenAI API key.

## To Update in Render Dashboard:

1. **Go to Render Dashboard**: https://dashboard.render.com
2. **Find your backend API service** (likely named `alttext-ai-backend` or similar)
3. **Click on the service** to open it
4. **Go to the "Environment" tab**
5. **Look for or add** the environment variable**:
   - **Variable Name**: `ALTTEXT_OPENAI_API_KEY`
   - **Value**: Your OpenAI API key (e.g., `sk-proj-...`)

## Important Notes:

- The variable name should be **exactly** `ALTTEXT_OPENAI_API_KEY` (case-sensitive)
- After updating, you **must restart** the backend service for changes to take effect
- To restart: Go to "Manual Deploy" → "Restart" or click the "Restart" button

## To Verify It's Working:

After updating and restarting, you can test with:

```bash
docker-compose exec -T wordpress php /var/www/html/wp-content/plugins/ai-alt-gpt/scripts/test-backend-openai-key.php
```

This will make a test generation request and verify the backend can successfully call OpenAI.

## Current Status:

The backend API is currently working (tested successfully), so it has a valid OpenAI key configured. If you need to update it to use `ALTTEXT_OPENAI_API_KEY` specifically, follow the steps above.

