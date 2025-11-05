#!/usr/bin/env node
/**
 * Convert SVG logo to PNG for Stripe branding.
 * Stripe requires: square, at least 128px, max 512KB, PNG or JPG format.
 */

const fs = require('fs');
const path = require('path');

function convertWithResvg(svgPath, pngPath, size) {
    try {
        const { Resvg } = require('@resvg/resvg-js');
        const svgContent = fs.readFileSync(svgPath, 'utf-8');
        
        const resvg = new Resvg(svgContent, {
            fitTo: {
                mode: 'width',
                value: size,
            },
        });
        
        const pngData = resvg.render();
        const pngBuffer = pngData.asPng();
        
        fs.writeFileSync(pngPath, pngBuffer);
        return Promise.resolve();
    } catch (e) {
        return null;
    }
}

async function convertSvgToPng(svgPath, pngPath, size) {
    const result = convertWithResvg(svgPath, pngPath, size);
    if (result) {
        return result;
    }
    
    throw new Error('No SVG conversion library found. Install one: npm install --save-dev @resvg/resvg-js');
}

async function main() {
    const scriptDir = __dirname;
    const projectRoot = path.dirname(scriptDir);
    const assetsDir = path.join(projectRoot, 'assets');
    
    const svgFiles = [
        { svg: 'logo-alttext-ai.svg', sizes: [128, 512] },
        { svg: 'logo-alttext-ai-white-bg.svg', sizes: [128, 512] }
    ];
    
    console.log('🔄 Converting SVG logos to PNG...\n');
    
    let successCount = 0;
    
    for (const { svg, sizes } of svgFiles) {
        const svgPath = path.join(assetsDir, svg);
        
        if (!fs.existsSync(svgPath)) {
            console.log(`⚠️  Skipping ${svg} (file not found)`);
            continue;
        }
        
        for (const size of sizes) {
            const baseName = path.basename(svg, '.svg');
            const pngName = `${baseName}-${size}x${size}.png`;
            const pngPath = path.join(assetsDir, pngName);
            
            try {
                process.stdout.write(`   Converting ${svg} → ${pngName} (${size}x${size})... `);
                
                await convertSvgToPng(svgPath, pngPath, size);
                
                const stats = fs.statSync(pngPath);
                const fileSizeKB = (stats.size / 1024).toFixed(1);
                
                console.log(`✅ (${fileSizeKB} KB)`);
                successCount++;
            } catch (error) {
                console.log(`❌ Failed: ${error.message}`);
            }
        }
    }
    
    console.log('');
    if (successCount > 0) {
        console.log(`✅ Successfully created ${successCount} PNG file(s)`);
        console.log('');
        console.log('📋 Stripe-ready logo files:');
        console.log('   Recommended: assets/logo-alttext-ai-512x512.png');
        console.log('   Minimum size: assets/logo-alttext-ai-128x128.png');
    } else {
        console.log('❌ No PNG files were created');
        console.log('');
        console.log('💡 To install conversion library, run:');
        console.log('   npm install --save-dev sharp');
        process.exit(1);
    }
}

main().catch(error => {
    console.error('Error:', error.message);
    process.exit(1);
});

