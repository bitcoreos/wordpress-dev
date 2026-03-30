/**
 * Goals - Goal Hierarchy Display
 * Fetches data from /wp-json/bitcore/v1/goals
 */
(function() {
    const container = document.getElementById('goals-container');
    if (!container) return;

    const apiEndpoint = (typeof bitcoreData !== 'undefined' ? bitcoreData.restUrl : '/wp-json/bitcore/v1/') + 'goals';

    function renderLoading() {
        container.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading goals...</p></div>';
    }

    function renderError(message) {
        container.innerHTML = '<div class="error-state"><p>Error: ' + message + '</p><button onclick="fetchGoals()">Retry</button></div>';
    }

    function renderGoals(data) {
        if (!data || (Array.isArray(data) && data.length === 0)) {
            container.innerHTML = '<div class="empty-state"><p>No goals defined.</p></div>';
            return;
        }

        let html = '<div class="goals-list">';

        if (Array.isArray(data)) {
            data.forEach(function(goal) {
                const progress = goal.progress || 0;
                const statusClass = progress >= 100 ? 'complete' : progress > 0 ? 'in-progress' : 'not-started';
                html += '<div class="goal-card ' + statusClass + '">';
                html += '<div class="goal-header"><h3>' + (goal.title || goal.name || 'Unnamed Goal') + '</h3>';
                if (goal.priority) html += '<span class="priority priority-' + goal.priority + '">' + goal.priority + '</span>';
                html += '</div>';
                if (goal.description) html += '<p class="goal-description">' + goal.description + '</p>';
                html += '<div class="goal-progress">';
                html += '<div class="progress-bar"><div class="progress-fill" style="width: ' + progress + '%"></div></div>';
                html += '<span class="progress-text">' + progress + '%</span>';
                html += '</div>';
                if (goal.agent_id) html += '<p class="goal-agent">Assigned to: ' + goal.agent_id + '</p>';
                html += '</div>';
            });
        }

        html += '</div>';
        container.innerHTML = html;
    }

    async function fetchGoals() {
        renderLoading();
        try {
            const response = await fetch(apiEndpoint);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            let data = await response.json();
            // Handle {"goals": []} format
            if (data && data.goals) data = data.goals;
            renderGoals(data);
        } catch (error) {
            console.error('Goals fetch error:', error);
            renderError(error.message);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fetchGoals);
    } else {
        fetchGoals();
    }
})();
