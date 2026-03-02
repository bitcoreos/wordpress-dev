<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Text Extractor
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Text Extractor
 *
 * Extracts content from URLs, PDFs, and HTML documents.
 *
 * @since 1.0.0
 */
class Text_Extractor
{
    /** @var int Maximum content length */
    private int $max_length;

    /** @var int Timeout for HTTP requests */
    private int $timeout;

    /** @var array<string> Allowed HTML tags */
    private array $allowed_tags;

    /** @var string|null Last error message */
    private ?string $last_error = null;

    /**
     * Constructor.
     *
     * @param int $max_length Maximum content length to extract.
    * @param int $timeout HTTP request timeout in seconds.
     * @since 1.0.0
     */
    public function __construct(int $max_length = 50000, int $timeout = 30)
    {
        $this->max_length = $max_length;
        $this->timeout = $timeout;
        $this->allowed_tags = ['p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote'];
    }

    /**
     * Extract text content from a URL.
     *
     * @param string $url The URL to extract from.
     * @return string|\WP_Error Extracted text or WP_Error on failure.
     * @since 1.0.0
     */
    public function extract_from_url(string $url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', __('Invalid URL provided.', 'bitcore'));
        }

        $response = wp_remote_get($url, [
            'timeout' => $this->timeout,
            'user-agent' => 'BITCORE Feeds/1.0 (+https://bitcoreos.org)',
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return new \WP_Error('request_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->last_error = "HTTP error: {$code}";
            return new \WP_Error('http_error', sprintf(__('HTTP error: %d', 'bitcore'), $code));
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $body = wp_remote_retrieve_body($response);

        // Handle PDF content
        if (strpos($content_type, 'application/pdf') !== false) {
            return $this->extract_from_pdf_content($body, $url);
        }

        // Handle HTML content
        return $this->extract_from_html($body);
    }

    /**
     * Extract text from PDF content.
     *
     * @param string $file_path Path to PDF file or raw content.
     * @param string|null $url Optional URL for context.
     * @return string|\WP_Error Extracted text or WP_Error on failure.
     * @since 1.0.0
     */
    public function extract_from_pdf(string $file_path, ?string $url = null)
    {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('PDF file not found.', 'bitcore'));
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return new \WP_Error('read_failed', __('Failed to read PDF file.', 'bitcore'));
        }

        return $this->extract_from_pdf_content($content, $url);
    }

    /**
     * Extract text from PDF binary content.
     *
     * @param string $content Raw PDF content.
     * @param string|null $url Optional URL for context.
     * @return string|\WP_Error Extracted text or WP_Error.
     * @since 1.0.0
     */
    private function extract_from_pdf_content(string $content, ?string $url = null)
    {
        // Check for pdftotext command-line tool
        if (function_exists('exec')) {
            $temp_file = tempnam(sys_get_temp_dir(), 'bitcore_pdf_');
            file_put_contents($temp_file, $content);
            
            $output_file = tempnam(sys_get_temp_dir(), 'bitcore_text_');
            $command = sprintf('pdftotext %s %s 2>&1', escapeshellarg($temp_file), escapeshellarg($output_file));
            
            exec($command, $output, $return_var);
            
            $text = '';
            if ($return_var === 0 && file_exists($output_file)) {
                $text = file_get_contents($output_file);
            }
            
            @unlink($temp_file);
            @unlink($output_file);
            
            if (!empty($text)) {
                return $this->clean_content($text);
            }
        }

        // Fallback: basic text extraction from PDF streams
        $text = $this->extract_pdf_streams($content);
        
        if (empty($text)) {
            return new \WP_Error('pdf_extraction_failed', __('Could not extract text from PDF.', 'bitcore'));
        }

        return $this->clean_content($text);
    }

    /**
     * Extract text from PDF stream objects.
     *
     * @param string $content PDF content.
     * @return string Extracted text.
     * @since 1.0.0
     */
    private function extract_pdf_streams(string $content): string
    {
        $text = '';
        
        // Match stream objects
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Try to decompress if it's compressed
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if ($decoded === false) {
                    $decoded = $stream;
                }
                
                // Extract text between parentheses (PDF text objects)
                if (preg_match_all('/\\(([^)]*)\\)/', $decoded, $text_matches)) {
                    $text .= ' ' . implode(' ', $text_matches[1]);
                }
            }
        }
        
        return $text;
    }

    /**
     * Extract text from HTML content.
     *
     * @param string $html HTML content.
     * @return string|\WP_Error Extracted text or WP_Error.
     * @since 1.0.0
     */
    public function extract_from_html(string $html)
    {
        if (empty($html)) {
            return new \WP_Error('empty_content', __('HTML content is empty.', 'bitcore'));
        }

        // Use WordPress's built-in HTML parser if available
        if (class_exists('WP_HTML_Tag_Processor')) {
            $text = $this->extract_with_tag_processor($html);
        } else {
            $text = $this->extract_with_dom($html);
        }

        return $this->clean_content($text);
    }

    /**
     * Extract text using DOMDocument.
     *
     * @param string $html HTML content.
     * @return string Extracted text.
     * @since 1.0.0
     */
    private function extract_with_dom(string $html): string
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        libxml_clear_errors();

        // Remove script and style elements
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script | //style | //nav | //footer | //header') as $node) {
            $node->parentNode->removeChild($node);
        }

        // Get body content
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            return $body->textContent;
        }

        return $dom->textContent;
    }

    /**
     * Extract text using WP_HTML_Tag_Processor (WordPress 6.4+).
     *
     * @param string $html HTML content.
     * @return string Extracted text.
     * @since 1.0.0
     */
    private function extract_with_tag_processor(string $html): string
    {
        // Strip scripts and styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Strip nav, header, footer
        $html = preg_replace('/<(nav|header|footer)[^>]*>.*?<\/\1>/is', '', $html);
        
        // Strip all tags but keep content
        $text = strip_tags($html, '<' . implode('><', $this->allowed_tags) . '>');
        
        return $text;
    }

    /**
     * Clean and normalize extracted content.
     *
     * @param string $content Raw content.
     * @return string Cleaned content.
     * @since 1.0.0
     */
    public function clean_content(string $content): string
    {
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove control characters except newlines and tabs
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        // Trim to max length
        if (strlen($content) > $this->max_length) {
            $content = substr($content, 0, $this->max_length);
            // Try to end at a word boundary
            $last_space = strrpos($content, ' ');
            if ($last_space !== false) {
                $content = substr($content, 0, $last_space) . '...';
            }
        }
        
        return trim($content);
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
     * Set maximum content length.
     *
     * @param int $length Maximum length.
     * @since 1.0.0
     */
    public function set_max_length(int $length): void
    {
        $this->max_length = $length;
    }

    /**
     * Set HTTP timeout.
     *
     * @param int $seconds Timeout in seconds.
     * @since 1.0.0
     */
    public function set_timeout(int $seconds): void
    {
        $this->timeout = $seconds;
    }
}
