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

# Fix storage permissions
echo ""
echo "==> Setting storage permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Run migrations
echo ""
echo "==> Running database migrations..."
php artisan migrate --force

# Import gullkorn legacy data if tables are empty or missing
GULLKORN_COUNT=$(php artisan tinker --execute="try { echo \DB::table('gullkorn_clean')->count(); } catch (\Exception \$e) { echo '0'; }" 2>/dev/null | tail -1)
if [ "$GULLKORN_COUNT" = "0" ] || [ -z "$GULLKORN_COUNT" ]; then
    echo ""
    echo "==> Importing gullkorn legacy data..."
    php artisan gullkorn:import
else
    echo ""
    echo "==> Gullkorn data already imported ($GULLKORN_COUNT rows)"
fi

echo ""
echo "========================================"
echo "  Setup complete!"
echo "========================================"
