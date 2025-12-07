#!/bin/bash

# ================================================
# API TEST SCRIPT - Space Data Platform Cassiopeia
# ================================================

BASE_URL_RUST="http://localhost:8081"
BASE_URL_PHP="http://localhost:8080"

echo "🚀 Testing Space Data Platform API..."
echo "======================================"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

function test_endpoint() {
    local name=$1
    local url=$2
    local expected_code=$3
    
    echo -n "Testing $name... "
    
    response=$(curl -s -w "\n%{http_code}" "$url")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" -eq "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $http_code)"
        echo "  Response: ${body:0:100}..."
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (Expected $expected_code, got $http_code)"
        echo "  Response: $body"
        return 1
    fi
}

function test_json_field() {
    local name=$1
    local url=$2
    local field=$3
    
    echo -n "Testing $name... "
    
    response=$(curl -s "$url")
    value=$(echo "$response" | jq -r ".$field")
    
    if [ "$value" != "null" ] && [ -n "$value" ]; then
        echo -e "${GREEN}✓ PASS${NC} ($field=$value)"
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} ($field is null or empty)"
        echo "  Response: $response"
        return 1
    fi
}

echo ""
echo "=== RUST SERVICE TESTS ==="
echo ""

# Health check
test_endpoint "Health Check" "$BASE_URL_RUST/health" 200
test_json_field "Health Status" "$BASE_URL_RUST/health" "status"

# ISS endpoints
test_endpoint "ISS Last Position" "$BASE_URL_RUST/last" 200
test_json_field "ISS Latitude" "$BASE_URL_RUST/last" "payload.latitude"

test_endpoint "ISS Trend" "$BASE_URL_RUST/iss/trend" 200
test_json_field "ISS Movement" "$BASE_URL_RUST/iss/trend" "movement"

# OSDR endpoints
test_endpoint "OSDR List" "$BASE_URL_RUST/osdr/list?limit=5" 200

# Space cache endpoints
test_endpoint "Space APOD Latest" "$BASE_URL_RUST/space/apod/latest" 200
test_endpoint "Space NEO Latest" "$BASE_URL_RUST/space/neo/latest" 200
test_endpoint "Space SpaceX Latest" "$BASE_URL_RUST/space/spacex/latest" 200

test_endpoint "Space Summary" "$BASE_URL_RUST/space/summary" 200

echo ""
echo "=== PHP SERVICE TESTS ==="
echo ""

# Dashboard
test_endpoint "Dashboard" "$BASE_URL_PHP/dashboard" 200

# API Proxy
test_endpoint "API ISS Last" "$BASE_URL_PHP/api/iss/last" 200
test_endpoint "API ISS Trend" "$BASE_URL_PHP/api/iss/trend" 200

# JWST API
test_endpoint "JWST Feed" "$BASE_URL_PHP/api/jwst/feed?perPage=5" 200

# CMS
test_endpoint "CMS Welcome Page" "$BASE_URL_PHP/page/welcome" 200

echo ""
echo "=== ERROR HANDLING TESTS ==="
echo ""

# Test unified error format
echo -n "Testing Unified Error Format... "
response=$(curl -s "$BASE_URL_RUST/space/invalid/latest")
ok=$(echo "$response" | jq -r '.ok')
error_code=$(echo "$response" | jq -r '.error.code')
trace_id=$(echo "$response" | jq -r '.error.trace_id')

if [ "$ok" == "false" ] && [ -n "$error_code" ] && [ -n "$trace_id" ]; then
    echo -e "${GREEN}✓ PASS${NC} (code=$error_code, trace_id=$trace_id)"
else
    echo -e "${RED}✗ FAIL${NC}"
    echo "  Response: $response"
fi

# Test 404
test_endpoint "404 Not Found" "$BASE_URL_PHP/page/nonexistent" 404

echo ""
echo "=== PERFORMANCE TESTS ==="
echo ""

echo "Measuring API latency..."

for endpoint in "/health" "/last" "/iss/trend" "/space/summary"; do
    url="$BASE_URL_RUST$endpoint"
    echo -n "  $endpoint: "
    
    start=$(date +%s%N)
    curl -s "$url" > /dev/null
    end=$(date +%s%N)
    
    latency=$(( ($end - $start) / 1000000 ))
    
    if [ $latency -lt 100 ]; then
        echo -e "${GREEN}${latency}ms ✓${NC}"
    elif [ $latency -lt 500 ]; then
        echo -e "${YELLOW}${latency}ms ⚠${NC}"
    else
        echo -e "${RED}${latency}ms ✗${NC}"
    fi
done

echo ""
echo "=== SECURITY TESTS ==="
echo ""

# Test SQL injection prevention
echo -n "Testing SQL Injection Prevention... "
response=$(curl -s "$BASE_URL_PHP/page/'; DROP TABLE cms_pages; --")
http_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL_PHP/page/'; DROP TABLE cms_pages; --")

if [ "$http_code" -eq "404" ]; then
    echo -e "${GREEN}✓ PASS${NC} (Invalid slug rejected)"
else
    echo -e "${RED}✗ FAIL${NC} (SQL injection possible!)"
fi

# Test XSS prevention
echo -n "Testing XSS Prevention... "
response=$(curl -s "$BASE_URL_PHP/page/welcome")

if echo "$response" | grep -q "<script>"; then
    echo -e "${RED}✗ FAIL${NC} (Script tags found in response!)"
else
    echo -e "${GREEN}✓ PASS${NC} (No script tags in response)"
fi

echo ""
echo "======================================"
echo "✅ Tests completed!"
echo ""
echo "For load testing, run:"
echo "  ab -n 1000 -c 10 $BASE_URL_RUST/health"
echo ""
