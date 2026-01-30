#!/bin/bash

set -e

echo "🎯 Select Development Setup"
echo "=============================="

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create .env if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Generate app key if not set
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=\"\"" .env; then
    echo "🔑 Generating application key..."
    # We'll generate it after containers are up
    NEED_KEY=true
fi

# Build and start containers
echo "🐳 Building and starting Docker containers..."
docker compose up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 5

# Generate app key if needed
if [ "$NEED_KEY" = true ]; then
    echo "🔑 Generating application key..."
    docker compose exec -T app php artisan key:generate
fi

# Run migrations
echo "📊 Running database migrations..."
docker compose exec -T app php artisan migrate --force

# Clear caches
echo "🧹 Clearing caches..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear

echo ""
echo "✅ Setup complete!"
echo ""
echo "🌐 API Server:     http://localhost:${APP_PORT:-8000}"
echo "🔌 WebSocket:      ws://localhost:${REVERB_PORT:-8080}"
echo "🗄️  Database:       localhost:${DB_PORT:-5432}"
echo ""
echo "📱 For mobile app, update mobileapp/.env with:"
echo "   EXPO_PUBLIC_API_URL=http://<YOUR_IP>:${APP_PORT:-8000}/api/v1"
echo "   EXPO_PUBLIC_REVERB_HOST=<YOUR_IP>"
echo ""
echo "Useful commands:"
echo "  docker compose logs -f        # View logs"
echo "  docker compose exec app bash  # Shell into app container"
echo "  docker compose down            # Stop containers"
echo "  docker compose down -v         # Stop and remove volumes"
