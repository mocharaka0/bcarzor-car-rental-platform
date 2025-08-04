# 🚗 Car Rental SaaS Platform

A comprehensive, multi-tenant car rental platform built with Laravel 12 and Next.js 14, featuring AI-powered insights, real-time GPS tracking, and multiple payment gateways.

## 🌟 Features

### 🎯 Core Features
- **Multi-Role System**: Admin, Agency, User, and Driver dashboards
- **Real-time GPS Tracking**: Driver location tracking via mobile app
- **AI-Powered Insights**: OpenAI integration for pricing, analytics, and support
- **Multiple Payment Gateways**: Stripe, PayPal, and Bank Transfer with fallbacks
- **Mobile-Ready APIs**: Complete API support for React Native mobile apps
- **Admin Panel**: Comprehensive CRUD operations for all entities
- **Multi-language Support**: Ready for internationalization

### 💳 Payment System
- **Stripe Integration**: Credit/debit card processing
- **PayPal Integration**: PayPal payments with sandbox/live mode
- **Bank Transfer**: Manual bank transfer with instructions
- **Automatic Fallback**: Falls back to bank transfer if gateways fail
- **Commission Tracking**: Automatic 3% commission calculation

### 🛡️ Security
- **Laravel Sanctum**: API authentication with tokens
- **Spatie Permissions**: Role-based access control
- **CSRF Protection**: Cross-site request forgery protection
- **XSS Protection**: Cross-site scripting prevention
- **Security Headers**: Comprehensive security headers
- **Rate Limiting**: API rate limiting and throttling

## 🚀 Quick Start

### Prerequisites
- PHP 8.4+
- Node.js 18+
- Composer
- NPM/Yarn

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/mocharaka0/bcarzor-car-rental-platform.git
cd bcarzor-car-rental-platform
```

2. **Setup Backend**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed
```

3. **Setup Frontend**
```bash
cd ../frontend
npm install
npm run dev
```

## 🤖 AI Features

### OpenAI Integration
- **Smart Pricing**: AI-powered pricing suggestions
- **Content Generation**: Automated descriptions and responses
- **Analytics**: AI-driven insights and forecasting
- **Support**: Automated ticket responses and classification

---

**Built with ❤️ for the car rental industry**