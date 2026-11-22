<?php
declare(strict_types=1);

final class NavigationConsolidationContractTest {
    private string $themeRoot;
    private int $pass = 0;
    private array $fail = [];

    public function __construct() {
        $this->themeRoot = dirname(__DIR__, 2);
    }

    public function run(): int {
        $this->exactCanonicalTemplates();
        $this->navHeaderCanonicalOnly();
        $this->enqueueMapCanonicalOnly();
        $this->hardcodedLinksCanonicalOnly();
        return $this->report();
    }

    private function exactCanonicalTemplates(): void {
        $expected = ['page-agents.php','page-chat.php','page-dashboard.php','page-knowledge.php','page-my-posts.php','page-work.php'];
        sort($expected);
        $actual = array_map('basename', glob($this->themeRoot . '/page-*.php') ?: []);
        sort($actual);
        $this->same($expected, $actual, 'exact six canonical templates');
    }

    private function navHeaderCanonicalOnly(): void {
        $nav = $this->read($this->themeRoot . '/nav-header.php');
        foreach (['dashboard','work','agents','chat','knowledge','my-posts'] as $slug) {
            $this->contains("'{$slug}'", $nav, "nav exposes {$slug}");
        }
        foreach ($this->legacySlugs() as $slug) {
            $this->notContains("'{$slug}'", $nav, "nav hides legacy {$slug}");
        }
        $this->notContains('$secondary_pages', $nav, 'nav has no secondary registry');
        $this->notContains('nav-dropdown-menu', $nav, 'nav has no More dropdown');
    }

    private function enqueueMapCanonicalOnly(): void {
        $functions = $this->read($this->themeRoot . '/functions.php');
        $section = $this->slice($functions, 'function bitcore_enqueue_page_scripts()', "add_action('wp_enqueue_scripts', 'bitcore_enqueue_page_scripts', 15);");
        $this->contains("'work'", $section, 'enqueue maps work');
        $this->contains('work.js', $section, 'enqueue loads work.js');
        foreach (['tasks','goals','org-chart','budget','approvals','sessions'] as $slug) {
            $this->notContains("'{$slug}'", $section, "enqueue does not map legacy {$slug}");
        }
        foreach (['tasks.js','goals.js','org-chart.js','budget.js','approvals.js','sessions.js'] as $asset) {
            $this->notContains($asset, $section, "enqueue does not load legacy {$asset}");
        }
    }

    private function hardcodedLinksCanonicalOnly(): void {
        $files = glob($this->themeRoot . '/page-*.php') ?: [];
        $files[] = $this->themeRoot . '/nav-header.php';
        foreach ($files as $file) {
            $content = $this->read($file);
            foreach ($this->legacySlugs() as $slug) {
                $this->notContains('href="/' . $slug . '/', $content, basename($file) . " has no legacy href {$slug}");
                $this->notContains("href='/" . $slug . '/', $content, basename($file) . " has no legacy single href {$slug}");
            }
        }
    }

    private function legacySlugs(): array {
        return ['tasks','goals','missions','sprints','marathons','sessions','org-chart','abilities','identities','workspaces','artifacts','library','marketplace','builder','budget','approvals','checkout','credits'];
    }

    private function read(string $path): string {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function slice(string $content, string $start, string $end): string {
        $s = strpos($content, $start);
        if ($s === false) { return ''; }
        $e = strpos($content, $end, $s);
        return $e === false ? substr($content, $s) : substr($content, $s, $e - $s + strlen($end));
    }

    private function contains(string $needle, string $haystack, string $name): void {
        str_contains($haystack, $needle) ? $this->ok($name) : $this->bad($name . " missing {$needle}");
    }

    private function notContains(string $needle, string $haystack, string $name): void {
        !str_contains($haystack, $needle) ? $this->ok($name) : $this->bad($name . " unexpected {$needle}");
    }

    private function same(array $expected, array $actual, string $name): void {
        $expected === $actual ? $this->ok($name) : $this->bad($name . ' expected ' . json_encode($expected) . ' got ' . json_encode($actual));
    }

    private function ok(string $name): void { $this->pass++; echo "PASS {$name}\n"; }
    private function bad(string $name): void { $this->fail[] = $name; echo "FAIL {$name}\n"; }

    private function report(): int {
        echo "\nAssertions passed: {$this->pass}\n";
        echo 'Assertions failed: ' . count($this->fail) . "\n";
        return $this->fail === [] ? 0 : 1;
    }
}

exit((new NavigationConsolidationContractTest())->run());
