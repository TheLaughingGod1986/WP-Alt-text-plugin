#!/bin/bash
# Keep queue processing running by periodically triggering WordPress cron
# This is needed because WordPress cron doesn't run automatically in Docker without traffic

echo "Starting queue processor watchdog..."
echo "This will trigger queue processing every 30 seconds"
echo "Press Ctrl+C to stop"
echo ""

while true; do
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Processing queue..."
    
    docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/opptiai-alt/scripts/check-and-process-queue.php 2>&1 | grep -E "(Queue Status|Pending|Processing|Failed|Completed|✓|⚠|Error)"
    
    sleep 30
done

