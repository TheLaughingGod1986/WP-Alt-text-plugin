#!/usr/bin/env node
/**
 * Minify CSS files for production
 * Uses cssnano for compression
 */

const fs = require('fs');
const path = require('path');
let postcss = null;
let cssnano = null;
let purgecss = null;
try {
    postcss = require('postcss');
    cssnano = require('cssnano');
    purgecss = require('@fullhuman/postcss-purgecss').default;
} catch (error) {
    console.warn('⚠️  Unable to load postcss/cssnano/purgecss from node_modules. CSS minification will be skipped.');
}

const assetsDir = path.join(__dirname, '..', 'assets');
const srcDir = path.join(assetsDir, 'src', 'css');
const distDir = path.join(assetsDir, 'dist', 'css');
const cssFiles = [
    'modern-style.css',
    'ui.css',
    'bbai-dashboard.css',
    'guide-settings-pages.css',
    'upgrade-modal.css',
    'components.css',
    'design-system.css',
    'bbai-modal.css',
    'bbai-tooltips.css',
    'auth-modal.css',
    'success-modal.css',
    'bbai-debug.css',
    'bulk-progress-modal.css',
    'dashboard-tailwind.css',
    'button-enhancements.css'
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

        if (!postcss || !cssnano) {
            return;
        }

        const css = await fs.promises.readFile(inputPath, 'utf8');

        // Build postcss plugins array
        const plugins = [];

        // Add PurgeCSS if explicitly enabled (use with caution for WordPress plugins)
        // To enable: set environment variable ENABLE_PURGECSS=true
        const enablePurgeCSS = process.env.ENABLE_PURGECSS === 'true';
        if (purgecss && enablePurgeCSS) {
            console.log('  🔍 PurgeCSS enabled - removing unused styles...');
            plugins.push(purgecss({
                content: [
                    path.join(__dirname, '..', 'includes/**/*.php'),
                    path.join(__dirname, '..', 'assets/**/*.js'),
                ],
                safelist: {
                    // Keep WordPress admin classes
                    standard: [/^wp-/, /^admin-/, /^notice-/, /^button-/, /^dashicons-/, /^media-/],
                    // Keep dynamic classes
                    greedy: [/^bbai-/, /^ai-alt-/, /^modal-/, /^tab-/, /^active/, /^is-/, /^has-/],
                },
            }));
        }

        // Add cssnano for minification
        plugins.push(cssnano());

        const result = await postcss(plugins).process(css, { from: inputPath, to: outputPath });

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
    if (!postcss || !cssnano) {
        console.warn('⚠️  postcss/cssnano is not available, skipping CSS minification.');
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
