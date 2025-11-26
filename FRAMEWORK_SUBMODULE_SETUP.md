# Framework Submodule Migration - Setup Instructions

## Current Status

The framework has been extracted and prepared for submodule migration. The framework code is ready in `/tmp/framework-extraction` with:
- ✅ All framework files
- ✅ Initial commit created
- ✅ Tagged as v1.0.0
- ✅ .gitignore configured

## Step 1: Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `plugin-framework`
3. Owner: `TheLaughingGod1986`
4. Description: "Optti WordPress Plugin Framework - Shared framework for all Optti plugins"
5. Set as **Public** or **Private** (your choice)
6. **DO NOT** initialize with README, .gitignore, or license (we have our own)
7. Click "Create repository"

## Step 2: Push Framework to GitHub

```bash
cd /tmp/framework-extraction
./push-to-github.sh
```

This will:
- Add the GitHub repository as remote
- Push the main branch
- Push the v1.0.0 tag

## Step 3: Complete Submodule Migration

Once the repository is created and pushed, run:

```bash
cd /Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/legacy/WP-Alt-text-plugin
./scripts/migrate-to-submodule.sh
```

This will:
- Verify the repository exists
- Remove the temporary framework directory
- Add framework as a Git submodule
- Checkout v1.0.0
- Commit the submodule reference

## Step 4: Test and Verify

After migration:
1. Test the plugin functionality
2. Verify admin pages load correctly
3. Check that framework classes are accessible
4. Test REST API endpoints
5. Verify styling loads correctly

## Alternative: Manual Setup

If you prefer to do it manually:

```bash
# 1. Push framework (from /tmp/framework-extraction)
cd /tmp/framework-extraction
git remote add origin https://github.com/TheLaughingGod1986/plugin-framework.git
git push -u origin main
git push origin v1.0.0

# 2. Add as submodule (from plugin root)
cd /Users/benjaminoats/Library/CloudStorage/SynologyDrive-File-sync/Coding/legacy/WP-Alt-text-plugin
rm -rf framework
git submodule add https://github.com/TheLaughingGod1986/plugin-framework.git framework
cd framework
git checkout v1.0.0
cd ..
git add .gitmodules framework
git commit -m "Add framework as Git submodule v1.0.0"
```

## Framework Location

The extracted framework is at: `/tmp/framework-extraction`

You can inspect it, modify it, or push it when ready.

