<?php
declare(strict_types=1);

/**
 * ASHI canonical app route auth contract.
 *
 * The six WordPress user-surface pages are app routes, not public marketing pages.
 * SSO remains owned by WP-Discourse; templates must use WordPress login redirect
 * so the existing WP-Discourse plugin can handle the login flow as configured.
 */
final class AppRouteAuthContractTest {
    private string $themeRoot;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct() {
        $this->themeRoot = dirname(__DIR__, 2);
    }

    public function run(): int {
        $this->canonical_app_templates_require_login();
        $this->canonical_app_templates_preserve_return_url();
        $this->public_home_and_nav_are_not_app_auth_targets();
        $this->canonical_app_templates_include_shared_navigation();
        $this->front_page_explains_ashi_without_private_api_calls();
        printf("\nAssertions passed: %d\nAssertions failed: %d\n", $this->passed, $this->failed);
        return $this->failed === 0 ? 0 : 1;
    }

    private function canonical_app_templates_require_login(): void {
        foreach ($this->canonicalTemplates() as $file) {
            $source = $this->read($file);
            $base = basename($file);
            $this->contains('is_user_logged_in()', $source, "$base requires logged-in user");
            $this->contains('wp_redirect(', $source, "$base redirects unauthenticated users");
            $this->contains('wp_login_url(', $source, "$base uses WordPress login URL");
            $this->contains('exit;', $source, "$base exits after redirect");
        }
    }

    private function canonical_app_templates_preserve_return_url(): void {
        foreach ($this->canonicalTemplates() as $file) {
            $source = $this->read($file);
            $base = basename($file);
            $this->contains('wp_login_url($_SERVER[\'REQUEST_URI\'])', $source, "$base preserves request URI return target");
            $this->notContains('hub.bitwiki.org/session/sso', $source, "$base does not hardcode Discourse SSO endpoint");
            $this->notContains('discourse_sso_secret', $source, "$base does not handle SSO secrets");
            $this->notContains('wpdc_sso_secret', $source, "$base does not handle WP-Discourse secret");
        }
    }

    private function public_home_and_nav_are_not_app_auth_targets(): void {
        $nav = $this->read($this->themeRoot . '/nav-header.php');
        $this->contains("'dashboard'", $nav, 'nav includes dashboard app route');
        $this->contains("'work'", $nav, 'nav includes work app route');
        $this->contains("'my-posts'", $nav, 'nav includes my-posts app route');
        $this->notContains('wp_redirect(', $nav, 'nav does not perform auth redirects');
    }


    private function canonical_app_templates_include_shared_navigation(): void {
        foreach ($this->canonicalTemplates() as $file) {
            $source = $this->read($file);
            $base = basename($file);
            $this->contains('nav-header.php', $source, "$base includes shared ASHI navigation");
        }
    }

    private function front_page_explains_ashi_without_private_api_calls(): void {
        $source = $this->read($this->themeRoot . '/front-page.php');
        $this->contains('ASHI · personal swarm manager', $source, 'front page identifies ASHI as personal swarm manager');
        $this->contains('BIThub is the shared Discourse forum', $source, 'front page explains BIThub role');
        $this->contains('wp_login_url(home_url(\'/dashboard/\'))', $source, 'front page uses WordPress login URL to dashboard');
        $this->contains('nav-header.php', $source, 'front page includes shared navigation');
        $this->notContains('wp-json/bitcore/v1/', $source, 'front page does not call private BITCORE REST APIs');
        $this->notContains('discourse_sso_secret', $source, 'front page does not handle SSO secrets');
        $this->notContains('wpdc_sso_secret', $source, 'front page does not handle WP-Discourse secret');
    }

    private function canonicalTemplates(): array {
        return array_map(fn(string $file): string => $this->themeRoot . '/' . $file, [
            'page-dashboard.php',
            'page-work.php',
            'page-agents.php',
            'page-chat.php',
            'page-knowledge.php',
            'page-my-posts.php',
        ]);
    }

    private function read(string $path): string {
        if (!is_file($path)) {
            $this->fail('missing file ' . $path);
            return '';
        }
        return (string) file_get_contents($path);
    }

    private function contains(string $needle, string $haystack, string $label): void {
        str_contains($haystack, $needle) ? $this->pass($label) : $this->fail($label . ' missing [' . $needle . ']');
    }

    private function notContains(string $needle, string $haystack, string $label): void {
        !str_contains($haystack, $needle) ? $this->pass($label) : $this->fail($label . ' unexpected [' . $needle . ']');
    }

    private function pass(string $label): void { $this->passed++; echo "PASS {$label}\n"; }
    private function fail(string $label): void { $this->failed++; echo "FAIL {$label}\n"; }
}

exit((new AppRouteAuthContractTest())->run());
