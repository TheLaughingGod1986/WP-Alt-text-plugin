#!/usr/bin/env node
/**
 * Capture real plugin screenshots for WordPress.org
 * Run: npx playwright install chromium && node scripts/capture-screenshots.js
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.WP_URL || 'http://localhost:8080';
const WP_ADMIN = BASE_URL + '/wp-admin';
const OUTPUT_DIR = path.join(__dirname, '../assets/wordpress-org/screenshots');

// Ensure output dir exists
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

async function captureScreenshots() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  try {
    // Login
    console.log('Logging in...');
    const loginUrl = BASE_URL + '/wp-login.php';
    const resp = await page.goto(loginUrl, { waitUntil: 'load', timeout: 60000 });
    if (!resp || resp.status() >= 400) {
      throw new Error('Login page failed: ' + (resp ? resp.status() : 'no response'));
    }
    await page.waitForTimeout(2000);
    await page.waitForSelector('#user_login', { timeout: 25000 });
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'Plymouth.09');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 20000 });

    // Screenshot 1: Dashboard
    console.log('Capturing Dashboard...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'screenshot-1.png'), fullPage: false });

    // Screenshot 2: ALT Library
    console.log('Capturing ALT Library...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai-library');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'screenshot-2.png'), fullPage: false });

    // Screenshot 3: Settings
    console.log('Capturing Settings...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai-settings');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'screenshot-3.png'), fullPage: false });

    // Screenshot 4: Media Library (with plugin integration)
    console.log('Capturing Media Library...');
    await page.goto(WP_ADMIN + '/upload.php');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'screenshot-4.png'), fullPage: false });

    // Screenshot 5: Credit Usage / Analytics
    console.log('Capturing Credit Usage...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai-credit-usage');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'screenshot-5.png'), fullPage: false });

    // Screenshot 6: Upgrade modal - trigger from dashboard
    console.log('Capturing Upgrade modal...');
    await page.goto(WP_ADMIN + '/admin.php?page=bbai');
    await page.waitForTimeout(2500);
    const upgradeBtn = await page.$('[data-action="show-upgrade-modal"], .bbai-upgrade-btn, button:has-text("Upgrade"), a:has-text("Compare plans"), a:has-text("Upgrade")');
    if (upgradeBtn) {
      await upgradeBtn.click();
      await page.waitForTimeout(2000);
    }
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'screenshot-6.png'), fullPage: false });

    console.log('Done! Screenshots saved to', OUTPUT_DIR);
  } catch (err) {
    console.error('Error:', err.message);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

captureScreenshots();
