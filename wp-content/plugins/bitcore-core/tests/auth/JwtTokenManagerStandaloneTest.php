<?php
declare(strict_types=1);

/**
 * Standalone contract tests for JWT token manager load order and validation.
 */

$base = dirname(__DIR__, 2) . '/infrastructure/auth';
$files = [
    $base . '/TokenPayload.php',
    $base . '/contracts/TokenManagerInterface.php',
    $base . '/JwtTokenManager.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "FAIL required_file_exists: {$file}\n";
        exit(1);
    }
    require_once $file;
}

use BITCORE\Infrastructure\Auth\JwtTokenManager;
use BITCORE\Infrastructure\Auth\TokenPayload;

final class JwtTokenManagerStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        $this->test_token_payload_is_independently_loadable();
        $this->test_generate_returns_three_segment_jwt();
        $this->test_validate_returns_token_payload_with_user_roles_and_data();
        $this->test_generated_tokens_include_unique_jti_claims();
        $this->test_signature_tampering_is_rejected();
        $this->test_wrong_secret_is_rejected();
        $this->test_invalid_algorithm_header_is_rejected();
        $this->test_invalid_base64_segment_is_rejected();
        $this->test_expired_token_is_rejected();
        $this->test_revoke_rejects_token();
        $this->test_refresh_revokes_old_token_and_preserves_claims();
        $this->report();
    }

    private function test_token_payload_is_independently_loadable(): void {
        $this->assert_true(class_exists(TokenPayload::class), 'token_payload_class_exists');
        $payload = new TokenPayload(7, ['subscriber'], 100, 200, ['scope' => 'ashi']);
        $this->assert_same(7, $payload->userId, 'token_payload_user_id');
        $this->assert_same(['subscriber'], $payload->roles, 'token_payload_roles');
        $this->assert_same(['scope' => 'ashi'], $payload->data, 'token_payload_data');
    }

    private function test_generate_returns_three_segment_jwt(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42, 'roles' => ['admin']], 3600);
        $parts = explode('.', $token);
        $this->assert_same(3, count($parts), 'jwt_has_three_segments');
        foreach ($parts as $index => $part) {
            $this->assert_true($part !== '', "jwt_segment_{$index}_not_empty");
            $this->assert_true((bool) preg_match('/^[A-Za-z0-9_-]+$/', $part), "jwt_segment_{$index}_base64url_only");
        }
    }

    private function test_validate_returns_token_payload_with_user_roles_and_data(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42, 'roles' => ['admin'], 'scope' => 'ashi'], 3600);
        $payload = $manager->validate($token);
        $this->assert_true($payload instanceof TokenPayload, 'validate_returns_token_payload');
        $this->assert_same(42, $payload->userId, 'validate_user_id');
        $this->assert_same(['admin'], $payload->roles, 'validate_roles');
        $this->assert_same('ashi', $payload->data['scope'] ?? null, 'validate_custom_data_preserved');
        $this->assert_true($payload->issuedAt > 0, 'validate_issued_at_set');
        $this->assert_true($payload->expiresAt > $payload->issuedAt, 'validate_expires_after_issued');
    }

    private function test_generated_tokens_include_unique_jti_claims(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $first = $manager->generate(['user_id' => 42], 3600);
        $second = $manager->generate(['user_id' => 42], 3600);
        $this->assert_true($first !== $second, 'same_second_tokens_are_unique');
        $firstPayload = json_decode($this->base64_url_decode(explode('.', $first)[1]), true);
        $secondPayload = json_decode($this->base64_url_decode(explode('.', $second)[1]), true);
        $this->assert_true((bool) preg_match('/^[a-f0-9]{32}$/', $firstPayload['jti'] ?? ''), 'first_jti_is_hex_128bit');
        $this->assert_true((bool) preg_match('/^[a-f0-9]{32}$/', $secondPayload['jti'] ?? ''), 'second_jti_is_hex_128bit');
        $this->assert_true(($firstPayload['jti'] ?? null) !== ($secondPayload['jti'] ?? null), 'jti_values_are_unique');
    }

    private function test_signature_tampering_is_rejected(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42, 'roles' => ['admin']], 3600);
        [$header, $payload, $signature] = explode('.', $token);
        $decoded = json_decode($this->base64_url_decode($payload), true);
        $decoded['data']['user_id'] = 99;
        $tampered = $header . '.' . $this->base64_url_encode(json_encode($decoded)) . '.' . $signature;
        $this->assert_throws(RuntimeException::class, fn() => $manager->validate($tampered), 'tampered_payload_rejected');
    }

    private function test_wrong_secret_is_rejected(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42], 3600);
        $other = new JwtTokenManager('different-test-secret-32-bytes-value');
        $this->assert_throws(RuntimeException::class, fn() => $other->validate($token), 'wrong_secret_rejected');
    }

    private function test_invalid_algorithm_header_is_rejected(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $now = time();
        $header = $this->base64_url_encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = $this->base64_url_encode(json_encode(['iat' => $now, 'exp' => $now + 3600, 'jti' => str_repeat('a', 32), 'data' => ['user_id' => 42]]));
        $signature = $this->base64_url_encode(hash_hmac('sha256', $header . '.' . $payload, 'test-secret-32-bytes-minimum-value', true));
        $token = $header . '.' . $payload . '.' . $signature;
        $this->assert_throws(RuntimeException::class, fn() => $manager->validate($token), 'invalid_algorithm_rejected_with_valid_signature');
    }

    private function test_invalid_base64_segment_is_rejected(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $this->assert_throws(InvalidArgumentException::class, fn() => $manager->validate('@@@.@@@.@@@'), 'invalid_base64_rejected');
    }

    private function test_expired_token_is_rejected(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42], -1);
        $this->assert_throws(RuntimeException::class, fn() => $manager->validate($token), 'expired_token_rejected');
    }

    private function test_revoke_rejects_token(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42], 3600);
        $manager->revoke($token);
        $this->assert_throws(RuntimeException::class, fn() => $manager->validate($token), 'revoked_token_rejected');
    }

    private function test_refresh_revokes_old_token_and_preserves_claims(): void {
        $manager = new JwtTokenManager('test-secret-32-bytes-minimum-value');
        $token = $manager->generate(['user_id' => 42, 'roles' => ['editor'], 'scope' => 'ashi'], 3600);
        usleep(1000);
        $newToken = $manager->refresh($token);
        $this->assert_true($newToken !== $token, 'refresh_returns_new_token');
        $this->assert_throws(RuntimeException::class, fn() => $manager->validate($token), 'refresh_revokes_old_token');
        $payload = $manager->validate($newToken);
        $this->assert_same(42, $payload->userId, 'refresh_preserves_user_id');
        $this->assert_same(['editor'], $payload->roles, 'refresh_preserves_roles');
        $this->assert_same('ashi', $payload->data['scope'] ?? null, 'refresh_preserves_custom_data');
    }

    private function base64_url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64_url_decode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) { $data .= str_repeat('=', 4 - $remainder); }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }

    private function assert_true(bool $condition, string $name): void { $condition ? $this->pass($name) : $this->fail($name); }
    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $expected === $actual ? $this->pass($name) : $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    private function assert_throws(string $class, callable $callback, string $name): void {
        try { $callback(); } catch (Throwable $e) {
            $e instanceof $class ? $this->pass($name) : $this->fail($name . ': expected ' . $class . ', got ' . get_class($e));
            return;
        }
        $this->fail($name . ': no exception thrown');
    }
    private function pass(string $name): void { $this->passed++; echo "PASS {$name}\n"; }
    private function fail(string $name): void { $this->failed++; echo "FAIL {$name}\n"; }
    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        if ($this->failed > 0) { exit(1); }
    }
}

(new JwtTokenManagerStandaloneTest())->run();
