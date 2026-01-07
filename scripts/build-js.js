#!/usr/bin/env node

/**
 * JavaScript Build Script
 * Concatenates modular JS files into bundles
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

const fs = require('fs');
const path = require('path');

const projectRoot = path.join(__dirname, '..');

/**
 * JS bundles configuration
 * Order matters - dependencies must come first
 */
const jsBundles = [
    {
        name: 'dashboard',
        output: path.join(projectRoot, 'assets', 'js', 'bbai-dashboard.bundle.js'),
        files: [
            'assets/src/js/dashboard/utils.js',
            'assets/src/js/dashboard/upgrade-modal.js',
            'assets/src/js/dashboard/subscription.js',
            'assets/src/js/dashboard/auth.js',
            'assets/src/js/dashboard/portal.js',
            'assets/src/js/dashboard/checkout.js',
            'assets/src/js/dashboard/countdown.js',
            'assets/src/js/dashboard/library.js',
            'assets/src/js/dashboard/seo-checker.js',
            'assets/src/js/dashboard/init.js'
        ]
    },
    {
        name: 'admin',
        output: path.join(projectRoot, 'assets', 'js', 'bbai-admin.bundle.js'),
        files: [
            'assets/src/js/admin/config.js',
            'assets/src/js/admin/queue.js',
            'assets/src/js/admin/progress-modal.js',
            'assets/src/js/admin/success-modal.js',
            'assets/src/js/admin/inline-generate.js',
            'assets/src/js/admin/bulk-operations.js',
            'assets/src/js/admin/single-regenerate.js',
            'assets/src/js/admin/license.js',
            'assets/src/js/admin/init.js'
        ]
    }
];

/**
 * Read and concatenate files
 */
function buildBundle(bundle) {
    console.log(`\nBuilding ${bundle.name} bundle...`);

    let content = '';
    const header = `/**
 * ${bundle.name.charAt(0).toUpperCase() + bundle.name.slice(1)} Bundle
 * Auto-generated from modular source files
 * DO NOT EDIT DIRECTLY - Edit source files in assets/src/js/${bundle.name}/
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 * @generated ${new Date().toISOString()}
 */

`;

    content += header;

    for (const filePath of bundle.files) {
        const fullPath = path.join(projectRoot, filePath);

        if (!fs.existsSync(fullPath)) {
            console.error(`  ERROR: File not found: ${filePath}`);
            continue;
        }

        const fileContent = fs.readFileSync(fullPath, 'utf8');
        const fileName = path.basename(filePath);

        content += `\n/* ============================================\n`;
        content += ` * ${fileName}\n`;
        content += ` * ============================================ */\n\n`;
        content += fileContent;
        content += '\n';

        console.log(`  + ${filePath}`);
    }

    // Ensure output directory exists
    const outputDir = path.dirname(bundle.output);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    // Write bundle
    fs.writeFileSync(bundle.output, content);
    const stats = fs.statSync(bundle.output);
    const sizeKB = (stats.size / 1024).toFixed(1);

    console.log(`  -> ${bundle.output} (${sizeKB} KB)`);

    return { name: bundle.name, size: stats.size, path: bundle.output };
}

/**
 * Simple minification (removes comments and extra whitespace)
 * For production, consider using terser or uglify-js
 */
function minifyBundle(bundlePath) {
    const content = fs.readFileSync(bundlePath, 'utf8');
    const minPath = bundlePath.replace('.bundle.js', '.bundle.min.js');

    // Basic minification - remove block comments and collapse whitespace
    let minified = content
        // Remove multi-line comments (but keep license/doc comments with @)
        .replace(/\/\*(?![\s\S]*?@)[\s\S]*?\*\//g, '')
        // Remove single-line comments (but not URLs)
        .replace(/(?<![:\/"'])\/\/[^\n]*/g, '')
        // Collapse multiple newlines
        .replace(/\n\s*\n\s*\n/g, '\n\n')
        // Remove leading whitespace on lines
        .replace(/^\s+/gm, '')
        // Remove trailing whitespace
        .replace(/\s+$/gm, '');

    fs.writeFileSync(minPath, minified);
    const stats = fs.statSync(minPath);
    const sizeKB = (stats.size / 1024).toFixed(1);

    console.log(`  -> ${minPath} (${sizeKB} KB)`);

    return { path: minPath, size: stats.size };
}

/**
 * Main build function
 */
function build() {
    console.log('='.repeat(50));
    console.log('JavaScript Build Script');
    console.log('='.repeat(50));

    const results = [];

    for (const bundle of jsBundles) {
        try {
            const result = buildBundle(bundle);
            const minResult = minifyBundle(result.path);
            results.push({
                ...result,
                minSize: minResult.size,
                minPath: minResult.path
            });
        } catch (error) {
            console.error(`\nERROR building ${bundle.name}:`, error.message);
        }
    }

    console.log('\n' + '='.repeat(50));
    console.log('Build Summary');
    console.log('='.repeat(50));

    for (const result of results) {
        const sizeKB = (result.size / 1024).toFixed(1);
        const minSizeKB = (result.minSize / 1024).toFixed(1);
        const savings = ((1 - result.minSize / result.size) * 100).toFixed(0);
        console.log(`${result.name}: ${sizeKB} KB -> ${minSizeKB} KB (${savings}% smaller)`);
    }

    console.log('\nBuild complete!\n');
}

// Run build
build();
