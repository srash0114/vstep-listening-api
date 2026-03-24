#!/bin/bash

# VSTEP API Testing Script
# Usage: bash test_api.sh

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

API_BASE="http://localhost:8000"

# Test counter
PASSED=0
FAILED=0

# Function to print test header
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

# Function to test endpoint
test_endpoint() {
    local METHOD=$1
    local ENDPOINT=$2
    local DATA=$3
    local DESCRIPTION=$4
    
    echo -e "\n${YELLOW}Testing:${NC} $DESCRIPTION"
    echo -e "${YELLOW}Request:${NC} $METHOD $ENDPOINT"
    
    if [ -z "$DATA" ]; then
        RESPONSE=$(curl -s -w "\n%{http_code}" -X $METHOD "$API_BASE$ENDPOINT" \
            -H "Content-Type: application/json")
    else
        RESPONSE=$(curl -s -w "\n%{http_code}" -X $METHOD "$API_BASE$ENDPOINT" \
            -H "Content-Type: application/json" \
            -d "$DATA")
    fi
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)
    
    echo -e "${YELLOW}Response Code:${NC} $HTTP_CODE"
    echo -e "${YELLOW}Response:${NC}"
    echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
    
    if [[ "$HTTP_CODE" =~ ^(200|201|400|401|404|500)$ ]]; then
        echo -e "${GREEN}✓ Test passed${NC}"
        ((PASSED++))
    else
        echo -e "${RED}✗ Test failed${NC}"
        ((FAILED++))
    fi
}

print_header "VSTEP API - Integration Tests"

# Test 1: Check if server is running
echo -e "\n${YELLOW}Checking API server...${NC}"
if curl -s "$API_BASE/api/tests" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ API server is running${NC}"
else
    echo -e "${RED}✗ API server is not running${NC}"
    echo -e "${RED}Please start server: php -S localhost:8000 -t public${NC}"
    exit 1
fi

# Test 2: Register user
print_header "User Authentication Tests"

REGISTER_DATA='{
  "username": "testuser_'$(date +%s)'",
  "email": "test_'$(date +%s)'@example.com",
  "password": "testpass123"
}'

test_endpoint "POST" "/api/users/register" "$REGISTER_DATA" "Register new user"

# Extract email for login test
EMAIL=$(echo "$REGISTER_DATA" | jq -r '.email')

# Test 3: Login
LOGIN_DATA='{
  "email": "'$EMAIL'",
  "password": "testpass123"
}'

test_endpoint "POST" "/api/users/login" "$LOGIN_DATA" "User login"

# Test 4: Check status
test_endpoint "GET" "/api/users/check-status" "" "Check user status"

# Test 5: Get all tests
print_header "Test Management - Read Operations"

test_endpoint "GET" "/api/tests" "" "Get all tests"

# Test 6: Create a simple test (legacy)
print_header "Test Management - Create Operations"

CREATE_TEST_DATA='{
  "title": "Sample Test - '$(date +%s)'",
  "level": "B1",
  "totalQuestions": 35,
  "duration": 3600
}'

RESPONSE=$(curl -s -X POST "$API_BASE/api/tests" \
    -H "Content-Type: application/json" \
    -d "$CREATE_TEST_DATA")

echo -e "\n${YELLOW}Testing:${NC} Create simple test"
echo -e "${YELLOW}Response:${NC}"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"

# Extract test ID if creation was successful
TEST_ID=$(echo "$RESPONSE" | jq -r '.data.id' 2>/dev/null)

if [ ! -z "$TEST_ID" ] && [ "$TEST_ID" != "null" ]; then
    echo -e "${GREEN}✓ Test created with ID: $TEST_ID${NC}"
    ((PASSED++))
    
    # Test 7: Get specific test
    echo -e "\n${YELLOW}Testing:${NC} Get specific test"
    test_endpoint "GET" "/api/tests/detail?id=$TEST_ID" "" "Get test by ID"
    
    # Test 8: Get test full structure (new endpoint)
    echo -e "\n${YELLOW}Testing:${NC} Get full test structure (NEW)"
    test_endpoint "GET" "/api/tests/$TEST_ID/full" "" "Get test with parts and questions"
    
    # Test 9: Get test parts
    echo -e "\n${YELLOW}Testing:${NC} Get test parts (NEW)"
    test_endpoint "GET" "/api/tests/$TEST_ID/parts" "" "Get all parts for test"
    
    # Test 10: Get test progress
    echo -e "\n${YELLOW}Testing:${NC} Get test progress (NEW)"
    test_endpoint "GET" "/api/tests/$TEST_ID/progress" "" "Get test progress/summary"
    
    # Test 11: Update test
    UPDATE_DATA='{
      "title": "Updated Test Title '$(date +%s)'",
      "level": "B2",
      "duration": 7200
    }'
    
    echo -e "\n${YELLOW}Testing:${NC} Update test"
    test_endpoint "PUT" "/api/tests?id=$TEST_ID" "$UPDATE_DATA" "Update test metadata"
    
else
    echo -e "${RED}✗ Failed to create test${NC}"
    ((FAILED++))
fi

# Test 12: Create batch questions
print_header "Question Management Tests"

if [ ! -z "$TEST_ID" ] && [ "$TEST_ID" != "null" ]; then
    BATCH_QUESTIONS_DATA='{
      "testId": '$TEST_ID',
      "partId": 1,
      "questions": [
        {
          "questionNumber": 1,
          "question": "What is the main topic?",
          "optionA": "Option A",
          "optionB": "Option B",
          "optionC": "Option C",
          "optionD": "Option D",
          "correctAnswer": "B",
          "correctAnswerIndex": 1,
          "script": "Test script"
        }
      ]
    }'
    
    echo -e "\n${YELLOW}Testing:${NC} Create questions batch (NEW)"
    test_endpoint "POST" "/api/tests/create-questions-batch" "$BATCH_QUESTIONS_DATA" "Create questions in batch"
fi

# Test 13: Get user results
print_header "Result Management Tests"

test_endpoint "GET" "/api/results" "" "Get all results"

test_endpoint "GET" "/api/results/stats" "" "Get statistics"

# Test 14: Error handling
print_header "Error Handling Tests"

test_endpoint "GET" "/api/tests/99999" "" "Get non-existent test"

test_endpoint "POST" "/api/users/register" '{"username":"test"}' "Register with missing fields"

# Test 15: Invalid endpoint
echo -e "\n${YELLOW}Testing:${NC} Invalid endpoint (should return 404)"
test_endpoint "GET" "/api/invalid/endpoint" "" "Access invalid endpoint"

# Summary
print_header "Test Summary"

TOTAL=$((PASSED + FAILED))
echo -e "\n${GREEN}✓ Passed: $PASSED${NC}"
echo -e "${RED}✗ Failed: $FAILED${NC}"
echo -e "${BLUE}Total: $TOTAL${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "\n${GREEN}All tests passed! 🎉${NC}"
    exit 0
else
    echo -e "\n${RED}Some tests failed. Please review the output above.${NC}"
    exit 1
fi
