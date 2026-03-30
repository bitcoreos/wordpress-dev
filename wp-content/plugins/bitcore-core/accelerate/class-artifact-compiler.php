<?php

declare(strict_types=1);

/**
 * BITCORE Accelerate - Artifact Compiler
 *
 * @package BITCORE\Accelerate
 * @since 1.0.0
 */

namespace BITCORE\Accelerate;

use BITCORE\Artifacts\Artifact_Repository;
use WP_Error;

/**
 * Artifact Compiler
 *
 * Merges and compiles multiple artifacts into unified knowledge resources.
 *
 * @since 1.0.0
 */
class Artifact_Compiler
{
    /** @var Artifact_Repository Artifact repository */
    private Artifact_Repository $repository;

    /** @var Distillation_Engine Distillation engine */
    private Distillation_Engine $distiller;

    /**
     * Constructor.
     *
     * @param Artifact_Repository|null $repository Optional repository.
     * @param Distillation_Engine|null $distiller Optional distiller.
     * @since 1.0.0
     */
    public function __construct(?Artifact_Repository $repository = null, ?Distillation_Engine $distiller = null)
    {
        $this->repository = $repository ?? new Artifact_Repository();
        $this->distiller = $distiller ?? new Distillation_Engine($this->repository);
    }

    /**
     * Compile multiple artifacts into one.
     *
     * @param array<int> $artifact_ids Artifact IDs to compile.
     * @param array<string, mixed> $options Compilation options.
     * @return array<string, mixed>|WP_Error Compiled result or error.
     * @since 1.0.0
     */
    public function compile(array $artifact_ids, array $options = []): array|WP_Error
    {
        if (empty($artifact_ids)) {
            return new WP_Error(
                'no_artifacts',
                'No artifacts provided for compilation.',
                ['status' => 400]
            );
        }

        $artifacts = [];
        foreach ($artifact_ids as $id) {
            $artifact = $this->repository->get($id);
            if ($artifact) {
                $artifacts[] = $artifact;
            }
        }

        if (empty($artifacts)) {
            return new WP_Error(
                'no_valid_artifacts',
                'No valid artifacts found for compilation.',
                ['status' => 404]
            );
        }

        $method = $options['method'] ?? 'merge';
        $separator = $options['separator'] ?? "\n\n---\n\n";

        switch ($method) {
            case 'merge':
                $result = $this->merge_artifacts($artifacts, $separator);
                break;
            case 'extract':
                $result = $this->extract_and_merge($artifacts, $options);
                break;
            case 'categorize':
                $result = $this->categorize_and_compile($artifacts, $options);
                break;
            case 'distill':
                $result = $this->distill_and_compile($artifacts, $options);
                break;
            default:
                $result = $this->merge_artifacts($artifacts, $separator);
        }

        return $result;
    }

    /**
     * Merge artifacts by concatenating content.
     *
     * @param array<int, array<string, mixed>> $artifacts Artifacts to merge.
     * @param string $separator Content separator.
     * @return array<string, mixed> Merged result.
     * @since 1.0.0
     */
    private function merge_artifacts(array $artifacts, string $separator): array
    {
        $contents = [];
        $metadata = [
            'source_ids' => [],
            'source_names' => [],
            'source_types' => [],
        ];

        foreach ($artifacts as $artifact) {
            $contents[] = sprintf(
                "# %s\n\n%s",
                $artifact['name'],
                $artifact['content']
            );

            $metadata['source_ids'][] = $artifact['id'];
            $metadata['source_names'][] = $artifact['name'];
            $metadata['source_types'][] = $artifact['type'];
        }

        $merged_content = implode($separator, $contents);

        return [
            'success' => true,
            'method' => 'merge',
            'content' => $merged_content,
            'metadata' => $metadata,
            'stats' => [
                'artifact_count' => count($artifacts),
                'total_length' => strlen($merged_content),
            ],
        ];
    }

    /**
     * Extract key points from each artifact and merge.
     *
     * @param array<int, array<string, mixed>> $artifacts Artifacts to process.
     * @param array<string, mixed> $options Extraction options.
     * @return array<string, mixed> Extracted and merged result.
     * @since 1.0.0
     */
    private function extract_and_merge(array $artifacts, array $options = []): array
    {
        $extracted = [];
        $all_points = [];

        foreach ($artifacts as $artifact) {
            $distilled = $this->distiller->distill($artifact['id'], 'extract_key_points', $options);

            if (!is_wp_error($distilled)) {
                $points = $distilled['result']['points'] ?? [];
                foreach ($points as $point) {
                    $all_points[] = [
                        'source' => $artifact['name'],
                        'point' => $point,
                    ];
                }
                $extracted[] = [
                    'artifact_id' => $artifact['id'],
                    'artifact_name' => $artifact['name'],
                    'points' => $points,
                ];
            }
        }

        $content = "# Extracted Key Points\n\n";
        foreach ($all_points as $item) {
            $content .= sprintf("- **%s**: %s\n", $item['source'], $item['point']);
        }

        return [
            'success' => true,
            'method' => 'extract',
            'content' => $content,
            'extracted_data' => $extracted,
            'total_points' => count($all_points),
            'stats' => [
                'artifact_count' => count($artifacts),
                'points_extracted' => count($all_points),
            ],
        ];
    }

    /**
     * Categorize artifacts by type and compile.
     *
     * @param array<int, array<string, mixed>> $artifacts Artifacts to process.
     * @param array<string, mixed> $options Compilation options.
     * @return array<string, mixed> Categorized result.
     * @since 1.0.0
     */
    private function categorize_and_compile(array $artifacts, array $options = []): array
    {
        $categorized = [];

        foreach ($artifacts as $artifact) {
            $type = $artifact['type'] ?? 'uncategorized';

            if (!isset($categorized[$type])) {
                $categorized[$type] = [];
            }

            $categorized[$type][] = $artifact;
        }

        $content = "# Categorized Compilation\n\n";
        $stats = [];

        foreach ($categorized as $type => $items) {
            $content .= sprintf("## %s (%d items)\n\n", ucfirst($type), count($items));

            foreach ($items as $item) {
                $content .= sprintf("### %s\n\n%s\n\n", $item['name'], $item['content']);
            }

            $stats[$type] = count($items);
        }

        return [
            'success' => true,
            'method' => 'categorize',
            'content' => $content,
            'categories' => array_keys($categorized),
            'categorized_data' => $categorized,
            'stats' => $stats,
        ];
    }

    /**
     * Distill each artifact and compile the results.
     *
     * @param array<int, array<string, mixed>> $artifacts Artifacts to process.
     * @param array<string, mixed> $options Distillation options.
     * @return array<string, mixed> Distilled and compiled result.
     * @since 1.0.0
     */
    private function distill_and_compile(array $artifacts, array $options = []): array
    {
        $strategy = $options['strategy'] ?? 'summarize';
        $distilled_results = [];
        $content = "# Distilled Compilation\n\n";

        foreach ($artifacts as $artifact) {
            $result = $this->distiller->distill($artifact['id'], $strategy, $options);

            if (!is_wp_error($result)) {
                $distilled_results[] = [
                    'artifact_id' => $artifact['id'],
                    'artifact_name' => $artifact['name'],
                    'distilled' => $result['result']['content'] ?? '',
                    'metadata' => $result['metadata'] ?? [],
                ];

                $content .= sprintf(
                    "## %s\n\n%s\n\n",
                    $artifact['name'],
                    $result['result']['content'] ?? ''
                );
            }
        }

        return [
            'success' => true,
            'method' => 'distill',
            'strategy' => $strategy,
            'content' => $content,
            'distilled_results' => $distilled_results,
            'stats' => [
                'artifact_count' => count($artifacts),
                'distilled_count' => count($distilled_results),
            ],
        ];
    }

    /**
     * Create compiled artifact from multiple sources.
     *
     * @param array<int> $artifact_ids Source artifact IDs.
     * @param string $name Name for compiled artifact.
     * @param array<string, mixed> $options Compilation options.
     * @return int|WP_Error New artifact ID or error.
     * @since 1.0.0
     */
    public function create_compiled_artifact(array $artifact_ids, string $name, array $options = []): int|WP_Error
    {
        $compiled = $this->compile($artifact_ids, $options);

        if (is_wp_error($compiled)) {
            return $compiled;
        }

        $type = $options['type'] ?? 'compiled';

        $new_artifact = $this->repository->create([
            'name' => $name,
            'type' => $type,
            'content' => $compiled['content'],
            'metadata' => [
                'compiled_from' => $artifact_ids,
                'compile_method' => $compiled['method'] ?? 'merge',
                'compile_stats' => $compiled['stats'] ?? [],
                'compiled_at' => current_time('mysql', true),
            ],
        ]);

        return $new_artifact;
    }

    /**
     * Get compilation preview without saving.
     *
     * @param array<int> $artifact_ids Artifact IDs to preview.
     * @param array<string, mixed> $options Compilation options.
     * @return array<string, mixed>|WP_Error Preview result.
     * @since 1.0.0
     */
    public function preview(array $artifact_ids, array $options = []): array|WP_Error
    {
        return $this->compile($artifact_ids, array_merge($options, ['preview' => true]));
    }

    /**
     * Get available compilation methods.
     *
     * @return array<string, string> Method names and descriptions.
     * @since 1.0.0
     */
    public function get_methods(): array
    {
        return [
            'merge' => 'Concatenate all artifact contents with separators',
            'extract' => 'Extract key points from each artifact and combine',
            'categorize' => 'Group by type and compile by category',
            'distill' => 'Apply distillation strategy to each artifact and combine',
        ];
    }
}
