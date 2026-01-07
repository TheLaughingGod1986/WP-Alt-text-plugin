#!/usr/bin/env node

/**
 * CSS Build Script
 * Concatenates modular CSS files into a single bundle
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

const fs = require('fs');
const path = require('path');

const projectRoot = path.join(__dirname, '..');

/**
 * CSS bundle configuration
 * Order matters - follows the index.css import order
 */
const cssBundle = {
    name: 'unified',
    output: path.join(projectRoot, 'assets', 'css', 'unified.css'),
    outputMin: path.join(projectRoot, 'assets', 'css', 'unified.min.css'),
    files: [
        { path: 'assets/src/css/unified/_tokens.css', section: '1. Design Tokens (CSS Variables)' },
        { path: 'assets/src/css/unified/_base.css', section: '2. Base & Reset Styles' },
        { path: 'assets/src/css/unified/_layout.css', section: '3. Layout Components' },
        { path: 'assets/src/css/unified/_cards.css', section: '4. UI Components - Cards' },
        { path: 'assets/src/css/unified/_buttons.css', section: '4. UI Components - Buttons' },
        { path: 'assets/src/css/unified/_badges.css', section: '4. UI Components - Badges' },
        { path: 'assets/src/css/unified/_forms.css', section: '5. Form Elements' },
        { path: 'assets/src/css/unified/_tables.css', section: '6. Data Display' },
        { path: 'assets/src/css/unified/_modals.css', section: '7. Modals & Overlays' },
        { path: 'assets/src/css/unified/_pages.css', section: '8. Page-Specific Styles' },
        { path: 'assets/src/css/unified/_utilities.css', section: '9. Utility Classes' },
        { path: 'assets/src/css/unified/_animations.css', section: '10. Animations' },
        { path: 'assets/src/css/unified/_header.css', section: '11. Header & Navigation' },
        { path: 'assets/src/css/unified/_alerts.css', section: '12. Alerts & Notifications' },
        { path: 'assets/src/css/unified/_auth-modal.css', section: '13. Auth Modal' },
        { path: 'assets/src/css/unified/_upgrade-modal.css', section: '14. Upgrade Modal & Pricing' },
        { path: 'assets/src/css/unified/_bulk-progress.css', section: '15. Bulk Progress Modal' },
        { path: 'assets/src/css/unified/_api-notice.css', section: '16. API Notice' },
        { path: 'assets/src/css/unified/_misc.css', section: '17. Miscellaneous' }
    ]
};

/**
 * Read and concatenate CSS files
 */
function buildBundle() {
    console.log('='.repeat(50));
    console.log('CSS Build Script');
    console.log('='.repeat(50));
    console.log(`\nBuilding ${cssBundle.name} bundle...`);

    const header = `/**
 * AltText AI - Unified Stylesheet (Modular)
 *
 * A single, optimized CSS file for the entire plugin.
 * Split into modular components for maintainability.
 *
 * @package BeepBeep_AI
 * @version 6.0.0
 * @generated ${new Date().toISOString()}
 */

`;

    let content = header;

    for (const file of cssBundle.files) {
        const fullPath = path.join(projectRoot, file.path);

        if (!fs.existsSync(fullPath)) {
            console.error(`  ERROR: File not found: ${file.path}`);
            continue;
        }

        const fileContent = fs.readFileSync(fullPath, 'utf8');
        const fileName = path.basename(file.path);

        content += `/* ${file.section} */\n`;
        content += `/* Imported from: ${fileName} */\n`;
        content += fileContent;
        content += '\n\n';

        console.log(`  + ${file.path}`);
    }

    // Ensure output directory exists
    const outputDir = path.dirname(cssBundle.output);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    // Write bundle
    fs.writeFileSync(cssBundle.output, content);
    const stats = fs.statSync(cssBundle.output);
    const sizeKB = (stats.size / 1024).toFixed(1);

    console.log(`\n  -> ${cssBundle.output} (${sizeKB} KB)`);

    return { size: stats.size, content };
}

/**
 * Simple CSS minification
 * Removes comments, extra whitespace, and newlines
 */
function minifyCSS(content) {
    return content
        // Remove multi-line comments
        .replace(/\/\*[\s\S]*?\*\//g, '')
        // Remove newlines and extra whitespace
        .replace(/\s+/g, ' ')
        // Remove spaces around special characters
        .replace(/\s*([{};:,>+~])\s*/g, '$1')
        // Remove trailing semicolons before closing braces
        .replace(/;}/g, '}')
        // Remove leading/trailing whitespace
        .trim();
}

/**
 * Create minified version
 */
function createMinified(content) {
    const minified = minifyCSS(content);

    fs.writeFileSync(cssBundle.outputMin, minified);
    const stats = fs.statSync(cssBundle.outputMin);
    const sizeKB = (stats.size / 1024).toFixed(1);

    console.log(`  -> ${cssBundle.outputMin} (${sizeKB} KB)`);

    return { size: stats.size };
}

/**
 * Main build function
 */
function build() {
    try {
        const result = buildBundle();
        const minResult = createMinified(result.content);

        const sizeKB = (result.size / 1024).toFixed(1);
        const minSizeKB = (minResult.size / 1024).toFixed(1);
        const savings = ((1 - minResult.size / result.size) * 100).toFixed(0);

        console.log('\n' + '='.repeat(50));
        console.log('Build Summary');
        console.log('='.repeat(50));
        console.log(`${cssBundle.name}: ${sizeKB} KB -> ${minSizeKB} KB (${savings}% smaller)`);
        console.log('\nBuild complete!\n');
    } catch (error) {
        console.error('\nERROR:', error.message);
        process.exit(1);
    }
}

// Run build
build();
