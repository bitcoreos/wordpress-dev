/**
 * Sessions - Session History Display
 * Fetches data from /wp-json/bitcore/v1/sessions
 */
(function() {
    const container = document.getElementById('sessions-container');
    if (!container) return;

    const apiEndpoint = (typeof bitcoreData !== 'undefined' ? bitcoreData.restUrl : '/wp-json/bitcore/v1/') + 'sessions';

    function renderLoading() {
        container.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading session history...</p></div>';
    }

    function renderError(message) {
        container.innerHTML = '<div class="error-state"><p>Error: ' + message + '</p><button onclick="fetchSessions()">Retry</button></div>';
    }

    function renderSessions(data) {
        if (!data || (Array.isArray(data) && data.length === 0)) {
            container.innerHTML = '<div class="empty-state"><p>No session history available.</p></div>';
            return;
        }

        let html = '<div class="sessions-list">';

        if (Array.isArray(data)) {
            data.forEach(function(session) {
                const statusClass = session.status || 'unknown';
                html += '<div class="session-card ' + statusClass + '">';
                html += '<div class="session-header">';
                html += '<h3>Session: ' + (session.id || 'Unknown') + '</h3>';
                html += '<span class="status status-' + statusClass + '">' + statusClass + '</span>';
                html += '</div>';
                html += '<div class="session-body">';
                if (session.agent_id) html += '<p><strong>Agent:</strong> ' + session.agent_id + '</p>';
                if (session.started_at) html += '<p><strong>Started:</strong> ' + session.started_at + '</p>';
                if (session.ended_at) html += '<p><strong>Ended:</strong> ' + session.ended_at + '</p>';
                if (session.duration) html += '<p><strong>Duration:</strong> ' + session.duration + '</p>';
                if (session.tasks_completed) html += '<p><strong>Tasks Completed:</strong> ' + session.tasks_completed + '</p>';
                if (session.tokens_used) html += '<p><strong>Tokens Used:</strong> ' + session.tokens_used.toLocaleString() + '</p>';
                html += '</div>';
                html += '</div>';
            });
        }

        html += '</div>';
        container.innerHTML = html;
    }

    async function fetchSessions() {
        renderLoading();
        try {
            const response = await fetch(apiEndpoint);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            let data = await response.json();
            // Handle {"sessions": []} format
            if (data && data.sessions) data = data.sessions;
            renderSessions(data);
        } catch (error) {
            console.error('Sessions fetch error:', error);
            renderError(error.message);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fetchSessions);
    } else {
        fetchSessions();
    }
})();
