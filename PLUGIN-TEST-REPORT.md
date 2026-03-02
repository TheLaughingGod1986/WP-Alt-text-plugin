# BeepBeep AI Alt Text Generator – Plugin Test Report

**Date:** February 11, 2026  
**Scope:** Full manual testing + codebase review

---

## ✅ What Works Well

### Core functionality
- **Dashboard** – Loads correctly, shows usage (e.g. "4 of 50 images used"), upgrade CTAs, testimonials
- **ALT Library** – Search, filters, image grid, Regen button all work
- **Single regeneration** – Regen opens modal, calls API, returns alt text (e.g. "Laptop, books, and stationery on a wooden desk...")
- **Settings** – Page loads; Tone & Style, Additional Instructions, Auto-generate on Upload visible
- **Credit Usage** – Page loads with quota display
- **Analytics** – Page loads
- **Upgrade modal** – Opens from "Compare plans" link, shows pricing
- **Navigation** – All menu items (Dashboard, ALT Library, Analytics, Credit Usage, How to, Settings, Debug Logs) work

### Technical
- No JavaScript errors in console (only JQMIGRATE and CursorBrowser warnings)
- API integration works (generation succeeds)
- Demo content setup script works (`scripts/setup-demo-content.php`)

---

## ⚠️ Problems & Bugs

### 1. **Inconsistent free plan messaging**
- **readme.txt** (line 82): "Bulk generation tools" under Free Plan Features
- **ComparisonTable.jsx** (line 64–65): "Bulk optimisation" = `false` for free plan
- **Impact:** Users may be unsure whether bulk is available on free. Clarify and align copy.

### 2. **Regenerate alt text mismatch**
- Tested Regen on "Lakeside sunset" image
- API returned: "Laptop, books, and stationery on a wooden desk with an open magazine"
- **Impact:** Description does not match image. Could be API/model behaviour or wrong image sent. Worth checking that the correct attachment is passed to the API.

### 3. **"Session expired" notice**
- "Session expired – Please log in again" appears on plugin pages
- Likely WordPress core heartbeat, not plugin-specific
- **Recommendation:** If it appears even when logged in, consider whether heartbeat or session handling needs adjustment.

### 4. **Installation instructions mismatch**
- **readme.txt** (line 99): "Open Media -> AI Alt Text"
- Actual menu: **BeepBeep AI** → Dashboard, ALT Library, etc. (no "Media -> AI Alt Text")
- **Impact:** New users may not find the plugin. Update readme to match current menu structure.

### 5. **TODO comments in code**
- `admin/class-bbai-core.php` line 1548: `// TODO: Update this if ALT Library slug changes`
- `admin/class-bbai-core.php` line 4711: `// TODO: Re-enable when React components are properly built`
- **Recommendation:** Resolve or document these TODOs.

---

## 💡 Improvements & Recommendations

### UX / Copy
1. **First-time / logged-out state** – Make it clearer that users can try 10 generations without an account, then sign up for 50/month.
2. **Generate Missing / Re-optimise All** – When disabled, add a short tooltip explaining why (e.g. "No images need generation" or "Upgrade for bulk").
3. **ALT Library** – Consider showing a short empty state when there are no images, with a link to Media Library.

### Technical
4. **Automated tests** – `package.json` has `"test": "echo \"Error: no test specified\" && exit 1"`. Add at least:
   - PHPUnit for critical PHP paths (generation, usage, auth)
   - Jest or Playwright for key JS flows (e.g. Regen modal, bulk actions)
5. **Error handling** – When API fails or times out, ensure clear, user-friendly messages and retry options.
6. **Accessibility** – Run an a11y audit (e.g. axe-core) on Dashboard, ALT Library, Settings, and modals.

### Documentation
7. **readme.txt** – Align with current UI:
   - Update "Media -> AI Alt Text" to "BeepBeep AI -> Dashboard" (or equivalent)
   - Clarify free vs paid bulk behaviour
8. **Inline docs** – Add PHPDoc to main service classes (e.g. `Generation_Service`, `Usage_Service`) for maintainability.

### Performance
9. **ALT Library** – For large libraries, consider pagination or virtual scrolling to avoid loading hundreds of images at once.
10. **Asset loading** – Ensure admin scripts/styles are only enqueued on plugin pages to avoid impacting other admin screens.

---

## Summary

| Category        | Status |
|----------------|--------|
| Core features   | ✅ Working |
| API integration| ✅ Working |
| UI/UX          | ⚠️ Minor inconsistencies |
| Documentation  | ⚠️ Needs updates |
| Tests          | ❌ None defined |
| Security       | ✅ Nonces/caps in place (from prior audit) |

**Overall:** The plugin works well for its main use cases. The main follow-ups are aligning documentation and feature messaging, fixing the installation path in the readme, and adding basic automated tests.
