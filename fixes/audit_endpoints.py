#!/usr/bin/env python3
"""BITCORE REST API Endpoint Auditor - Tests all endpoints and documents status."""
import json, subprocess, re, sys

BASE = "https://ashi.bitwiki.org/wp-json"
APP_AUTH = "hyperion:7c6hkS4DdikxFo5nK4ShsysP"

# All endpoints discovered from /wp-json/ route list
ENDPOINTS = [
    # Health / Public
    ("GET", "/bitcore/v1"),
    ("GET", "/bitcore/v1/ping"),
    ("GET", "/bitcore/v1/health"),
    ("POST", "/bitcore/v1/health/refresh"),
    ("GET", "/bitcore/v1/dashboard"),
    ("GET", "/bitcore/v1/retention"),
    ("GET", "/bitcore/v1/digest"),
    ("GET", "/bitcore/v1/activity"),
    ("GET", "/bitcore/v1/stats"),
    ("POST", "/bitcore/v1/token/rotate"),
    # Tasks
    ("GET", "/bitcore/v1/tasks"),
    ("POST", "/bitcore/v1/tasks"),
    ("GET", "/bitcore/v1/tasks/1"),
    ("POST", "/bitcore/v1/tasks/1"),
    ("PUT", "/bitcore/v1/tasks/1"),
    ("PATCH", "/bitcore/v1/tasks/1"),
    ("DELETE", "/bitcore/v1/tasks/1"),
    ("POST", "/bitcore/v1/tasks/1/dispatch"),
    # Goals
    ("GET", "/bitcore/v1/goals"),
    ("POST", "/bitcore/v1/goals"),
    ("GET", "/bitcore/v1/goals/1"),
    ("POST", "/bitcore/v1/goals/1"),
    ("DELETE", "/bitcore/v1/goals/1"),
    ("GET", "/bitcore/v1/goals/1/children"),
    ("GET", "/bitcore/v1/goals/1/ancestry"),
    ("GET", "/bitcore/v1/goals/agent/hyperion"),
    # Sessions
    ("GET", "/bitcore/v1/sessions"),
    ("POST", "/bitcore/v1/sessions"),
    ("GET", "/bitcore/v1/sessions/1"),
    ("DELETE", "/bitcore/v1/sessions/1"),
    # Approvals
    ("GET", "/bitcore/v1/approvals"),
    ("POST", "/bitcore/v1/approvals"),
    ("GET", "/bitcore/v1/approvals/1"),
    ("POST", "/bitcore/v1/approvals/1/approve"),
    ("POST", "/bitcore/v1/approvals/1/reject"),
    # Budget
    ("GET", "/bitcore/v1/budget"),
    ("GET", "/bitcore/v1/budget/hyperion"),
    ("POST", "/bitcore/v1/budget/hyperion/check"),
    ("POST", "/bitcore/v1/budget/hyperion/consume"),
    ("POST", "/bitcore/v1/budget/hyperion/policy"),
    # Checkout
    ("POST", "/bitcore/v1/checkout"),
    ("GET", "/bitcore/v1/checkout/1"),
    ("DELETE", "/bitcore/v1/checkout/1"),
    ("POST", "/bitcore/v1/checkout/1/complete"),
    ("POST", "/bitcore/v1/checkout/1/extend"),
    # Context
    ("GET", "/bitcore/v1/context"),
    ("POST", "/bitcore/v1/context"),
    ("GET", "/bitcore/v1/context/test-ctx"),
    ("DELETE", "/bitcore/v1/context/test-ctx"),
    # Workspaces
    ("GET", "/bitcore/v1/workspaces"),
    ("POST", "/bitcore/v1/workspaces"),
    ("GET", "/bitcore/v1/workspaces/test-ws"),
    ("DELETE", "/bitcore/v1/workspaces/test-ws"),
    ("POST", "/bitcore/v1/workspaces/test-ws/archive"),
    # Episodes
    ("GET", "/bitcore/v1/episodes"),
    ("POST", "/bitcore/v1/episodes"),
    ("GET", "/bitcore/v1/episodes/ep1"),
    ("POST", "/bitcore/v1/score"),
    # Aggregate
    ("POST", "/bitcore/v1/aggregate"),
    # Org Chart
    ("GET", "/bitcore/v1/org-chart"),
    ("POST", "/bitcore/v1/org-chart"),
    ("GET", "/bitcore/v1/org-chart/hyperion"),
    ("DELETE", "/bitcore/v1/org-chart/hyperion"),
    ("GET", "/bitcore/v1/org-chart/hyperion/reports"),
    ("GET", "/bitcore/v1/org-chart/hyperion/chain"),
    ("POST", "/bitcore/v1/org-chart/hyperion/broadcast"),
    # Eliza
    ("GET", "/bitcore/v1/eliza/health"),
    ("GET", "/bitcore/v1/eliza/agents"),
    ("POST", "/bitcore/v1/eliza/agents/aria/activate"),
    ("POST", "/bitcore/v1/eliza/agents/aria/deactivate"),
    ("POST", "/bitcore/v1/eliza/chat"),
    ("GET", "/bitcore/v1/eliza/sessions"),
    ("POST", "/bitcore/v1/eliza/sessions"),
    ("DELETE", "/bitcore/v1/eliza/sessions/s1"),
    ("GET", "/bitcore/v1/eliza/rate-limit"),
    # Hermes
    ("POST", "/bitcore/v1/hermes/chat"),
    ("POST", "/bitcore/v1/hermes/task"),
    ("GET", "/bitcore/v1/hermes/task/t1"),
    ("POST", "/bitcore/v1/hermes/task/t1/cancel"),
    ("GET", "/bitcore/v1/hermes/skills"),
    ("POST", "/bitcore/v1/hermes/skill/test-skill/run"),
    ("GET", "/bitcore/v1/hermes/health"),
    ("POST", "/bitcore/v1/hermes/callback"),
    # Events
    ("POST", "/bitcore/v1/events/broadcast"),
    ("GET", "/bitcore/v1/events/stream"),
    # Knowledge Graph
    ("GET", "/bitcore/v1/knowledge-graph"),
    ("POST", "/bitcore/v1/knowledge-graph/nodes"),
    ("POST", "/bitcore/v1/knowledge-graph/edges"),
    ("GET", "/bitcore/v1/knowledge-graph/nodes/n1"),
    ("DELETE", "/bitcore/v1/knowledge-graph/nodes/n1"),
    ("GET", "/bitcore/v1/knowledge-graph/search"),
    # Identities
    ("GET", "/bitcore/v1/identities"),
    ("POST", "/bitcore/v1/identities"),
    ("DELETE", "/bitcore/v1/identities/test-id"),
    # Missions
    ("GET", "/bitcore/v1/missions"),
    ("POST", "/bitcore/v1/missions"),
    ("GET", "/bitcore/v1/missions/1"),
    ("POST", "/bitcore/v1/missions/1"),
    ("DELETE", "/bitcore/v1/missions/1"),
    ("GET", "/bitcore/v1/missions/statuses"),
    # Workflows
    ("GET", "/bitcore/v1/workflows"),
    ("POST", "/bitcore/v1/workflows/test-wf/start"),
    # MCP
    ("GET", "/bitcore/v1/mcp/tools"),
    ("POST", "/bitcore/v1/mcp/call"),
    # Coordination (may not be registered)
    ("GET", "/bitcore/v1/bids"),
    ("GET", "/bitcore/v1/bids/task/1"),
    ("GET", "/bitcore/v1/votes"),
    ("POST", "/bitcore/v1/votes/v1/cast"),
    ("GET", "/bitcore/v1/votes/v1/result"),
    ("GET", "/bitcore/v1/consensus"),
    # Feeds (may not be registered)
    ("GET", "/bitcore/v1/feeds"),
    ("GET", "/bitcore/v1/feeds/1"),
    ("POST", "/bitcore/v1/feeds/extract"),
    ("POST", "/bitcore/v1/feeds/summarize"),
    ("GET", "/bitcore/v1/watchlists"),
    ("POST", "/bitcore/v1/watchlists"),
    ("GET", "/bitcore/v1/watchlists/1"),
    ("POST", "/bitcore/v1/watchlists/1/keywords"),
    ("GET", "/bitcore/v1/trends"),
    ("GET", "/bitcore/v1/cluster"),
    # wp-abilities
    ("GET", "/wp-abilities/v1"),
    ("GET", "/wp-abilities/v1/abilities"),
    ("GET", "/wp-abilities/v1/abilities/test"),
    ("GET", "/wp-abilities/v1/abilities/test/run"),
    ("POST", "/wp-abilities/v1/abilities/test/run"),
    ("GET", "/wp-abilities/v1/categories"),
    ("GET", "/wp-abilities/v1/categories/test-cat"),
]


def test(method, path, auth=False):
    url = BASE + path
    cmd = ["curl", "-s", "-w", "\n__STATUS__:%{http_code}\n__TIME__:%{time_total}", "-X", method, "-H", "Content-Type: application/json"]
    if auth:
        cmd += ["-u", APP_AUTH]
    # Add minimal body for POST/PUT/PATCH
    if method in ("POST", "PUT", "PATCH"):
        cmd += ["-d", "{}"]
    cmd.append(url)
    try:
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=15).stdout
        status = re.search(r"__STATUS__:(\d+)", out)
        time = re.search(r"__TIME__:([\d.]+)", out)
        body = out.split("__STATUS__:")[0].strip()
        return {
            "method": method,
            "path": path,
            "status": int(status.group(1)) if status else 0,
            "time": float(time.group(1)) if time else 0,
            "body": body[:500],
            "auth": auth
        }
    except Exception as e:
        return {"method": method, "path": path, "status": -1, "time": 0, "body": str(e), "auth": auth}


results = []
total = len(ENDPOINTS)
for i, (m, p) in enumerate(ENDPOINTS, 1):
    # Test without auth first
    r = test(m, p, auth=False)
    results.append(r)
    auth_note = ""
    # If 401/403, try with auth
    if r["status"] in (401, 403):
        r2 = test(m, p, auth=True)
        if r2["status"] != r["status"]:
            auth_note = f" [WITH_AUTH->{r2['status']}]"
            r["auth_status"] = r2["status"]
    print(f"[{i}/{total}] {m} {p} -> {r['status']}{auth_note}")
    sys.stdout.flush()

# Summary
with open("/a0/usr/projects/hyperion/endpoint_audit_results.json", "w") as f:
    json.dump(results, f, indent=2)

print("\n=== SUMMARY ===")
by_status = {}
for r in results:
    by_status.setdefault(r["status"], []).append(r)
for st in sorted(by_status.keys()):
    print(f"Status {st}: {len(by_status[st])} endpoints")
    for r in by_status[st][:5]:
        print(f"  {r['method']} {r['path']}")
    if len(by_status[st]) > 5:
        print(f"  ... and {len(by_status[st]) - 5} more")
