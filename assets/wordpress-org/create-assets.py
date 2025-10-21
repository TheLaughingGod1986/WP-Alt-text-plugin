#!/usr/bin/env python3
"""
Create WordPress.org assets from templates
This script creates placeholder PNG files for WordPress.org submission
"""

import os
from PIL import Image, ImageDraw, ImageFont
import io

def create_banner(width, height, filename):
    """Create a banner image with gradient background"""
    # Create image with gradient background
    img = Image.new('RGB', (width, height), color='#667eea')
    draw = ImageDraw.Draw(img)
    
    # Create gradient effect
    for y in range(height):
        ratio = y / height
        r = int(102 + (118 - 102) * ratio)  # 667eea to 764ba2
        g = int(126 + (75 - 126) * ratio)
        b = int(234 + (162 - 234) * ratio)
        draw.line([(0, y), (width, y)], fill=(r, g, b))
    
    # Add text
    try:
        # Try to use a system font
        font_large = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", 32)
        font_medium = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", 18)
        font_small = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", 14)
    except:
        # Fallback to default font
        font_large = ImageFont.load_default()
        font_medium = ImageFont.load_default()
        font_small = ImageFont.load_default()
    
    # Title
    draw.text((100, 40), "AI Alt Text Generator", fill='white', font=font_large)
    draw.text((100, 80), "Automatically generate accessible alt text", fill='white', font=font_medium)
    
    # Features
    features = ["✓ 10 free generations per month", "✓ Bulk processing", "✓ SEO optimized"]
    y_start = 120
    for i, feature in enumerate(features):
        draw.text((100, y_start + i * 25), feature, fill='white', font=font_small)
    
    # CTA Button
    button_rect = [100, 200, 240, 240]
    draw.rectangle(button_rect, fill='white', outline='white')
    draw.text((170, 210), "Get Started Free", fill='#667eea', font=font_medium)
    
    # Save image
    img.save(filename, 'PNG')
    print(f"Created {filename}")

def create_icon(size, filename):
    """Create an icon image"""
    img = Image.new('RGB', (size, size), color='#667eea')
    draw = ImageDraw.Draw(img)
    
    # Create circular background
    margin = 10
    draw.ellipse([margin, margin, size-margin, size-margin], fill='#667eea')
    
    # Add AI text
    try:
        font = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", size//4)
    except:
        font = ImageFont.load_default()
    
    text = "AI"
    bbox = draw.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]
    
    x = (size - text_width) // 2
    y = (size - text_height) // 2
    draw.text((x, y), text, fill='white', font=font)
    
    # Save image
    img.save(filename, 'PNG')
    print(f"Created {filename}")

def create_screenshot(width, height, filename, title, description):
    """Create a screenshot placeholder"""
    img = Image.new('RGB', (width, height), color='#f0f0f1')
    draw = ImageDraw.Draw(img)
    
    # Add title
    try:
        font_large = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", 24)
        font_medium = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", 16)
    except:
        font_large = ImageFont.load_default()
        font_medium = ImageFont.load_default()
    
    # Center the text
    bbox = draw.textbbox((0, 0), title, font=font_large)
    text_width = bbox[2] - bbox[0]
    x = (width - text_width) // 2
    y = height // 2 - 50
    
    draw.text((x, y), title, fill='#1d2327', font=font_large)
    
    bbox = draw.textbbox((0, 0), description, font=font_medium)
    text_width = bbox[2] - bbox[0]
    x = (width - text_width) // 2
    y += 40
    
    draw.text((x, y), description, fill='#646970', font=font_medium)
    
    # Save image
    img.save(filename, 'PNG')
    print(f"Created {filename}")

def main():
    """Create all WordPress.org assets"""
    print("Creating WordPress.org assets...")
    
    # Create screenshots directory
    os.makedirs('screenshots', exist_ok=True)
    
    # Create banner assets
    create_banner(772, 250, 'banner-772x250.png')
    create_banner(1544, 500, 'banner-1544x500.png')
    
    # Create icon assets
    create_icon(128, 'icon-128x128.png')
    create_icon(256, 'icon-256x256.png')
    
    # Create screenshot placeholders
    screenshots = [
        ('screenshots/screenshot-1.png', 'Dashboard', 'Main dashboard with usage stats and bulk optimization'),
        ('screenshots/screenshot-2.png', 'ALT Library', 'Image library with alt text and quality scores'),
        ('screenshots/screenshot-3.png', 'Settings', 'Plugin settings and configuration options'),
        ('screenshots/screenshot-4.png', 'Media Library', 'WordPress media library integration'),
        ('screenshots/screenshot-5.png', 'Notifications', 'Toast notifications and user feedback'),
        ('screenshots/screenshot-6.png', 'Upgrade Modal', 'Pro upgrade prompt and pricing')
    ]
    
    for filename, title, description in screenshots:
        create_screenshot(1200, 800, filename, title, description)
    
    print("\nWordPress.org assets created successfully!")
    print("\nCreated files:")
    print("  - banner-772x250.png")
    print("  - banner-1544x500.png") 
    print("  - icon-128x128.png")
    print("  - icon-256x256.png")
    print("  - screenshots/screenshot-1.png through screenshot-6.png")
    print("\nYou can now submit your plugin to WordPress.org!")

if __name__ == "__main__":
    main()
