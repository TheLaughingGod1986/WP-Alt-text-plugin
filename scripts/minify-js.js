#!/usr/bin/env node
/**
 * Minify JavaScript files for production
 * Uses terser for compression
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const assetsDir = path.join(__dirname, '..', 'assets');
const srcDir = path.join(assetsDir, 'src', 'js');
const distDir = path.join(assetsDir, 'dist', 'js');
const jsFiles = [
    'ai-alt-admin.js',
    'ai-alt-dashboard.js',
    'auth-modal.js',
    'upgrade-modal.js',
    'ai-alt-queue-monitor.js'
];

function minifyFile(inputFile) {
    const inputPath = path.join(srcDir, inputFile);
    const outputFile = inputFile.replace('.js', '.min.js');
    const outputPath = path.join(distDir, outputFile);

    console.log(`📦 Minifying ${inputFile}...`);

    try {
        // Use npx terser with proper CLI syntax
        // Reserved names need to be in a config file or passed differently
        const terserCmd = `npx --yes terser "${inputPath}" -o "${outputPath}" --compress drop_console=false,passes=2 --mangle --format comments=false`;
        
        execSync(terserCmd, {
            stdio: 'pipe',
            cwd: path.join(__dirname, '..'),
            maxBuffer: 10 * 1024 * 1024 // 10MB buffer
        });

        const originalSize = fs.statSync(inputPath).size;
        const minifiedSize = fs.statSync(outputPath).size;
        const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

        console.log(`  ✅ ${outputFile} (${formatSize(minifiedSize)} / ${formatSize(originalSize)} - ${savings}% reduction)`);
    } catch (error) {
        console.error(`  ❌ Error minifying ${inputFile}:`, error.message);
        // Don't exit - continue with other files
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function main() {
    console.log('🚀 Starting JavaScript minification...\n');

    if (!fs.existsSync(distDir)) {
        fs.mkdirSync(distDir, { recursive: true });
    }

    jsFiles.forEach(file => {
        const inputPath = path.join(srcDir, file);
        if (!fs.existsSync(inputPath)) {
            console.warn(`  ⚠️  ${file} not found, skipping...`);
            return;
        }
        minifyFile(file);
    });

    console.log('\n✅ JavaScript minification complete!');
}

main();
