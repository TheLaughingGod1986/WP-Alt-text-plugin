#!/usr/bin/env node
/**
 * Minify CSS files for production
 * Uses cssnano for compression
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const assetsDir = path.join(__dirname, '..', 'assets');
const cssFiles = [
    'ai-alt-dashboard.css',
    'auth-modal.css',
    'upgrade-modal.css',
    'modern-style.css',
    'design-system.css',
    'components.css',
    'button-enhancements.css',
    'guide-settings-pages.css',
    'dashboard-tailwind.css'
];

function minifyFile(inputFile) {
    const inputPath = path.join(assetsDir, inputFile);
    const outputFile = inputFile.replace('.css', '.min.css');
    const outputPath = path.join(assetsDir, outputFile);

    console.log(`📦 Minifying ${inputFile}...`);

    try {
        if (!fs.existsSync(inputPath)) {
            console.warn(`  ⚠️  ${inputFile} not found, skipping...`);
            return;
        }

        // Use cssnano-cli to minify
        execSync(`npx cssnano-cli "${inputPath}" "${outputPath}"`, {
            stdio: 'pipe',
            cwd: path.join(__dirname, '..')
        });

        const originalSize = fs.statSync(inputPath).size;
        const minifiedSize = fs.statSync(outputPath).size;
        const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

        console.log(`  ✅ ${outputFile} (${formatSize(minifiedSize)} / ${formatSize(originalSize)} - ${savings}% reduction)`);
    } catch (error) {
        console.error(`  ❌ Error minifying ${inputFile}:`, error.message);
        // Don't exit on CSS errors - some files might not need minification
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function main() {
    console.log('🚀 Starting CSS minification...\n');

    cssFiles.forEach(minifyFile);

    console.log('\n✅ CSS minification complete!');
}

main();

