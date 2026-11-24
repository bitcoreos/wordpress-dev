#!/bin/sh
# Executes the self-executing *Test.php scripts.
#
# 29 of the 38 *Test.php files declare no test class: they define their own
# WordPress stubs, run assertions inline and exit non-zero on failure (18 of
# them call exit() at file scope). PHPUnit cannot collect such files, so each
# one runs as its own PHP process here.
#
# Usage: sh tests/run-standalone.sh [plugin-root]
set -u
ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
PASS=0; FAIL=0; LIST=""
for f in $(find "$ROOT/tests" -name '*Test.php' | sort); do
    if grep -qE '^[[:space:]]*(final )?class[[:space:]]+[A-Za-z0-9_]*Test[[:space:]]+extends' "$f"; then
        continue
    fi
    out=$(php "$f" 2>&1); rc=$?
    printf '\n===== %s (exit %s) =====\n%s\n' "${f#$ROOT/}" "$rc" "$out"
    if [ $rc -eq 0 ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); LIST="$LIST\n  ${f#$ROOT/}"; fi
done
printf '\n--- standalone summary: scripts_pass=%s scripts_fail=%s ---\n' "$PASS" "$FAIL"
if [ -n "$LIST" ]; then printf 'failed scripts:%b\n' "$LIST"; fi
if [ "$FAIL" -ne 0 ]; then exit 1; fi
exit 0
