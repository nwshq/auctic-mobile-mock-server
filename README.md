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

### Camera Performance Analysis API
These endpoints are only available when the `camera-performance-test` scenario is active:

- `GET /api/test-scenarios/camera-performance/analysis` - Get detailed analysis of upload and changes requests
- `POST /api/test-scenarios/camera-performance/clear` - Clear tracking data and reinitialize

### Rotation Test Analysis API
These endpoints are only available when the `rotation-test` scenario is active:

- `GET /api/test-scenarios/rotation-test/analysis` - Get analysis of media changes during device rotation
- `POST /api/test-scenarios/rotation-test/clear` - Clear tracking data and reinitialize

### Remove Listing Test Analysis API
These endpoints are only available when the `remove-listing-test` scenario is active:

- `GET /api/test-scenarios/remove-listing-test/analysis` - Get analysis of listing and media removals
- `POST /api/test-scenarios/remove-listing-test/clear` - Clear tracking data and reinitialize
- `GET /api/test-scenarios/remove-listing-test/timeline` - Get detailed removal timeline

#### Analysis Endpoint
Returns comprehensive metrics about media uploads and changes during a camera performance test session.

**Request:**
```bash
GET /api/test-scenarios/camera-performance/analysis
Headers:
  X-Test-Session-ID: {session_id}
# OR
GET /api/test-scenarios/camera-performance/analysis?test_session_id={session_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": "maestro_session_xxx",
    "started_at": "2025-09-12T20:00:00Z",
    "analysis_at": "2025-09-12T20:30:00Z",
    "upload_requests": {
      "total_requests": 26,
      "total_media_items": 28,
      "unique_media_items": 25,
      "duplicate_items": 3,
      "unique_identifiers": ["id1", "id2", ...],
      "duplicates": {"id5": 2, "id10": 2}
    },
    "changes_requests": {
      "total_requests": 5,
      "total_media_items": 25,
      "unique_media_items": 25,
      "unique_identifiers": ["temp_id1", "temp_id2", ...]
    },
    "timeline": {
      "upload_requests": [...],
      "changes_requests": [...]
    }
  },
  "summary": {
    "total_unique_uploads": 25,
    "total_upload_requests": 26,
    "total_duplicate_uploads": 3,
    "duplicate_upload_identifiers": {
      "unique_id_5": 2,
      "unique_id_10": 2,
      "unique_id_15": 2
    },
    "total_unique_changes": 25,
    "total_changes_requests": 5,
    "has_duplicates": true
  }
}
```

#### Clear Tracking Endpoint
Clears all tracking data for the current session and reinitializes tracking.

**Request:**
```bash
POST /api/test-scenarios/camera-performance/clear
Headers:
  X-Test-Session-ID: {session_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Tracking data cleared and re-initialized",
  "session_id": "maestro_session_xxx"
}
```

### Rotation Test Analysis Details

#### Analysis Endpoint
Returns comprehensive metrics about media changes during device rotation testing.

**Request:**
```bash
GET /api/test-scenarios/rotation-test/analysis
Headers:
  X-Test-Session-ID: {session_id}
# OR
GET /api/test-scenarios/rotation-test/analysis?test_session_id={session_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": "maestro_session_xxx",
    "started_at": "2025-09-17T22:03:00Z",
    "analysis_at": "2025-09-17T22:04:00Z",
    "media_changes": {
      "total_changes": 2,
      "total_added": 1,
      "total_removed": 1,
      "unique_added": 1,
      "unique_removed": 1,
      "added_identifiers": ["1e9dfd0f-63bf-42d1-85b7-173af42f2f7d"],
      "removed_identifiers": ["72054"],
      "matches_expected_pattern": true,
      "expected_pattern": "1 new media and 1 removed"
    },
    "rotation_events": {
      "total_events": 3,
      "events": [
        {
          "timestamp": "2025-09-17T22:03:00Z",
          "orientation": "portrait",
          "media_state": ["media_001"]
        },
        {
          "timestamp": "2025-09-17T22:03:15Z",
          "orientation": "landscape",
          "media_state": ["media_001"]
        },
        {
          "timestamp": "2025-09-17T22:03:50Z",
          "orientation": "portrait",
          "media_state": ["media_002"]
        }
      ]
    },
    "timeline": {
      "media_changes": [
        {
          "timestamp": "2025-09-17T22:03:32Z",
          "added_count": 0,
          "removed_count": 1,
          "removed_identifiers": ["72054"]
        },
        {
          "timestamp": "2025-09-17T22:03:50Z",
          "added_count": 1,
          "removed_count": 0,
          "added_identifiers": ["1e9dfd0f-63bf-42d1-85b7-173af42f2f7d"]
        }
      ]
    }
  },
  "summary": {
    "total_unique_added": 1,
    "total_unique_removed": 1,
    "matches_expected_pattern": true,
    "expected_pattern": "1 new media and 1 removed",
    "rotation_events_count": 3
  }
}
```

**Key Points:**
- The test validates that exactly 1 media is added and 1 is removed during rotation
- Changes may arrive in separate batches (deletion first, then creation after upload)
- The `matches_expected_pattern` field indicates if the test passed
- Timeline shows when each change occurred

### How Rotation Test Flow Works

The rotation test scenario tracks media changes that occur when a device is rotated while capturing media. Here's the typical flow:

1. **Activation**: Test scenario is activated via `/test-scenarios/activate` with `rotation-test`
2. **Initial State**: User takes a photo in portrait mode
3. **Rotation**: Device is rotated to landscape
4. **Media Deletion**: Original media is marked for deletion and sent immediately to `/catalog/changes`:
   ```json
   {
     "changes": {
       "media": [
         {"action": "delete", "id": 72054, "last_modified": "2025-09-17 22:03:32"}
       ]
     }
   }
   ```
5. **Media Recreation**: New rotated media is uploaded to S3
6. **Media Creation**: After upload completes, creation is sent to `/catalog/changes`:
   ```json
   {
     "changes": {
       "media": [
         {"action": "create", "temp_id": "1e9dfd0f-63bf-42d1-85b7-173af42f2f7d", ...}
       ]
     }
   }
   ```
7. **Analysis**: Call `/api/test-scenarios/rotation-test/analysis` to verify the pattern

**Important Notes:**
- Deletions and creations typically arrive in separate HTTP requests
- There may be a 10-20 second delay between deletion and creation (due to S3 upload time)
- The tracker accumulates all changes across requests for the final analysis
- The test passes if exactly 1 unique media was added AND 1 unique media was removed

### Remove Listing Test Analysis Details

#### Analysis Endpoint
Returns comprehensive metrics about listing and media removals during listing deletion testing.

**Request:**
```bash
GET /api/test-scenarios/remove-listing-test/analysis
Headers:
  X-Test-Session-ID: {session_id}
# OR
GET /api/test-scenarios/remove-listing-test/analysis?test_session_id={session_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": "maestro_session_xxx",
    "started_at": "2025-09-19T10:00:00Z",
    "analysis_at": "2025-09-19T10:05:00Z",
    "removal_summary": {
      "total_removal_events": 1,
      "total_listings_removed": 1,
      "total_media_removed": 5,
      "unique_listings_removed": 1,
      "unique_media_removed": 5,
      "listings_removed": ["listing-001"],
      "media_removed": ["media-001", "media-002", "media-003", "media-004", "media-005"],
      "matches_expected_pattern": true,
      "expected_pattern": "1 listing removed with all associated media",
      "avg_media_per_listing": 5.0
    },
    "media_by_listing": {
      "listing-001": ["media-001", "media-002", "media-003", "media-004", "media-005"]
    },
    "removal_events": {
      "total_events": 1,
      "events": [
        {
          "timestamp": "2025-09-19T10:04:00Z",
          "action": "swipe_to_delete",
          "target_listing": "listing-001",
          "associated_media_count": 5,
          "context": {
            "user_action": "manual_deletion",
            "screen": "listing_detail"
          }
        }
      ]
    },
    "test_result": {
      "success": true,
      "message": "Test passed: One listing successfully removed with all associated media",
      "details": {
        "listings_removed": 1,
        "media_items_removed": 5,
        "expected_listings": 1
      }
    }
  },
  "summary": {
    "total_listings_removed": 1,
    "total_media_removed": 5,
    "matches_expected_pattern": true,
    "expected_pattern": "1 listing removed with all associated media",
    "avg_media_per_listing": 5.0,
    "test_passed": true
  }
}
```

#### Timeline Endpoint
Returns detailed timeline of removal events for debugging and analysis.

**Request:**
```bash
GET /api/test-scenarios/remove-listing-test/timeline
Headers:
  X-Test-Session-ID: {session_id}
```

**Response:**
```json
{
  "success": true,
  "session_id": "maestro_session_xxx",
  "timeline": {
    "removal_changes": [
      {
        "timestamp": "2025-09-19T10:04:00Z",
        "removed_listings_count": 1,
        "removed_media_count": 5,
        "removed_listing_ids": ["listing-001"],
        "removed_media_ids": ["media-001", "media-002", "media-003", "media-004", "media-005"]
      }
    ],
    "removed_listings": [
      {
        "identifier": "listing-001",
        "title": "Test Listing",
        "status": "active",
        "timestamp": "2025-09-19T10:04:00Z",
        "type": "listing_removed"
      }
    ],
    "removed_media": [
      {
        "identifier": "media-001",
        "listing_id": "listing-001",
        "timestamp": "2025-09-19T10:04:00Z",
        "type": "media_removed"
      },
      // ... more media items
    ]
  },
  "media_by_listing": {
    "listing-001": ["media-001", "media-002", "media-003", "media-004", "media-005"]
  }
}
```

### How Remove Listing Test Flow Works

The remove listing test scenario tracks listing and media removals that occur when a user deletes a listing. Here's the typical flow:

1. **Activation**: Test scenario is activated via `/test-scenarios/activate` with `remove-listing-test`
2. **Initial State**: User has a listing with multiple media items attached
3. **Deletion Action**: User performs a delete action (swipe-to-delete, delete button, etc.)
4. **Removal Submission**: Deletion is sent to `/catalog/changes`:
   ```json
   {
     "changes": {
       "listings": [
         {"action": "delete", "id": "listing-001", "title": "Test Listing"}
       ],
       "media": [
         {"action": "delete", "id": "media-001", "listing_id": "listing-001"},
         {"action": "delete", "id": "media-002", "listing_id": "listing-001"},
         {"action": "delete", "id": "media-003", "listing_id": "listing-001"}
       ]
     }
   }
   ```
5. **Analysis**: Call `/api/test-scenarios/remove-listing-test/analysis` to verify the removal
6. **Timeline**: Call `/api/test-scenarios/remove-listing-test/timeline` for detailed event history

**Key Points:**
- The test validates that exactly 1 listing is removed with all its associated media
- Both listing and media deletions should arrive in the same request
- The `test_result.success` field indicates if the test passed
- The tracker groups media by listing for easier analysis
- Average media per listing is calculated to understand the removal scope

### Available Scenarios

#### Default Scenario
- **Name**: `default`
- **Description**: Standard mock server behavior with no modifications
- **Effects**: None

#### Camera Performance Test
- **Name**: `camera-performance-test`
- **Description**: Simulates performance testing conditions for camera features
- **Effects**:
  - 5-second delay on `/catalog/changes`, `/catalog/request-upload`, and S3 upload endpoints
  - Extensive request logging for debugging (`[UPLOAD-REQUEST-IN]` and `[CHANGES-REQUEST]` logs)
  - Fixed `last_modified` timestamp (2025-08-27 20:24:35) on `/catalog/hydrate`
  - Automatic tracking of all upload and changes requests for analysis
  - Access to performance analysis endpoints for metrics and duplicate detection

#### Rotation Test
- **Name**: `rotation-test`
- **Description**: Tracks media changes during device rotation
- **Effects**:
  - Tracks all media additions and deletions from `/catalog/changes` requests
  - Validates expected pattern: 1 new media added and 1 removed during rotation
  - Logs all media changes with `[ROTATION-TEST]` prefix
  - Fixed `last_modified` timestamp on `/catalog/hydrate`
  - Access to rotation analysis endpoints for validation

#### Remove Listing Test
- **Name**: `remove-listing-test`
- **Description**: Tracks listing and media removals during listing deletion
- **Effects**:
  - Tracks all listing deletions from `/catalog/changes` requests
  - Tracks all associated media removals when a listing is deleted
  - Validates expected pattern: 1 listing removed with all its associated media
  - Logs all removal events with `[REMOVE-LISTING-TEST]` prefix
  - Fixed `last_modified` timestamp on `/catalog/hydrate`
  - Access to removal analysis endpoints for validation and timeline viewing

### Example Maestro Integration
```yaml
# Activate test scenario
- http:
    url: "${MOCK_SERVER_URL}/test-scenarios/activate"
    method: POST
    body:
      scenario: "camera-performance-test"
    saveResponse: testSession

# Launch app with session
- launchApp:
    arguments:
      TEST_SESSION_ID: "${testSession.session_id}"
      TEST_SCENARIO: "camera-performance-test"
```

## Adding New Test Scenarios

The mock server uses the **Strategy Pattern** to handle different test scenarios. Follow these steps to add a new scenario:

### Step 1: Create a Strategy Class

Create a new strategy class in `src/TestScenarios/Strategies/`:

```php
<?php

namespace MockServer\TestScenarios\Strategies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class YourScenarioStrategy implements ScenarioStrategyInterface
{
    public function processRequest(Request $request, array $config, array $session): array
    {
        // Add pre-processing logic (delays, logging, etc.)
        // Example: sleep(3); // Add 3-second delay
        
        return [
            'continue' => true,
            'modifications' => []
        ];
    }
    
    public function processResponse(Response $response, array $config, array $session): Response
    {
        // Modify the response if needed
        // Example: Add custom headers, modify JSON data, etc.
        
        return $response;
    }
    
    public function shouldOverrideResponse(array $config): bool
    {
        // Return true if you want to completely replace the controller response
        return false;
    }
    
    public function generateResponse(Request $request, array $config, array $session): ?Response
    {
        // Generate a custom response (only if shouldOverrideResponse returns true)
        return null;
    }
}
```

### Step 2: Register the Strategy

Add your strategy to the factory in `src/TestScenarios/Strategies/ScenarioStrategyFactory.php`:

```php
private static array $strategies = [
    'default' => DefaultScenarioStrategy::class,
    'camera-performance-test' => CameraPerformanceStrategy::class,
    'rotation-test' => RotationTestStrategy::class,
    'your-scenario' => YourScenarioStrategy::class, // Add this line
];
```

### Step 3: Configure Endpoints

Add configuration in `config/test-scenarios/catalog-scenarios.php`:

```php
'your-scenario' => [
    'name' => 'Your Scenario Name',
    'description' => 'Description of what this scenario does',
    'responses' => [
        'catalog.hydrate' => [
            'type' => 'dynamic',
            'generator' => \MockServer\TestScenarios\Strategies\YourScenarioStrategy::class,
            'parameters' => [
                // Add any parameters your strategy needs
                'delay' => 3,
                'custom_param' => 'value'
            ]
        ],
        // Add more endpoints as needed
    ]
]
```

### Step 4: Test Your Scenario

Create a test file in `tests/Feature/TestScenarios/`:

```php
public function test_your_scenario_works()
{
    // Activate your scenario
    $response = $this->postJson('/api/test-scenarios/activate', [
        'scenario' => 'your-scenario'
    ]);
    
    $sessionId = $response->json('session_id');
    
    // Test that your scenario effects are applied
    $testResponse = $this->getJson('/your-endpoint', [
        'X-Test-Session-ID' => $sessionId
    ]);
    
    // Assert expected behavior
    $testResponse->assertStatus(200);
}
```

### Common Strategy Use Cases

1. **Network Conditions**: Add delays, timeouts, or connection failures
2. **Error Simulation**: Return specific error codes and messages
3. **Data Variations**: Return different data sets (empty, partial, full)
4. **Performance Testing**: Add logging, delays, and monitoring
5. **Security Testing**: Simulate authentication failures or permission issues

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
