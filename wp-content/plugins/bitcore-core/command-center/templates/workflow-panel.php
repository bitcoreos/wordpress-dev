<?php
/**
 * BITCORE Command Center - Workflow Panel Template
 *
 * @package BITCORE\CommandCenter
 * @since 1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$workflows = $this->workflow_runner->get_workflows(true);
?>

<div class="workflow-panel">
    <table class="workflows-table">
        <thead>
            <tr>
                <th>Workflow</th>
                <th>Status</th>
                <th>Last Run</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($workflows)) : ?>
            <tr>
                <td colspan="4">No active workflows found.</td>
            </tr>
            <?php else : ?>
            <?php foreach ($workflows as $workflow) : ?>
            <tr class="workflow-row" data-workflow-id="<?php echo esc_attr($workflow['id'] ?? ''); ?>">
                <td>
                    <strong><?php echo esc_html($workflow['name'] ?? 'Unnamed'); ?></strong>
                    <small>ID: <?php echo esc_html($workflow['id'] ?? 'N/A'); ?></small>
                </td>
                <td>
                    <span class="status-badge status-<?php echo ($workflow['active'] ?? false) ? 'active' : 'inactive'; ?>">
                        <?php echo ($workflow['active'] ?? false) ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td class="last-run">
                    <?php echo esc_html($workflow['lastRun'] ?? 'Never'); ?>
                </td>
                <td class="actions">
                    <button class="button run-btn" data-workflow="<?php echo esc_attr($workflow['id'] ?? ''); ?>">
                        Run
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.workflow-panel { overflow-x: auto; }
.workflows-table { width: 100%; border-collapse: collapse; }
.workflows-table th, .workflows-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.run-btn { background: #0073aa; color: white; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px; }
.run-btn:hover { background: #005177; }
</style>
