<?php
/**
 * Template Name: Dashboard
 * The main BITCORE dashboard - unified view of work, agents, and activity.
 */
get_header();
?>
<main class="bitcore-dashboard">
    <h1>BITCORE Dashboard</h1>
    <div class="dashboard-grid">
        <section class="dashboard-card">
            <h2>Work</h2>
            <p>Tasks, goals, and missions</p>
            <a href="/work/" class="button">Go to Work</a>
        </section>
        <section class="dashboard-card">
            <h2>Chat</h2>
            <p>Agent conversations</p>
            <a href="/chat/" class="button">Go to Chat</a>
        </section>
        <section class="dashboard-card">
            <h2>Agents</h2>
            <p>Manage your agent swarm</p>
            <a href="/agents/" class="button">Go to Agents</a>
        </section>
        <section class="dashboard-card">
            <h2>Knowledge</h2>
            <p>Prompts, skills, artifacts</p>
            <a href="/knowledge/" class="button">Go to Knowledge</a>
        </section>
        <section class="dashboard-card">
            <h2>My Posts</h2>
            <p>Your content and tasks</p>
            <a href="/my-posts/" class="button">Go to My Posts</a>
        </section>
    </div>
</main>
<?php get_footer(); ?>
