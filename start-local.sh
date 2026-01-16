#!/bin/bash

echo "Waiting for Docker to be ready..."
while ! docker info > /dev/null 2>&1; do
  echo "Docker is still starting, waiting..."
  sleep 2
done

echo "Docker is ready! Starting containers..."
cd "$(dirname "$0")"
docker-compose up -d

echo ""
echo "✅ Containers started!"
echo ""
echo "WordPress: http://localhost:8080"
echo "phpMyAdmin: http://localhost:8081"
echo ""
echo "To view logs: docker-compose logs -f"
echo "To stop: docker-compose down"

