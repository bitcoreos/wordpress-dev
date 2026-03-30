/**
 * Org Chart - Agent Hierarchy Visualization
 * Fetches data from /wp-json/bitcore/v1/org-chart
 */
(function() {
    const container = document.getElementById('org-chart-container');
    if (!container) return;

    const apiEndpoint = (typeof bitcoreData !== 'undefined' ? bitcoreData.restUrl : '/wp-json/bitcore/v1/') + 'org-chart';

    function renderLoading() {
        container.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading agent hierarchy...</p></div>';
    }

    function renderError(message) {
        container.innerHTML = '<div class="error-state"><p>Error: ' + message + '</p><button onclick="fetchOrgChart()">Retry</button></div>';
    }

    function renderOrgChart(data) {
        if (!data || (Array.isArray(data) && data.length === 0)) {
            container.innerHTML = '<div class="empty-state"><p>No agents found in the hierarchy.</p></div>';
            return;
        }

        let html = '<div class="org-chart-grid">';

        if (Array.isArray(data)) {
            data.forEach(function(agent) {
                const statusClass = agent.status === 'active' ? 'status-active' : 'status-inactive';
                html += '<div class="agent-card ' + statusClass + '">';
                html += '<div class="agent-header"><h3>' + (agent.name || 'Unknown') + '</h3>';
                html += '<span class="agent-role">' + (agent.role || 'Agent') + '</span></div>';
                html += '<div class="agent-body">';
                html += '<p><strong>ID:</strong> ' + (agent.id || 'N/A') + '</p>';
                html += '<p><strong>Status:</strong> ' + (agent.status || 'Unknown') + '</p>';
                html += '<p><strong>Level:</strong> ' + (agent.level || 'N/A') + '</p>';
                if (agent.parent) {
                    html += '<p><strong>Reports to:</strong> ' + agent.parent + '</p>';
                }
                html += '</div></div>';
            });
        }

        html += '</div>';
        container.innerHTML = html;
    }

    async function fetchOrgChart() {
        renderLoading();

        try {
            const response = await fetch(apiEndpoint);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            let data = await response.json();
            // Handle {"agents": []} format
            if (data && data.agents) data = data.agents;
            renderOrgChart(data);
        } catch (error) {
            console.error('Org chart fetch error:', error);
            renderError(error.message);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fetchOrgChart);
    } else {
        fetchOrgChart();
    }
})();
