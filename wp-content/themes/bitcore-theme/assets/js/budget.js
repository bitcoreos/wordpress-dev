/**
 * Budget - Budget Tracking Dashboard
 * Fetches data from /wp-json/bitcore/v1/budget/hyperion
 */
(function() {
    const container = document.getElementById('budget-container');
    if (!container) return;

    const apiEndpoint = '/wp-json/bitcore/v1/budget/hyperion';

    function renderLoading() {
        container.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading budget data...</p></div>';
    }

    function renderError(message) {
        container.innerHTML = '<div class="error-state"><p>Error: ' + message + '</p><button onclick="fetchBudget()">Retry</button></div>';
    }

    function renderBudget(data) {
        if (!data) {
            container.innerHTML = '<div class="empty-state"><p>No budget data available.</p></div>';
            return;
        }

        let html = '<div class="budget-dashboard">';
        html += '<div class="budget-header">';
        html += '<div class="budget-total"><h3>Total Budget</h3><span class="value">$' + (data.total_budget || 0).toLocaleString() + '</span></div>';
        html += '<div class="budget-spent"><h3>Spent</h3><span class="value">$' + (data.total_spent || 0).toLocaleString() + '</span></div>';
        html += '<div class="budget-remaining"><h3>Remaining</h3><span class="value">$' + (data.remaining || 0).toLocaleString() + '</span></div>';
        html += '</div>';

        if (data.agents && Array.isArray(data.agents)) {
            html += '<div class="budget-agents"><h3>Agent Allocations</h3>';
            data.agents.forEach(function(agent) {
                const percentage = data.total_budget ? ((agent.budget / data.total_budget) * 100).toFixed(1) : 0;
                html += '<div class="agent-budget">';
                html += '<div class="agent-name">' + (agent.name || agent.agent_id) + '</div>';
                html += '<div class="budget-bar"><div class="budget-fill" style="width: ' + percentage + '%"></div></div>';
                html += '<div class="budget-info">$' + (agent.budget || 0).toLocaleString() + ' (' + percentage + '%)</div>';
                html += '</div>';
            });
            html += '</div>';
        }

        html += '</div>';
        container.innerHTML = html;
    }

    async function fetchBudget() {
        renderLoading();
        try {
            const response = await fetch(apiEndpoint);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            let data = await response.json();
            // Handle {"budgets": []} format
            if (data && data.budgets) data = data.budgets;
            renderBudget(data);
        } catch (error) {
            console.error('Budget fetch error:', error);
            renderError(error.message);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fetchBudget);
    } else {
        fetchBudget();
    }
})();
