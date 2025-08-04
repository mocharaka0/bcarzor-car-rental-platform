# 🚗 Car Rental Platform - Development Commands
.PHONY: help install dev build test clean deploy

# Default help command
help:
	@echo "🚗 Car Rental Platform Development Commands"
	@echo ""
	@echo "📦 Setup Commands:"
	@echo "  install     Install all dependencies (backend + frontend)"
	@echo "  setup       Complete project setup with database"
	@echo ""
	@echo "🚀 Development Commands:"
	@echo "  dev         Start development servers (backend + frontend)"
	@echo "  backend     Start Laravel backend server"
	@echo "  frontend    Start Next.js frontend server"
	@echo "  mobile      Start React Native mobile development"
	@echo ""
	@echo "🧪 Testing Commands:"
	@echo "  test        Run all tests"
	@echo "  test-backend Run Laravel backend tests"
	@echo "  test-frontend Run Next.js frontend tests"
	@echo ""
	@echo "🐳 Docker Commands:"
	@echo "  docker-up   Start all services with Docker"
	@echo "  docker-down Stop all Docker services"
	@echo "  docker-logs View Docker container logs"
	@echo ""
	@echo "🚢 Deployment Commands:"
	@echo "  build       Build production assets"
	@echo "  deploy      Deploy to production"
	@echo "  clean       Clean build artifacts"

# Installation commands
install:
	@echo "📦 Installing backend dependencies..."
	cd backend && composer install
	@echo "📦 Installing frontend dependencies..."
	cd frontend && npm install
	@echo "✅ All dependencies installed!"

setup: install
	@echo "🔧 Setting up backend environment..."
	cd backend && cp .env.example .env
	cd backend && php artisan key:generate
	cd backend && touch database/database.sqlite
	cd backend && php artisan migrate
	cd backend && php artisan db:seed
	@echo "✅ Project setup complete!"

# Development commands
dev:
	@echo "🚀 Starting development servers..."
	@echo "🔥 Backend will be available at http://localhost:8000"
	@echo "🔥 Frontend will be available at http://localhost:3000"
	$(MAKE) -j2 backend frontend

backend:
	@echo "🚀 Starting Laravel backend server..."
	cd backend && php artisan serve --port=8000

frontend:
	@echo "🚀 Starting Next.js frontend server..."
	cd frontend && npm run dev

mobile:
	@echo "📱 Starting React Native development..."
	cd mobile && npx react-native start

# Testing commands
test:
	@echo "🧪 Running all tests..."
	$(MAKE) test-backend
	$(MAKE) test-frontend

test-backend:
	@echo "🧪 Running Laravel backend tests..."
	cd backend && php artisan test

test-frontend:
	@echo "🧪 Running Next.js frontend tests..."
	cd frontend && npm test

# Docker commands
docker-up:
	@echo "🐳 Starting Docker services..."
	docker-compose up -d
	@echo "✅ All services started!"

docker-down:
	@echo "🐳 Stopping Docker services..."
	docker-compose down
	@echo "✅ All services stopped!"

docker-logs:
	@echo "📋 Viewing Docker logs..."
	docker-compose logs -f

# Build commands
build:
	@echo "🏗️ Building production assets..."
	cd backend && composer install --optimize-autoloader --no-dev
	cd frontend && npm run build
	@echo "✅ Production build complete!"

# Deployment commands
deploy:
	@echo "🚢 Preparing deployment package..."
	./deploy.sh
	@echo "✅ Deployment package ready in deployment/ directory"

# Cleanup commands
clean:
	@echo "🧹 Cleaning build artifacts..."
	rm -rf backend/bootstrap/cache/*
	rm -rf frontend/.next
	rm -rf frontend/out
	rm -rf deployment
	@echo "✅ Cleanup complete!"

# Database commands
db-reset:
	@echo "🗃️ Resetting database..."
	cd backend && php artisan migrate:reset
	cd backend && php artisan migrate
	cd backend && php artisan db:seed
	@echo "✅ Database reset complete!"

db-fresh:
	@echo "🗃️ Fresh database migration..."
	cd backend && php artisan migrate:fresh --seed
	@echo "✅ Fresh database ready!"

# Code quality commands
lint:
	@echo "🔍 Running code linting..."
	cd backend && ./vendor/bin/phpcs
	cd frontend && npm run lint
	@echo "✅ Linting complete!"

format:
	@echo "✨ Formatting code..."
	cd backend && ./vendor/bin/php-cs-fixer fix
	cd frontend && npm run format
	@echo "✅ Code formatting complete!"