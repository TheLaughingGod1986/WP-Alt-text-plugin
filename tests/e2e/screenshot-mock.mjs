/**
 * Takes a screenshot of the static dashboard mock HTML for visual comparison.
 * Run: node tests/e2e/screenshot-mock.mjs
 */
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const mockFile = path.resolve(__dirname, 'dashboard-visual-mock.html');
const outputDir = path.resolve(__dirname, '../../output/playwright');

fs.mkdirSync(outputDir, { recursive: true });

// Use the pre-installed Chromium from the system
const execPath = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const browser = await chromium.launch({ headless: true, executablePath: execPath });
const page = await browser.newPage();

// Set viewport to match a typical WordPress admin viewport
await page.setViewportSize({ width: 1200, height: 900 });

// Navigate to the local HTML file
await page.goto(`file://${mockFile}`);

// Wait for fonts and layout
await page.waitForTimeout(500);

// Full-page screenshot
const screenshotPath = path.join(outputDir, 'dashboard-mock-redesign.png');
await page.screenshot({ path: screenshotPath, fullPage: true });
console.log(`Screenshot saved: ${screenshotPath}`);

// Also take a clipped screenshot of just the main content area
await page.screenshot({
  path: path.join(outputDir, 'dashboard-mock-hero.png'),
  clip: { x: 0, y: 0, width: 1200, height: 600 }
});
console.log(`Hero screenshot saved: ${path.join(outputDir, 'dashboard-mock-hero.png')}`);

await browser.close();
console.log('Done.');
