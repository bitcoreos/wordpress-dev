<?php
/**
 * BITCORE Timeline Feeds Module
 *
 * @package BITCORE\Feeds
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Feeds;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feeds Module Loader
 *
trait FeedsEndpoints {
    public static function get_feeds(\WP_REST_Request $request): \WP_REST_Response
    {
        $format = $request->get_param('format') ?? 'json';
        $aggregator = new Source_Aggregator();
        $items = $aggregator->aggregate();

        $generator = new Feed_Generator();
        $content = match ($format) {
            'rss' => $generator->generate_rss($items),
            'atom' => $generator->generate_atom($items),
            default => $generator->generate_json($items),
        };

        return new \WP_REST_Response(['content' => $content, 'count' => count($items)]);
    }

    /**
     * Get single feed.
     */
    public static function get_feed(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['message' => 'Feed endpoint']);
    }

    /**
     * Extract text from URL.
     */
    public static function extract_text(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = $request->get_param('url');
        $extractor = new Text_Extractor();
        $result = $extractor->extract_from_url($url);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['content' => $result]);
    }

    /**
     * Summarize content.
     */
    public static function summarize_content(\WP_REST_Request $request): \WP_REST_Response
    {
        $content = $request->get_param('content');
        $summarizer = new Summarizer();
        $result = $summarizer->summarize($content);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['summary' => $result]);
    }

    /**
     * Get watchlists.
     */
    public static function get_watchlists(\WP_REST_Request $request): \WP_REST_Response
    {
        $watchlists = Watchlist::get_user_watchlists();
        return new \WP_REST_Response(['watchlists' => $watchlists]);
    }

    /**
     * Create watchlist.
     */
    public static function create_watchlist(\WP_REST_Request $request): \WP_REST_Response
    {
        $watchlist = new Watchlist();
        $result = $watchlist->create_watchlist(
            $request->get_param('name'),
            ['keywords' => $request->get_param('keywords') ?? []]
        );

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['id' => $result, 'message' => 'Watchlist created']);
    }

    /**
     * Delete watchlist.
     */
    public static function delete_watchlist(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $watchlist = new Watchlist($id);
        $result = $watchlist->delete();

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['message' => 'Watchlist deleted']);
    }

    /**
     * Add watchlist keyword.
     */
    public static function add_watchlist_keyword(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $keyword = $request->get_param('keyword');
        $watchlist = new Watchlist($id);
        $result = $watchlist->add_keyword($keyword);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['message' => 'Keyword added']);
    }

    /**
     * Get trends.
     */
    public static function get_trends(\WP_REST_Request $request): \WP_REST_Response
    {
        $aggregator = new Source_Aggregator();
        $items = $aggregator->aggregate();
        $detector = new Trend_Detector();
        $trends = $detector->detect_trends($items);

        return new \WP_REST_Response($trends);
    }

    /**
     * Cluster content.
     */
    public static function cluster_content(\WP_REST_Request $request): \WP_REST_Response
    {
        $items = $request->get_param('items') ?? [];
        $clusterer = new Topic_Clusterer();
        $result = $clusterer->cluster($items);

        return new \WP_REST_Response($result);
    }

    /**
     * Get RAG workspaces.
     */
    public static function get_rag_workspaces(\WP_REST_Request $request): \WP_REST_Response
    {
        $workspaces = RAG_Workspace::get_user_workspaces();
        return new \WP_REST_Response(['workspaces' => $workspaces]);
    }

    /**
     * Create RAG workspace.
     */
    public static function create_rag_workspace(\WP_REST_Request $request): \WP_REST_Response
    {
        $workspace = new RAG_Workspace();
        $result = $workspace->create_workspace($request->get_param('name'));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['id' => $result, 'message' => 'Workspace created']);
    }

    /**
     * Store RAG document.
     */
    public static function store_rag_document(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $workspace = new RAG_Workspace($id);
        $result = $workspace->store_document(
            $request->get_param('content'),
            $request->get_param('metadata') ?? []
        );

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['document_id' => $result, 'message' => 'Document stored']);
    }

    /**
     * Query RAG workspace.
     */
    public static function query_rag_workspace(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $workspace = new RAG_Workspace($id);
        $result = $workspace->query($request->get_param('query'));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response($result);
    }

    // ==================== Permission Callbacks ====================

    /**
     * Check read permission.
}
