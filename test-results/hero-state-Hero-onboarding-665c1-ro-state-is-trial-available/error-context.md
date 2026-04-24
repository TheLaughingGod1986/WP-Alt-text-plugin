# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hero-state.spec.ts >> Hero / onboarding state machine >> Logged-out hero states >> trial_available — no trial usage >> data-hero-state is trial_available
- Location: tests/e2e/hero-state.spec.ts:130:11

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('.bbai-logged-out')
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for locator('.bbai-logged-out')

```

# Test source

```ts
  35  | // WP-CLI helpers (run synchronously in global/fixture setup).
  36  | // ---------------------------------------------------------------------------
  37  | 
  38  | function wp(...args: string[]): string {
  39  |   const cmd = `docker exec ${CLI_CONTAINER} wp ${WP_PATH} ${args.join(' ')} 2>/dev/null`;
  40  |   try {
  41  |     return execSync(cmd, { encoding: 'utf8' }).trim();
  42  |   } catch {
  43  |     return '';
  44  |   }
  45  | }
  46  | 
  47  | function setTrialUsed(count: number) {
  48  |   if (count === 0) {
  49  |     wp('option', 'delete', TRIAL_OPTION);
  50  |   } else {
  51  |     wp('option', 'set', TRIAL_OPTION, String(count), '--autoload=no');
  52  |   }
  53  | }
  54  | 
  55  | function saveOption(key: string): string {
  56  |   return wp('option', 'get', key);
  57  | }
  58  | 
  59  | function deleteOption(key: string) {
  60  |   wp('option', 'delete', key);
  61  | }
  62  | 
  63  | function restoreOption(key: string, value: string) {
  64  |   if (value) {
  65  |     // Use wp eval to set binary-safe values.
  66  |     wp('option', `set ${key} "${value.replace(/"/g, '\\"')}" --autoload=no`);
  67  |   }
  68  | }
  69  | 
  70  | // ---------------------------------------------------------------------------
  71  | // Login helper — creates a fresh authenticated browser session.
  72  | // ---------------------------------------------------------------------------
  73  | 
  74  | async function loginAsAdmin(page: Page) {
  75  |   await page.goto(`${BASE}/wp-login.php`);
  76  |   await page.fill('#user_login', 'admin');
  77  |   await page.fill('#user_pass', 'password');
  78  |   await page.click('#wp-submit');
  79  |   // WP may redirect to its canonical siteurl (localhost:8888) even if the
  80  |   // test navigated via 127.0.0.1; wait for any URL that contains wp-admin.
  81  |   await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
  82  | }
  83  | 
  84  | // ---------------------------------------------------------------------------
  85  | // Test suite
  86  | // ---------------------------------------------------------------------------
  87  | 
  88  | test.describe('Hero / onboarding state machine', () => {
  89  |   // Must run serially — all tests share the same WP database state and would
  90  |   // race each other if parallelised.
  91  |   test.describe.configure({ mode: 'serial' });
  92  | 
  93  |   test.skip(!process.env.BBAI_E2E_BASE_URL && BASE === 'http://127.0.0.1:8888',
  94  |     'Set BBAI_E2E_BASE_URL to your local WP base to run these tests');
  95  | 
  96  |   // Saved auth option values — restored after the logged-out tests.
  97  |   let savedTokens: Record<string, string> = {};
  98  | 
  99  |   test.describe('Logged-out hero states', () => {
  100 |     test.beforeEach(async ({ page }) => {
  101 |       await forceLoggedOut(page);
  102 |       await page.goto('/wp-admin/admin.php?page=bbai', { waitUntil: 'domcontentloaded' });
  103 |     });
  104 | 
  105 |     test.beforeAll(async () => {
  106 |       // Save and remove auth tokens so dashboard routes to logged-out view.
  107 |       for (const key of AUTH_OPTIONS) {
  108 |         savedTokens[key] = saveOption(key);
  109 |         deleteOption(key);
  110 |       }
  111 |     });
  112 | 
  113 |     test.afterAll(async () => {
  114 |       // Restore auth tokens regardless of test outcomes.
  115 |       for (const key of AUTH_OPTIONS) {
  116 |         if (savedTokens[key]) {
  117 |           restoreOption(key, savedTokens[key]);
  118 |         }
  119 |       }
  120 |     });
  121 | 
  122 |     // -----------------------------------------------------------------------
  123 |     // STATE: trial_available
  124 |     // -----------------------------------------------------------------------
  125 |     test.describe('trial_available — no trial usage', () => {
  126 |       test.beforeEach(async () => {
  127 |         setTrialUsed(0);
  128 |       });
  129 | 
  130 |       test('data-hero-state is trial_available', async ({ page }) => {
  131 |         await loginAsAdmin(page);
  132 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  133 | 
  134 |         const root = page.locator('.bbai-logged-out');
> 135 |         await expect(root).toBeVisible();
      |                            ^ Error: expect(locator).toBeVisible() failed
  136 |         await expect(root).toHaveAttribute('data-hero-state', 'trial_available');
  137 |       });
  138 | 
  139 |       test('trial panel is visible', async ({ page }) => {
  140 |         await loginAsAdmin(page);
  141 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  142 | 
  143 |         const trialPanel = page.locator('#bbai-ftue-panel-trial');
  144 |         await expect(trialPanel).toBeVisible();
  145 |       });
  146 | 
  147 |       test('conversion panel is hidden', async ({ page }) => {
  148 |         await loginAsAdmin(page);
  149 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  150 | 
  151 |         const conversionPanel = page.locator('#bbai-ftue-panel-conversion');
  152 |         await expect(conversionPanel).toBeHidden();
  153 |       });
  154 | 
  155 |       test('"Generate alt text for 3 images (free)" button is rendered and visible', async ({ page }) => {
  156 |         await loginAsAdmin(page);
  157 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  158 | 
  159 |         const btn = page.locator('#bbai-trial-generate-btn');
  160 |         await expect(btn).toBeVisible();
  161 |         await expect(btn).toContainText('Generate alt text for 3 images');
  162 |       });
  163 | 
  164 |       test('conversion CTAs are not in the DOM', async ({ page }) => {
  165 |         await loginAsAdmin(page);
  166 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  167 | 
  168 |         // Conversion panel hidden — its children should not be interactable.
  169 |         const registerBtn = page.locator('#bbai-conversion-register-btn');
  170 |         await expect(registerBtn).toBeHidden();
  171 |         const loginBtn = page.locator('#bbai-conversion-login-btn');
  172 |         await expect(loginBtn).toBeHidden();
  173 |       });
  174 | 
  175 |       test('preview / before-after section is visible', async ({ page }) => {
  176 |         await loginAsAdmin(page);
  177 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  178 | 
  179 |         const preview = page.locator('.bbai-ftue-preview');
  180 |         await expect(preview).toBeVisible();
  181 |       });
  182 |     });
  183 | 
  184 |     // -----------------------------------------------------------------------
  185 |     // STATE: trial_complete (via used count > 0)
  186 |     // -----------------------------------------------------------------------
  187 |     test.describe('trial_complete — trial usage recorded', () => {
  188 |       test.beforeEach(async () => {
  189 |         setTrialUsed(3);
  190 |       });
  191 | 
  192 |       test.afterEach(async () => {
  193 |         setTrialUsed(0);
  194 |       });
  195 | 
  196 |       test('data-hero-state is trial_complete', async ({ page }) => {
  197 |         await loginAsAdmin(page);
  198 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  199 | 
  200 |         const root = page.locator('.bbai-logged-out');
  201 |         await expect(root).toBeVisible();
  202 |         await expect(root).toHaveAttribute('data-hero-state', 'trial_complete');
  203 |       });
  204 | 
  205 |       test('trial panel is hidden', async ({ page }) => {
  206 |         await loginAsAdmin(page);
  207 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  208 | 
  209 |         const trialPanel = page.locator('#bbai-ftue-panel-trial');
  210 |         await expect(trialPanel).toBeHidden();
  211 |       });
  212 | 
  213 |       test('conversion panel is visible', async ({ page }) => {
  214 |         await loginAsAdmin(page);
  215 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  216 | 
  217 |         const conversionPanel = page.locator('#bbai-ftue-panel-conversion');
  218 |         await expect(conversionPanel).toBeVisible();
  219 |       });
  220 | 
  221 |       test('"Generate alt text for 3 images (free)" button is NOT in the DOM', async ({ page }) => {
  222 |         await loginAsAdmin(page);
  223 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  224 | 
  225 |         // PHP gates this button — it must not exist in the DOM at all.
  226 |         const btn = page.locator('#bbai-trial-generate-btn');
  227 |         await expect(btn).toHaveCount(0);
  228 |       });
  229 | 
  230 |       test('"Create free account" CTA is visible', async ({ page }) => {
  231 |         await loginAsAdmin(page);
  232 |         await page.goto(`${BASE}${DASHBOARD_PATH}`);
  233 | 
  234 |         const registerBtn = page.locator('#bbai-conversion-register-btn');
  235 |         await expect(registerBtn).toBeVisible();
```