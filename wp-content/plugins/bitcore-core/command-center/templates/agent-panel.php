<?php
/**
 * BITCORE Command Center - Agent Panel Template
 *
 * @package BITCORE\CommandCenter
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$agents = $this->swarm_monitor->get_all_agents();
?>

<div class="agent-panel">
    <table class="agents-table">
        <thead>
            <tr>
                <th>Agent</th>
                <th>Type</th>
                <th>Status</th>
                <th>Capabilities</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($agents as $agent) : ?>
            <tr class="agent-row" data-agent-id="<?php echo esc_attr($agent['id']); ?>">
                <td class="agent-name">
                    <strong><?php echo esc_html($agent['name']); ?></strong>
                    <small><?php echo esc_html($agent['role'] ?? ''); ?></small>
                </td>
                <td><?php echo esc_html($agent['type']); ?></td>
                <td>
                    <span class="status-badge status-<?php echo esc_attr($agent['status']); ?>">
                        <?php echo esc_html($agent['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($agent['capabilities'])) : ?>
                    <ul class="capabilities-list">
                        <?php foreach (array_slice($agent['capabilities'], 0, 3) as $cap) : ?>
                        <li><?php echo esc_html($cap); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <button class="button ping-btn" data-agent="<?php echo esc_attr($agent['id']); ?>">
                        Ping
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.agent-panel { overflow-x: auto; }
.agents-table { width: 100%; border-collapse: collapse; }
.agents-table th, .agents-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-error { background: #fff3cd; color: #856404; }
.capabilities-list { margin: 0; padding: 0; list-style: none; }
.capabilities-list li { display: inline-block; background: #e9ecef; padding: 2px 6px; margin: 2px; border-radius: 3px; font-size: 11px; }
.agent-name small { display: block; color: #666; }
</style>
