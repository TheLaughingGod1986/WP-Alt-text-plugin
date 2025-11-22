# Create WordPress.org Submission Zip

## Quick Method (Recommended)

Open Terminal in this directory and run:

```bash
python3 create_zip.py
```

## Alternative Method (if Python doesn't work)

```bash
cd /Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/legacy/WP-Alt-text-plugin
rm -f beepbeep-ai-alt-text-generator-4.2.3-wp-submission.zip
zip -r beepbeep-ai-alt-text-generator-4.2.3-wp-submission.zip \
  beepbeep-ai-alt-text-generator.php \
  readme.txt \
  LICENSE \
  uninstall.php \
  admin \
  includes \
  assets \
  languages \
  templates \
  -x "*.DS_Store" "*/.git/*" "*/.gitkeep" "*node_modules/*" "*.zip" "*.md" "*.sh" "*.sql" "dist/*" "docs/*" "scripts/*" "beepbeep-ai-alt-text-generator/*" "docker-compose.yml" "package.json" "package-lock.json" "*.py"
```

## What's Included

✅ Main plugin file  
✅ readme.txt (WordPress.org required)  
✅ LICENSE file  
✅ uninstall.php  
✅ admin/ directory  
✅ includes/ directory  
✅ assets/ directory  
✅ languages/ directory (with .pot file)  
✅ templates/ directory  

## What's Excluded

❌ Development files (.md, .sh, .sql, .py)  
❌ Build directories (node_modules, dist, docs, scripts)  
❌ Git files (.git, .gitkeep)  
❌ Existing zip files  

## Output

The zip file will be created as:
**`beepbeep-ai-alt-text-generator-4.2.3-wp-submission.zip`**

This is ready to upload to WordPress.org!

