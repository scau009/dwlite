#!/bin/sh
set -e

# Clear and warm up Symfony cache
echo "Clearing Symfony cache..."
php bin/console cache:clear --no-warmup
echo "Warming up Symfony cache..."
php bin/console cache:warmup

# Execute the main command
exec "$@"