#!/usr/bin/env node
/**
 * Compress minified assets with gzip and brotli
 * Provides additional 60-70% size reduction
 */

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const { promisify } = require('util');

const gzip = promisify(zlib.gzip);
const brotliCompress = promisify(zlib.brotliCompress);

const distDir = path.join(__dirname, '..', 'assets', 'dist');

async function compressFile(filePath) {
    const fileName = path.basename(filePath);
    const fileBuffer = await fs.promises.readFile(filePath);
    const originalSize = fileBuffer.length;

    console.log(`📦 Compressing ${fileName}...`);

    try {
        // Gzip compression
        const gzipped = await gzip(fileBuffer, { level: 9 });
        const gzipPath = `${filePath}.gz`;
        await fs.promises.writeFile(gzipPath, gzipped);
        const gzipSize = gzipped.length;
        const gzipSavings = ((originalSize - gzipSize) / originalSize * 100).toFixed(1);

        // Brotli compression (better than gzip)
        const brotlied = await brotliCompress(fileBuffer, {
            params: {
                [zlib.constants.BROTLI_PARAM_QUALITY]: zlib.constants.BROTLI_MAX_QUALITY,
            },
        });
        const brotliPath = `${filePath}.br`;
        await fs.promises.writeFile(brotliPath, brotlied);
        const brotliSize = brotlied.length;
        const brotliSavings = ((originalSize - brotliSize) / originalSize * 100).toFixed(1);

        console.log(`  ✅ Gzip: ${formatSize(gzipSize)} (${gzipSavings}% reduction)`);
        console.log(`  ✅ Brotli: ${formatSize(brotliSize)} (${brotliSavings}% reduction)`);
    } catch (error) {
        console.error(`  ❌ Error compressing ${fileName}:`, error.message);
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function main() {
    console.log('🚀 Starting asset compression...\n');

    // Find all minified files
    const jsDir = path.join(distDir, 'js');
    const cssDir = path.join(distDir, 'css');

    const files = [];

    // Add JS files
    if (fs.existsSync(jsDir)) {
        const jsFiles = await fs.promises.readdir(jsDir);
        jsFiles
            .filter(f => f.endsWith('.min.js'))
            .forEach(f => files.push(path.join(jsDir, f)));
    }

    // Add CSS files
    if (fs.existsSync(cssDir)) {
        const cssFiles = await fs.promises.readdir(cssDir);
        cssFiles
            .filter(f => f.endsWith('.min.css'))
            .forEach(f => files.push(path.join(cssDir, f)));
    }

    if (files.length === 0) {
        console.log('⚠️  No minified files found. Run npm run build first.');
        return;
    }

    console.log(`Found ${files.length} files to compress\n`);

    for (const file of files) {
        await compressFile(file);
    }

    console.log('\n✅ Compression complete!');
    console.log('\nCompressed files created:');
    console.log('  - *.min.js.gz (gzip)');
    console.log('  - *.min.js.br (brotli)');
    console.log('  - *.min.css.gz (gzip)');
    console.log('  - *.min.css.br (brotli)');
}

main().catch(error => {
    console.error('❌ Compression failed:', error);
    process.exitCode = 1;
});
