#!/bin/bash
set -e

echo "========================================"
echo "  Select - First Time Setup"
echo "========================================"

# Create .env first (needed for composer's package:discover)
if [ ! -f "/var/www/.env" ]; then
    echo ""
    echo "==> Creating .env from .env.example..."
    cp /var/www/.env.example /var/www/.env
fi

# Install Composer dependencies if needed
if [ ! -f "/var/www/vendor/autoload.php" ]; then
    echo ""
    echo "==> Installing Composer dependencies..."
    composer install --no-interaction
else
    echo ""
    echo "==> Composer dependencies already installed"
fi

# Generate app key if not set
if grep -q "^APP_KEY=$" /var/www/.env; then
    echo ""
    echo "==> Generating application key..."
    php artisan key:generate
fi

# Run migrations
echo ""
echo "==> Running database migrations..."
php artisan migrate --force

echo ""
echo "========================================"
echo "  Setup complete!"
echo "========================================"
