#!/usr/bin/env python3
"""
Create a clean WordPress.org submission zip file
"""

import os
import zipfile

# Plugin root directory
PLUGIN_ROOT = os.path.dirname(os.path.abspath(__file__))
ZIP_NAME = "beepbeep-ai-alt-text-generator-4.2.3-wp-submission.zip"

# Files/directories to include
INCLUDE = [
    "beepbeep-ai-alt-text-generator.php",
    "readme.txt",
    "LICENSE",
    "uninstall.php",
    "admin",
    "includes",
    "assets",
    "languages",
    "templates",
]

def should_exclude(rel_path):
    """Check if a file should be excluded"""
    # Normalize path
    path_lower = rel_path.lower().replace('\\', '/')
    
    # Always include readme.txt and LICENSE
    if path_lower == "readme.txt" or path_lower == "license":
        return False
    
    # Exclude patterns
    exclude_keywords = [
        '.ds_store',
        '.git',
        '.gitkeep',
        'node_modules',
        '.zip',
        '.md',
        '.sh',
        '.sql',
        '/dist/',
        '/docs/',
        '/scripts/',
        'beepbeep-ai-alt-text-generator/',  # Build directory
        'docker-compose.yml',
        'package.json',
        'package-lock.json',
        'create_zip.py',
        'create-wp-submission-zip.sh',
    ]
    
    for keyword in exclude_keywords:
        if keyword in path_lower:
            return True
    
    # Exclude certain file extensions
    ext = os.path.splitext(path_lower)[1]
    if ext in ['.md', '.sh', '.sql', '.zip', '.py', '.json', '.lock']:
        return True
    
    return False

def create_zip():
    """Create the WordPress.org submission zip"""
    zip_path = os.path.join(PLUGIN_ROOT, ZIP_NAME)
    
    # Remove existing zip if it exists
    if os.path.exists(zip_path):
        os.remove(zip_path)
        print(f"Removed existing {ZIP_NAME}")
    
    files_added = []
    
    print(f"Creating {ZIP_NAME}...")
    print(f"Plugin root: {PLUGIN_ROOT}\n")
    
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for item in INCLUDE:
            item_path = os.path.join(PLUGIN_ROOT, item)
            
            if not os.path.exists(item_path):
                print(f"⚠️  Warning: {item} not found, skipping...")
                continue
            
            if os.path.isfile(item_path):
                # Add single file
                if not should_exclude(item):
                    zipf.write(item_path, item)
                    files_added.append(item)
                    print(f"✅ Added: {item}")
            elif os.path.isdir(item_path):
                # Add directory recursively
                for root, dirs, files in os.walk(item_path):
                    # Filter out excluded directories before walking
                    dirs[:] = [d for d in dirs if not should_exclude(os.path.relpath(os.path.join(root, d), PLUGIN_ROOT).replace('\\', '/'))]
                    
                    for file in files:
                        file_path = os.path.join(root, file)
                        rel_path = os.path.relpath(file_path, PLUGIN_ROOT).replace('\\', '/')
                        
                        if not should_exclude(rel_path):
                            zipf.write(file_path, rel_path)
                            files_added.append(rel_path)
                            # Show progress for first 10 files, then every 50th file
                            if len(files_added) <= 10 or len(files_added) % 50 == 0:
                                print(f"✅ Added: {rel_path[:70]}{'...' if len(rel_path) > 70 else ''}")
    
    # Get file size
    file_size = os.path.getsize(zip_path)
    size_mb = file_size / (1024 * 1024)
    
    print(f"\n{'='*70}")
    print(f"✅ Successfully created: {ZIP_NAME}")
    print(f"📦 Total files added: {len(files_added)}")
    print(f"📊 File size: {size_mb:.2f} MB ({file_size:,} bytes)")
    print(f"📍 Location: {zip_path}")
    print(f"{'='*70}\n")
    
    return zip_path

if __name__ == "__main__":
    try:
        zip_path = create_zip()
        print("✅ Zip file is ready for WordPress.org submission!\n")
    except Exception as e:
        print(f"❌ Error creating zip: {e}")
        import traceback
        traceback.print_exc()
        exit(1)
