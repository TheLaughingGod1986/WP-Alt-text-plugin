#!/usr/bin/env node
/**
 * Build modern.css by resolving all @import statements
 * This creates a single bundled CSS file that WordPress can serve
 */

const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const cssnano = require('cssnano');

const modernCssPath = path.join(__dirname, '..', 'assets', 'css', 'modern.css');
const outputPath = path.join(__dirname, '..', 'assets', 'css', 'modern.bundle.css');
const outputMinPath = path.join(__dirname, '..', 'assets', 'css', 'modern.bundle.min.css');

/**
 * Resolve @import statements manually
 * PostCSS doesn't handle @import by default, so we need to inline them
 */
function resolveImports(cssContent, basePath) {
    const importRegex = /@import\s+['"]([^'"]+)['"]\s*;/g;

    return cssContent.replace(importRegex, (match, importPath) => {
        const fullPath = path.join(basePath, importPath);

        if (!fs.existsSync(fullPath)) {
            console.warn(`  ⚠️  Import not found: ${importPath}`);
            return `/* Import not found: ${importPath} */`;
        }

        const importedContent = fs.readFileSync(fullPath, 'utf8');
        const importedDir = path.dirname(fullPath);

        // Recursively resolve imports in the imported file
        return `/* Imported from: ${importPath} */\n${resolveImports(importedContent, importedDir)}\n`;
    });
}

async function buildModernCss() {
    console.log('🚀 Building modern.css bundle...\n');

    try {
        // Read modern.css
        const modernCss = fs.readFileSync(modernCssPath, 'utf8');
        const basePath = path.dirname(modernCssPath);

        // Resolve all imports
        console.log('📦 Resolving @import statements...');
        const bundledCss = resolveImports(modernCss, basePath);

        // Write unminified bundle
        fs.writeFileSync(outputPath, bundledCss, 'utf8');
        console.log(`  ✅ Created: modern.bundle.css (${formatSize(fs.statSync(outputPath).size)})`);

        // Minify the bundled CSS
        console.log('📦 Minifying bundle...');
        const result = await postcss([cssnano()]).process(bundledCss, {
            from: outputPath,
            to: outputMinPath
        });

        fs.writeFileSync(outputMinPath, result.css, 'utf8');

        const minStat = fs.statSync(outputMinPath);
        const origStat = fs.statSync(outputPath);
        const savings = ((origStat.size - minStat.size) / origStat.size * 100).toFixed(1);

        console.log(`  ✅ Created: modern.bundle.min.css (${formatSize(minStat.size)} - ${savings}% reduction)`);

        console.log('\n✅ Modern CSS bundle complete!');
    } catch (error) {
        console.error('❌ Build failed:', error);
        process.exitCode = 1;
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

buildModernCss();
