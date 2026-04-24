# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: dashboard-state-truth.spec.ts >> Dashboard truth-driven UI >> review polling reaches ALL_CLEAR and then stops polling
- Location: tests/e2e/dashboard-state-truth.spec.ts:536:7

# Error details

```
Test timeout of 90000ms exceeded.
```

```
Error: page.waitForLoadState: Test timeout of 90000ms exceeded.
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - generic [ref=e2]:
    - navigation "Main menu":
      - link "Skip to main content" [ref=e3] [cursor=pointer]:
        - /url: "#wpbody-content"
      - link "Skip to toolbar" [ref=e4] [cursor=pointer]:
        - /url: "#wp-toolbar"
      - list [ref=e7]:
        - listitem [ref=e8]:
          - link "Dashboard" [ref=e9] [cursor=pointer]:
            - /url: index.php
            - generic [ref=e10]: 
            - generic [ref=e11]: Dashboard
          - list [ref=e12]:
            - listitem [ref=e13]:
              - link "Home" [ref=e14] [cursor=pointer]:
                - /url: index.php
            - listitem [ref=e15]:
              - link "Updates" [ref=e16] [cursor=pointer]:
                - /url: update-core.php
        - listitem [ref=e17]
        - listitem [ref=e19]:
          - link "Posts" [ref=e20] [cursor=pointer]:
            - /url: edit.php
            - generic [ref=e21]: 
            - generic [ref=e22]: Posts
          - list [ref=e23]:
            - listitem [ref=e24]:
              - link "All Posts" [ref=e25] [cursor=pointer]:
                - /url: edit.php
            - listitem [ref=e26]:
              - link "Add Post" [ref=e27] [cursor=pointer]:
                - /url: post-new.php
            - listitem [ref=e28]:
              - link "Categories" [ref=e29] [cursor=pointer]:
                - /url: edit-tags.php?taxonomy=category
            - listitem [ref=e30]:
              - link "Tags" [ref=e31] [cursor=pointer]:
                - /url: edit-tags.php?taxonomy=post_tag
        - listitem [ref=e32]:
          - link "Media" [ref=e33] [cursor=pointer]:
            - /url: upload.php
            - generic [ref=e34]: 
            - generic [ref=e35]: Media
          - list [ref=e36]:
            - listitem [ref=e37]:
              - link "Library" [ref=e38] [cursor=pointer]:
                - /url: upload.php
            - listitem [ref=e39]:
              - link "Add Media File" [ref=e40] [cursor=pointer]:
                - /url: media-new.php
        - listitem [ref=e41]:
          - link "Pages" [ref=e42] [cursor=pointer]:
            - /url: edit.php?post_type=page
            - generic [ref=e43]: 
            - generic [ref=e44]: Pages
          - list [ref=e45]:
            - listitem [ref=e46]:
              - link "All Pages" [ref=e47] [cursor=pointer]:
                - /url: edit.php?post_type=page
            - listitem [ref=e48]:
              - link "Add Page" [ref=e49] [cursor=pointer]:
                - /url: post-new.php?post_type=page
        - listitem [ref=e50]:
          - link "Comments" [ref=e51] [cursor=pointer]:
            - /url: edit-comments.php
            - generic [ref=e52]: 
            - generic [ref=e53]: Comments
        - listitem [ref=e54]:
          - link "BeepBeep AI" [ref=e55] [cursor=pointer]:
            - /url: admin.php?page=bbai
            - generic [ref=e56]: 
            - generic [ref=e57]: BeepBeep AI
          - list [ref=e58]:
            - listitem [ref=e59]:
              - link "Dashboard" [ref=e60] [cursor=pointer]:
                - /url: admin.php?page=bbai
            - listitem [ref=e61]:
              - link "ALT Library" [ref=e62] [cursor=pointer]:
                - /url: admin.php?page=bbai-library
            - listitem [ref=e63]:
              - link "Analytics" [ref=e64] [cursor=pointer]:
                - /url: admin.php?page=bbai-analytics
            - listitem [ref=e65]:
              - link "Usage" [ref=e66] [cursor=pointer]:
                - /url: admin.php?page=bbai-credit-usage
            - listitem [ref=e67]:
              - link "Settings" [ref=e68] [cursor=pointer]:
                - /url: admin.php?page=bbai-settings
        - listitem [ref=e69]
        - listitem [ref=e71]:
          - link "Appearance" [ref=e72] [cursor=pointer]:
            - /url: themes.php
            - generic [ref=e73]: 
            - generic [ref=e74]: Appearance
          - list [ref=e75]:
            - listitem [ref=e76]:
              - link "Themes" [ref=e77] [cursor=pointer]:
                - /url: themes.php
            - listitem [ref=e78]:
              - link "Editor" [ref=e79] [cursor=pointer]:
                - /url: site-editor.php
        - listitem [ref=e80]:
          - link "Plugins" [ref=e81] [cursor=pointer]:
            - /url: plugins.php
            - generic [ref=e82]: 
            - generic [ref=e83]: Plugins
          - list [ref=e84]:
            - listitem [ref=e85]:
              - link "Installed Plugins" [ref=e86] [cursor=pointer]:
                - /url: plugins.php
            - listitem [ref=e87]:
              - link "Add Plugin" [ref=e88] [cursor=pointer]:
                - /url: plugin-install.php
        - listitem [ref=e89]:
          - link "Users" [ref=e90] [cursor=pointer]:
            - /url: users.php
            - generic [ref=e91]: 
            - generic [ref=e92]: Users
          - list [ref=e93]:
            - listitem [ref=e94]:
              - link "All Users" [ref=e95] [cursor=pointer]:
                - /url: users.php
            - listitem [ref=e96]:
              - link "Add User" [ref=e97] [cursor=pointer]:
                - /url: user-new.php
            - listitem [ref=e98]:
              - link "Profile" [ref=e99] [cursor=pointer]:
                - /url: profile.php
        - listitem [ref=e100]:
          - link "Tools" [ref=e101] [cursor=pointer]:
            - /url: tools.php
            - generic [ref=e102]: 
            - generic [ref=e103]: Tools
          - list [ref=e104]:
            - listitem [ref=e105]:
              - link "Available Tools" [ref=e106] [cursor=pointer]:
                - /url: tools.php
            - listitem [ref=e107]:
              - link "Import" [ref=e108] [cursor=pointer]:
                - /url: import.php
            - listitem [ref=e109]:
              - link "Export" [ref=e110] [cursor=pointer]:
                - /url: export.php
            - listitem [ref=e111]:
              - link "Site Health" [ref=e112] [cursor=pointer]:
                - /url: site-health.php
            - listitem [ref=e113]:
              - link "Export Personal Data" [ref=e114] [cursor=pointer]:
                - /url: export-personal-data.php
            - listitem [ref=e115]:
              - link "Erase Personal Data" [ref=e116] [cursor=pointer]:
                - /url: erase-personal-data.php
            - listitem [ref=e117]:
              - link "Plugin Check" [ref=e118] [cursor=pointer]:
                - /url: tools.php?page=plugin-check
            - listitem [ref=e119]:
              - link "Plugin Check Namer" [ref=e120] [cursor=pointer]:
                - /url: tools.php?page=plugin-check-namer
            - listitem [ref=e121]:
              - link "Theme File Editor" [ref=e122] [cursor=pointer]:
                - /url: theme-editor.php
            - listitem [ref=e123]:
              - link "Plugin File Editor" [ref=e124] [cursor=pointer]:
                - /url: plugin-editor.php
        - listitem [ref=e125]:
          - link "Settings" [ref=e126] [cursor=pointer]:
            - /url: options-general.php
            - generic [ref=e127]: 
            - generic [ref=e128]: Settings
          - list [ref=e129]:
            - listitem [ref=e130]:
              - link "General" [ref=e131] [cursor=pointer]:
                - /url: options-general.php
            - listitem [ref=e132]:
              - link "Writing" [ref=e133] [cursor=pointer]:
                - /url: options-writing.php
            - listitem [ref=e134]:
              - link "Reading" [ref=e135] [cursor=pointer]:
                - /url: options-reading.php
            - listitem [ref=e136]:
              - link "Discussion" [ref=e137] [cursor=pointer]:
                - /url: options-discussion.php
            - listitem [ref=e138]:
              - link "Media" [ref=e139] [cursor=pointer]:
                - /url: options-media.php
            - listitem [ref=e140]:
              - link "Permalinks" [ref=e141] [cursor=pointer]:
                - /url: options-permalink.php
            - listitem [ref=e142]:
              - link "Privacy" [ref=e143] [cursor=pointer]:
                - /url: options-privacy.php
        - listitem [ref=e144]:
          - button "Collapse Main menu" [expanded] [ref=e145] [cursor=pointer]:
            - generic [ref=e147]: Collapse Menu
    - generic:
      - generic [ref=e148]:
        - navigation "Toolbar":
          - menu:
            - group [ref=e149]:
              - menuitem "About WordPress" [ref=e150] [cursor=pointer]:
                - generic [ref=e152]: About WordPress
            - group [ref=e153]:
              - menuitem "WP-Alt-text-plugin" [ref=e154] [cursor=pointer]
            - group [ref=e155]:
              - menuitem "0 Comments in moderation" [ref=e156] [cursor=pointer]:
                - generic [ref=e158]: "0"
                - generic [ref=e159]: 0 Comments in moderation
            - group [ref=e160]:
              - menuitem "New" [ref=e161] [cursor=pointer]:
                - generic [ref=e163]: New
          - menu [ref=e164]:
            - group [ref=e165]:
              - menuitem "Howdy, admin" [ref=e166] [cursor=pointer]
      - main:
        - generic [ref=e167]:
          - generic [ref=e168]:
            - generic [ref=e170]:
              - generic [ref=e171]:
                - img [ref=e172]
                - generic [ref=e180]: BeepBeep AI – Alt Text Generator
              - navigation "Main navigation" [ref=e181]:
                - generic [ref=e182]:
                  - link "Dashboard" [ref=e183] [cursor=pointer]:
                    - /url: http://localhost:8888/wp-admin/admin.php?page=bbai
                  - link "ALT Library" [ref=e184] [cursor=pointer]:
                    - /url: http://localhost:8888/wp-admin/admin.php?page=bbai-library
                  - link "Analytics" [ref=e185] [cursor=pointer]:
                    - /url: http://localhost:8888/wp-admin/admin.php?page=bbai-analytics
                  - link "Usage" [ref=e186] [cursor=pointer]:
                    - /url: http://localhost:8888/wp-admin/admin.php?page=bbai-credit-usage
                  - link "Settings" [ref=e187] [cursor=pointer]:
                    - /url: http://localhost:8888/wp-admin/admin.php?page=bbai-settings
                - link "Help" [ref=e188] [cursor=pointer]:
                  - /url: http://localhost:8888/wp-admin/admin.php?page=bbai-guide
                  - img [ref=e189]
                  - generic [ref=e192]: Help
              - generic [ref=e194]:
                - generic [ref=e195]: ben@gmail.coms
                - generic [ref=e196]: Free
            - region "Logged-in dashboard" [ref=e202]:
              - generic "3 images are ready for review" [ref=e204]:
                - button "Open review queue" [ref=e205] [cursor=pointer]:
                  - generic [ref=e206]:
                    - generic "3 images need review" [ref=e207]:
                      - generic [ref=e210]: "3"
                    - paragraph [ref=e211]: 3 ready for review
                    - paragraph [ref=e212]: Open review queue
                - generic [ref=e213]:
                  - generic [ref=e214]: Review ready
                  - heading "3 images are ready for review" [level=1] [ref=e215]
                  - paragraph [ref=e216]: ALT text is ready for a quick review before it goes live.
                  - generic [ref=e217]:
                    - link "Approve all" [ref=e218] [cursor=pointer]:
                      - /url: "#"
                    - link "Open review queue" [ref=e219] [cursor=pointer]:
                      - /url: http://localhost:8888/wp-admin/admin.php?page=bbai-library&status=needs_review&filter=needs-review#bbai-review-filter-tabs
                  - generic "Status summary" [ref=e220]:
                    - generic [ref=e221]:
                      - term [ref=e222]: Missing
                      - definition [ref=e223]: "0"
                    - generic [ref=e224]:
                      - term [ref=e225]: To review
                      - definition [ref=e226]: "3"
                    - generic [ref=e227]:
                      - term [ref=e228]: Credits left
                      - definition [ref=e229]: 76 / 100
                  - list "Workflow steps" [ref=e230]:
                    - listitem [ref=e231]: Generate
                    - listitem [ref=e234]: Review
                    - listitem [ref=e237]: Done
              - generic [ref=e239]:
                - generic [ref=e241]: 3 ready for review
                - generic [ref=e243]: Last run 2 days ago
              - paragraph [ref=e244]: 3 ready for review
          - contentinfo [ref=e245]:
            - paragraph [ref=e246]:
              - generic [ref=e247]:
                - text: Thank you for creating with
                - link "WordPress" [ref=e248] [cursor=pointer]:
                  - /url: https://wordpress.org/
                - text: .
            - paragraph [ref=e249]: Version 6.9.4
          - button "Help" [ref=e251] [cursor=pointer]
  - text: "* * * * * * * *"
  - dialog "Keyboard Shortcuts":
    - generic:
      - generic:
        - heading "Keyboard Shortcuts" [level=2]
        - button "Close":
          - img
      - generic:
        - generic:
          - generic: Generate missing alt text
          - generic:
            - generic: G
        - generic:
          - generic: Regenerate all alt text
          - generic:
            - generic: R
        - generic:
          - generic: Open upgrade modal
          - generic:
            - generic: U
        - generic:
          - generic: Show keyboard shortcuts
          - generic:
            - generic: "?"
        - generic:
          - generic: Close modals
          - generic:
            - generic: Esc
        - generic:
          - generic: Quick actions menu
          - generic:
            - generic: Ctrl
            - generic: +
            - generic: K
```

# Test source

```ts
  1   | import { test, expect, type Page } from '@playwright/test';
  2   | import { execSync } from 'child_process';
  3   | 
  4   | const BASE = (process.env.BBAI_E2E_BASE_URL ?? 'http://localhost:8888').replace('127.0.0.1', 'localhost');
  5   | const DASHBOARD_PATH = '/wp-admin/admin.php?page=bbai';
  6   | const CLI_CONTAINER = '06fe8883b07a5e21412cec8c726b075e-cli-1';
  7   | const WP_PATH = '--path=/var/www/html';
  8   | const FIXTURE_OPTION = 'bbai_e2e_dashboard_state_truth_fixture';
  9   | 
  10  | type TruthFixture = {
  11  |   state: string;
  12  |   counts: {
  13  |     missing: number;
  14  |     review: number;
  15  |     complete: number;
  16  |     failed: number;
  17  |     total: number;
  18  |   };
  19  |   credits: {
  20  |     used: number;
  21  |     total: number;
  22  |     remaining: number;
  23  |     plan: string;
  24  |     plan_slug: string;
  25  |     is_pro: boolean;
  26  |   };
  27  |   job: null | {
  28  |     active: boolean;
  29  |     pausable: boolean;
  30  |     status: string;
  31  |     done?: number;
  32  |     total?: number;
  33  |     eta_seconds?: number | null;
  34  |     last_checked_at?: string | null;
  35  |   };
  36  |   site: {
  37  |     site_hash: string;
  38  |     has_connected_account: boolean;
  39  |   };
  40  |   resolution_sources: Record<string, string>;
  41  |   last_run_at?: string;
  42  | };
  43  | 
  44  | function wp(...args: string[]): string {
  45  |   const cmd = `docker exec ${CLI_CONTAINER} wp ${WP_PATH} ${args.join(' ')} 2>/dev/null`;
  46  |   try {
  47  |     return execSync(cmd, { encoding: 'utf8' }).trim();
  48  |   } catch {
  49  |     return '';
  50  |   }
  51  | }
  52  | 
  53  | function setDashboardTruthFixture(fixture: TruthFixture | null) {
  54  |   if (!fixture) {
  55  |     wp('option', 'delete', FIXTURE_OPTION);
  56  |     return;
  57  |   }
  58  | 
  59  |   const json = JSON.stringify(fixture).replace(/'/g, `'\"'\"'`);
  60  |   wp(`option update ${FIXTURE_OPTION} '${json}' --format=json`);
  61  | }
  62  | 
  63  | async function loginAsAdmin(page: Page) {
  64  |   await page.goto(`${BASE}/wp-login.php`);
  65  |   await page.fill('#user_login', 'admin');
  66  |   await page.fill('#user_pass', 'password');
  67  |   await page.click('#wp-submit');
  68  |   await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
  69  | }
  70  | 
  71  | async function fetchDashboardTruth(page: Page) {
  72  |   return page.evaluate(async () => {
  73  |     const rootTruthUrl =
  74  |       document
  75  |         .querySelector('[data-bbai-li-state-truth-url]')
  76  |         ?.getAttribute('data-bbai-li-state-truth-url') ||
  77  |       '/wp-json/bbai/v1/dashboard/state-truth';
  78  |     const nonce =
  79  |       (window as any).BBAI?.nonce ||
  80  |       (window as any).bbai_env?.nonce ||
  81  |       (window as any).wpApiSettings?.nonce ||
  82  |       '';
  83  |     const res = await fetch(rootTruthUrl, {
  84  |       method: 'GET',
  85  |       credentials: 'same-origin',
  86  |       headers: {
  87  |         'X-WP-Nonce': nonce,
  88  |       },
  89  |     });
  90  |     const json = await res.json();
  91  |     return {
  92  |       status: res.status,
  93  |       json,
  94  |     };
  95  |   });
  96  | }
  97  | 
  98  | async function openDashboard(page: Page) {
  99  |   await page.goto(`${BASE}${DASHBOARD_PATH}`);
> 100 |   await page.waitForLoadState('networkidle');
      |              ^ Error: page.waitForLoadState: Test timeout of 90000ms exceeded.
  101 | }
  102 | 
  103 | async function expectHeroState(page: Page, state: string, timeout = 15000) {
  104 |   await expect(page.locator('[data-bbai-li-hero]')).toHaveAttribute('data-bbai-li-state', state, { timeout });
  105 | }
  106 | 
  107 | function heroSummaryValue(page: Page, label: string) {
  108 |   return page
  109 |     .locator('.bbai-li-summary__item')
  110 |     .filter({ has: page.locator('.bbai-li-summary__label', { hasText: label }) })
  111 |     .locator('.bbai-li-summary__value');
  112 | }
  113 | 
  114 | test.describe('Dashboard truth-driven UI', () => {
  115 |   test.describe.configure({ mode: 'serial' });
  116 | 
  117 |   test.beforeEach(() => {
  118 |     setDashboardTruthFixture(null);
  119 |   });
  120 | 
  121 |   test.afterEach(() => {
  122 |     setDashboardTruthFixture(null);
  123 |   });
  124 | 
  125 |   test('QUEUED state keeps SSR, truth endpoint, and hydrated UI aligned without processing copy', async ({ page }) => {
  126 |     const fixture: TruthFixture = {
  127 |       state: 'QUEUED',
  128 |       counts: { missing: 4, review: 2, complete: 14, failed: 0, total: 20 },
  129 |       credits: { used: 10, total: 50, remaining: 40, plan: 'free', plan_slug: 'free', is_pro: false },
  130 |       job: {
  131 |         active: true,
  132 |         pausable: false,
  133 |         status: 'queued',
  134 |         done: 0,
  135 |         total: 4,
  136 |         last_checked_at: '2026-04-22T08:00:00Z',
  137 |       },
  138 |       site: { site_hash: 'fixture-site', has_connected_account: true },
  139 |       resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
  140 |     };
  141 | 
  142 |     setDashboardTruthFixture(fixture);
  143 |     await loginAsAdmin(page);
  144 |     await openDashboard(page);
  145 | 
  146 |     const root = page.locator('[data-bbai-logged-in-dashboard]');
  147 |     const hero = page.locator('[data-bbai-li-hero]');
  148 |     const truth = await fetchDashboardTruth(page);
  149 | 
  150 |     await expect(root).toHaveAttribute('data-state', 'QUEUED');
  151 |     await expect(hero).toHaveAttribute('data-bbai-li-state', 'QUEUED');
  152 |     expect(truth.status).toBe(200);
  153 |     expect(truth.json.state).toBe('QUEUED');
  154 | 
  155 |     await expect(hero.locator('[data-bbai-li-hero-headline]')).toContainText('queued');
  156 |     await expect(hero.locator('[data-bbai-li-hero-support]')).not.toContainText('Generating ALT text now');
  157 |     await expect(hero.locator('[data-bbai-li-donut-sub]')).not.toContainText('generating now');
  158 |     await expect(page.locator('.bbai-li-progress-steps')).toHaveCount(0);
  159 |     await expect(hero.locator('[data-bbai-li-action="pause-job"]')).toHaveCount(0);
  160 |     await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);
  161 |     await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toBeHidden();
  162 |     await expect(page.locator('.bbai-li-activity-strip')).toContainText('Queued automatically');
  163 |     await expect(page.locator('.bbai-li-activity-strip')).toHaveClass(/bbai-li-activity-strip--queued/);
  164 |     await expect(page.locator('.bbai-li-activity-strip')).toContainText('Last checked');
  165 |     await expect(hero.locator('.bbai-li-hero-status-line')).toContainText('Checking queue');
  166 | 
  167 |     await page.reload({ waitUntil: 'networkidle' });
  168 |     await expect(root).toHaveAttribute('data-state', 'QUEUED');
  169 |     await expect(hero).toHaveAttribute('data-bbai-li-state', 'QUEUED');
  170 |   });
  171 | 
  172 |   test('PROCESSING state only shows Pause when the backend marks the job active and pausable', async ({ page }) => {
  173 |     await loginAsAdmin(page);
  174 | 
  175 |     for (const scenario of [
  176 |       {
  177 |         name: 'pause visible',
  178 |         fixture: {
  179 |           state: 'PROCESSING',
  180 |           counts: { missing: 7, review: 1, complete: 12, failed: 0, total: 20 },
  181 |           credits: { used: 22, total: 100, remaining: 78, plan: 'pro', plan_slug: 'pro', is_pro: true },
  182 |           job: {
  183 |             active: true,
  184 |             pausable: true,
  185 |             status: 'processing',
  186 |             done: 5,
  187 |             total: 12,
  188 |             eta_seconds: 120,
  189 |             last_checked_at: '2026-04-22T08:00:00Z',
  190 |           },
  191 |           site: { site_hash: 'fixture-site', has_connected_account: true },
  192 |           resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
  193 |         } satisfies TruthFixture,
  194 |         pauseVisible: true,
  195 |       },
  196 |       {
  197 |         name: 'pause hidden for inactive processing job',
  198 |         fixture: {
  199 |           state: 'PROCESSING',
  200 |           counts: { missing: 7, review: 1, complete: 12, failed: 0, total: 20 },
```