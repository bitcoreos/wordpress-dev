#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
export GIT_PAGER=cat PAGER=cat

TIMEOUT_SECONDS="${ASHI_TEST_TIMEOUT:-25}"
RESULT_DIR="${ASHI_RESULT_DIR:-/tmp}"
PHP_FILES="$RESULT_DIR/ashi_all_php_files.txt"
ALL_TESTS="$RESULT_DIR/ashi_all_php_tests.txt"
EXEC_TESTS="$RESULT_DIR/ashi_executable_php_tests.txt"
INTEGRATION_TESTS="$RESULT_DIR/ashi_integration_only_php_tests.txt"
SYNTAX_FAILURES="$RESULT_DIR/ashi_php_syntax_failures.txt"
RESULTS="$RESULT_DIR/ashi_local_test_results.tsv"

: > "$PHP_FILES"
: > "$ALL_TESTS"
: > "$EXEC_TESTS"
: > "$INTEGRATION_TESTS"
: > "$SYNTAX_FAILURES"
: > "$RESULTS"

printf '%s\n' '=== ASHI LOCAL REGRESSION START ==='
printf 'root=%s\n' "$ROOT"
printf 'timeout_seconds=%s\n' "$TIMEOUT_SECONDS"

printf '\n--- environment ---\n'
php -r 'echo "PHP_VERSION=" . PHP_VERSION . PHP_EOL; echo "WP_TESTS_DIR=" . (getenv("WP_TESTS_DIR") ?: "") . PHP_EOL;'
(command -v phpunit && phpunit --version) || echo 'phpunit_not_available'
(command -v composer && composer --version) || echo 'composer_not_available'

printf '\n--- git state summary ---\n'
git --no-pager status --short | sed -n '1,180p' || true

printf '\n--- PHP syntax: plugin/theme ---\n'
find wp-content/plugins/bitcore-core wp-content/themes/bitcore-theme -type f -name '*.php' | sort > "$PHP_FILES"
printf 'php_files=%s\n' "$(wc -l < "$PHP_FILES" | tr -d ' ')"
syntax_failures=0
while IFS= read -r file; do
  if ! php -l "$file" >/tmp/ashi_php_lint.out 2>&1; then
    syntax_failures=$((syntax_failures + 1))
    printf 'SYNTAX_FAIL\t%s\t' "$file" >> "$SYNTAX_FAILURES"
    tr '\n' ' ' < /tmp/ashi_php_lint.out >> "$SYNTAX_FAILURES"
    printf '\n' >> "$SYNTAX_FAILURES"
  fi
done < "$PHP_FILES"
printf 'syntax_failures=%s\n' "$syntax_failures"
cat "$SYNTAX_FAILURES"

printf '\n--- classify tests ---\n'
find wp-content/plugins/bitcore-core/tests wp-content/themes/bitcore-theme/tests -type f -name '*.php' 2>/dev/null | sort > "$ALL_TESTS"
while IFS= read -r file; do
  [ -n "$file" ] || continue
  case "$file" in
    */bootstrap.php)
      printf '%s\treason=wordpress_phpunit_bootstrap_requires_WP_TESTS_DIR\n' "$file" >> "$INTEGRATION_TESTS"
      ;;
    *StandaloneTest.php|*ContractTest.php|*FunctionalTest.php|*ScalabilityTest.php|*HooksTest.php|*RouteOwnershipTest.php|*MessageContractTest.php|*RateLimitTest.php|*DashboardStatsEndpointTest.php|*DiscoursePermissionCaptureTest.php|*PrivacyRetentionTest.php|*X402CoreBootstrapTest.php|*X402FacilitatorClientTest.php|*X402VerifierTest.php|*/sanitization.php|*/test-rest-api.php|*/test-sanitization.php)
      printf '%s\n' "$file" >> "$EXEC_TESTS"
      ;;
    *)
      if grep -Eq 'extends \\WP_UnitTestCase|use PHPUnit\\Framework\\TestCase|extends TestCase' "$file"; then
        printf '%s\treason=integration_or_phpunit_dependency_required\n' "$file" >> "$INTEGRATION_TESTS"
      else
        printf '%s\treason=unclassified_non_executable\n' "$file" >> "$INTEGRATION_TESTS"
      fi
      ;;
  esac
done < "$ALL_TESTS"
printf 'all_tests=%s\n' "$(wc -l < "$ALL_TESTS" | tr -d ' ')"
printf 'executable_tests=%s\n' "$(wc -l < "$EXEC_TESTS" | tr -d ' ')"
printf 'integration_only_tests=%s\n' "$(wc -l < "$INTEGRATION_TESTS" | tr -d ' ')"

printf '\n--- integration-only inventory ---\n'
cat "$INTEGRATION_TESTS"

printf '\n--- execute local tests ---\n'
failed=0
passed=0
while IFS= read -r file; do
  [ -n "$file" ] || continue
  printf 'RUN %s\n' "$file"
  if timeout "$TIMEOUT_SECONDS" php "$file" >/tmp/ashi_one_test.out 2>&1; then
    pass_line=$(grep -E 'Assertions passed:|OK \(' /tmp/ashi_one_test.out | tail -1 || true)
    fail_line=$(grep -E 'Assertions failed:|FAILURES!|Fatal error|PHP Fatal' /tmp/ashi_one_test.out | tail -1 || true)
    if grep -Eq 'Assertions failed:[[:space:]]*[1-9][0-9]*|FAILURES!|Fatal error|PHP Fatal|Uncaught ' /tmp/ashi_one_test.out; then
      failed=$((failed + 1))
      printf 'FAIL\t%s\trc=0_but_failure_marker\t' "$file" | tee -a "$RESULTS"
      tail -30 /tmp/ashi_one_test.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g' | tee -a "$RESULTS"
      printf '\n'
    else
      passed=$((passed + 1))
      printf 'PASS\t%s\t%s\t%s\n' "$file" "$pass_line" "$fail_line" | tee -a "$RESULTS"
    fi
  else
    rc=$?
    failed=$((failed + 1))
    printf 'FAIL\t%s\trc=%s\t' "$file" "$rc" | tee -a "$RESULTS"
    tail -30 /tmp/ashi_one_test.out | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g' | tee -a "$RESULTS"
    printf '\n'
  fi
done < "$EXEC_TESTS"

printf '\n--- local regression summary ---\n'
printf 'syntax_failures=%s\n' "$syntax_failures"
printf 'passed=%s\n' "$passed"
printf 'failed=%s\n' "$failed"
printf 'results=%s\n' "$RESULTS"
printf 'integration_only=%s\n' "$INTEGRATION_TESTS"

if [ "$syntax_failures" -ne 0 ] || [ "$failed" -ne 0 ]; then
  printf '%s\n' '=== ASHI LOCAL REGRESSION FAILED ==='
  exit 1
fi

printf '%s\n' '=== ASHI LOCAL REGRESSION PASSED ==='
