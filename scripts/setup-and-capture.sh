#!/bin/bash
# Setup demo content and capture screenshots.
# Run from project root. Requires Docker (WordPress on 8080) and Node.

set -e
cd "$(dirname "$0")/.."
CONTAINER="${WP_CONTAINER:-wp-alt-text-plugin-wordpress-1}"

echo "=== 1. Setup demo content ==="
docker cp scripts/setup-demo-content.php "$CONTAINER:/var/www/html/setup-demo-content.php"
docker exec "$CONTAINER" php /var/www/html/setup-demo-content.php
docker exec "$CONTAINER" rm /var/www/html/setup-demo-content.php

echo ""
echo "=== 2. Capture screenshots ==="
node scripts/capture-screenshots.js

echo ""
echo "Done. Screenshots saved to assets/wordpress-org/screenshots/"
