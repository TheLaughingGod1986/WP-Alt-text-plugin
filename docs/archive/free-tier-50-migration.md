# Free Tier 50-Generation Migration Plan

This release raises the free allowance to 50 generations per calendar month. Existing users provisioned under the 10-generation limit will be brought forward automatically by running the Phase 2 backend reset job after deployment.

## Recommended Steps

1. **Deploy backend changes** for the Phase 1 proxy (`server.js`) and Phase 2 service (`server-v2.js`).
2. **Run token reset** once the new code is live:
   - For Phase 2, invoke the monthly reset webhook (`POST /api/webhook/reset`) or execute `resetMonthlyTokens()` via a one-off script.
   - For legacy installs, run the existing admin CLI/cron job that clears `db.json` usage counters.
3. **Verify accounts** by spot-checking a few free-plan users to confirm `tokensRemaining` is now 50.
4. **Monitor support inbox** for 24 hours to catch any edge cases.

These steps align production data with the plugin UI and changelog so all free customers benefit from the upgraded allowance immediately.
