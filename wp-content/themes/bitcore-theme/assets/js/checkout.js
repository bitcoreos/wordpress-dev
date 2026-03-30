// Checkout Page JavaScript
(function() {
    console.log('CheckoutPage initializing...');
    
    const container = document.getElementById('checkout-container');
    if (!container) {
        console.error('Checkout container not found');
        return;
    }
    
    const restUrl = (typeof bitcoreData !== 'undefined' && bitcoreData.restUrl) 
        ? bitcoreData.restUrl 
        : '/wp-json/bitcore/v1/';
    
    async function loadCheckouts() {
        try {
            const response = await fetch(restUrl + 'checkout', {
                method: 'GET',
                headers: {'Content-Type': 'application/json'}
            });
            
            if (!response.ok) throw new Error('API returned ' + response.status);
            
            let data = await response.json();
            if (data && data.checkouts) data = data.checkouts;
            
            renderCheckouts(data);
        } catch (error) {
            console.error('Load checkouts error:', error);
            container.innerHTML = '<p class="error">Error loading checkouts: ' + error.message + '</p>';
        }
    }
    
    function renderCheckouts(data) {
        if (!data || data.length === 0) {
            container.innerHTML = '<p class="empty">No active checkouts found.</p>';
            return;
        }
        
        let html = '<div class="checkout-list">';
        
        if (Array.isArray(data)) {
            data.forEach(function(item) {
                const statusClass = item.status || 'available';
                html += '<div class="checkout-card ' + statusClass + '">';
                html += '<div class="checkout-header">';
                html += '<h3>' + (item.title || item.task_id || 'Task') + '</h3>';
                html += '<span class="status status-' + statusClass + '">' + statusClass + '</span>';
                html += '</div>';
                if (item.description) html += '<p class="checkout-description">' + item.description + '</p>';
                html += '<div class="checkout-meta">';
                if (item.checked_out_by) html += '<span>Checked out by: ' + item.checked_out_by + '</span>';
                if (item.locked_at) html += '<span>Since: ' + item.locked_at + '</span>';
                html += '</div>';
                if (statusClass === 'locked' && item.can_release) {
                    html += '<button class="btn-release" data-id="' + item.id + '">Release Lock</button>';
                }
                html += '</div>';
            });
        }
        
        html += '</div>';
        container.innerHTML = html;
        
        // Add event listeners for release buttons
        document.querySelectorAll('.btn-release').forEach(function(btn) {
            btn.addEventListener('click', function() {
                releaseCheckout(this.getAttribute('data-id'));
            });
        });
    }
    
    window.releaseCheckout = function(id) {
        fetch(restUrl + 'checkout/' + id + '/release', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        })
        .then(response => response.json())
        .then(data => {
            alert('Lock released successfully');
            loadCheckouts();
        })
        .catch(error => {
            alert('Error releasing lock: ' + error.message);
        });
    };
    
    loadCheckouts();
})();
