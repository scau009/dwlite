#!/bin/sh
set -e

# Generate JWT keys if they don't exist
if [ ! -f /app/config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    mkdir -p /app/config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
    echo "JWT keys generated successfully"
fi

# Clear and warm up Symfony cache
echo "Clearing Symfony cache..."
php bin/console cache:clear --no-warmup
echo "Warming up Symfony cache..."
php bin/console cache:warmup

# Execute the main command
exec "$@"