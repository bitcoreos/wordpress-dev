<?php

declare(strict_types=1);

/**
 * BITCORE Timeline Feeds - Feed Generator
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Feeds;

/**
 * Feed Generator
 *
 * Generates RSS and Atom feeds from aggregated content.
 *
 * @since 1.0.0
 */
class Feed_Generator
{
    /** @var string Feed title */
    private string $title;

    /** @var string Feed description */
    private string $description;

    /** @var string Feed URL */
    private string $feed_url;

    /** @var string Site URL */
    private string $site_url;

    /**
     * Constructor.
     *
     * @param string $title Feed title.
     * @param string $description Feed description.
     * @since 1.0.0
     */
    public function __construct(string $title = 'BITCORE Feed', string $description = 'Personalized feed from BITCORE')
    {
        $this->title = $title;
        $this->description = $description;
        $this->site_url = home_url('/');
        $this->feed_url = rest_url('bitcore/v1/feeds');
    }

    /**
     * Generate RSS 2.0 feed.
     *
     * @param array<int, array<string, mixed>> $items Feed items.
     * @return string RSS XML.
     * @since 1.0.0
     */
    public function generate_rss(array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "    <title>{$this->escape($this->title)}</title>\n";
        $xml .= "    <link>{$this->escape($this->site_url)}</link>\n";
        $xml .= "    <description>{$this->escape($this->description)}</description>\n";
        $xml .= "    <language>en-us</language>\n";
        $xml .= "    <lastBuildDate>" . date('r') . "</lastBuildDate>\n";
        $xml .= "    <atom:link href=\"{$this->escape($this->feed_url)}\" rel=\"self\" type=\"application/rss+xml\"/>\n";

        foreach ($items as $item) {
            $xml .= $this->generate_rss_item($item);
        }

        $xml .= "</channel>\n";
        $xml .= "</rss>";

        return $xml;
    }

    /**
     * Generate Atom 1.0 feed.
     *
     * @param array<int, array<string, mixed>> $items Feed items.
     * @return string Atom XML.
     * @since 1.0.0
     */
    public function generate_atom(array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "    <title>{$this->escape($this->title)}</title>\n";
        $xml .= "    <link href=\"{$this->escape($this->site_url)}\"/>\n";
        $xml .= "    <link href=\"{$this->escape($this->feed_url)}\" rel=\"self\"/>\n";
        $xml .= "    <updated>" . date('c') . "</updated>\n";
        $xml .= "    <id>{$this->escape($this->feed_url)}</id>\n";
        $xml .= "    <subtitle>{$this->escape($this->description)}</subtitle>\n";

        foreach ($items as $item) {
            $xml .= $this->generate_atom_entry($item);
        }

        $xml .= "</feed>";

        return $xml;
    }

    /**
     * Generate JSON Feed 1.1.
     *
     * @param array<int, array<string, mixed>> $items Feed items.
     * @return string JSON.
     * @since 1.0.0
     */
    public function generate_json(array $items): string
    {
        $feed = [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => $this->title,
            'description' => $this->description,
            'home_page_url' => $this->site_url,
            'feed_url' => $this->feed_url,
            'items' => array_map([$this, 'format_json_item'], $items),
        ];

        return wp_json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate OPML outline.
     *
     * @param array<string, array<string, string>> $feeds Feed definitions.
     * @return string OPML XML.
     * @since 1.0.0
     */
    public function generate_opml(array $feeds): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<opml version="2.0">' . "\n";
        $xml .= "<head>\n";
        $xml .= "    <title>{$this->escape($this->title)} Feeds</title>\n";
        $xml .= "    <dateCreated>" . date('r') . "</dateCreated>\n";
        $xml .= "</head>\n";
        $xml .= "<body>\n";

        foreach ($feeds as $name => $feed) {
            $xml .= sprintf(
                '    <outline type="rss" text="%s" title="%s" xmlUrl="%s" htmlUrl="%s"/>%s',
                $this->escape($name),
                $this->escape($feed['title'] ?? $name),
                $this->escape($feed['url']),
                $this->escape($feed['site'] ?? ''),
                "\n"
            );
        }

        $xml .= "</body>\n";
        $xml .= "</opml>";

        return $xml;
    }

    /**
     * Generate single RSS item.
     *
     * @param array<string, mixed> $item Item data.
     * @return string RSS item XML.
     * @since 1.0.0
     */
    private function generate_rss_item(array $item): string
    {
        $title = $this->escape($item['title'] ?? 'Untitled');
        $link = $this->escape($item['url'] ?? '');
        $description = $this->escape($item['excerpt'] ?? $item['content'] ?? '');
        $author = $this->escape($item['author'] ?? 'Unknown');
        $pub_date = $this->format_date($item['created_at'] ?? '', 'r');
        $guid = $this->escape($item['id'] ?? uniqid());

        $xml = "    <item>\n";
        $xml .= "        <title>{$title}</title>\n";
        $xml .= "        <link>{$link}</link>\n";
        $xml .= "        <description>{$description}</description>\n";
        $xml .= "        <dc:creator>{$author}</dc:creator>\n";
        $xml .= "        <pubDate>{$pub_date}</pubDate>\n";
        $xml .= "        <guid isPermaLink=\"false\">{$guid}</guid>\n";

        // Add source
        if (!empty($item['source'])) {
            $source = $this->escape($item['source']);
            $xml .= "        <source url=\"{$link}\">{$source}</source>\n";
        }

        // Add categories/tags
        if (!empty($item['tags']) && is_array($item['tags'])) {
            foreach ($item['tags'] as $tag) {
                $xml .= "        <category>{$this->escape($tag)}</category>\n";
            }
        }

        // Add match score if present
        if (isset($item['match']['score'])) {
            $score = number_format($item['match']['score'], 2);
            $xml .= "        <bitcore:score xmlns:bitcore=\"https://bitcoreos.org/ns\">{$score}</bitcore:score>\n";
        }

        $xml .= "    </item>\n";

        return $xml;
    }

    /**
     * Generate single Atom entry.
     *
     * @param array<string, mixed> $item Item data.
     * @return string Atom entry XML.
     * @since 1.0.0
     */
    private function generate_atom_entry(array $item): string
    {
        $title = $this->escape($item['title'] ?? 'Untitled');
        $link = $item['url'] ?? '';
        $content = $item['content'] ?? $item['excerpt'] ?? '';
        $author = $this->escape($item['author'] ?? 'Unknown');
        $updated = $this->format_date($item['created_at'] ?? '', 'c');
        $id = $this->escape($item['id'] ?? uniqid());

        $xml = "    <entry>\n";
        $xml .= "        <title>{$title}</title>\n";
        $xml .= "        <link href=\"{$this->escape($link)}\"/>\n";
        $xml .= "        <id>{$id}</id>\n";
        $xml .= "        <updated>{$updated}</updated>\n";
        $xml .= "        <author><name>{$author}</name></author>\n";
        $xml .= "        <content type=\"html\">{$this->escape($content)}</content>\n";

        if (!empty($item['tags']) && is_array($item['tags'])) {
            foreach ($item['tags'] as $tag) {
                $xml .= "        <category term=\"{$this->escape($tag)}\"/>\n";
            }
        }

        $xml .= "    </entry>\n";

        return $xml;
    }

    /**
     * Format item for JSON Feed.
     *
     * @param array<string, mixed> $item Item data.
     * @return array<string, mixed> Formatted item.
     * @since 1.0.0
     */
    private function format_json_item(array $item): array
    {
        $formatted = [
            'id' => $item['id'] ?? uniqid(),
            'url' => $item['url'] ?? '',
            'title' => $item['title'] ?? 'Untitled',
            'content_html' => $item['content'] ?? '',
            'date_published' => $this->format_date($item['created_at'] ?? '', 'c'),
            'authors' => [
                ['name' => $item['author'] ?? 'Unknown'],
            ],
        ];

        if (!empty($item['tags'])) {
            $formatted['tags'] = $item['tags'];
        }

        if (!empty($item['source'])) {
            $formatted['_bitcore'] = [
                'source' => $item['source'],
                'match_score' => $item['match']['score'] ?? null,
                'intent' => $item['match']['primary_intent'] ?? null,
            ];
        }

        return $formatted;
    }

    /**
     * Format date string.
     *
     * @param string $date Input date.
     * @param string $format Output format.
     * @return string Formatted date.
     * @since 1.0.0
     */
    private function format_date(string $date, string $format): string
    {
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : date($format);
    }

    /**
     * Escape XML special characters.
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     * @since 1.0.0
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Set feed metadata.
     *
     * @param string $key Metadata key.
     * @param string $value Metadata value.
     * @since 1.0.0
     */
    public function set_meta(string $key, string $value): void
    {
        if (property_exists($this, $key)) {
            $this->$key = $value;
        }
    }
}
