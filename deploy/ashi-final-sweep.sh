#!/bin/bash
BASE="https://ashi.bitwiki.org/wp-json"

# Initialize counters
ok=0; auth=0; forbidden=0; server=0; notfound=0; other=0

hit() {
  code=$(curl -s -o /dev/null -w "%{http_code}" "$@")
  case "$code" in
    200) ((ok++)) ;;
    401) ((auth++)) ;;
    403) ((forbidden++)) ;;
    500) ((server++)) ;;
    404) ((notfound++)) ;;
    *) ((other++)) ;;
  esac
  echo "$code $@"
}

echo "=== FINAL SWEEP ==="

# Category 1: Tasks (was 403)
hit -X GET "$BASE/bitcore/v1/tasks"
hit -X POST "$BASE/bitcore/v1/tasks"
hit -X GET "$BASE/bitcore/v1/tasks/1"
hit -X POST "$BASE/bitcore/v1/tasks/1"
hit -X PUT "$BASE/bitcore/v1/tasks/1"
hit -X PATCH "$BASE/bitcore/v1/tasks/1"
hit -X DELETE "$BASE/bitcore/v1/tasks/1"
hit -X POST "$BASE/bitcore/v1/tasks/1/dispatch"

# Category 2: Server Errors (was 500)
hit -X GET "$BASE/bitcore/v1/missions/1"
hit -X POST "$BASE/bitcore/v1/events/broadcast"

# Category 3: Missing Registrations (was 404)
hit -X GET "$BASE/bitcore/v1/mcp/tools"
hit -X POST "$BASE/bitcore/v1/mcp/call"
hit -X GET "$BASE/bitcore/v1/bids"
hit -X GET "$BASE/bitcore/v1/votes"
hit -X GET "$BASE/bitcore/v1/consensus"
hit -X GET "$BASE/bitcore/v1/feeds"

# Bonus: verify missions list works
hit -X GET "$BASE/bitcore/v1/missions"

echo ""
echo "=== SUMMARY ==="
echo "200 OK:        $ok"
echo "401 Auth Req:  $auth"
echo "403 Forbidden: $forbidden"
echo "500 Server:    $server"
echo "404 Not Found: $notfound"
echo "Other:         $other"
