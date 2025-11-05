#!/usr/bin/env python3
"""
Convert SVG logo to PNG for Stripe branding.
Stripe requires: square, at least 128px, max 512KB, PNG or JPG format.
"""

import sys
import os
import subprocess

def install_cairosvg():
    """Install cairosvg if not available."""
    try:
        import cairosvg
        return True
    except ImportError:
        print("Installing cairosvg...")
        try:
            subprocess.check_call([sys.executable, "-m", "pip", "install", "cairosvg", "--quiet"])
            import cairosvg
            return True
        except Exception as e:
            print(f"Failed to install cairosvg: {e}")
            return False

def convert_svg_to_png(svg_path, png_path, size=512):
    """Convert SVG to PNG at specified size."""
    try:
        import cairosvg
        cairosvg.svg2png(url=svg_path, write_to=png_path, output_width=size, output_height=size)
        return True
    except Exception as e:
        print(f"Error converting {svg_path}: {e}")
        return False

def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.dirname(script_dir)
    assets_dir = os.path.join(project_root, "assets")
    
    if not install_cairosvg():
        print("\n❌ Could not install cairosvg. Please install manually:")
        print("   pip install cairosvg")
        sys.exit(1)
    
    # Convert both SVG files to PNG
    svg_files = [
        ("logo-alttext-ai.svg", "logo-alttext-ai-512x512.png"),
        ("logo-alttext-ai-white-bg.svg", "logo-alttext-ai-white-bg-512x512.png"),
    ]
    
    # Also create 128px versions (minimum required)
    svg_files.extend([
        ("logo-alttext-ai.svg", "logo-alttext-ai-128x128.png"),
        ("logo-alttext-ai-white-bg.svg", "logo-alttext-ai-white-bg-128x128.png"),
    ])
    
    print("🔄 Converting SVG logos to PNG...")
    print("")
    
    success_count = 0
    for svg_name, png_name in svg_files:
        svg_path = os.path.join(assets_dir, svg_name)
        png_path = os.path.join(assets_dir, png_name)
        
        if not os.path.exists(svg_path):
            print(f"⚠️  Skipping {svg_name} (file not found)")
            continue
        
        # Determine size from filename
        if "128x128" in png_name:
            size = 128
        else:
            size = 512
        
        print(f"   Converting {svg_name} → {png_name} ({size}x{size})")
        
        if convert_svg_to_png(svg_path, png_path, size):
            file_size = os.path.getsize(png_path) / 1024  # KB
            print(f"      ✅ Created ({file_size:.1f} KB)")
            success_count += 1
        else:
            print(f"      ❌ Failed")
    
    print("")
    if success_count > 0:
        print(f"✅ Successfully created {success_count} PNG file(s)")
        print("")
        print("📋 Stripe-ready logo files:")
        print("   Recommended: assets/logo-alttext-ai-512x512.png")
        print("   Minimum size: assets/logo-alttext-ai-128x128.png")
    else:
        print("❌ No PNG files were created")
        sys.exit(1)

if __name__ == "__main__":
    main()

