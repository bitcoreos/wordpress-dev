<?php
/**
 * Template Name: Context
 * Description: Context management - CRUD, search, persistence
 * 
 * @package BitCore
 * @since 1.2.0
 */
defined('ABSPATH') || exit;
get_header();
?>
<main id="context-page" class="bitcore-page">
    <header class="page-header">
        <h1>Context</h1>
        <p class="subtitle">Context Storage & Retrieval</p>
    </header>
    <section class="content-grid">
        <div class="card">
            <h2>Contexts</h2>
            <div id="context-list">Loading...</div>
        </div>
        <div class="card">
            <h2>Search</h2>
            <form id="context-search-form">
                <input type="text" id="search-query" placeholder="Search query">
                <button type="submit" class="btn">Search</button>
            </form>
            <div id="search-results"></div>
        </div>
    </section>
</main>
<script>
const API = '/wp-json/bitcore/v1/context';
async function loadContexts() {
    const res = await fetch(API);
    const data = await res.json();
    document.getElementById('context-list').innerHTML = JSON.stringify(data, null, 2);
}
document.addEventListener('DOMContentLoaded', loadContexts);
</script>
<?php get_footer(); ?>
