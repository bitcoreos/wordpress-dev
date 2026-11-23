<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Chunker
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Chunker
 *
 * Splits content into processable segments for AI processing.
 *
 * @since 1.0.0
 */
class Chunker
{
    /** @var int Default chunk size in characters */
    private int $chunk_size;

    /** @var int Default overlap between chunks */
    private int $overlap;

    /** @var int Minimum chunk size */
    private int $min_chunk_size;

    /** @var string Sentence boundary pattern */
    private string $sentence_pattern;

    /**
     * Constructor.
     *
     * @param int $chunk_size Default chunk size in characters.
     * @param int $overlap Overlap between chunks.
     * @since 1.0.0
     */
    public function __construct(int $chunk_size = 1000, int $overlap = 100)
    {
        $this->chunk_size = $chunk_size;
        $this->overlap = $overlap;
        $this->min_chunk_size = 100;
        $this->sentence_pattern = '/(?<=[.!?])\s+(?=[A-Z])/u';
    }

    /**
     * Chunk content into segments.
     *
     * @param string $content Content to chunk.
     * @param array<string, mixed> $options Chunking options.
     * @return array<int, array<string, mixed>> Array of chunks with metadata.
     * @since 1.0.0
     */
    public function chunk(string $content, array $options = []): array
    {
        $chunk_size = $options['chunk_size'] ?? $this->chunk_size;
        $overlap = $options['overlap'] ?? $this->overlap;
        $method = $options['method'] ?? 'auto';
        $preserve_sentences = $options['preserve_sentences'] ?? true;

        if (empty($content)) {
            return [];
        }

        // Choose chunking method
        switch ($method) {
            case 'sentence':
                return $this->chunk_by_sentences($content, $options);
            case 'paragraph':
                return $this->chunk_by_paragraphs($content, $options);
            case 'fixed':
                return $this->chunk_fixed($content, $chunk_size, $overlap);
            case 'auto':
            default:
                return $this->chunk_auto($content, $chunk_size, $overlap, $preserve_sentences);
        }
    }

    /**
     * Chunk content by sentences.
     *
     * @param string $content Content to chunk.
     * @param array<string, mixed> $options Chunking options.
     * @return array<int, array<string, mixed>> Array of chunks.
     * @since 1.0.0
     */
    public function chunk_by_sentences(string $content, array $options = []): array
    {
        $sentences_per_chunk = $options['sentences_per_chunk'] ?? 5;
        $overlap_sentences = $options['overlap_sentences'] ?? 1;

        // Split into sentences
        $sentences = preg_split($this->sentence_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        if ($sentences === false || empty($sentences)) {
            // Fallback: split by period
            $sentences = explode('.', $content);
        }

        $sentences = array_filter(array_map('trim', $sentences));
        $sentences = array_values($sentences);

        $chunks = [];
        $chunk_index = 0;
        $sentence_count = count($sentences);

        for ($i = 0; $i < $sentence_count; $i += ($sentences_per_chunk - $overlap_sentences)) {
            $chunk_sentences = array_slice($sentences, $i, $sentences_per_chunk);
            $chunk_text = implode(' ', $chunk_sentences);

            if (strlen($chunk_text) >= $this->min_chunk_size) {
                $chunks[] = [
                    'index' => $chunk_index,
                    'content' => $chunk_text,
                    'sentence_count' => count($chunk_sentences),
                    'char_count' => strlen($chunk_text),
                    'start_sentence' => $i,
                    'end_sentence' => min($i + $sentences_per_chunk - 1, $sentence_count - 1),
                ];
                $chunk_index++;
            }
        }

        return $chunks;
    }

    /**
     * Chunk content by paragraphs.
     *
     * @param string $content Content to chunk.
     * @param array<string, mixed> $options Chunking options.
     * @return array<int, array<string, mixed>> Array of chunks.
     * @since 1.0.0
     */
    public function chunk_by_paragraphs(string $content, array $options = []): array
    {
        $paragraphs_per_chunk = $options['paragraphs_per_chunk'] ?? 3;
        $overlap_paragraphs = $options['overlap_paragraphs'] ?? 0;
        $max_chunk_size = $options['max_chunk_size'] ?? $this->chunk_size * 2;

        // Split by double newlines or paragraph tags
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p\s*>/i', "\n\n", $content);
        $content = strip_tags($content);

        $paragraphs = preg_split('/\n\s*\n/', $content);
        $paragraphs = array_filter(array_map('trim', $paragraphs));
        $paragraphs = array_values($paragraphs);

        $chunks = [];
        $chunk_index = 0;
        $paragraph_count = count($paragraphs);

        for ($i = 0; $i < $paragraph_count; $i += max(1, $paragraphs_per_chunk - $overlap_paragraphs)) {
            $chunk_paragraphs = [];
            $current_size = 0;
            $start_para = $i;

            for ($j = $i; $j < min($i + $paragraphs_per_chunk, $paragraph_count); $j++) {
                if ($current_size + strlen($paragraphs[$j]) > $max_chunk_size && !empty($chunk_paragraphs)) {
                    break;
                }
                $chunk_paragraphs[] = $paragraphs[$j];
                $current_size += strlen($paragraphs[$j]);
            }

            if (!empty($chunk_paragraphs)) {
                $chunk_text = implode("\n\n", $chunk_paragraphs);
                $chunks[] = [
                    'index' => $chunk_index,
                    'content' => $chunk_text,
                    'paragraph_count' => count($chunk_paragraphs),
                    'char_count' => strlen($chunk_text),
                    'start_paragraph' => $start_para,
                    'end_paragraph' => $start_para + count($chunk_paragraphs) - 1,
                ];
                $chunk_index++;
            }
        }

        return $chunks;
    }

    /**
     * Apply overlap to existing chunks.
     *
     * @param array<int, array<string, mixed>> $chunks Existing chunks.
     * @param int $overlap_chars Number of characters to overlap.
     * @return array<int, array<string, mixed>> Chunks with overlap applied.
     * @since 1.0.0
     */
    public function overlap_chunks(array $chunks, int $overlap_chars = 100): array
    {
        if (count($chunks) < 2 || $overlap_chars <= 0) {
            return $chunks;
        }

        $overlapped = [];

        foreach ($chunks as $index => $chunk) {
            $content = $chunk['content'];

            // Add content from previous chunk end
            if ($index > 0 && isset($chunks[$index - 1])) {
                $prev_content = $chunks[$index - 1]['content'];
                $prev_suffix = substr($prev_content, -$overlap_chars);
                
                // Find word boundary
                $space_pos = strpos($prev_suffix, ' ');
                if ($space_pos !== false) {
                    $prev_suffix = substr($prev_suffix, $space_pos + 1);
                }
                
                $content = $prev_suffix . ' ' . $content;
            }

            $overlapped[] = array_merge($chunk, [
                'content' => trim($content),
                'overlap_applied' => true,
                'overlap_chars' => $overlap_chars,
            ]);
        }

        return $overlapped;
    }

    /**
     * Fixed-size chunking with optional overlap.
     *
     * @param string $content Content to chunk.
     * @param int $chunk_size Size of each chunk.
     * @param int $overlap Overlap between chunks.
     * @return array<int, array<string, mixed>> Array of chunks.
     * @since 1.0.0
     */
    private function chunk_fixed(string $content, int $chunk_size, int $overlap): array
    {
        $chunks = [];
        $length = strlen($content);
        $chunk_index = 0;
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunk_size, $length);
            $chunk_text = substr($content, $start, $chunk_size);

            // Try to end at word boundary
            if ($end < $length) {
                $last_space = strrpos($chunk_text, ' ');
                if ($last_space !== false && $last_space > $chunk_size * 0.7) {
                    $chunk_text = substr($chunk_text, 0, $last_space);
                    $end = $start + $last_space;
                }
            }

            $chunks[] = [
                'index' => $chunk_index,
                'content' => trim($chunk_text),
                'char_count' => strlen($chunk_text),
                'start_char' => $start,
                'end_char' => $end,
            ];

            $chunk_index++;
            $start = $end - $overlap;

            if ($start >= $length) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Automatic chunking based on content structure.
     *
     * @param string $content Content to chunk.
     * @param int $chunk_size Target chunk size.
     * @param int $overlap Overlap between chunks.
     * @param bool $preserve_sentences Preserve sentence boundaries.
     * @return array<int, array<string, mixed>> Array of chunks.
     * @since 1.0.0
     */
    private function chunk_auto(string $content, int $chunk_size, int $overlap, bool $preserve_sentences): array
    {
        // Detect content type
        $paragraph_count = substr_count($content, "\n\n");
        $content_length = strlen($content);

        // If content has clear paragraph structure, use paragraph chunking
        if ($paragraph_count > 3 && $content_length > $chunk_size * 2) {
            return $this->chunk_by_paragraphs($content, [
                'max_chunk_size' => $chunk_size,
                'overlap_paragraphs' => 0,
            ]);
        }

        // Otherwise, use sentence-aware fixed chunking
        $chunks = $this->chunk_fixed($content, $chunk_size, $overlap);

        if ($preserve_sentences) {
            $chunks = $this->align_to_sentences($content, $chunks);
        }

        return $chunks;
    }

    /**
     * Align chunk boundaries to sentence boundaries.
     *
     * @param string $content Original content.
     * @param array<int, array<string, mixed>> $chunks Chunks to align.
     * @return array<int, array<string, mixed>> Aligned chunks.
     * @since 1.0.0
     */
    private function align_to_sentences(string $content, array $chunks): array
    {
        $aligned = [];

        foreach ($chunks as $chunk) {
            $chunk_text = $chunk['content'];

            // Check if chunk ends mid-sentence
            if (!preg_match('/[.!?]$/', trim($chunk_text))) {
                // Find next sentence end
                $remaining = substr($content, $chunk['end_char'] ?? 0);
                if (preg_match('/^[^.!?]*[.!?]/', $remaining, $matches)) {
                    $chunk_text .= $matches[0];
                }
            }

            $aligned[] = array_merge($chunk, [
                'content' => trim($chunk_text),
                'char_count' => strlen($chunk_text),
                'sentence_aligned' => true,
            ]);
        }

        return $aligned;
    }

    /**
     * Set default chunk size.
     *
     * @param int $size Chunk size in characters.
     * @since 1.0.0
     */
    public function set_chunk_size(int $size): void
    {
        $this->chunk_size = max($this->min_chunk_size, $size);
    }

    /**
     * Set default overlap.
     *
     * @param int $overlap Overlap in characters.
     * @since 1.0.0
     */
    public function set_overlap(int $overlap): void
    {
        $this->overlap = max(0, $overlap);
    }
}
