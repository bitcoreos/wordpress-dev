// Approvals Page JavaScript
(function() {
    console.log('ApprovalsPage initializing...');
    
    const container = document.getElementById('approvals-container');
    if (!container) {
        console.error('Approvals container not found');
        return;
    }
    
    const restUrl = (typeof bitcoreData !== 'undefined' && bitcoreData.restUrl) 
        ? bitcoreData.restUrl 
        : '/wp-json/bitcore/v1/';
    
    async function loadApprovals() {
        try {
            const response = await fetch(restUrl + 'approvals', {
                method: 'GET',
                headers: {'Content-Type': 'application/json'}
            });
            
            if (!response.ok) throw new Error('API returned ' + response.status);
            
            let data = await response.json();
            if (data && data.approvals) data = data.approvals;
            
            renderApprovals(data);
        } catch (error) {
            console.error('Load approvals error:', error);
            container.innerHTML = '<p class="error">Error loading approvals: ' + error.message + '</p>';
        }
    }
    
    function renderApprovals(data) {
        if (!data || data.length === 0) {
            container.innerHTML = '<p class="empty">No pending approvals found.</p>';
            return;
        }
        
        let html = '<div class="approvals-list">';
        
        if (Array.isArray(data)) {
            data.forEach(function(item) {
                html += '<div class="approval-card">';
                html += '<div class="approval-header">';
                html += '<h3>' + (item.title || 'Approval Request') + '</h3>';
                html += '<span class="status">' + (item.status || 'pending') + '</span>';
                html += '</div>';
                if (item.description) html += '<p>' + item.description + '</p>';
                html += '<div class="approval-meta">';
                if (item.requester) html += '<span>Requested by: ' + item.requester + '</span>';
                if (item.created_at) html += '<span>Date: ' + item.created_at + '</span>';
                html += '</div>';
                html += '<div class="approval-actions">';
                html += '<button class="btn-approve" data-id="' + item.id + '">Approve</button>';
                html += '<button class="btn-reject" data-id="' + item.id + '">Reject</button>';
                html += '</div>';
                html += '</div>';
            });
        }
        
        html += '</div>';
        container.innerHTML = html;
        
        // Add event listeners
        document.querySelectorAll('.btn-approve').forEach(function(btn) {
            btn.addEventListener('click', function() {
                handleApproval(this.getAttribute('data-id'), 'approve');
            });
        });
        
        document.querySelectorAll('.btn-reject').forEach(function(btn) {
            btn.addEventListener('click', function() {
                handleApproval(this.getAttribute('data-id'), 'reject');
            });
        });
    }
    
    window.handleApproval = function(id, action) {
        fetch(restUrl + 'approvals/' + id + '/' + action, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        })
        .then(response => response.json())
        .then(data => {
            alert('Approval ' + action + 'd successfully');
            loadApprovals();
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    };
    
    loadApprovals();
})();
