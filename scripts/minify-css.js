#!/usr/bin/env node
/**
 * Minify CSS files for production
 * Uses cssnano for compression
 */

const fs = require('fs');
const path = require('path');
let cssnano = null;
try {
    cssnano = require('cssnano');
} catch (error) {
    console.warn('⚠️  Unable to load cssnano from node_modules. CSS minification will be skipped.');
}

const assetsDir = path.join(__dirname, '..', 'assets');
const srcDir = path.join(assetsDir, 'src', 'css');
const distDir = path.join(assetsDir, 'dist', 'css');
const cssFiles = [
    'ai-alt-dashboard.css',
    'auth-modal.css',
    'upgrade-modal.css',
    'modern-style.css',
    'design-system.css',
    'components.css',
    'button-enhancements.css',
    'guide-settings-pages.css',
    'dashboard-tailwind.css',
    'ai-alt-debug.css'
];

async function minifyFile(inputFile) {
    const inputPath = path.join(srcDir, inputFile);
    const outputFile = inputFile.replace('.css', '.min.css');
    const outputPath = path.join(distDir, outputFile);

    console.log(`📦 Minifying ${inputFile}...`);

    try {
        if (!fs.existsSync(inputPath)) {
            console.warn(`  ⚠️  ${inputFile} not found, skipping...`);
            return;
        }

        if (!cssnano) {
            return;
        }

        const css = await fs.promises.readFile(inputPath, 'utf8');
        const result = await cssnano.process(css, { from: inputPath, to: outputPath });

        await fs.promises.mkdir(path.dirname(outputPath), { recursive: true });
        await fs.promises.writeFile(outputPath, result.css, 'utf8');

        const [originalStat, minifiedStat] = await Promise.all([
            fs.promises.stat(inputPath),
            fs.promises.stat(outputPath),
        ]);
        const savings = ((originalStat.size - minifiedStat.size) / originalStat.size * 100).toFixed(1);

        console.log(`  ✅ ${outputFile} (${formatSize(minifiedStat.size)} / ${formatSize(originalStat.size)} - ${savings}% reduction)`);
    } catch (error) {
        console.error(`  ❌ Error minifying ${inputFile}:`, error.message);
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function main() {
    if (!cssnano) {
        console.warn('⚠️  cssnano is not available, skipping CSS minification.');
        return;
    }

    console.log('🚀 Starting CSS minification...\n');

    if (!fs.existsSync(distDir)) {
        fs.mkdirSync(distDir, { recursive: true });
    }

    for (const file of cssFiles) {
        await minifyFile(file);
    }

    console.log('\n✅ CSS minification complete!');
}

main().catch(error => {
    console.error('❌ CSS minification failed:', error);
    process.exitCode = 1;
});
