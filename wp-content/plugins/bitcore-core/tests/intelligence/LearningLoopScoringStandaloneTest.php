<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../../../../');
}

function sanitize_text_field($value) {
    return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $value);
}

function current_time($type) {
    return '2026-05-26 10:00:00';
}

final class FakeLearningLoopWpdb {
    public string $prefix = 'wp_';
    public ?object $episode = null;
    public ?object $recommendation = null;
    public array $queries = [];

    public function prepare($query, ...$args) {
        return ['query' => $query, 'args' => $args];
    }

    public function get_row($prepared) {
        $query = is_array($prepared) ? $prepared['query'] : (string) $prepared;
        $this->queries[] = $query;
        if (str_contains($query, 'bitcore_learning_episodes')) {
            return $this->episode;
        }
        if (str_contains($query, 'bitcore_learning_recommendations')) {
            return $this->recommendation;
        }
        return null;
    }
}

final class LearningLoopScoringStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;
    private FakeLearningLoopWpdb $db;

    public function run(): int {
        global $wpdb;
        $this->db = new FakeLearningLoopWpdb();
        $wpdb = $this->db;
        require_once __DIR__ . '/../../classes/class-learning-loop.php';

        $this->episode_score_uses_weighted_real_data();
        $this->episode_score_clamps_inputs_and_failure_penalty();
        $this->recommendation_fallback_uses_action_type_hint();
        $this->hash_fallback_is_deterministic_and_bounded();
        $this->source_has_no_random_primitives();

        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function freshLoop(): BITCORE_Learning_Loop {
        return new BITCORE_Learning_Loop();
    }

    private function episode_score_uses_weighted_real_data(): void {
        $this->db->episode = (object) [
            'score' => 80,
            'confidence' => 75,
            'risk_score' => 20,
            'outcome_label' => 'success',
            'action_type' => 'task_create',
            'agent_id' => 'aria',
        ];
        $this->db->recommendation = null;
        $result = $this->freshLoop()->compute_action_score('task_create_abc123', 'org_main');

        $this->assert_same(0.84, $result['score'], 'episode_weighted_score_with_success_modifier');
        $this->assert_same('episode', $result['breakdown']['source'], 'episode_breakdown_source');
        $this->assert_same(0.8, $result['breakdown']['episode_score'], 'episode_breakdown_score');
        $this->assert_same(0.75, $result['breakdown']['confidence'], 'episode_breakdown_confidence');
        $this->assert_same(0.2, $result['breakdown']['risk_score'], 'episode_breakdown_risk');
        $this->assert_same('aria', $result['breakdown']['agent_id'], 'episode_breakdown_agent');
    }

    private function episode_score_clamps_inputs_and_failure_penalty(): void {
        $this->db->episode = (object) [
            'score' => 150,
            'confidence' => -10,
            'risk_score' => 250,
            'outcome_label' => 'failure',
            'action_type' => 'deploy',
            'agent_id' => 'forge',
        ];
        $result = $this->freshLoop()->compute_action_score('deploy_xyz', 'org_main');

        $this->assert_same(0.6, $result['score'], 'episode_clamps_extreme_values_and_applies_failure_penalty');
        $this->assert_same(1.0, $result['breakdown']['episode_score'], 'episode_score_clamped_to_one');
        $this->assert_same(0.0, $result['breakdown']['confidence'], 'confidence_clamped_to_zero');
        $this->assert_same(1.0, $result['breakdown']['risk_score'], 'risk_clamped_to_one');
    }

    private function recommendation_fallback_uses_action_type_hint(): void {
        $this->db->episode = null;
        $this->db->recommendation = (object) [
            'avg_score' => 90,
            'success_rate' => 0.8,
            'confidence' => 0.5,
            'sample_size' => 100,
        ];
        $result = $this->freshLoop()->compute_action_score('task_create_a1b2', 'org_main');

        $this->assert_same('recommendation', $result['breakdown']['source'], 'recommendation_breakdown_source');
        $this->assert_same('task_create', $result['breakdown']['action_type'], 'recommendation_action_type_hint');
        $this->assert_same(0.79, $result['score'], 'recommendation_weighted_score');
        $this->assert_same(100, $result['breakdown']['sample_size'], 'recommendation_sample_size_recorded');
    }

    private function hash_fallback_is_deterministic_and_bounded(): void {
        $this->db->episode = null;
        $this->db->recommendation = null;
        $first = $this->freshLoop()->compute_action_score('unknown_action_999', 'org_main');
        $second = $this->freshLoop()->compute_action_score('unknown_action_999', 'org_main');
        $other = $this->freshLoop()->compute_action_score('unknown_action_998', 'org_main');

        $this->assert_same('hash_fallback', $first['breakdown']['source'], 'hash_fallback_source');
        $this->assert_same($first['score'], $second['score'], 'hash_fallback_same_input_same_score');
        $this->assert_true($first['score'] >= 0.2 && $first['score'] <= 0.8, 'hash_fallback_score_bounded_to_center_band');
        $this->assert_true($other['score'] >= 0.2 && $other['score'] <= 0.8, 'hash_fallback_other_score_bounded');
    }

    private function source_has_no_random_primitives(): void {
        $source = file_get_contents(__DIR__ . '/../../classes/class-learning-loop.php');
        $this->assert_true(strpos($source, 'rand(') === false, 'source_no_rand');
        $this->assert_true(strpos($source, 'mt_rand(') === false, 'source_no_mt_rand');
        $this->assert_true(strpos($source, 'wp_rand(') === false, 'source_no_wp_rand');
    }

    private function assert_same($expected, $actual, string $label): void {
        if ($expected === $actual) {
            echo "PASS {$label}\n";
            $this->passed++;
            return;
        }
        echo "FAIL {$label} expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n";
        $this->failed++;
    }

    private function assert_true(bool $condition, string $label): void {
        if ($condition) {
            echo "PASS {$label}\n";
            $this->passed++;
            return;
        }
        echo "FAIL {$label}\n";
        $this->failed++;
    }
}

exit((new LearningLoopScoringStandaloneTest())->run());
