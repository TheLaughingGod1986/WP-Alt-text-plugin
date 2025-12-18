#!/usr/bin/env node
/**
 * Generate versioned asset manifest for cache busting
 * Creates a manifest.json with file hashes
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const distDir = path.join(__dirname, '..', 'assets', 'dist');
const manifestPath = path.join(distDir, 'manifest.json');

function generateHash(filePath) {
    const content = fs.readFileSync(filePath);
    return crypto.createHash('md5').update(content).digest('hex').substring(0, 8);
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function main() {
    console.log('🚀 Generating asset manifest...\n');

    const manifest = {
        generated: new Date().toISOString(),
        version: require('../package.json').version,
        files: {},
    };

    // Process JS files
    const jsDir = path.join(distDir, 'js');
    if (fs.existsSync(jsDir)) {
        const jsFiles = fs.readdirSync(jsDir).filter(f => f.endsWith('.min.js'));

        for (const file of jsFiles) {
            const filePath = path.join(jsDir, file);
            const hash = generateHash(filePath);
            const stats = fs.statSync(filePath);
            const baseName = file.replace('.min.js', '');

            manifest.files[`js/${baseName}`] = {
                file: `js/${file}`,
                hash: hash,
                version: `${baseName}.${hash}.min.js`,
                size: stats.size,
                sizeFormatted: formatSize(stats.size),
            };

            // Check for compressed versions
            if (fs.existsSync(`${filePath}.gz`)) {
                manifest.files[`js/${baseName}`].gzip = {
                    file: `js/${file}.gz`,
                    size: fs.statSync(`${filePath}.gz`).size,
                    sizeFormatted: formatSize(fs.statSync(`${filePath}.gz`).size),
                };
            }
            if (fs.existsSync(`${filePath}.br`)) {
                manifest.files[`js/${baseName}`].brotli = {
                    file: `js/${file}.br`,
                    size: fs.statSync(`${filePath}.br`).size,
                    sizeFormatted: formatSize(fs.statSync(`${filePath}.br`).size),
                };
            }

            console.log(`✅ JS: ${baseName} → ${hash}`);
        }
    }

    // Process CSS files
    const cssDir = path.join(distDir, 'css');
    if (fs.existsSync(cssDir)) {
        const cssFiles = fs.readdirSync(cssDir).filter(f => f.endsWith('.min.css'));

        for (const file of cssFiles) {
            const filePath = path.join(cssDir, file);
            const hash = generateHash(filePath);
            const stats = fs.statSync(filePath);
            const baseName = file.replace('.min.css', '');

            manifest.files[`css/${baseName}`] = {
                file: `css/${file}`,
                hash: hash,
                version: `${baseName}.${hash}.min.css`,
                size: stats.size,
                sizeFormatted: formatSize(stats.size),
            };

            // Check for compressed versions
            if (fs.existsSync(`${filePath}.gz`)) {
                manifest.files[`css/${baseName}`].gzip = {
                    file: `css/${file}.gz`,
                    size: fs.statSync(`${filePath}.gz`).size,
                    sizeFormatted: formatSize(fs.statSync(`${filePath}.gz`).size),
                };
            }
            if (fs.existsSync(`${filePath}.br`)) {
                manifest.files[`css/${baseName}`].brotli = {
                    file: `css/${file}.br`,
                    size: fs.statSync(`${filePath}.br`).size,
                    sizeFormatted: formatSize(fs.statSync(`${filePath}.br`).size),
                };
            }

            console.log(`✅ CSS: ${baseName} → ${hash}`);
        }
    }

    // Write manifest
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

    console.log(`\n✅ Manifest generated: ${manifestPath}`);
    console.log(`\nTotal assets: ${Object.keys(manifest.files).length}`);
}

main().catch(error => {
    console.error('❌ Manifest generation failed:', error);
    process.exitCode = 1;
});
