#!/bin/bash
BASE="https://ashi.bitwiki.org/wp-json"
echo "=== BITCORE Endpoint Sweep ==="
echo ""

# Category 1: Previously 403 (nonce bug)
echo "--- Category 1: Tasks (was 403) ---"
for method in GET POST; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -X $method "$BASE/bitcore/v1/tasks")
  echo "$method /bitcore/v1/tasks -> $code"
done
for method in GET POST PUT PATCH DELETE; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -X $method "$BASE/bitcore/v1/tasks/1")
  echo "$method /bitcore/v1/tasks/1 -> $code"
done
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/bitcore/v1/tasks/1/dispatch")
echo "POST /bitcore/v1/tasks/1/dispatch -> $code"

# Category 2: Previously 500
echo ""
echo "--- Category 2: Server Errors (was 500) ---"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/bitcore/v1/missions/1")
echo "GET /bitcore/v1/missions/1 -> $code"
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/bitcore/v1/events/broadcast")
echo "POST /bitcore/v1/events/broadcast -> $code"

# Category 3: Previously 404
echo ""
echo "--- Category 3: Missing Registrations (was 404) ---"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/bitcore/v1/mcp/tools")
echo "GET /bitcore/v1/mcp/tools -> $code"
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/bitcore/v1/mcp/call")
echo "POST /bitcore/v1/mcp/call -> $code"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/bitcore/v1/bids")
echo "GET /bitcore/v1/bids -> $code"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/bitcore/v1/votes")
echo "GET /bitcore/v1/votes -> $code"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/bitcore/v1/consensus")
echo "GET /bitcore/v1/consensus -> $code"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/bitcore/v1/feeds")
echo "GET /bitcore/v1/feeds -> $code"

echo ""
echo "=== Sweep Complete ==="
