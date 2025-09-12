# Auctic Mobile Mock Server

A Laravel-based mock server for testing the Auctic mobile application. It provides comprehensive API endpoints for media uploads, catalog management, and sophisticated test scenario management for automated testing with Maestro.

## Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js & npm
- Redis (for test scenarios)
- SQLite or MySQL

### Installation

1. Clone the repository
```bash
git clone <repository-url>
cd auctic-mobile-mock-server
```

2. Install dependencies
```bash
composer install
npm install
```

3. Setup environment
```bash
cp .env.example .env
php artisan key:generate
```

4. Run migrations
```bash
php artisan migrate
```

5. Start development server
```bash
composer dev
```

This will start:
- Laravel server on http://localhost:8000
- Queue worker for background jobs
- Real-time log viewer (Pail)
- Vite dev server for frontend assets

## Configuration Requirements

### PHP Settings for Media Upload

To handle media file uploads properly, you need to configure the following PHP settings in your Herd or PHP configuration:

- **`upload_max_filesize`**: Set to at least `50M` (or higher based on your needs)
- **`post_max_size`**: Set to at least `50M` (or higher, should be >= upload_max_filesize)
- **`memory_limit`**: Recommended `256M` or higher
- **`max_execution_time`**: Consider increasing to `300` for large uploads

If you encounter a **413 Request Entity Too Large** error during file uploads, these settings need to be increased.

#### For Laravel Herd Users:
1. Open Herd's PHP configuration
2. Update the values mentioned above
3. Restart PHP services

## API Endpoints

### Authentication
- `GET /mobile/profile` - Generate JWT token for mobile app authentication

### Catalog Management
- `GET /mobile-api/v1/catalog/hydrate` - Get full catalog data (events, listings, categories, sellers)
- `GET /mobile-api/v1/catalog/sync` - Incremental catalog synchronization
- `POST /mobile-api/v1/catalog/changes` - Submit catalog changes with media attachments

### Media Upload
- `POST /mobile-api/v1/catalog/request-upload` - Request S3-compatible upload URL
- `PUT /mock-s3-upload/{uploadId}` - Mock S3 upload endpoint for testing
- `POST /api/listings/{listingId}/media` - Associate uploaded media with listing
- `GET /api/listings/{listingId}/media/{collection}` - Get listing media

### User
- `GET /mobile-api/v1/user` - Get user profile information

## Test Scenarios System

The mock server includes a sophisticated test scenario system designed for Maestro automation testing:

### Features
- **Session-based isolation**: Each test run gets a unique session
- **Dynamic responses**: Different API responses based on active scenario
- **Mid-test switching**: Change scenarios during test execution
- **Configuration-driven**: Define scenarios in YAML/PHP configuration files

### Test Scenario Control API
- `POST /test-scenarios/activate` - Activate a test scenario
- `GET /test-scenarios/current` - Get current active scenario
- `POST /test-scenarios/switch` - Switch to different scenario
- `POST /test-scenarios/reset` - Reset test session
- `GET /test-scenarios/available` - List all available scenarios

### Example Maestro Integration
```yaml
# Activate test scenario
- http:
    url: "${MOCK_SERVER_URL}/test-scenarios/activate"
    method: POST
    body:
      scenario: "empty_catalog"
    saveResponse: testSession

# Launch app with session
- launchApp:
    arguments:
      TEST_SESSION_ID: "${testSession.session_id}"
      TEST_SCENARIO: "empty_catalog"
```

## Development

### Available Commands

#### Development Server
```bash
composer dev           # Run all services concurrently
php artisan serve     # Laravel server only
npm run dev          # Vite dev server only
php artisan queue:listen  # Queue worker only
php artisan pail     # Real-time log viewer
```

#### Testing
```bash
composer test        # Run all tests
php artisan test    # Run PHPUnit/Pest tests
php artisan test --filter=TestName  # Run specific test
```

#### Build & Maintenance
```bash
npm run build       # Build frontend assets
php artisan migrate # Run database migrations
php artisan cache:clear    # Clear cache
php artisan config:clear   # Clear config cache
```

### Project Structure
```
auctic-mobile-mock-server/
├── src/                    # Main application code (MockServer namespace)
│   ├── Auth/              # JWT authentication
│   ├── MobileApi/         # API controllers
│   ├── Services/          # Business logic
│   └── TestScenarios/     # Test scenario system
├── routes/
│   └── mobile-api.php     # API route definitions
├── tests/                 # Test files (Pest PHP)
├── config/
│   └── test-scenarios/    # Scenario configurations
└── docs/                  # Documentation
    ├── TEST_SCENARIO_ARCHITECTURE.md
    └── TEST_SCENARIOS_USAGE.md
```

## Testing with Postman

A Postman collection is included: `Auctic_Mobile_API_Mock.postman_collection.json`

Import this collection to test all API endpoints with pre-configured requests.

## Documentation

- [Test Scenario Architecture](docs/TEST_SCENARIO_ARCHITECTURE.md) - Detailed architecture documentation
- [Test Scenarios Usage](docs/TEST_SCENARIOS_USAGE.md) - How to use test scenarios

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
