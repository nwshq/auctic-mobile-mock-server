# Test Scenarios Usage Guide

## Overview

The Test Scenario system enables Maestro automation tests to control mock server responses dynamically without modifying the mobile application. This guide explains how to use the system.

## Quick Start

### 1. List Available Scenarios

```bash
GET /api/test-scenarios/available
```

Returns all configured test scenarios with their descriptions and affected endpoints.

### 2. Activate a Scenario

```bash
POST /api/test-scenarios/activate
Content-Type: application/json

{
  "scenario": "empty_catalog",
  "metadata": {
    "test_name": "catalog_empty_test",
    "test_suite": "catalog_tests",
    "maestro_flow": "catalog_empty.yaml"
  }
}
```

Response:
```json
{
  "session_id": "maestro_session_abc123",
  "scenario": "empty_catalog",
  "expires_at": "2025-01-01T14:00:00Z"
}
```

### 3. Use Session in API Requests

Include the session ID in your API requests:

```bash
GET /mobile-api/v1/catalog/hydrate
Authorization: Bearer mock_pat_xxxxx
X-Test-Session-ID: maestro_session_abc123
```

## Available Scenarios

### Catalog Scenarios

| Scenario | Description | Effect |
|----------|-------------|--------|
| `empty_catalog` | Empty catalog state | Returns no events or listings |
| `single_event_with_listings` | Single event | Returns 1 event with 5 listings |
| `multiple_events` | Multiple events | Returns 5 events with 3 listings each |
| `sold_out_events` | Sold out events | Returns events with no listings |

### Error Scenarios

| Scenario | Description | HTTP Status |
|----------|-------------|-------------|
| `auth_failure` | Authentication failure | 401 |
| `server_error` | Internal server error | 500 |
| `rate_limit` | Rate limit exceeded | 429 |
| `network_timeout` | Network timeout | 504 |
| `maintenance_mode` | Service maintenance | 503 |
| `validation_error` | Validation errors | 422 |
| `not_found` | Resource not found | 404 |

## API Endpoints

### Control Endpoints

- `POST /api/test-scenarios/activate` - Activate a scenario
- `GET /api/test-scenarios/current` - Get current scenario
- `POST /api/test-scenarios/switch` - Switch scenarios
- `POST /api/test-scenarios/reset` - Reset/destroy session
- `GET /api/test-scenarios/available` - List available scenarios
- `GET /api/test-scenarios/metrics` - View metrics
- `GET /api/test-scenarios/debug/{session_id}` - Debug session

### Headers

- `X-Test-Session-ID`: Required for all test requests
- `X-Test-Scenario`: Optional, for debugging

## Maestro Integration Example

```yaml
appId: com.auctic.mobile
---
# Step 1: Initialize test session
- http:
    url: "${MOCK_SERVER_URL}/api/test-scenarios/activate"
    method: POST
    headers:
      Content-Type: application/json
    body:
      scenario: "empty_catalog"
      metadata:
        test_name: "catalog_empty_state_test"
    saveResponse: testSession

# Step 2: Launch app with session
- launchApp:
    arguments:
      TEST_SESSION_ID: "${testSession.session_id}"
      MOCK_SERVER_URL: "${MOCK_SERVER_URL}"

# Step 3: Test empty state
- assertVisible: "No items available"

# Step 4: Switch scenario
- http:
    url: "${MOCK_SERVER_URL}/api/test-scenarios/switch"
    method: POST
    headers:
      X-Test-Session-ID: "${testSession.session_id}"
    body:
      scenario: "single_event_with_listings"

# Step 5: Refresh and verify
- tapOn: "Refresh"
- assertVisible: "Event Name"

# Step 6: Cleanup
- http:
    url: "${MOCK_SERVER_URL}/api/test-scenarios/reset"
    method: POST
    headers:
      X-Test-Session-ID: "${testSession.session_id}"
```

## Laravel Testing

Run the test suite:

```bash
php artisan test --filter=TestScenarioSystemTest
```

Example test:
```php
public function test_empty_catalog_scenario()
{
    // Activate scenario
    $response = $this->postJson('/api/test-scenarios/activate', [
        'scenario' => 'empty_catalog'
    ]);
    $sessionId = $response->json('session_id');

    // Test API with scenario
    $catalogResponse = $this->getJson('/mobile-api/v1/catalog/hydrate', [
        'Authorization' => 'Bearer mock_pat_test123',
        'X-Test-Session-ID' => $sessionId
    ]);

    $catalogResponse->assertJson([
        'events' => [],
        'listings' => []
    ]);
}
```

## Creating Custom Scenarios

### 1. Create Configuration File

Create a new file in `config/test-scenarios/`:

```php
// config/test-scenarios/custom-scenarios.php
return [
    'custom_scenario' => [
        'name' => 'Custom Scenario',
        'description' => 'Description of the scenario',
        'responses' => [
            'catalog.hydrate' => [
                'type' => 'static',
                'data' => [
                    'events' => [...],
                    'listings' => [...]
                ]
            ]
        ]
    ]
];
```

### 2. Create Custom Generator (Optional)

```php
namespace MockServer\TestScenarios\Generators;

use MockServer\TestScenarios\Contracts\ResponseGeneratorInterface;

class CustomGenerator implements ResponseGeneratorInterface
{
    public function generate(array $parameters, array $session = []): array
    {
        // Generate dynamic response
        return [
            'events' => $this->generateEvents($parameters),
            'listings' => $this->generateListings($parameters)
        ];
    }
    
    public function getName(): string
    {
        return 'CustomGenerator';
    }
    
    public function getDescription(): string
    {
        return 'Generates custom responses';
    }
}
```

## Session Management

- Sessions expire after 2 hours by default
- Session TTL can be configured via `TEST_SESSION_TTL` environment variable
- Sessions are stored in Redis/Cache
- Each session tracks request count and last request time

## Debugging

### Enable Debug Headers

Set `TEST_SCENARIOS_DEBUG_HEADERS=true` in `.env` to add debug headers to responses:
- `X-Test-Scenario`: Current scenario name
- `X-Test-Session-ID`: Session ID

### View Logs

Test scenario activity is logged to `storage/logs/test-scenarios.log`

### Check Metrics

```bash
GET /api/test-scenarios/metrics
```

Returns:
- Active sessions count
- Scenarios in use
- Total requests
- Session details

## Environment Configuration

```env
# Enable/disable test scenarios
TEST_SCENARIOS_ENABLED=true

# Session time-to-live (seconds)
TEST_SESSION_TTL=7200

# Cache driver for sessions
TEST_SESSION_CACHE_DRIVER=redis

# Enable debug features
TEST_SCENARIOS_DEBUG=true
TEST_SCENARIOS_METRICS=true
TEST_SCENARIOS_DEBUG_HEADERS=true

# Logging
TEST_SCENARIOS_LOGGING=true
TEST_SCENARIOS_LOG_CHANNEL=test_scenarios
```

## Troubleshooting

### Session Not Found
- Verify session ID is correct
- Check if session has expired (2-hour default TTL)
- Ensure Redis/Cache is running

### Scenario Not Working
- Verify scenario name is correct
- Check endpoint configuration matches route
- Review logs for errors

### Normal Data Returned
- Ensure `X-Test-Session-ID` header is included
- Verify `TEST_SCENARIOS_ENABLED=true` in `.env`
- Check middleware is registered correctly

## Security Notes

- Test scenarios only work when explicitly enabled
- Sessions are isolated and expire automatically
- Control endpoints can be rate-limited
- Debug endpoints can be disabled in production