#!/bin/bash

# Test Scenario Example Script for Maestro Integration
# This demonstrates how to use the test scenario system

BASE_URL="https://auctic-mobile-mock-server.test"
AUTH_TOKEN="Bearer mock_pat_test123"

echo "================================================"
echo "Test Scenario System Demonstration"
echo "================================================"
echo ""

# 1. List available scenarios
echo "1. Available scenarios:"
curl -s -X GET "$BASE_URL/api/test-scenarios/available" \
  -H "Accept: application/json" \
  -k | jq '.scenarios[].name' | head -5
echo ""

# 2. Activate empty catalog scenario
echo "2. Activating 'empty_catalog' scenario..."
SESSION_RESPONSE=$(curl -s -X POST "$BASE_URL/api/test-scenarios/activate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "scenario": "empty_catalog",
    "metadata": {
      "test_name": "catalog_empty_state_test",
      "test_suite": "catalog_tests",
      "maestro_flow": "catalog_empty_flow.yaml"
    }
  }' -k)

SESSION_ID=$(echo $SESSION_RESPONSE | jq -r '.session_id')
echo "Session created: $SESSION_ID"
echo ""

# 3. Test empty catalog
echo "3. Testing empty catalog response..."
CATALOG_RESPONSE=$(curl -s -X GET "$BASE_URL/mobile-api/v1/catalog/hydrate" \
  -H "Accept: application/json" \
  -H "Authorization: $AUTH_TOKEN" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -k)

echo "Events count: $(echo $CATALOG_RESPONSE | jq '.events | length')"
echo "Listings count: $(echo $CATALOG_RESPONSE | jq '.listings | length')"
echo ""

# 4. Switch to single event scenario
echo "4. Switching to 'single_event_with_listings' scenario..."
curl -s -X POST "$BASE_URL/api/test-scenarios/switch" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -d '{"scenario": "single_event_with_listings"}' \
  -k | jq '.message'
echo ""

# 5. Test single event catalog
echo "5. Testing single event response..."
CATALOG_RESPONSE=$(curl -s -X GET "$BASE_URL/mobile-api/v1/catalog/hydrate" \
  -H "Accept: application/json" \
  -H "Authorization: $AUTH_TOKEN" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -k)

echo "Events count: $(echo $CATALOG_RESPONSE | jq '.events | length')"
echo "Listings count: $(echo $CATALOG_RESPONSE | jq '.listings | length')"
echo "First event name: $(echo $CATALOG_RESPONSE | jq -r '.events[0].name')"
echo ""

# 6. Test error scenario
echo "6. Switching to 'server_error' scenario..."
curl -s -X POST "$BASE_URL/api/test-scenarios/switch" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -d '{"scenario": "server_error"}' \
  -k | jq '.message'

echo "Testing server error response..."
ERROR_RESPONSE=$(curl -s -X GET "$BASE_URL/mobile-api/v1/catalog/hydrate" \
  -H "Accept: application/json" \
  -H "Authorization: $AUTH_TOKEN" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -k)

echo "Error: $(echo $ERROR_RESPONSE | jq -r '.error')"
echo "Message: $(echo $ERROR_RESPONSE | jq -r '.message')"
echo ""

# 7. Check session status
echo "7. Checking session status..."
curl -s -X GET "$BASE_URL/api/test-scenarios/current" \
  -H "Accept: application/json" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -k | jq '{scenario: .scenario, request_count: .request_count}'
echo ""

# 8. Check metrics
echo "8. Checking metrics..."
curl -s -X GET "$BASE_URL/api/test-scenarios/metrics" \
  -H "Accept: application/json" \
  -k | jq '{active_sessions: .active_sessions, total_requests: .total_requests}'
echo ""

# 9. Clean up
echo "9. Cleaning up session..."
curl -s -X POST "$BASE_URL/api/test-scenarios/reset" \
  -H "Accept: application/json" \
  -H "X-Test-Session-ID: $SESSION_ID" \
  -k | jq '.message'
echo ""

echo "================================================"
echo "Test completed successfully!"
echo "================================================"