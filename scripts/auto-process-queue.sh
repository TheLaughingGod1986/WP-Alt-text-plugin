#!/bin/bash
# Automatically process the queue by triggering WordPress cron
# Run this in the background to keep the queue moving

INTERVAL=${1:-30}  # Default 30 seconds, can be overridden

echo "Starting automatic queue processor (every ${INTERVAL} seconds)"
echo "Press Ctrl+C to stop"
echo ""

while true; do
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Trigger WordPress cron which will process scheduled queue events
    docker exec wp-alt-text-plugin-wordpress-1 curl -s http://localhost/wp-cron.php?doing_wp_cron > /dev/null 2>&1
    
    # Also directly trigger the queue processor
    docker exec wp-alt-text-plugin-wordpress-1 php /var/www/html/wp-content/plugins/ai-alt-gpt/scripts/check-and-process-queue.php 2>&1 | grep -E "(Pending|Processing|Failed|Completed|✓|⚠)" | head -5
    
    echo "[$TIMESTAMP] Waiting ${INTERVAL} seconds..."
    sleep $INTERVAL
done

