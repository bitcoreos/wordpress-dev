<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$core_file = $root . '/classes/core.php';

final class X402CoreBootstrapTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;

    public function __construct(string $source) {
        $this->source = $source;
    }

    public function run(): int {
        $this->test_core_registers_verifier_and_facilitator();
        $this->test_init_services_instantiates_verifier_and_facilitator();
        $this->test_facilitator_instantiates_after_verifier();
        $this->test_facilitator_include_precedes_registration();

        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_core_registers_verifier_and_facilitator(): void {
        $this->assert_contains("register('x402.verifier'", $this->source, 'registers_x402_verifier_service');
        $this->assert_contains("register('x402.facilitator'", $this->source, 'registers_x402_facilitator_service');
    }

    private function test_init_services_instantiates_verifier_and_facilitator(): void {
        $body = $this->method_body('init_services');
        $this->assert_contains("get('x402.verifier')", $body, 'init_services_gets_x402_verifier');
        $this->assert_contains("get('x402.facilitator')", $body, 'init_services_gets_x402_facilitator');
    }

    private function test_facilitator_instantiates_after_verifier(): void {
        $body = $this->method_body('init_services');
        $verifier = strpos($body, "get('x402.verifier')");
        $facilitator = strpos($body, "get('x402.facilitator')");
        $this->assert_true($verifier !== false && $facilitator !== false && $facilitator > $verifier, 'facilitator_get_after_verifier_get');
    }

    private function test_facilitator_include_precedes_registration(): void {
        $include = strpos($this->source, "infrastructure/class-x402-facilitator.php");
        $register = strpos($this->source, "register('x402.facilitator'");
        $this->assert_true($include !== false && $register !== false && $include < $register, 'facilitator_include_before_registration');
    }

    private function method_body(string $method): string {
        $needle = 'function ' . $method . '(';
        $start = strpos($this->source, $needle);
        if ($start === false) {
            return '';
        }
        $brace = strpos($this->source, '{', $start);
        if ($brace === false) {
            return '';
        }
        $depth = 0;
        $length = strlen($this->source);
        for ($i = $brace; $i < $length; $i++) {
            if ($this->source[$i] === '{') {
                $depth++;
            } elseif ($this->source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($this->source, $brace + 1, $i - $brace - 1);
                }
            }
        }
        return '';
    }

    private function assert_contains(string $needle, string $haystack, string $name): void {
        $this->assert_true(strpos($haystack, $needle) !== false, $name);
    }

    private function assert_true(bool $condition, string $name): void {
        if ($condition) {
            $this->passed++;
            echo "PASS {$name}\n";
            return;
        }
        $this->failed++;
        echo "FAIL {$name}\n";
    }
}

if (!is_file($core_file)) {
    fwrite(STDERR, "Missing core file: {$core_file}\n");
    exit(1);
}

$test = new X402CoreBootstrapTest(file_get_contents($core_file));
exit($test->run());
