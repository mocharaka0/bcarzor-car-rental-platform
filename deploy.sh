#!/bin/bash

# 🚗 Car Rental Platform - Deployment Script
# This script prepares the application for shared hosting deployment

set -e  # Exit on any error

echo "🚀 Starting deployment preparation for Car Rental Platform..."
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DEPLOYMENT_DIR="deployment"
API_DIR="$DEPLOYMENT_DIR/api"
FRONTEND_DIR="$DEPLOYMENT_DIR/frontend"
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Validate dependencies
print_status "Checking dependencies..."

if ! command_exists php; then
    print_error "PHP is not installed. Please install PHP 8.4 or higher."
    exit 1
fi

if ! command_exists composer; then
    print_error "Composer is not installed. Please install Composer."
    exit 1
fi

if ! command_exists node; then
    print_error "Node.js is not installed. Please install Node.js 18 or higher."
    exit 1
fi

if ! command_exists npm; then
    print_error "NPM is not installed. Please install NPM."
    exit 1
fi

print_success "All dependencies are available."

# Create deployment directory
print_status "Creating deployment directory..."
rm -rf "$DEPLOYMENT_DIR"
mkdir -p "$API_DIR"
mkdir -p "$FRONTEND_DIR"
mkdir -p "$BACKUP_DIR"

# ==========================================
# BACKEND DEPLOYMENT PREPARATION
# ==========================================

print_status "Preparing Laravel backend for deployment..."

# Check if backend directory exists
if [ ! -d "backend" ]; then
    print_error "Backend directory not found. Please ensure the Laravel backend is in the 'backend' directory."
    exit 1
fi

cd backend

# Install production dependencies
print_status "Installing backend production dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction

# Generate optimized autoloader
print_status "Optimizing autoloader..."
composer dump-autoload --optimize

# Check if .env exists
if [ ! -f ".env" ]; then
    print_warning ".env file not found. Copying from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Clear and cache configurations
print_status "Optimizing Laravel configuration..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache configurations for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations (with confirmation)
read -p "Do you want to run database migrations? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_status "Running database migrations..."
    php artisan migrate --force
fi

# Copy backend files to deployment directory
print_status "Copying backend files..."
cd ..
cp -r backend/* "$API_DIR/"

# Remove unnecessary files from API deployment
print_status "Cleaning up backend deployment..."
rm -rf "$API_DIR/tests"
rm -rf "$API_DIR/storage/app/public"
rm -rf "$API_DIR/storage/logs/*"
rm -rf "$API_DIR/node_modules"
rm -rf "$API_DIR/.git"
rm -f "$API_DIR/.env"
rm -f "$API_DIR/.env.example"
rm -f "$API_DIR/README.md"

# Create necessary directories
mkdir -p "$API_DIR/storage/logs"
mkdir -p "$API_DIR/storage/app/public"
mkdir -p "$API_DIR/bootstrap/cache"

# Set proper permissions
print_status "Setting proper permissions for backend..."
chmod -R 755 "$API_DIR"
chmod -R 775 "$API_DIR/storage"
chmod -R 775 "$API_DIR/bootstrap/cache"

# ==========================================
# FRONTEND DEPLOYMENT PREPARATION
# ==========================================

print_status "Preparing Next.js frontend for deployment..."

# Check if frontend directory exists
if [ ! -d "frontend" ]; then
    print_error "Frontend directory not found. Please ensure the Next.js frontend is in the 'frontend' directory."
    exit 1
fi

cd frontend

# Install dependencies
print_status "Installing frontend dependencies..."
npm ci --only=production

# Build the application
print_status "Building Next.js application..."
npm run build

# Copy frontend files to deployment directory
print_status "Copying frontend files..."
cd ..
cp -r frontend/* "$FRONTEND_DIR/"

# Remove unnecessary files from frontend deployment
print_status "Cleaning up frontend deployment..."
rm -rf "$FRONTEND_DIR/node_modules"
rm -rf "$FRONTEND_DIR/.git"
rm -rf "$FRONTEND_DIR/.next/cache"
rm -f "$FRONTEND_DIR/.env.local"
rm -f "$FRONTEND_DIR/.env.development.local"
rm -f "$FRONTEND_DIR/README.md"

# ==========================================
# CREATE DEPLOYMENT DOCUMENTATION
# ==========================================

print_status "Creating deployment documentation..."

cat > "$DEPLOYMENT_DIR/DEPLOYMENT_GUIDE.md" << 'EOF'
# 🚗 Car Rental Platform - Deployment Guide

This package contains the production-ready files for your Car Rental Platform.

## 📁 Directory Structure

```
deployment/
├── api/                 # Laravel Backend (upload to your web root)
├── frontend/           # Next.js Frontend (upload to frontend subdomain)
└── DEPLOYMENT_GUIDE.md # This file
```

## 🚀 Deployment Steps

### 1. Backend Deployment (API)

1. **Upload Files**
   - Upload the contents of `api/` directory to your web server's document root
   - Ensure the `public/` folder is your web server's document root

2. **Set Permissions**
   ```bash
   chmod -R 755 /path/to/your/api
   chmod -R 775 /path/to/your/api/storage
   chmod -R 775 /path/to/your/api/bootstrap/cache
   ```

3. **Environment Configuration**
   ```bash
   cd /path/to/your/api
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Setup**
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```

5. **Final Optimization**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

### 2. Frontend Deployment

1. **Upload Files**
   - Upload the contents of `frontend/` directory to your frontend hosting
   - Configure your web server to serve the Next.js application

2. **Environment Variables**
   - Set `NEXT_PUBLIC_API_URL` to your backend API URL
   - Configure other environment variables as needed

### 3. Post-Deployment

1. **SSL Certificate**
   - Enable HTTPS for both frontend and backend
   - Update `APP_URL` in backend `.env` to use HTTPS

2. **Admin Account**
   - Access `/admin` to set up your admin account
   - Configure payment gateways and API keys

3. **Testing**
   - Test all functionality including payments
   - Verify mobile app API endpoints

## 🔧 Configuration

### Required Environment Variables

See `.env.example` for all configuration options.

### Payment Gateway Setup

1. **Stripe**: Add your live API keys
2. **PayPal**: Configure for production mode
3. **Bank Transfer**: Update bank account details

### API Keys Required

- Google Maps API Key
- OpenAI API Key (for AI features)
- Firebase Server Key (for push notifications)

## 📞 Support

For deployment support, refer to the main repository documentation or contact support.
EOF

# ==========================================
# CREATE .HTACCESS FOR SHARED HOSTING
# ==========================================

print_status "Creating .htaccess for shared hosting..."

cat > "$API_DIR/public/.htaccess" << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'"
</IfModule>

# Disable directory browsing
Options -Indexes

# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.lock">
    Order allow,deny
    Deny from all
</Files>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
</IfModule>
EOF

# ==========================================
# FINAL STEPS
# ==========================================

# Create deployment summary
print_status "Creating deployment summary..."

DEPLOYMENT_SIZE=$(du -sh "$DEPLOYMENT_DIR" | cut -f1)
API_SIZE=$(du -sh "$API_DIR" | cut -f1)
FRONTEND_SIZE=$(du -sh "$FRONTEND_DIR" | cut -f1)

cat > "$DEPLOYMENT_DIR/DEPLOYMENT_SUMMARY.txt" << EOF
🚗 Car Rental Platform - Deployment Summary
============================================

Deployment prepared on: $(date)
Total package size: $DEPLOYMENT_SIZE

Components:
- Backend API: $API_SIZE
- Frontend: $FRONTEND_SIZE

Files ready for upload in:
- $DEPLOYMENT_DIR/

Next steps:
1. Upload API files to your web server
2. Upload frontend files to your frontend hosting
3. Configure environment variables
4. Run database migrations
5. Test the application

For detailed instructions, see DEPLOYMENT_GUIDE.md
EOF

print_success "Deployment preparation completed!"
print_status "Package location: ./$DEPLOYMENT_DIR/"
print_status "Package size: $DEPLOYMENT_SIZE"
echo ""
print_status "Next steps:"
echo "  1. Upload the API files to your web server"
echo "  2. Upload the frontend files to your frontend hosting"
echo "  3. Follow the instructions in DEPLOYMENT_GUIDE.md"
echo ""
print_success "🚀 Your Car Rental Platform is ready for deployment!"