# Release Instructions

Use `git archive` so `.gitattributes` `export-ignore` rules are honored and dev/debug assets stay out of the ZIP.

1. Ensure working tree is clean and version in `beepbeep-ai-alt-text-generator.php`, `readme.txt`, and any constants match the release (currently `4.2.3`).
2. Build the ZIP:
   ```bash
   chmod +x scripts/build-zip.sh
   ./scripts/build-zip.sh
   ```
3. Inspect the archive (spot-check for excluded files):
   ```bash
   unzip -l beepbeep-ai-alt-text-generator.zip | sed -n '1,40p'
   ```
4. Test-install the generated ZIP on a clean WordPress site (PHP 7.4+ and latest WP), verify activate/deactivate/uninstall work without notices.

The `scripts/` directory is excluded from the distributed ZIP, so the build helper stays out of the submission package.
