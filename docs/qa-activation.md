# Activation QA Checklist

## Scope
Validate the 1-click demo activation flow on `admin.php?page=bbai` for trial users.

## 1. Fresh Install -> Demo -> Instant Win
1. Start from a site with no connected bbai account and at least 3 images missing alt text.
2. Open `wp-admin/admin.php?page=bbai`.
3. Click `Generate alt text for 3 images (free)`.
4. Verify:
- Progress updates while request runs.
- Instant win panel appears immediately.
- Title reads `✅ Generated alt text for 3 images` (or the generated count if fewer).
- Each row shows filename and before -> after preview text.
- `Generate 7 more (free trial)` and `Unlock 50/month (free)` actions are visible.

## 2. No Images Missing Alt Text
1. Ensure all images already have alt text.
2. Open `wp-admin/admin.php?page=bbai`.
3. Click `Generate alt text for 3 images (free)`.
4. Verify:
- A friendly message appears explaining no missing images.
- The message includes an upload suggestion/link.
- No broken/spinner-stuck state remains.

## 3. Trial Exhausted -> Upgrade CTA Path
1. Exhaust trial credits (remaining = 0).
2. Reload `wp-admin/admin.php?page=bbai`.
3. Verify CTA area shows:
- Primary: `Unlock 50/month (free)`
- Secondary: `View optimized images`
4. Click primary CTA and verify it opens auth/signup modal or navigates to signup path.
5. Confirm primary CTA is never rendered disabled.

## 4. Multiple Click Protection / UI Stability
1. On a trial site with remaining credits, rapidly click `Generate alt text for 3 images (free)` multiple times.
2. Verify:
- Only one request is processed at a time.
- No duplicate result rows from overlapping requests.
- No frozen overlay or scroll lock after completion/errors.

