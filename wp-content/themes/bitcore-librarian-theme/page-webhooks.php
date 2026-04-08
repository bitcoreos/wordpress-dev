<?php
/**
 * Template Name: Webhooks Dashboard
 * Description: Webhook CRUD management and trigger interface
 * 
 * @package BITCORE_Librarian
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="bitcore-dashboard" id="webhooks-dashboard">
    <header class="dashboard-header">
        <div class="dashboard-title">
            <div class="dashboard-icon">🔗</div>
            <div>
                <h1>Webhooks</h1>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.875rem;">Manage and Monitor Webhook Endpoints</p>
            </div>
        </div>
        <div class="dashboard-actions">
            <button class="btn btn-primary" id="create-webhook-btn">+ New Webhook</button>
        </div>
    </header>

    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Webhooks</div>
            <div class="stat-value accent" id="webhooks-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active</div>
            <div class="stat-value secondary" id="active-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Triggers Today</div>
            <div class="stat-value" id="triggers-count">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value warning" id="success-rate">--</div>
        </div>
    </div>

    <!-- Main Content -->
    <div style="display: grid; grid-template-columns: 1fr 400px; gap: var(--space-xl);">
        <!-- Webhooks List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Webhook Endpoints</h3>
                <input type="search" class="form-input" id="webhook-search" placeholder="Search..." style="width: 150px; padding: 6px 12px;">
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table" id="webhooks-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="webhooks-body">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: var(--space-xl);">
                                <div class="spinner"></div>
                                <p>Loading webhooks...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit/Create Panel -->
        <div class="card" id="webhook-editor-panel">
            <div class="card-header">
                <h3 class="card-title" id="editor-title">Create Webhook</h3>
            </div>
            <div class="card-body">
                <form id="webhook-form">
                    <input type="hidden" id="webhook-id">
                    
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-input" id="webhook-name" required placeholder="My Webhook">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Endpoint URL *</label>
                        <input type="url" class="form-input" id="webhook-url" required placeholder="https://example.com/webhook">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Secret (optional)</label>
                        <input type="text" class="form-input" id="webhook-secret" placeholder="Signing secret">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Events</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-sm);">
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="task.created"> task.created
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="task.completed"> task.completed
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="agent.spawned"> agent.spawned
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="agent.completed"> agent.completed
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="system.alert"> system.alert
                            </label>
                            <label style="display: flex; align-items: center; gap: var(--space-sm); font-size: 0.85rem;">
                                <input type="checkbox" name="events" value="*"> all events
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Headers (JSON)</label>
                        <textarea class="form-input" id="webhook-headers" rows="2" placeholder='{"X-Custom": "value"}'></textarea>
                    </div>
                    
                    <div style="display: flex; gap: var(--space-md);">
                        <button type="submit" class="btn btn-primary" id="save-btn">Save Webhook</button>
                        <button type="button" class="btn btn-secondary" id="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Trigger Log -->
    <div class="card" style="margin-top: var(--space-xl);">
        <div class="card-header">
            <h3 class="card-title">Recent Deliveries</h3>
            <button class="btn btn-sm btn-secondary" id="clear-log">Clear</button>
        </div>
        <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
            <table class="data-table" id="delivery-log">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Webhook</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody id="delivery-body">
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted);">No deliveries yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Trigger Modal -->
<div id="trigger-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 500px; max-width: 90%;">
        <div class="card-header">
            <h3 class="card-title">Trigger Webhook</h3>
            <button class="btn btn-sm btn-secondary" onclick="closeTriggerModal()">×</button>
        </div>
        <div class="card-body">
            <form id="trigger-form">
                <input type="hidden" id="trigger-webhook-id">
                <div class="form-group">
                    <label class="form-label">Event Type</label>
                    <input type="text" class="form-input" id="trigger-event" placeholder="custom.event" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Payload (JSON)</label>
                    <textarea class="form-input" id="trigger-payload" rows="5" placeholder='{"key": "value"}'></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Send Test</button>
            </form>
            <div id="trigger-result" style="margin-top: var(--space-lg);"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const restUrl = '<?php echo rest_url("bitcore/v1"); ?>';
    let webhooks = [];
    let deliveries = [];

    // Load webhooks
    async function loadWebhooks() {
        try {
            const response = await fetch(restUrl + '/webhooks');
            webhooks = await response.json();
            if (webhooks.webhooks) webhooks = webhooks.webhooks;
            renderWebhooks();
            updateStats();
        } catch (error) {
            console.error('Failed to load webhooks:', error);
            document.getElementById('webhooks-body').innerHTML = 
                '<tr><td colspan="5" style="text-align: center; color: var(--accent-danger);">Failed to load webhooks</td></tr>';
        }
    }

    // Render webhooks table
    function renderWebhooks(filter = '') {
        const filtered = webhooks.filter(w => 
            (w.name || '').toLowerCase().includes(filter.toLowerCase()) ||
            (w.url || '').toLowerCase().includes(filter.toLowerCase())
        );

        if (filtered.length === 0) {
            document.getElementById('webhooks-body').innerHTML = 
                '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No webhooks found</td></tr>';
            return;
        }

        document.getElementById('webhooks-body').innerHTML = filtered.map(w => `
            <tr data-id="${w.id}">
                <td><strong>${escapeHtml(w.name)}</strong></td>
                <td><code style="font-size: 0.8rem;">${escapeHtml(w.url?.substring(0, 40))}${w.url?.length > 40 ? '...' : ''}</code></td>
                <td>${(w.events || []).map(e => `<span class="badge badge-info" style="font-size: 0.65rem;">${e}</span>`).join(' ')}</td>
                <td><span class="badge ${w.active ? 'badge-success' : 'badge-warning'}">${w.active ? 'Active' : 'Inactive'}</span></td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="editWebhook('${w.id}')">Edit</button>
                    <button class="btn btn-sm btn-primary" onclick="showTriggerModal('${w.id}')">Trigger</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteWebhook('${w.id}')">Delete</button>
                </td>
            </tr>
        `).join('');
    }

    // Create/Update webhook
    async function saveWebhook(e) {
        e.preventDefault();
        
        const id = document.getElementById('webhook-id').value;
        const data = {
            name: document.getElementById('webhook-name').value,
            url: document.getElementById('webhook-url').value,
            secret: document.getElementById('webhook-secret').value || undefined,
            events: Array.from(document.querySelectorAll('input[name="events"]:checked')).map(el => el.value),
            headers: document.getElementById('webhook-headers').value ? 
                JSON.parse(document.getElementById('webhook-headers').value) : undefined
        };

        try {
            const url = id ? restUrl + '/webhooks/' + id : restUrl + '/webhooks';
            const method = id ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                resetForm();
                loadWebhooks();
            } else {
                const error = await response.json();
                alert('Failed to save: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Failed to save webhook: ' + error.message);
        }
    }

    // Edit webhook
    window.editWebhook = function(id) {
        const webhook = webhooks.find(w => w.id === id);
        if (!webhook) return;

        document.getElementById('webhook-id').value = webhook.id;
        document.getElementById('webhook-name').value = webhook.name || '';
        document.getElementById('webhook-url').value = webhook.url || '';
        document.getElementById('webhook-secret').value = webhook.secret || '';
        document.getElementById('webhook-headers').value = webhook.headers ? JSON.stringify(webhook.headers) : '';
        
        // Check events
        document.querySelectorAll('input[name="events"]').forEach(el => {
            el.checked = (webhook.events || []).includes(el.value);
        });

        document.getElementById('editor-title').textContent = 'Edit Webhook';
        document.getElementById('save-btn').textContent = 'Update Webhook';
    };

    // Delete webhook
    window.deleteWebhook = async function(id) {
        if (!confirm('Delete this webhook?')) return;
        
        try {
            const response = await fetch(restUrl + '/webhooks/' + id, { method: 'DELETE' });
            if (response.ok) {
                loadWebhooks();
            }
        } catch (error) {
            alert('Failed to delete: ' + error.message);
        }
    };

    // Trigger modal
    window.showTriggerModal = function(id) {
        document.getElementById('trigger-webhook-id').value = id;
        document.getElementById('trigger-modal').style.display = 'flex';
    };

    window.closeTriggerModal = function() {
        document.getElementById('trigger-modal').style.display = 'none';
    };

    // Trigger webhook
    async function triggerWebhook(e) {
        e.preventDefault();
        
        const id = document.getElementById('trigger-webhook-id').value;
        const event = document.getElementById('trigger-event').value;
        let payload = {};
        
        try {
            payload = JSON.parse(document.getElementById('trigger-payload').value || '{}');
        } catch (err) {}

        const resultEl = document.getElementById('trigger-result');
        resultEl.innerHTML = '<div class="spinner"></div><p>Sending...</p>';

        try {
            const response = await fetch(restUrl + '/webhooks/' + id + '/trigger', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event, data: payload })
            });
            const result = await response.json();
            
            resultEl.innerHTML = `
                <div class="badge ${response.ok ? 'badge-success' : 'badge-danger'}" style="margin-bottom: var(--space-sm);">
                    ${response.ok ? '✓ Delivered' : '✗ Failed'}
                </div>
                <div class="code-block"><pre>${escapeHtml(JSON.stringify(result, null, 2))}</pre></div>
            `;
            
            // Add to deliveries
            deliveries.unshift({
                timestamp: new Date().toISOString(),
                webhook: id,
                event,
                status: response.ok ? 200 : 500,
                response: result
            });
            renderDeliveries();
        } catch (error) {
            resultEl.innerHTML = `<p style="color: var(--accent-danger);">${escapeHtml(error.message)}</p>`;
        }
    }

    // Render deliveries
    function renderDeliveries() {
        const tbody = document.getElementById('delivery-body');
        if (deliveries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No deliveries yet</td></tr>';
            return;
        }

        tbody.innerHTML = deliveries.slice(0, 20).map(d => {
            const webhook = webhooks.find(w => w.id === d.webhook);
            return `
                <tr>
                    <td>${new Date(d.timestamp).toLocaleTimeString()}</td>
                    <td>${webhook ? escapeHtml(webhook.name) : d.webhook}</td>
                    <td><code>${escapeHtml(d.event)}</code></td>
                    <td><span class="badge ${d.status < 400 ? 'badge-success' : 'badge-danger'}">${d.status}</span></td>
                    <td><button class="btn btn-sm btn-secondary" onclick="alert(JSON.stringify(deliveries.find(x => x.timestamp === '${d.timestamp}')?.response, null, 2))">View</button></td>
                </tr>
            `;
        }).join('');
    }

    // Update stats
    function updateStats() {
        document.getElementById('webhooks-count').textContent = webhooks.length;
        document.getElementById('active-count').textContent = webhooks.filter(w => w.active).length;
        document.getElementById('triggers-count').textContent = deliveries.length;
        const successes = deliveries.filter(d => d.status < 400).length;
        const rate = deliveries.length > 0 ? Math.round((successes / deliveries.length) * 100) : 0;
        document.getElementById('success-rate').textContent = rate + '%';
    }

    // Reset form
    function resetForm() {
        document.getElementById('webhook-id').value = '';
        document.getElementById('webhook-form').reset();
        document.getElementById('editor-title').textContent = 'Create Webhook';
        document.getElementById('save-btn').textContent = 'Save Webhook';
    }

    // Helpers
    function escapeHtml(str) {
        if (typeof str !== 'string') return String(str);
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Event listeners
    document.getElementById('webhook-form').addEventListener('submit', saveWebhook);
    document.getElementById('trigger-form').addEventListener('submit', triggerWebhook);
    document.getElementById('cancel-btn').addEventListener('click', resetForm);
    document.getElementById('create-webhook-btn').addEventListener('click', resetForm);
    document.getElementById('webhook-search').addEventListener('input', (e) => renderWebhooks(e.target.value));
    document.getElementById('clear-log').addEventListener('click', () => {
        deliveries = [];
        renderDeliveries();
        updateStats();
    });

    // Initialize
    loadWebhooks();
})();
</script>

<?php get_footer(); ?>
