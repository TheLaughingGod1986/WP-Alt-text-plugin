#!/bin/bash
# Trigger WordPress cron manually
# This simulates a visitor hitting wp-cron.php, which triggers scheduled events

echo "Triggering WordPress cron..."
docker exec wp-alt-text-plugin-wordpress-1 curl -s http://localhost/wp-cron.php?doing_wp_cron > /dev/null
echo "✓ WordPress cron triggered"

