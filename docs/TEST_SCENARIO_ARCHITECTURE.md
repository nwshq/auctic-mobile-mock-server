# Dynamic Test Scenario Architecture for Auctic Mobile Mock Server

## Executive Summary

This document outlines the architecture for enabling dynamic response variations in the Auctic Mobile Mock Server based on test scenarios set by Maestro automation tests. The solution allows the mock server to respond differently based on active test cases without modifying the mobile application code.

## Problem Statement

- **Challenge**: Maestro integration tests need different mock server responses for various test scenarios
- **Constraint**: The mobile app cannot be modified during test execution
- **Requirement**: Test scenarios must persist across multiple API requests during a test session
- **Goal**: Enable dynamic response switching without affecting production code

## Architecture Overview

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   Maestro Tests  │────▶│   Mock Server    │◀────│   Mobile App     │
│                  │     │  (Laravel/PHP)   │     │  (React Native)  │
└──────────────────┘     └──────────────────┘     └──────────────────┘
        │                         │                         │
        │ 1. Activate            │                         │
        │    Scenario            │                         │
        │                        │                         │
        │ 2. Get Session ID      │                         │
        │◀───────────────────────│                         │
        │                        │                         │
        │ 3. Launch App with     │                         │
        │    Session ID          │                         │
        │────────────────────────┼────────────────────────▶│
        │                        │                         │
        │                        │ 4. API Requests with    │
        │                        │    Session Headers      │
        │                        │◀────────────────────────│
        │                        │                         │
        │                        │ 5. Scenario-Based       │
        │                        │    Responses            │
        │                        │────────────────────────▶│
```

## Core Components

### 1. Test Session Management

#### Purpose
Maintains state across multiple API requests during a test execution.

#### Implementation
- **Storage**: Redis/Cache-based session storage
- **Identifier**: UUID-based session IDs
- **Lifecycle**: Auto-expiry after 2 hours (configurable)
- **Isolation**: Each test run gets unique session

#### Session Structure
```json
{
  "session_id": "maestro_session_abc123",
  "scenario": "empty_catalog",
  "created_at": "2025-01-01T12:00:00Z",
  "expires_at": "2025-01-01T14:00:00Z",
  "metadata": {
    "test_suite": "catalog_tests",
    "test_name": "empty_state_handling",
    "maestro_flow": "catalog_empty_flow.yaml"
  },
  "state": {
    "request_count": 0,
    "last_request_at": null,
    "custom_data": {}
  }
}
```

### 2. Test Scenario Control API

#### Endpoints

##### POST /test-scenarios/activate
Activates a test scenario for a session.

**Request:**
```json
{
  "scenario": "empty_catalog",
  "metadata": {
    "test_name": "empty_catalog_test",
    "test_suite": "catalog_suite"
  }
}
```

**Response:**
```json
{
  "session_id": "maestro_session_abc123",
  "scenario": "empty_catalog",
  "expires_at": "2025-01-01T14:00:00Z"
}
```

##### GET /test-scenarios/current
Gets the current active scenario for a session.

**Headers:**
```
X-Test-Session-ID: maestro_session_abc123
```

**Response:**
```json
{
  "session_id": "maestro_session_abc123",
  "scenario": "empty_catalog",
  "active": true,
  "request_count": 5,
  "expires_at": "2025-01-01T14:00:00Z"
}
```

##### POST /test-scenarios/switch
Switches to a different scenario mid-test.

**Headers:**
```
X-Test-Session-ID: maestro_session_abc123
```

**Request:**
```json
{
  "scenario": "single_event_with_listings"
}
```

##### POST /test-scenarios/reset
Resets or destroys a test session.

**Headers:**
```
X-Test-Session-ID: maestro_session_abc123
```

##### GET /test-scenarios/available
Lists all available test scenarios.

**Response:**
```json
{
  "scenarios": [
    {
      "name": "empty_catalog",
      "description": "Returns empty events and listings",
      "endpoints": ["catalog.hydrate", "catalog.sync"]
    },
    {
      "name": "auth_failure",
      "description": "Simulates authentication failures",
      "endpoints": ["*"]
    }
  ]
}
```

### 3. Response Variation Middleware

#### Purpose
Intercepts API requests and applies scenario-based response variations.

#### Process Flow
1. Extract session ID from request headers or query parameters
2. Load active scenario from session storage
3. Determine response variation based on endpoint and scenario
4. Generate or retrieve appropriate response
5. Apply response modifications (delays, errors, etc.)

#### Implementation
```php
class TestScenarioMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Extract session ID from multiple sources
        $sessionId = $this->extractSessionId($request);
        
        if (!$sessionId) {
            return $next($request); // Normal flow
        }
        
        // Load scenario configuration
        $scenario = $this->scenarioService->getActiveScenario($sessionId);
        
        // Attach to request context
        $request->attributes->set('test_scenario', $scenario);
        $request->attributes->set('test_session_id', $sessionId);
        
        // Process request
        $response = $next($request);
        
        // Apply response variations
        return $this->applyScenarioVariations($response, $scenario);
    }
}
```

### 4. Scenario Configuration System

#### Structure
```yaml
# config/test-scenarios/catalog-scenarios.yaml
empty_catalog:
  name: "Empty Catalog State"
  description: "Returns empty events and listings"
  responses:
    catalog_hydrate:
      type: static
      data:
        events: []
        categories: [...] # Static data
        sellers: []
        last_modified: "{{timestamp}}"
    catalog_sync:
      type: static
      data:
        events: []
        incremental_id: "{{uuid}}"

single_event_with_listings:
  name: "Single Event with Listings"
  description: "One active event with multiple listings"
  responses:
    catalog_hydrate:
      type: dynamic
      generator: SingleEventGenerator
      parameters:
        event_count: 1
        listing_count: 5
        status: active

auth_failure:
  name: "Authentication Failure"
  description: "Simulates auth failures"
  responses:
    "*": # Applies to all endpoints
      type: error
      status_code: 401
      data:
        error: "Unauthorized"
        message: "Invalid authentication token"
```

### 5. Response Generators

#### Static Generator
Returns predefined JSON responses with variable substitution.

#### Dynamic Generator
Generates responses programmatically based on parameters.

```php
interface ResponseGeneratorInterface {
    public function generate(array $parameters): array;
}

class SingleEventGenerator implements ResponseGeneratorInterface {
    public function generate(array $parameters): array {
        $listingCount = $parameters['listing_count'] ?? 1;
        
        return [
            'events' => [$this->generateEvent()],
            'listings' => $this->generateListings($listingCount),
            // ... other fields
        ];
    }
}
```

## Mobile App Integration

### Session Propagation

#### Launch Arguments Method (Recommended)
```typescript
// Maestro sets launch arguments
launchApp:
  arguments:
    TEST_SESSION_ID: "maestro_session_abc123"
    TEST_SCENARIO: "empty_catalog"
    MOCK_SERVER_URL: "http://localhost:8000"
```

#### TestSessionManager Implementation
```typescript
class TestSessionManager {
  private static instance: TestSessionManager;
  private sessionId: string | null = null;
  private scenarioName: string | null = null;

  static initialize() {
    const args = NativeModules.ProcessInfo?.arguments || {};
    
    if (args.TEST_SESSION_ID) {
      this.instance.sessionId = args.TEST_SESSION_ID;
      this.instance.scenarioName = args.TEST_SCENARIO;
    }
  }

  static getSessionHeaders(): Record<string, string> {
    if (!this.instance.sessionId) return {};
    
    return {
      'X-Test-Session-ID': this.instance.sessionId,
      'X-Test-Scenario': this.instance.scenarioName || ''
    };
  }

  static isTestMode(): boolean {
    return !!this.instance.sessionId;
  }
}
```

#### API Client Integration
```typescript
// Axios interceptor to inject test headers
apiClient.interceptors.request.use((config) => {
  const testHeaders = TestSessionManager.getSessionHeaders();
  config.headers = {
    ...config.headers,
    ...testHeaders
  };
  return config;
});
```

## Maestro Test Flow

### Example Test Scenario
```yaml
appId: com.auctic.mobile
---
# Step 1: Initialize test session
- http:
    url: "${MOCK_SERVER_URL}/test-scenarios/activate"
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
      TEST_SCENARIO: "empty_catalog"
      MOCK_SERVER_URL: "${MOCK_SERVER_URL}"

# Step 3: Verify empty state
- assertVisible: "No items available"

# Step 4: Switch scenario
- http:
    url: "${MOCK_SERVER_URL}/test-scenarios/switch"
    method: POST
    headers:
      X-Test-Session-ID: "${testSession.session_id}"
    body:
      scenario: "single_event_with_listings"

# Step 5: Refresh and verify new state
- tapOn: "Refresh"
- assertVisible: "Test Event 1"

# Step 6: Cleanup
- http:
    url: "${MOCK_SERVER_URL}/test-scenarios/reset"
    method: POST
    headers:
      X-Test-Session-ID: "${testSession.session_id}"
```

## Implementation Phases

### Phase 1: Core Infrastructure (Week 1)
- [ ] Test session management service
- [ ] Basic control API endpoints
- [ ] Session storage implementation
- [ ] Request/Response middleware

### Phase 2: Scenario System (Week 2)
- [ ] Scenario configuration structure
- [ ] Static response generator
- [ ] Dynamic response generator
- [ ] Response variation logic

### Phase 3: Integration (Week 3)
- [ ] Update existing controllers
- [ ] Add scenario configurations for all endpoints
- [ ] Error handling and logging
- [ ] Session cleanup mechanisms

### Phase 4: Testing & Documentation (Week 4)
- [ ] Create Maestro test examples
- [ ] Performance testing
- [ ] Documentation updates
- [ ] Team training

## Benefits

1. **Isolation**: Each test runs in isolated session
2. **Flexibility**: Scenarios can change mid-test
3. **Maintainability**: Configuration-driven approach
4. **Debugging**: Clear test execution traces
5. **Scalability**: Supports concurrent test execution
6. **Non-invasive**: No production code changes

## Security Considerations

1. **Session Security**
   - Sessions only accessible via secure token
   - Auto-expiry prevents orphaned sessions
   - Rate limiting on control endpoints

2. **Environment Isolation**
   - Test features only enabled in non-production
   - Clear separation of test and production data
   - Audit logging for all test operations

## Monitoring & Debugging

### Logging Strategy
```php
// Log all test session operations
Log::channel('test_scenarios')->info('Session created', [
    'session_id' => $sessionId,
    'scenario' => $scenario,
    'maestro_test' => $metadata['test_name']
]);
```

### Debug Endpoints
- GET /test-scenarios/debug/{session_id} - Session details
- GET /test-scenarios/logs/{session_id} - Request logs
- GET /test-scenarios/metrics - Performance metrics

## Configuration Examples

### Empty Catalog Scenario
```php
return [
    'empty_catalog' => [
        'name' => 'Empty Catalog',
        'endpoints' => [
            'catalog.hydrate' => [
                'events' => [],
                'categories' => CategorySeeder::getDefault(),
                'sellers' => [],
                'qualities' => QualitySeeder::getDefault(),
                'last_modified' => '{{timestamp}}'
            ]
        ]
    ]
];
```

### Network Failure Scenario
```php
return [
    'network_timeout' => [
        'name' => 'Network Timeout',
        'endpoints' => [
            '*' => [
                'behavior' => 'delay',
                'delay_ms' => 30000,
                'then' => 'timeout'
            ]
        ]
    ]
];
```

## Success Metrics

1. **Test Reliability**: >99% test execution success rate
2. **Performance**: <10ms overhead per request
3. **Maintainability**: New scenarios added in <30 minutes
4. **Coverage**: 100% of API endpoints support scenarios

## Appendix

### A. Environment Variables
```env
TEST_SCENARIOS_ENABLED=true
TEST_SESSION_TTL=7200
TEST_SESSION_CACHE_DRIVER=redis
TEST_SCENARIO_CONFIG_PATH=config/test-scenarios
```

### B. Database Schema (Optional)
```sql
-- For persistent session storage
CREATE TABLE test_sessions (
    id UUID PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    scenario VARCHAR(100) NOT NULL,
    metadata JSON,
    created_at TIMESTAMP,
    expires_at TIMESTAMP,
    request_count INT DEFAULT 0
);

CREATE INDEX idx_session_id ON test_sessions(session_id);
CREATE INDEX idx_expires_at ON test_sessions(expires_at);
```

### C. Error Codes
- TSE001: Invalid session ID
- TSE002: Session expired
- TSE003: Unknown scenario
- TSE004: Scenario activation failed
- TSE005: Invalid scenario configuration

## Conclusion

This architecture provides a robust, maintainable solution for dynamic mock server responses during Maestro test execution. The session-based approach ensures test isolation while the configuration-driven design enables rapid scenario development without code changes.