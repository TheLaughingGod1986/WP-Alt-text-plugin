#!/usr/bin/env node
/**
 * Build CSS bundles - unified.css and modern.css
 * Creates single bundled CSS files that WordPress can serve
 */

const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const cssnano = require('cssnano');

// CSS files to build
const cssBundles = [
    {
        name: 'unified',
        input: path.join(__dirname, '..', 'assets', 'src', 'css', 'unified.css'),
        output: path.join(__dirname, '..', 'assets', 'css', 'unified.css'),
        outputMin: path.join(__dirname, '..', 'assets', 'css', 'unified.min.css')
    },
    {
        name: 'modern',
        input: path.join(__dirname, '..', 'assets', 'css', 'modern.css'),
        output: path.join(__dirname, '..', 'assets', 'css', 'modern.bundle.css'),
        outputMin: path.join(__dirname, '..', 'assets', 'css', 'modern.bundle.min.css')
    }
];

// Legacy paths for backward compatibility
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

async function buildCssBundle(bundle) {
    console.log(`\n📦 Building ${bundle.name}.css...`);

    if (!fs.existsSync(bundle.input)) {
        console.warn(`  ⚠️  Input not found: ${bundle.input}`);
        return false;
    }

    // Read source CSS
    const css = fs.readFileSync(bundle.input, 'utf8');
    const basePath = path.dirname(bundle.input);

    // Resolve all imports
    console.log('  📦 Resolving @import statements...');
    const bundledCss = resolveImports(css, basePath);

    // Ensure output directory exists
    const outputDir = path.dirname(bundle.output);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    // Write unminified bundle
    fs.writeFileSync(bundle.output, bundledCss, 'utf8');
    console.log(`  ✅ Created: ${path.basename(bundle.output)} (${formatSize(fs.statSync(bundle.output).size)})`);

    // Minify the bundled CSS
    console.log('  📦 Minifying...');
    const result = await postcss([cssnano()]).process(bundledCss, {
        from: bundle.output,
        to: bundle.outputMin
    });

    fs.writeFileSync(bundle.outputMin, result.css, 'utf8');

    const minStat = fs.statSync(bundle.outputMin);
    const origStat = fs.statSync(bundle.output);
    const savings = ((origStat.size - minStat.size) / origStat.size * 100).toFixed(1);

    console.log(`  ✅ Created: ${path.basename(bundle.outputMin)} (${formatSize(minStat.size)} - ${savings}% reduction)`);

    return true;
}

async function buildModernCss() {
    console.log('🚀 Building CSS bundles...\n');

    try {
        let successCount = 0;

        for (const bundle of cssBundles) {
            const success = await buildCssBundle(bundle);
            if (success) successCount++;
        }

        console.log(`\n✅ CSS build complete! (${successCount}/${cssBundles.length} bundles)`);
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
