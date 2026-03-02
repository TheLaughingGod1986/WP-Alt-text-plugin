#!/usr/bin/env node
/**
 * Create demo video from screenshots (reliable - Playwright video recording often freezes).
 *
 * Prerequisites:
 *   1. Screenshots in assets/wordpress-org/screenshots/ (run ./scripts/setup-and-capture.sh)
 *   2. ffmpeg installed (brew install ffmpeg)
 *
 * Run: node scripts/create-demo-video-from-screenshots.js
 *
 * Output: assets/wordpress-org/demo-video.mp4
 *
 * Each screenshot is shown for 4 seconds. Order: Dashboard, ALT Library, Settings,
 * Media Library, Credit Usage, Upgrade modal.
 */

const path = require('path');
const fs = require('fs');
const { spawn } = require('child_process');

const SCREENSHOTS_DIR = path.join(__dirname, '../assets/wordpress-org/screenshots');
const OUTPUT_DIR = path.join(__dirname, '../assets/wordpress-org');
const OUTPUT_MP4 = path.join(OUTPUT_DIR, 'demo-video.mp4');
const SECONDS_PER_SLIDE = 4;

// Order matches capture-screenshots.js
const SCREENSHOTS = [
  'screenshot-1.png', // Dashboard
  'screenshot-2.png', // ALT Library
  'screenshot-3.png', // Settings
  'screenshot-4.png', // Media Library
  'screenshot-5.png', // Credit Usage
  'screenshot-6.png', // Upgrade modal
];

function checkFfmpeg() {
  return new Promise((resolve) => {
    const proc = spawn('ffmpeg', ['-version'], { stdio: 'ignore' });
    proc.on('error', () => resolve(false));
    proc.on('close', (code) => resolve(code === 0));
  });
}

function createVideo() {
  return new Promise((resolve, reject) => {
    // ffmpeg -framerate 1/4 = 1 frame every 4 seconds per image
    // -start_number 1 -i screenshot-%d.png reads screenshot-1.png, screenshot-2.png, ...
    const args = [
      '-y',
      '-framerate', String(1 / SECONDS_PER_SLIDE),
      '-start_number', '1',
      '-i', path.join(SCREENSHOTS_DIR, 'screenshot-%d.png'),
      '-c:v', 'libx264',
      '-pix_fmt', 'yuv420p',
      '-r', '30',
      '-movflags', '+faststart',
      OUTPUT_MP4,
    ];
    const proc = spawn('ffmpeg', args, { stdio: 'inherit' });
    proc.on('error', reject);
    proc.on('close', (code) => (code === 0 ? resolve() : reject(new Error(`ffmpeg exited ${code}`))));
  });
}

async function main() {
  const missing = SCREENSHOTS.filter((f) => !fs.existsSync(path.join(SCREENSHOTS_DIR, f)));
  if (missing.length > 0) {
    console.error('Missing screenshots:', missing.join(', '));
    console.error('Run: ./scripts/setup-and-capture.sh');
    process.exit(1);
  }

  const hasFfmpeg = await checkFfmpeg();
  if (!hasFfmpeg) {
    console.error('ffmpeg is required. Install with: brew install ffmpeg');
    process.exit(1);
  }

  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  console.log('Creating demo video from screenshots...');
  await createVideo();
  console.log('Done! Video saved to', OUTPUT_MP4);
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
