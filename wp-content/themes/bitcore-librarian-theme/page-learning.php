<?php
/**
 * Template Name: Learning
 * Description: Learning hub - episodes, retention, scoring
 * 
 * @package BitCore
 * @since 1.2.0
 */
defined('ABSPATH') || exit;
get_header();
?>
<main id="learning-page" class="bitcore-page">
    <header class="page-header">
        <h1>Learning</h1>
        <p class="subtitle">Episodes, Retention & Scoring</p>
    </header>
    <section class="content-grid">
        <div class="card"><h2>Episodes</h2><div id="episodes">Loading...</div></div>
        <div class="card"><h2>Retention</h2><div id="retention">Loading...</div></div>
        <div class="card">
            <h2>Score</h2>
            <form id="score-form">
                <input type="text" id="episode-id" placeholder="Episode ID">
                <input type="number" id="score" placeholder="Score" min="0" max="100">
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </section>
</main>
<script>
const API = '/wp-json/bitcore/v1/learning';
async function load() {
    const [ep, ret] = await Promise.all([
        fetch(`${API}/episodes`).then(r => r.json()),
        fetch(`${API}/retention`).then(r => r.json())
    ]);
    document.getElementById('episodes').innerHTML = JSON.stringify(ep, null, 2);
    document.getElementById('retention').innerHTML = JSON.stringify(ret, null, 2);
}
document.addEventListener('DOMContentLoaded', load);
</script>
<?php get_footer(); ?>
