<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Summarizer
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Summarizer
 *
 * AI-powered content summarization using BITCORE AI services.
 *
 * @since 1.0.0
 */
class Summarizer
{
    /** @var string AI API endpoint */
    private string $api_url;

    /** @var string AI API key */
    private string $api_key;

    /** @var string Model identifier */
    private string $model;

    /** @var int Maximum tokens for summary */
    private int $max_tokens;

    /** @var float Temperature for generation */
    private float $temperature;

    /** @var int Timeout for API requests */
    private int $timeout;

    /** @var string|null Last error */
    private ?string $last_error = null;

    /**
     * Constructor.
     *
     * @param string|null $api_url Optional API URL override.
     * @param string|null $api_key Optional API key override.
     * @since 1.0.0
     */
    public function __construct(?string $api_url = null, ?string $api_key = null)
    {
        $this->api_url = $api_url ?? $this->get_default_api_url();
        $this->api_key = $api_key ?? $this->get_default_api_key();
        $this->model = 'gpt-4o-mini';
        $this->max_tokens = 500;
        $this->temperature = 0.3;
        $this->timeout = 60;
    }

    /**
     * Summarize content.
     *
     * @param string $content Content to summarize.
     * @param array<string, mixed> $options Summarization options.
     * @return string|\WP_Error Summary or WP_Error on failure.
     * @since 1.0.0
     */
    public function summarize(string $content, array $options = [])
    {
        if (empty($content)) {
            return new \WP_Error('empty_content', __('Content cannot be empty.', 'bitcore'));
        }

        $max_length = $options['max_length'] ?? 200;
        $style = $options['style'] ?? 'concise'; // concise, detailed, bullet
        $focus = $options['focus'] ?? null;

        $prompt = $this->build_summary_prompt($content, $max_length, $style, $focus);
        
        $response = $this->call_ai_api($prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Extract key points from content.
     *
     * @param string $content Content to analyze.
     * @param int $max_points Maximum number of points.
     * @return array<int, string>|\WP_Error Array of key points or WP_Error.
     * @since 1.0.0
     */
    public function extract_key_points(string $content, int $max_points = 5)
    {
        if (empty($content)) {
            return new \WP_Error('empty_content', __('Content cannot be empty.', 'bitcore'));
        }

        $prompt = sprintf(
            "Extract the %d most important key points from the following content. " .
            "Return as a JSON array of strings, each point being a single sentence.\n\n" .
            "Content:\n%s",
            $max_points,
            $this->truncate_content($content, 3000)
        );

        $response = $this->call_ai_api($prompt, ['json_output' => true]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parse JSON response
        $points = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($points)) {
            // Fallback: try to extract bullet points
            $points = $this->parse_bullet_points($response);
        }

        return array_slice($points, 0, $max_points);
    }

    /**
     * Generate an abstract for content.
     *
     * @param string $content Content to abstract.
     * @param int $word_limit Word limit for abstract.
     * @return string|\WP_Error Abstract or WP_Error.
     * @since 1.0.0
     */
    public function generate_abstract(string $content, int $word_limit = 150)
    {
        if (empty($content)) {
            return new \WP_Error('empty_content', __('Content cannot be empty.', 'bitcore'));
        }

        $prompt = sprintf(
            "Generate an academic-style abstract for the following content. " .
            "The abstract should be approximately %d words and provide a comprehensive overview.\n\n" .
            "Content:\n%s",
            $word_limit,
            $this->truncate_content($content, 4000)
        );

        return $this->call_ai_api($prompt);
    }

    /**
     * Set the AI model to use.
     *
     * @param string $model Model identifier.
     * @since 1.0.0
     */
    public function set_model(string $model): void
    {
        $this->model = $model;
    }

    /**
     * Set maximum tokens for output.
     *
     * @param int $tokens Maximum tokens.
     * @since 1.0.0
     */
    public function set_max_tokens(int $tokens): void
    {
        $this->max_tokens = $tokens;
    }

    /**
     * Set temperature for generation.
     *
     * @param float $temperature Temperature (0.0-2.0).
     * @since 1.0.0
     */
    public function set_temperature(float $temperature): void
    {
        $this->temperature = max(0.0, min(2.0, $temperature));
    }

    /**
     * Get the last error message.
     *
     * @return string|null Last error or null.
     * @since 1.0.0
     */
    public function get_last_error(): ?string
    {
        return $this->last_error;
    }

    /**
     * Build summary prompt.
     *
     * @param string $content Content to summarize.
     * @param int $max_length Maximum length.
     * @param string $style Summary style.
     * @param string|null $focus Optional focus area.
     * @return string Formatted prompt.
     * @since 1.0.0
     */
    private function build_summary_prompt(string $content, int $max_length, string $style, ?string $focus): string
    {
        $style_instructions = [
            'concise' => 'Provide a brief, direct summary without unnecessary words.',
            'detailed' => 'Provide a comprehensive summary covering all main points.',
            'bullet' => 'Format the summary as bullet points, each starting with a dash.',
        ];

        $instruction = $style_instructions[$style] ?? $style_instructions['concise'];

        $prompt = sprintf(
            "Summarize the following content. %s " .
            "Keep the summary under %d characters.\n",
            $instruction,
            $max_length
        );

        if ($focus) {
            $prompt .= sprintf("Focus particularly on: %s.\n", $focus);
        }

        $prompt .= sprintf("\nContent:\n%s", $this->truncate_content($content, 3500));

        return $prompt;
    }

    /**
     * Call the AI API.
     *
     * @param string $prompt The prompt to send.
     * @param array<string, mixed> $options API options.
     * @return string|\WP_Error Response or WP_Error.
     * @since 1.0.0
     */
    private function call_ai_api(string $prompt, array $options = [])
    {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', __('AI API key is not configured.', 'bitcore'));
        }

        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise summarization assistant. Provide clear, accurate summaries.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
        ];

        if (!empty($options['json_output'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post($this->api_url, [
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return new \WP_Error('api_error', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body_response = wp_remote_retrieve_body($response);
            $error_data = json_decode($body_response, true);
            $error_msg = $error_data['error']['message'] ?? "API error: {$code}";
            $this->last_error = $error_msg;
            return new \WP_Error('api_error', $error_msg);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['choices'][0]['message']['content'])) {
            $this->last_error = 'Invalid API response structure';
            return new \WP_Error('invalid_response', __('Invalid API response.', 'bitcore'));
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Truncate content to fit within limits.
     *
     * @param string $content Content to truncate.
     * @param int $max_chars Maximum characters.
     * @return string Truncated content.
     * @since 1.0.0
     */
    private function truncate_content(string $content, int $max_chars): string
    {
        if (strlen($content) <= $max_chars) {
            return $content;
        }

        $truncated = substr($content, 0, $max_chars);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }

        return $truncated . '...';
    }

    /**
     * Parse bullet points from text.
     *
     * @param string $text Text containing bullet points.
     * @return array<int, string> Array of points.
     * @since 1.0.0
     */
    private function parse_bullet_points(string $text): array
    {
        $lines = explode("\n", $text);
        $points = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Remove common bullet markers
            $line = preg_replace('/^[\-•*]\s*/', '', $line);
            $line = preg_replace('/^\d+\.\s*/', '', $line);

            if (!empty($line)) {
                $points[] = $line;
            }
        }

        return $points;
    }

    /**
     * Get default API URL from settings.
     *
     * @return string API URL.
     * @since 1.0.0
     */
    private function get_default_api_url(): string
    {
        return get_option('bitcore_ai_api_url', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * Get default API key from settings.
     *
     * @return string API key.
     * @since 1.0.0
     */
    private function get_default_api_key(): string
    {
        return get_option('bitcore_ai_api_key', '');
    }
}
