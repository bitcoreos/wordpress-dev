<?php
/**
 * BITCORE Command Center - Main Dashboard Template
 *
 * @package BITCORE\CommandCenter
 * @since 1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$health = $this->swarm_monitor->get_health_summary();
$stats = $this->workflow_runner->get_stats();
?>

<div id="bitcore-command-center" class="bitcore-dashboard">
    <header class="dashboard-header">
        <h1>BITCORE Command Center</h1>
        <p class="subtitle">Swarm Orchestration Dashboard</p>
    </header>

    <section class="dashboard-stats">
        <div class="stat-card">
            <span class="stat-value"><?php echo esc_html($health['total_agents']); ?></span>
            <span class="stat-label">Total Agents</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo esc_html($health['active']); ?></span>
            <span class="stat-label">Active</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo esc_html($stats['active_workflows']); ?></span>
            <span class="stat-label">Active Workflows</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo esc_html($stats['success_rate']); ?>%</span>
            <span class="stat-label">Success Rate</span>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="panel agents-panel">
            <h2>Swarm Agents</h2>
            <div id="agents-list" data-endpoint="<?php echo esc_url(rest_url('bitcore/v1/command-center/agents')); ?>">
                <?php echo do_shortcode('[bitcore_agent_panel]'); ?>
            </div>
        </section>

        <section class="panel workflows-panel">
            <h2>Workflows</h2>
            <div id="workflows-list" data-endpoint="<?php echo esc_url(rest_url('bitcore/v1/command-center/workflows')); ?>">
                <?php echo do_shortcode('[bitcore_workflow_panel]'); ?>
            </div>
        </section>
    </div>

    <section class="dashboard-health">
        <h2>System Health</h2>
        <div class="health-meter">
            <div class="health-bar" style="width: <?php echo esc_attr($health['health_score']); ?>%;"></div>
            <span class="health-score"><?php echo esc_html($health['health_score']); ?>%</span>
        </div>
        <p class="last-updated">Last updated: <?php echo esc_html($health['last_updated']); ?></p>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // SSE connection for real-time updates
    const eventSource = new EventSource('<?php echo esc_url(rest_url('bitcore/v1/realtime/sse')); ?>');
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.type === 'swarm_update') {
            location.reload();
        }
    };
    
    eventSource.onerror = function() {
        console.log('SSE connection lost. Reconnecting...');
    };
});
</script>
