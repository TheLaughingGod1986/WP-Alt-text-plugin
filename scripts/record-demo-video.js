#!/usr/bin/env node
/**
 * Record a demo video of the BeepBeep AI plugin (Playwright - may freeze).
 *
 * PREFERRED: Use screenshot-based video instead (reliable, shows all tabs):
 *   ./scripts/setup-and-capture.sh   # captures screenshots + creates demo-video.mp4
 *   # or: node scripts/create-demo-video-from-screenshots.js
 *
 * This script uses Playwright video recording, which often freezes on one frame.
 * Uses demo account by default (demo / demo123) - no sensitive credentials.
 * Override with WP_USER and WP_PASS env vars if needed.
 *
 * Prerequisites:
 *   1. WordPress running at localhost:8080
 *   2. Demo content: ./scripts/setup-and-capture.sh (or run setup-demo-content.php)
 *
 * Run: node scripts/record-demo-video.js
 *
 * Options:
 *   RUN_REGEN=false   Skip the Regenerate step (saves credits, avoids modal timeout)
 *   SKIP_LOGIN=true   Login before recording starts - video begins at Dashboard (password never shown)
 *   HEADED=false      Run headless (may produce frozen/single-frame video - headed is default)
 *
 * Output: assets/wordpress-org/demo-video.webm
 *
 * To convert to MP4 for broader compatibility:
 *   ffmpeg -i demo-video.webm -c:v libx264 -c:a aac demo-video.mp4
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.WP_URL || 'http://localhost:8080';
const WP_ADMIN = BASE_URL + '/wp-admin';
// Use demo user by default (created by setup-demo-content.php) - password not sensitive
const WP_USER = process.env.WP_USER || 'demo';
const WP_PASS = process.env.WP_PASS || 'demo123';
const OUTPUT_DIR = path.join(__dirname, '../assets/wordpress-org');
const VIDEO_PATH = path.join(OUTPUT_DIR, 'demo-video.webm');

// Pause between actions (ms) - adjust for desired video pace
const PAUSE = 2500;
const LONG_PAUSE = 4000;

// Headed mode fixes Playwright video freeze (headless often captures only one frame)
// Set HEADED=false to run without visible browser (may produce frozen video)
const HEADED = process.env.HEADED !== 'false';

if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

/** Navigate and wait for page to be painted (helps video capture all frames) */
async function goAndWait(page, url, waitMs = LONG_PAUSE) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  await page.evaluate(() => document.body.offsetHeight); // force layout
  await page.waitForTimeout(waitMs);
}

async function recordDemo() {
  const browser = await chromium.launch({ headless: !HEADED });
  const skipLogin = process.env.SKIP_LOGIN === 'true';
  let storageState = null;

  if (skipLogin) {
    // Login in a separate context - password never shown in video
    console.log('Logging in (before recording)...');
    const loginContext = await browser.newContext({ baseURL: BASE_URL });
    const loginPage = await loginContext.newPage();
    await loginPage.goto(BASE_URL + '/wp-login.php', { waitUntil: 'load', timeout: 60000 });
    await loginPage.fill('#user_login', WP_USER);
    await loginPage.fill('#user_pass', WP_PASS);
    await loginPage.click('#wp-submit');
    await loginPage.waitForURL(/wp-admin/, { timeout: 20000 });
    storageState = await loginContext.storageState();
    await loginContext.close();
    console.log('Recording starts from Dashboard (login not in video)...');
  }

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    ignoreHTTPSErrors: true,
    storageState: storageState || undefined,
    recordVideo: {
      dir: OUTPUT_DIR,
      size: { width: 1920, height: 1080 },
    },
  });
  const page = await context.newPage();

  try {
    // 1. Login (only if not skipped)
    if (!skipLogin) {
      console.log('Recording: Login...');
      await page.goto(BASE_URL + '/wp-login.php', { waitUntil: 'load', timeout: 60000 });
      await page.waitForTimeout(1000);
      await page.fill('#user_login', WP_USER);
      await page.waitForTimeout(300);
      await page.fill('#user_pass', WP_PASS);
      await page.waitForTimeout(300);
      await page.click('#wp-submit');
      await page.waitForURL(/wp-admin/, { timeout: 20000 });
      await page.waitForTimeout(PAUSE);
    }

    // 2. Dashboard
    console.log('Recording: Dashboard...');
    await goAndWait(page, WP_ADMIN + '/admin.php?page=bbai');

    // 3. ALT Library
    console.log('Recording: ALT Library...');
    await goAndWait(page, WP_ADMIN + '/admin.php?page=bbai-library');

    // 4. Regen one image (optional - uses 1 credit, skip if RUN_REGEN=false)
    if (process.env.RUN_REGEN !== 'false') {
      try {
        const regenBtn = await page.$('button:has-text("Regen")');
        if (regenBtn) {
          console.log('Recording: Regenerate alt text...');
          await regenBtn.click();
          await page.waitForTimeout(6000); // Wait for API
          const acceptBtn = await page.$('button:has-text("Accept")');
          if (acceptBtn) {
            await acceptBtn.click();
            await page.waitForTimeout(PAUSE);
          }
          const cancelBtn = await page.$('button:has-text("Cancel")');
          if (cancelBtn) await cancelBtn.click();
          await page.waitForTimeout(PAUSE);
        }
      } catch (e) {
        console.warn('Skipping Regen step:', e.message);
        const cancelBtn = await page.$('button:has-text("Cancel")');
        if (cancelBtn) await cancelBtn.click();
      }
    }

    // 5. Settings
    console.log('Recording: Settings...');
    await goAndWait(page, WP_ADMIN + '/admin.php?page=bbai-settings', PAUSE);

    // 6. Credit Usage
    console.log('Recording: Credit Usage...');
    await goAndWait(page, WP_ADMIN + '/admin.php?page=bbai-credit-usage', PAUSE);

    // 7. Upgrade modal
    console.log('Recording: Upgrade modal...');
    await goAndWait(page, WP_ADMIN + '/admin.php?page=bbai', PAUSE);
    const compareLink = await page.$('a:has-text("Compare plans")');
    if (compareLink) {
      await compareLink.click();
      await page.waitForTimeout(LONG_PAUSE);
    }

    // 8. Media Library
    console.log('Recording: Media Library...');
    await goAndWait(page, WP_ADMIN + '/upload.php', PAUSE);

    // 9. Back to Dashboard (closing shot)
    console.log('Recording: Closing shot...');
    await goAndWait(page, WP_ADMIN + '/admin.php?page=bbai', PAUSE);

    console.log('Finalizing video...');
  } catch (err) {
    console.error('Error:', err.message);
  } finally {
    await context.close();
  }

  // Playwright saves video on context close with a hash filename; move to demo-video.webm
  const videos = fs.readdirSync(OUTPUT_DIR).filter(
    (f) => f.endsWith('.webm') && f !== 'demo-video.webm'
  );
  if (videos.length > 0) {
    const src = path.join(OUTPUT_DIR, videos[0]);
    if (fs.existsSync(VIDEO_PATH)) fs.unlinkSync(VIDEO_PATH);
    fs.renameSync(src, VIDEO_PATH);
    console.log('Done! Video saved to', VIDEO_PATH);
  } else {
    console.warn('No video file found. Video may be in', OUTPUT_DIR);
  }

  await browser.close();
}

recordDemo();
