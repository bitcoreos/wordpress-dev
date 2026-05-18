<?php
/**
 * Template Name: Knowledge
 * Knowledge Graph - prompts, skills, artifacts from Bithub and BitWiki.
 */
get_header();
?>
<main class="bitcore-knowledge">
    <h1>Knowledge</h1>
    <div class="knowledge-layout">
        <nav class="knowledge-tabs">
            <button class="tab-active" data-tab="prompts">Prompts</button>
            <button data-tab="skills">Skills</button>
            <button data-tab="artifacts">Artifacts</button>
        </nav>
        <section class="knowledge-content" id="prompts-panel">
            <h2>Prompts</h2>
            <p>Loading from Bithub...</p>
        </section>
        <section class="knowledge-content hidden" id="skills-panel">
            <h2>Skills</h2>
            <p>Loading from BitWiki...</p>
        </section>
        <section class="knowledge-content hidden" id="artifacts-panel">
            <h2>Artifacts</h2>
            <p>Loading from library...</p>
        </section>
    </div>
</main>
<?php get_footer(); ?>
