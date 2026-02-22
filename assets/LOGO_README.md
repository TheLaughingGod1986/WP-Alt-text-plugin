# AltText AI Logo Assets

This directory contains logo files for AltText AI branding, suitable for use in:
- Stripe Dashboard branding
- Email templates
- Marketing materials
- Web applications

## Files

### SVG Files (Source)
- **`logo-alttext-ai.svg`** - Transparent background (recommended for Stripe)
- **`logo-alttext-ai-white-bg.svg`** - White background variant

### PNG Files (For Stripe)
Stripe requires PNG or JPG format, square, at least 128px, max 512KB.

**To generate PNG files:**
1. **Browser Tool (Easiest)**: Open `scripts/svg-to-png.html` in your browser
   - Select desired size (128px, 256px, or 512px)
   - Click "Load Transparent Logo" or "Load White Background"
   - Click "Download PNG"
   
2. **Online Converter**: Use https://cloudconvert.com/svg-to-png or similar
   - Upload `logo-alttext-ai.svg`
   - Set dimensions to 512×512px (or 128×128px minimum)
   - Download the PNG file

3. **Command Line** (if you have ImageMagick installed):
   ```bash
   convert -background none -resize 512x512 assets/logo-alttext-ai.svg assets/logo-alttext-ai-512x512.png
   ```

## Design Specifications

- **Primary Colors**: 
  - Teal Gradient: `#14b8a6` → `#10b981`
- **Icon Elements**:
  - Central AI sparkle symbol
  - Image frame representing alt text/image optimization
  - Decorative sparkles for visual interest
- **Style**: Modern, clean, professional

## Usage in Stripe Dashboard

1. Go to: https://dashboard.stripe.com/settings/branding
2. Click "Upload logo"
3. Upload a PNG file (512×512px recommended):
   - `logo-alttext-ai-512x512.png` (if generated)
   - Or use the HTML converter at `scripts/svg-to-png.html`
4. Stripe will automatically optimize the logo for display

## File Size Guidelines

- **128×128px**: Minimum size (~5-15 KB typical)
- **256×256px**: Standard size (~20-40 KB typical)
- **512×512px**: Recommended size (~50-150 KB typical)
- **Maximum**: 512 KB (Stripe limit)

All generated PNGs should be well under the 512KB limit.

## Notes

- SVG files are the source format (scalable, crisp at any size)
- PNG files are required for Stripe Dashboard
- The transparent version is recommended for Stripe (Stripe handles backgrounds automatically)
- Logo follows WordPress plugin design standards
