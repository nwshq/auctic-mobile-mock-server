# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based mock server for testing the Auctic mobile application. It provides mock API endpoints for media uploads, catalog management, and test scenario management for automated testing with Maestro.

## Key Commands

### Development
- `composer dev` - Runs Laravel server, queue listener, logs viewer (pail), and Vite dev server concurrently
- `php artisan serve` - Run Laravel development server
- `npm run dev` - Run Vite dev server
- `php artisan queue:listen --tries=1` - Run queue worker
- `php artisan pail --timeout=0` - View real-time logs

### Testing
- `composer test` - Run all tests
- `php artisan test` - Run PHPUnit/Pest tests
- `php artisan test --filter=TestName` - Run specific test

### Build & Dependencies
- `npm run build` - Build frontend assets with Vite
- `composer install` - Install PHP dependencies
- `npm install` - Install Node dependencies

### Laravel Artisan
- `php artisan migrate` - Run database migrations
- `php artisan cache:clear` - Clear application cache
- `php artisan config:clear` - Clear configuration cache
- `php artisan key:generate` - Generate application key

## Architecture

### Directory Structure
- **`src/`** - Main application code (custom namespace `MockServer\`)
  - `Auth/` - JWT token generation controllers
  - `MobileApi/` - Mobile API controllers (Catalog, Changes, MediaUpload, User)
  - `Services/` - Business logic services
  - `TestScenarios/` - Test scenario management system
- **`routes/mobile-api.php`** - Mobile API route definitions
- **`app/`** - Standard Laravel application directory

### Key API Endpoints
- `GET /mobile/profile` - Generate JWT token
- `GET /mobile-api/v1/catalog/hydrate` - Full catalog data
- `GET /mobile-api/v1/catalog/sync` - Incremental catalog sync
- `POST /mobile-api/v1/catalog/changes` - Submit catalog changes
- `POST /mobile-api/v1/catalog/request-upload` - Request media upload URL
- `PUT /mock-s3-upload/{uploadId}` - Mock S3 upload endpoint

### Test Scenarios System
The server implements a sophisticated test scenario system for Maestro automation:
- Session-based test isolation with Redis/Cache storage
- Dynamic response variations based on active scenarios
- Control API at `/test-scenarios/*` for scenario management
- Middleware that intercepts requests and applies scenario-based responses
- Configuration-driven scenarios in `config/test-scenarios/`

### Environment Configuration
Key environment variables:
- `JWT_ENCRYPTION_KEY` - Required for JWT token generation
- `JWT_KEY` - Required for JWT signing
- `TEST_SCENARIOS_ENABLED` - Enable/disable test scenario system
- `TEST_SESSION_TTL` - Test session timeout (default: 7200 seconds)

### PHP Configuration Requirements
For media uploads to work properly:
- `upload_max_filesize`: At least 50M
- `post_max_size`: At least 50M
- `memory_limit`: 256M or higher
- `max_execution_time`: 300 for large uploads

## Testing Approach
- Uses Pest PHP testing framework
- Test files located in `tests/Feature/` and `tests/Unit/`
- Mock server designed specifically for Maestro UI automation tests
- Test scenarios allow dynamic response variations without modifying mobile app code