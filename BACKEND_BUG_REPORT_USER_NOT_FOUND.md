# 🐛 Backend Bug Report: "User not found" Error on Generate Endpoint

## Issue Summary
The `/api/generate` endpoint is returning **HTTP 500 (Internal Server Error)** with "User not found" message when it should return **HTTP 401 (Unauthorized)** or **HTTP 403 (Forbidden)**.

## What's Happening
1. WordPress plugin sends authenticated request to `/api/generate` with valid JWT token in `Authorization: Bearer {token}` header
2. Backend processes the request but cannot find the user in the database
3. Backend returns **500 status** with error: `{"error":"Failed to generate alt text","code":"GENERATION_ERROR","message":"User not found"}`
4. WordPress plugin retries the request 3 times, causing multiple error messages

## Expected Behavior
1. When a JWT token references a non-existent user, the backend should:
   - Return **HTTP 401 (Unauthorized)** for authentication errors
   - Return **HTTP 403 (Forbidden)** if the token is valid but the user doesn't have permission
   - **NOT** return HTTP 500 (Internal Server Error) - this indicates a server problem, not an auth problem

2. The error response should follow this format:
   ```json
   {
     "error": "User not found",
     "code": "USER_NOT_FOUND",
     "status": 401
   }
   ```

## Why This Matters
- **HTTP 500** indicates a server error and triggers retry logic in the frontend
- **HTTP 401/403** tells the frontend this is an authentication issue that should prompt re-login
- Proper status codes prevent unnecessary retries and provide better UX

## When This Happens
- User was deleted from the database but JWT token still exists
- Token was signed for a user ID that no longer exists in the database
- Database sync issue between user table and token validation

## Recommended Fix
1. **Validate user exists in auth middleware** before processing the request
2. **Return HTTP 401** if the user from the JWT token doesn't exist in the database
3. **Handle this as an authentication error**, not an internal server error

## Current Frontend Workaround
The WordPress plugin now:
- Detects "User not found" errors even when returned as 500
- Clears invalid tokens automatically
- Prompts user to log in again
- Validates tokens proactively before generate requests

However, this is a **workaround** - the backend should return proper HTTP status codes.

## API Endpoint Affected
- **Endpoint**: `POST /api/generate`
- **Headers Required**: `Authorization: Bearer {jwt_token}`
- **Current Response**: `500 {"error":"Failed to generate alt text","code":"GENERATION_ERROR","message":"User not found"}`
- **Expected Response**: `401 {"error":"User not found","code":"USER_NOT_FOUND"}`

## Test Case
1. Create a JWT token for a user
2. Delete that user from the database
3. Send a request to `/api/generate` with that token
4. **Current**: Returns 500 with "User not found"
5. **Expected**: Returns 401 with "User not found"

---

**Priority**: Medium (functionality works, but error handling is incorrect)
**Severity**: Low (frontend has workaround, but causes unnecessary retries)
**Impact**: Multiple error messages shown to user instead of clean auth prompt
