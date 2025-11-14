#!/usr/bin/env node
/**
 * Minify JavaScript files for production
 * Uses terser for compression
 */

const fs = require('fs');
const path = require('path');
let terser = null;
try {
    terser = require('terser');
} catch (error) {
    console.warn('⚠️  Unable to load terser from node_modules. JavaScript minification will be skipped.');
}

const assetsDir = path.join(__dirname, '..', 'assets');
const srcDir = path.join(assetsDir, 'src', 'js');
const distDir = path.join(assetsDir, 'dist', 'js');
const jsFiles = [
    'ai-alt-admin.js',
    'ai-alt-dashboard.js',
    'auth-modal.js',
    'upgrade-modal.js',
    'ai-alt-queue-monitor.js',
    'ai-alt-debug.js'
];

async function minifyFile(inputFile) {
    const inputPath = path.join(srcDir, inputFile);
    const outputFile = inputFile.replace('.js', '.min.js');
    const outputPath = path.join(distDir, outputFile);

    console.log(`📦 Minifying ${inputFile}...`);

    try {
        if (!terser) {
            return;
        }

        const source = await fs.promises.readFile(inputPath, 'utf8');
        const result = await terser.minify(source, {
            compress: {
                drop_console: false,
                passes: 2,
            },
            mangle: true,
            format: {
                comments: false,
            },
        });

        if (!result.code) {
            throw new Error('Terser did not produce any output');
        }

        await fs.promises.mkdir(path.dirname(outputPath), { recursive: true });
        await fs.promises.writeFile(outputPath, result.code, 'utf8');

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
    if (!terser) {
        console.warn('⚠️  Terser is not available, skipping JavaScript minification.');
        return;
    }

    console.log('🚀 Starting JavaScript minification...\n');

    if (!fs.existsSync(distDir)) {
        fs.mkdirSync(distDir, { recursive: true });
    }

    for (const file of jsFiles) {
        const inputPath = path.join(srcDir, file);
        if (!fs.existsSync(inputPath)) {
            console.warn(`  ⚠️  ${file} not found, skipping...`);
            continue;
        }
        await minifyFile(file);
    }

    console.log('\n✅ JavaScript minification complete!');
}

main().catch(error => {
    console.error('❌ JavaScript minification failed:', error);
    process.exitCode = 1;
});
