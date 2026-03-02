#!/usr/bin/env node
/**
 * Capture demo video frames showing user interaction (Generate, Regenerate, etc.).
 * Performs real clicks and captures screenshots at each step, then creates video.
 *
 * Prerequisites:
 *   1. WordPress running at localhost:8080
 *   2. Demo content: ./scripts/setup-and-capture.sh (or setup-demo-content.php)
 *   3. ffmpeg installed (brew install ffmpeg)
 *
 * Run: node scripts/capture-demo-frames.js
 *
 * Options:
 *   RUN_REGEN=true   Run actual Regenerate (uses 1 credit) - default true for full demo
 *   WP_USER=admin    Override login user
 *   WP_PASS=xxx      Override login password
 *
 * Output: assets/wordpress-org/demo-video.mp4
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { spawn } = require('child_process');

const BASE_URL = process.env.WP_URL || 'http://localhost:8080';
const WP_ADMIN = BASE_URL + '/wp-admin';
const WP_USER = process.env.WP_USER || 'admin';
const WP_PASS = process.env.WP_PASS || 'Plymouth.09';
const FRAMES_DIR = path.join(__dirname, '../assets/wordpress-org/demo-video-frames');
const OUTPUT_DIR = path.join(__dirname, '../assets/wordpress-org');
const OUTPUT_MP4 = path.join(OUTPUT_DIR, 'demo-video.mp4');
const RUN_REGEN = process.env.RUN_REGEN !== 'false';
const SECONDS_PER_FRAME = 3;

let frameIndex = 0;

async function screenshot(page, name) {
  frameIndex++;
  const file = path.join(FRAMES_DIR, `frame-${String(frameIndex).padStart(3, '0')}-${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  console.log(`  Frame ${frameIndex}: ${name}`);
  return file;
}

async function main() {
  if (!fs.existsSync(FRAMES_DIR)) {
    fs.mkdirSync(FRAMES_DIR, { recursive: true });
  }
  // Clear previous frames
  const existing = fs.readdirSync(FRAMES_DIR).filter((f) => f.endsWith('.png'));
  existing.forEach((f) => fs.unlinkSync(path.join(FRAMES_DIR, f)));
  frameIndex = 0;

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  try {
    // Login
    console.log('Logging in...');
    await page.goto(BASE_URL + '/wp-login.php', { waitUntil: 'networkidle', timeout: 60000 });
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 20000 });
    await page.waitForTimeout(1500);

    // 1. Dashboard
    console.log('Capturing: Dashboard...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2500);
    await screenshot(page, 'dashboard');

    // 2. Dashboard - hover Generate Missing button (show it's clickable)
    const generateBtn = await page.$('[data-action="generate-missing"]:not([disabled])');
    if (generateBtn) {
      await generateBtn.hover();
      await page.waitForTimeout(800);
      await screenshot(page, 'dashboard-generate-hover');
    }

    // 3. ALT Library
    console.log('Capturing: ALT Library...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai-library', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2500);
    await screenshot(page, 'library');

    // 4. Click Regenerate on first image
    const regenBtn = await page.$('[data-action="regenerate-single"]');
    if (regenBtn && RUN_REGEN) {
      console.log('Capturing: Regenerate flow (uses 1 credit)...');
      await regenBtn.click();
      await page.waitForTimeout(1500);
      await screenshot(page, 'regen-modal-open');

      // Wait for API response (loading -> result) - up to 12s
      await page.waitForTimeout(8000);
      const resultActive = await page.$('.bbai-regenerate-modal__result.active');
      if (resultActive) {
        await screenshot(page, 'regen-modal-result');
        const acceptBtn = await page.$('.bbai-regenerate-modal__btn--accept:not([disabled])');
        if (acceptBtn) {
          await acceptBtn.click();
          await page.waitForTimeout(1500);
          await screenshot(page, 'regen-accepted');
        }
      } else {
        // Loading or error - capture anyway
        await screenshot(page, 'regen-modal-state');
      }
      // Close modal if still open
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);
    } else if (regenBtn && !RUN_REGEN) {
      await regenBtn.hover();
      await page.waitForTimeout(600);
      await screenshot(page, 'library-regen-hover');
    }

    // 5. Settings
    console.log('Capturing: Settings...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai-settings', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000);
    await screenshot(page, 'settings');

    // 6. Credit Usage
    console.log('Capturing: Credit Usage...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai-credit-usage', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000);
    await screenshot(page, 'credit-usage');

    // 7. Upgrade modal
    console.log('Capturing: Upgrade modal...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(1500);
    const compareLink = await page.$('a:has-text("Compare plans")');
    if (compareLink) {
      await compareLink.click();
      await page.waitForTimeout(2000);
      await screenshot(page, 'upgrade-modal');
    }

    // 8. Media Library
    console.log('Capturing: Media Library...');
    await page.goto(WP_ADMIN + '/upload.php', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000);
    await screenshot(page, 'media-library');

    // 9. Back to Dashboard
    await page.goto(WP_ADMIN + '/admin.php?page=bbai', { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(1500);
    await screenshot(page, 'dashboard-end');

    await browser.close();
  } catch (err) {
    console.error('Error:', err.message);
    await browser.close();
    process.exit(1);
  }

  // Create video from frames
  const frames = fs.readdirSync(FRAMES_DIR).filter((f) => f.endsWith('.png')).sort();
  if (frames.length === 0) {
    console.error('No frames captured.');
    process.exit(1);
  }

  // Rename to frame-001.png, frame-002.png for ffmpeg sequence
  const seqDir = path.join(FRAMES_DIR, 'seq');
  if (!fs.existsSync(seqDir)) fs.mkdirSync(seqDir, { recursive: true });
  frames.forEach((f, i) => {
    const num = String(i + 1).padStart(3, '0');
    fs.copyFileSync(path.join(FRAMES_DIR, f), path.join(seqDir, `frame-${num}.png`));
  });

  console.log('\nCreating video from', frames.length, 'frames...');
  const hasFfmpeg = await new Promise((resolve) => {
    const p = spawn('ffmpeg', ['-version'], { stdio: 'ignore' });
    p.on('error', () => resolve(false));
    p.on('close', (c) => resolve(c === 0));
  });
  if (!hasFfmpeg) {
    console.error('ffmpeg required. Install: brew install ffmpeg');
    process.exit(1);
  }

  await new Promise((resolve, reject) => {
    const args = [
      '-y',
      '-framerate', String(1 / SECONDS_PER_FRAME),
      '-i', path.join(seqDir, 'frame-%03d.png'),
      '-c:v', 'libx264',
      '-pix_fmt', 'yuv420p',
      '-r', '30',
      '-movflags', '+faststart',
      OUTPUT_MP4,
    ];
    const proc = spawn('ffmpeg', args, { stdio: 'inherit' });
    proc.on('error', reject);
    proc.on('close', (c) => (c === 0 ? resolve() : reject(new Error(`ffmpeg exited ${c}`))));
  });

  // Cleanup seq dir
  fs.rmSync(seqDir, { recursive: true });
  console.log('Done! Video saved to', OUTPUT_MP4);
}

main();
