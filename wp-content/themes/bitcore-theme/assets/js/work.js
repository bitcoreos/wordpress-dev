/**
 * BITCORE Work Page Controller
 * Unified Tasks/Goals/Missions hierarchy interface
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    var WorkPage = {
        config: {
            nonce: null,
            restUrl: '/wp-json/bitcore/v1/'
        },

        state: {
            activeTab: 'tasks',
            tasks: [],
            goals: [],
            missions: [],
            taskFilter: 'all',
            goalFilter: 'all',
            missionFilter: 'all'
        },

        init: function() {
            if (typeof bitcoreData !== 'undefined' && bitcoreData.nonce) {
                this.config.nonce = bitcoreData.nonce;
            }
            if (typeof bitcoreData !== 'undefined' && bitcoreData.restUrl) {
                this.config.restUrl = bitcoreData.restUrl;
            }
            this.bindTabs();
            this.bindTasks();
            this.bindGoals();
            this.bindMissions();
            this.bindRefresh();
            this.loadAll();
        },

        getHeaders: function() {
            var headers = { 'Content-Type': 'application/json' };
            if (this.config.nonce) {
                headers['X-WP-Nonce'] = this.config.nonce;
            }
            return headers;
        },

        // === TAB NAVIGATION ===
        bindTabs: function() {
            var self = this;
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
                    document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
                    this.classList.add('active');
                    var tabId = 'tab-' + this.dataset.tab;
                    var tabEl = document.getElementById(tabId);
                    if (tabEl) tabEl.classList.add('active');
                    self.state.activeTab = this.dataset.tab;
                    self.updateStats();
                });
            });

            // Detail close buttons
            var closeDetail = document.getElementById('close-detail');
            if (closeDetail) closeDetail.addEventListener('click', function() {
                var card = document.getElementById('task-detail-card');
                if (card) card.style.display = 'none';
            });
            var closeGoal = document.getElementById('close-goal-detail');
            if (closeGoal) closeGoal.addEventListener('click', function() {
                var card = document.getElementById('goal-detail-card');
                if (card) card.style.display = 'none';
            });
            var closeMission = document.getElementById('close-mission-detail');
            if (closeMission) closeMission.addEventListener('click', function() {
                var card = document.getElementById('mission-detail-card');
                if (card) card.style.display = 'none';
            });
        },

        bindRefresh: function() {
            var self = this;
            var refreshBtn = document.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() { self.loadAll(); });
            }
        },

        loadAll: function() {
            this.loadTasks();
            this.loadKanban();
            this.loadGoals();
            this.loadMissions();
        },

        updateStats: function() {
            var tab = this.state.activeTab;
            var totalEl = document.getElementById('stat-total');
            var pendingEl = document.getElementById('stat-pending');
            var activeEl = document.getElementById('stat-active');
            var doneEl = document.getElementById('stat-done');
            var totalLabel = document.getElementById('stat-total-label');
            var pendingLabel = document.getElementById('stat-pending-label');
            var activeLabel = document.getElementById('stat-active-label');
            var doneLabel = document.getElementById('stat-done-label');

            if (tab === 'tasks') {
                if (totalLabel) totalLabel.textContent = 'Total Tasks';
                if (pendingLabel) pendingLabel.textContent = 'Pending';
                if (activeLabel) activeLabel.textContent = 'Running';
                if (doneLabel) doneLabel.textContent = 'Completed';
                var tasks = this.state.tasks;
                if (totalEl) totalEl.textContent = tasks.length;
                if (pendingEl) pendingEl.textContent = tasks.filter(function(t) { return (t.task_status || t.status || 'pending') === 'pending'; }).length;
                if (activeEl) activeEl.textContent = tasks.filter(function(t) { return (t.task_status || t.status || 'pending') === 'running'; }).length;
                if (doneEl) doneEl.textContent = tasks.filter(function(t) { return (t.task_status || t.status || 'pending') === 'completed'; }).length;
            } else if (tab === 'goals') {
                if (totalLabel) totalLabel.textContent = 'Total Goals';
                if (pendingLabel) pendingLabel.textContent = 'Missions';
                if (activeLabel) activeLabel.textContent = 'Objectives';
                if (doneLabel) doneLabel.textContent = 'Tasks';
                var goals = this.state.goals;
                if (totalEl) totalEl.textContent = goals.length;
                if (pendingEl) pendingEl.textContent = goals.filter(function(g) { return g.level === 'mission'; }).length;
                if (activeEl) activeEl.textContent = goals.filter(function(g) { return g.level === 'objective'; }).length;
                if (doneEl) doneEl.textContent = goals.filter(function(g) { return g.level === 'task'; }).length;
            } else if (tab === 'missions') {
                if (totalLabel) totalLabel.textContent = 'Total Missions';
                if (pendingLabel) pendingLabel.textContent = 'Proposed';
                if (activeLabel) activeLabel.textContent = 'In Progress';
                if (doneLabel) doneLabel.textContent = 'Completed';
                var missions = this.state.missions;
                if (totalEl) totalEl.textContent = missions.length;
                if (pendingEl) pendingEl.textContent = missions.filter(function(m) { return m.status === 'proposed'; }).length;
                if (activeEl) activeEl.textContent = missions.filter(function(m) { return m.status === 'in-progress'; }).length;
                if (doneEl) doneEl.textContent = missions.filter(function(m) { return m.status === 'completed'; }).length;
            }
        },

        // === TASKS ===
        bindTasks: function() {
            var self = this;
            // Create task
            var submitBtn = document.getElementById('submit-task-btn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.createTask();
                });
            }
            // Filter buttons
            document.querySelectorAll('#task-filter-bar .filter-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#task-filter-bar .filter-btn').forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    self.state.taskFilter = this.dataset.filter;
                    self.renderTasks();
                });
            });
        },

        loadTasks: async function() {
            var self = this;
            var listEl = document.getElementById('task-list');
            if (listEl) listEl.innerHTML = '<div class="empty-state">Loading tasks...</div>';
            try {
                var response = await fetch(this.config.restUrl + 'tasks', {
                    method: 'GET', headers: this.getHeaders()
                });
                if (!response.ok) throw new Error('API returned ' + response.status);
                var data = await response.json();
                this.state.tasks = Array.isArray(data) ? data : (data.tasks || []);
                this.renderTasks();
                this.updateStats();
                var countEl = document.getElementById('tasks-count');
                if (countEl) countEl.textContent = this.state.tasks.length;
            } catch (error) {
                console.error('Load tasks error:', error);
                if (listEl) listEl.innerHTML = '<div class="empty-state">Failed to load tasks</div>';
            }
        },

        renderTasks: function() {
            var listEl = document.getElementById('task-list');
            if (!listEl) return;
            var filtered = this.state.tasks;
            if (this.state.taskFilter !== 'all') {
                filtered = filtered.filter(function(t) {
                    return (t.task_status || t.status || 'pending') === self.state.taskFilter;
                }.bind(this)); // bind fix for self reference
            }
            // Re-filter using proper closure
            var filter = this.state.taskFilter;
            filtered = this.state.tasks.filter(function(t) {
                var status = t.task_status || t.status || 'pending';
                return filter === 'all' || status === filter;
            });

            if (filtered.length === 0) {
                listEl.innerHTML = '<div class="empty-state">No tasks found</div>';
                return;
            }
            var html = '';
            filtered.forEach(function(task) {
                var status = task.task_status || task.status || 'pending';
                html += '<div class="task-item" data-task-id="' + task.id + '">' +
                    '<div class="task-item-header">' +
                    '<span class="task-name">' + WorkPage.escapeHtml(task.title || 'Untitled') + '</span>' +
                    '<span class="task-status ' + status + '">' + status + '</span>' +
                    '</div>' +
                    '<div class="task-desc">' + WorkPage.escapeHtml((task.content || '').substring(0, 100)) + '</div>' +
                    '</div>';
            });
            listEl.innerHTML = html;

            // Bind click events
            listEl.querySelectorAll('.task-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    var id = parseInt(this.dataset.taskId);
                    WorkPage.selectTask(id);
                });
            });

            var countEl = document.getElementById('task-count');
            if (countEl) countEl.textContent = filtered.length + ' tasks';
        },

        selectTask: function(taskId) {
            var task = this.state.tasks.find(function(t) { return t.id == taskId; });
            if (!task) return;
            var status = task.task_status || task.status || 'pending';
            var detailCard = document.getElementById('task-detail-card');
            var detailBody = document.getElementById('task-detail-body');
            if (detailCard && detailBody) {
                detailCard.style.display = 'block';
                detailBody.innerHTML =
                    '<p><strong>Title:</strong> ' + this.escapeHtml(task.title || 'Untitled') + '</p>' +
                    '<p><strong>Status:</strong> ' + status + '</p>' +
                    '<p><strong>Content:</strong> ' + this.escapeHtml(task.content || 'None') + '</p>' +
                    '<p><strong>Date:</strong> ' + (task.date || 'Unknown') + '</p>' +
                    '<div style="margin-top:12px;display:flex;gap:8px;">' +
                    '<button class="btn btn-danger btn-sm" onclick="WorkPage.deleteTask(' + task.id + ')">Delete Task</button>' +
                    '</div>';
            }
        },

        createTask: async function() {
            var nameEl = document.getElementById('new-task-name');
            var descEl = document.getElementById('new-task-desc');
            var title = nameEl ? nameEl.value.trim() : '';
            var content = descEl ? descEl.value.trim() : '';
            if (!title) { this.showNotification('Task name required', 'error'); return; }

            try {
                var response = await fetch(this.config.restUrl + 'tasks', {
                    method: 'POST', headers: this.getHeaders(),
                    body: JSON.stringify({ title: title, content: content })
                });
                if (!response.ok) {
                    var errorMsg = 'Failed to create task: ' + response.status;
                    if (response.status === 401 || response.status === 403) errorMsg = 'Permission denied. Please log in.';
                    throw new Error(errorMsg);
                }
                if (nameEl) nameEl.value = '';
                if (descEl) descEl.value = '';
                this.loadTasks();
                this.showNotification('Task created!', 'success');
            } catch (error) {
                console.error('Create task error:', error);
                this.showNotification(error.message, 'error');
            }
        },

        deleteTask: async function(taskId) {
            if (!confirm('Delete this task?')) return;
            try {
                var response = await fetch(this.config.restUrl + 'tasks/' + taskId, {
                    method: 'DELETE', headers: this.getHeaders()
                });
                if (!response.ok) throw new Error('Failed to delete: ' + response.status);
                var detailCard = document.getElementById('task-detail-card');
                if (detailCard) detailCard.style.display = 'none';
                this.showNotification('Task deleted', 'success');
                this.loadTasks();
            } catch (error) {
                console.error('Delete error:', error);
                this.showNotification('Delete failed: ' + error.message, 'error');
            }
        },

        // === GOALS ===
        bindGoals: function() {
            var self = this;
            var submitBtn = document.getElementById('submit-goal-btn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.createGoal();
                });
            }
            document.querySelectorAll('#goal-filter-bar .filter-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#goal-filter-bar .filter-btn').forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    self.state.goalFilter = this.dataset.filter;
                    self.renderGoals();
                });
            });
        },

        loadGoals: async function() {
            var self = this;
            var treeEl = document.getElementById('goal-tree');
            if (treeEl) treeEl.innerHTML = '<div class="empty-state">Loading goals...</div>';
            try {
                var response = await fetch(this.config.restUrl + 'goals', {
                    method: 'GET', headers: this.getHeaders()
                });
                if (!response.ok) throw new Error('API returned ' + response.status);
                var data = await response.json();
                this.state.goals = data.goals || (Array.isArray(data) ? data : []);
                this.renderGoals();
                this.updateStats();
                var countEl = document.getElementById('goals-count');
                if (countEl) countEl.textContent = this.state.goals.length;
            } catch (error) {
                console.error('Load goals error:', error);
                if (treeEl) treeEl.innerHTML = '<div class="empty-state">Failed to load goals</div>';
            }
        },

        renderGoals: function() {
            var treeEl = document.getElementById('goal-tree');
            if (!treeEl) return;
            var filter = this.state.goalFilter;
            var goals = this.state.goals;
            if (filter !== 'all') {
                goals = goals.filter(function(g) { return g.level === filter; });
            }

            if (goals.length === 0) {
                treeEl.innerHTML = '<div class="empty-state">No goals found</div>';
                return;
            }

            var html = '';
            goals.forEach(function(g) {
                var level = g.level || 'task';
                var indentClass = 'level-' + level;
                var icon = level === 'mission' ? '🎯' : level === 'objective' ? '📋' : '✅';
                html += '<div class="goal-node ' + indentClass + '" data-goal-id="' + g.id + '">' +
                    '<div class="goal-header">' +
                    '<span class="goal-title-text">' + icon + ' ' + WorkPage.escapeHtml(g.title || 'Untitled') + '</span>' +
                    '<span class="goal-level ' + level + '">' + level + '</span>' +
                    '</div>';
                if (g.description) html += '<div class="goal-desc">' + WorkPage.escapeHtml(g.description) + '</div>';
                if (g.status) html += '<div style="margin-top:4px;font-size:0.75rem;color:var(--text-muted)">Status: ' + g.status + '</div>';
                html += '</div>';
            });
            treeEl.innerHTML = html;

            // Bind click
            treeEl.querySelectorAll('.goal-node').forEach(function(item) {
                item.addEventListener('click', function() {
                    var id = parseInt(this.dataset.goalId);
                    WorkPage.selectGoal(id);
                });
            });

            var countEl = document.getElementById('goal-count');
            if (countEl) countEl.textContent = goals.length + ' goals';

            // Populate parent select
            this.populateGoalParentSelect();
        },

        populateGoalParentSelect: function() {
            var select = document.getElementById('new-goal-parent');
            if (!select) return;
            var currentVal = select.value;
            select.innerHTML = '<option value="">None (top-level)</option>';
            this.state.goals.forEach(function(g) {
                if (g.level === 'mission' || g.level === 'objective') {
                    var opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = (g.level === 'mission' ? '🎯 ' : '📋 ') + (g.title || 'Goal #' + g.id);
                    select.appendChild(opt);
                }
            });
            select.value = currentVal;
        },

        selectGoal: function(goalId) {
            var goal = this.state.goals.find(function(g) { return g.id == goalId; });
            if (!goal) return;
            var detailCard = document.getElementById('goal-detail-card');
            var detailBody = document.getElementById('goal-detail-body');
            if (detailCard && detailBody) {
                detailCard.style.display = 'block';
                detailBody.innerHTML =
                    '<p><strong>Title:</strong> ' + this.escapeHtml(goal.title || 'Untitled') + '</p>' +
                    '<p><strong>Level:</strong> ' + (goal.level || 'task') + '</p>' +
                    '<p><strong>Status:</strong> ' + (goal.status || 'N/A') + '</p>' +
                    '<p><strong>Description:</strong> ' + this.escapeHtml(goal.description || 'None') + '</p>' +
                    (goal.parent_id ? '<p><strong>Parent:</strong> Goal #' + goal.parent_id + '</p>' : '') +
                    '<div style="margin-top:12px;display:flex;gap:8px;">' +
                    '<button class="btn btn-danger btn-sm" onclick="WorkPage.deleteGoal(' + goal.id + ')">Delete Goal</button>' +
                    '</div>';
            }
        },

        createGoal: async function() {
            var titleEl = document.getElementById('new-goal-title');
            var descEl = document.getElementById('new-goal-desc');
            var levelEl = document.getElementById('new-goal-level');
            var parentEl = document.getElementById('new-goal-parent');
            var title = titleEl ? titleEl.value.trim() : '';
            if (!title) { this.showNotification('Goal title required', 'error'); return; }

            var data = {
                title: title,
                description: descEl ? descEl.value.trim() : '',
                level: levelEl ? levelEl.value : 'task'
            };
            var parentId = parentEl ? parentEl.value : '';
            if (parentId) data.parent_id = parseInt(parentId);

            try {
                var response = await fetch(this.config.restUrl + 'goals', {
                    method: 'POST', headers: this.getHeaders(),
                    body: JSON.stringify(data)
                });
                if (!response.ok) {
                    var errorMsg = 'Failed to create goal: ' + response.status;
                    if (response.status === 401 || response.status === 403) errorMsg = 'Permission denied. Please log in.';
                    throw new Error(errorMsg);
                }
                if (titleEl) titleEl.value = '';
                if (descEl) descEl.value = '';
                this.loadGoals();
                this.showNotification('Goal created!', 'success');
            } catch (error) {
                console.error('Create goal error:', error);
                this.showNotification(error.message, 'error');
            }
        },

        deleteGoal: async function(goalId) {
            if (!confirm('Delete this goal?')) return;
            try {
                var response = await fetch(this.config.restUrl + 'goals/' + goalId, {
                    method: 'DELETE', headers: this.getHeaders()
                });
                if (!response.ok) throw new Error('Failed to delete: ' + response.status);
                var detailCard = document.getElementById('goal-detail-card');
                if (detailCard) detailCard.style.display = 'none';
                this.showNotification('Goal deleted', 'success');
                this.loadGoals();
            } catch (error) {
                console.error('Delete goal error:', error);
                this.showNotification('Delete failed: ' + error.message, 'error');
            }
        },

        // === MISSIONS ===
        bindMissions: function() {
            var self = this;
            var submitBtn = document.getElementById('submit-mission-btn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.createMission();
                });
            }
            document.querySelectorAll('#mission-filter-bar .filter-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#mission-filter-bar .filter-btn').forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    self.state.missionFilter = this.dataset.filter;
                    self.renderMissions();
                });
            });
        },

        loadMissions: async function() {
            var self = this;
            var gridEl = document.getElementById('mission-grid');
            if (gridEl) gridEl.innerHTML = '<div class="empty-state">Loading missions...</div>';
            try {
                var response = await fetch(this.config.restUrl + 'missions', {
                    method: 'GET', headers: this.getHeaders()
                });
                if (!response.ok) throw new Error('API returned ' + response.status);
                var data = await response.json();
                this.state.missions = data.missions || [];
                this.renderMissions();
                this.updateStats();
                var countEl = document.getElementById('missions-count');
                if (countEl) countEl.textContent = this.state.missions.length;
            } catch (error) {
                console.error('Load missions error:', error);
                if (gridEl) gridEl.innerHTML = '<div class="empty-state">Failed to load missions</div>';
            }
        },

        renderMissions: function() {
            var gridEl = document.getElementById('mission-grid');
            if (!gridEl) return;
            var filter = this.state.missionFilter;
            var missions = this.state.missions;
            if (filter !== 'all') {
                missions = missions.filter(function(m) { return m.status === filter; });
            }

            if (missions.length === 0) {
                gridEl.innerHTML = '<div class="empty-state">No missions found</div>';
                return;
            }

            var html = '';
            missions.forEach(function(m) {
                var priorityLabels = { 1: 'Critical', 2: 'High', 3: 'Medium', 4: 'Low', 5: 'Minimal' };
                var prioLabel = priorityLabels[m.priority] || 'Medium';
                html += '<div class="mission-card" data-mission-id="' + m.id + '">' +
                    '<div class="mission-card-header">' +
                    '<span class="mission-title">' + WorkPage.escapeHtml(m.title || 'Untitled') + '</span>' +
                    '<span class="mission-status ' + (m.status || 'proposed') + '">' + (m.status || 'proposed') + '</span>' +
                    '</div>' +
                    '<div class="mission-desc">' + WorkPage.escapeHtml((m.content || '').substring(0, 120)) + '</div>' +
                    '<div class="mission-meta">' +
                    '<span class="mission-priority">P' + (m.priority || 3) + ': ' + prioLabel + '</span>' +
                    '<span>' + (m.date ? m.date.substring(0, 10) : 'N/A') + '</span>' +
                    '</div></div>';
            });
            gridEl.innerHTML = html;

            // Bind click events
            gridEl.querySelectorAll('.mission-card').forEach(function(item) {
                item.addEventListener('click', function() {
                    var id = parseInt(this.dataset.missionId);
                    WorkPage.selectMission(id);
                });
            });

            var countEl = document.getElementById('mission-count');
            if (countEl) countEl.textContent = missions.length + ' missions';
        },

        selectMission: function(missionId) {
            var mission = this.state.missions.find(function(m) { return m.id == missionId; });
            if (!mission) return;
            var detailCard = document.getElementById('mission-detail-card');
            var detailBody = document.getElementById('mission-detail-body');
            if (detailCard && detailBody) {
                detailCard.style.display = 'block';
                var priorityLabels = { 1: 'Critical', 2: 'High', 3: 'Medium', 4: 'Low', 5: 'Minimal' };
                detailBody.innerHTML =
                    '<p><strong>Title:</strong> ' + this.escapeHtml(mission.title || 'Untitled') + '</p>' +
                    '<p><strong>Status:</strong> <span class="mission-status ' + mission.status + '">' + mission.status + '</span></p>' +
                    '<p><strong>Priority:</strong> P' + mission.priority + ' - ' + (priorityLabels[mission.priority] || 'Medium') + '</p>' +
                    '<p><strong>Description:</strong> ' + this.escapeHtml(mission.content || 'None') + '</p>' +
                    (mission.due_date ? '<p><strong>Due:</strong> ' + mission.due_date + '</p>' : '') +
                    '<p><strong>Author:</strong> ' + this.escapeHtml(mission.author || 'Unknown') + '</p>' +
                    '<p><strong>Created:</strong> ' + (mission.date || 'Unknown') + '</p>' +
                    '<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">' +
                    '<select id="mission-status-select" class="form-input" style="width:auto;">' +
                    '<option value="proposed"' + (mission.status === 'proposed' ? ' selected' : '') + '>Proposed</option>' +
                    '<option value="approved"' + (mission.status === 'approved' ? ' selected' : '') + '>Approved</option>' +
                    '<option value="in-progress"' + (mission.status === 'in-progress' ? ' selected' : '') + '>In Progress</option>' +
                    '<option value="completed"' + (mission.status === 'completed' ? ' selected' : '') + '>Completed</option>' +
                    '<option value="archived"' + (mission.status === 'archived' ? ' selected' : '') + '>Archived</option>' +
                    '</select>' +
                    '<button class="btn btn-primary btn-sm" onclick="WorkPage.updateMissionStatus(' + mission.id + ')">Update Status</button>' +
                    '<button class="btn btn-danger btn-sm" onclick="WorkPage.deleteMission(' + mission.id + ')">Delete Mission</button>' +
                    '</div>';
            }
        },

        updateMissionStatus: async function(missionId) {
            var selectEl = document.getElementById('mission-status-select');
            if (!selectEl) return;
            var newStatus = selectEl.value;
            try {
                var response = await fetch(this.config.restUrl + 'missions/' + missionId, {
                    method: 'POST', headers: this.getHeaders(),
                    body: JSON.stringify({ status: newStatus })
                });
                if (!response.ok) throw new Error('Failed to update: ' + response.status);
                this.showNotification('Mission status updated!', 'success');
                this.loadMissions();
                var detailCard = document.getElementById('mission-detail-card');
                if (detailCard) detailCard.style.display = 'none';
            } catch (error) {
                console.error('Update mission error:', error);
                this.showNotification('Update failed: ' + error.message, 'error');
            }
        },

        createMission: async function() {
            var titleEl = document.getElementById('new-mission-title');
            var contentEl = document.getElementById('new-mission-content');
            var statusEl = document.getElementById('new-mission-status');
            var priorityEl = document.getElementById('new-mission-priority');
            var title = titleEl ? titleEl.value.trim() : '';
            if (!title) { this.showNotification('Mission name required', 'error'); return; }

            var data = {
                title: title,
                content: contentEl ? contentEl.value.trim() : '',
                status: statusEl ? statusEl.value : 'proposed',
                priority: priorityEl ? parseInt(priorityEl.value) : 3
            };

            try {
                var response = await fetch(this.config.restUrl + 'missions', {
                    method: 'POST', headers: this.getHeaders(),
                    body: JSON.stringify(data)
                });
                if (!response.ok) {
                    var errorMsg = 'Failed to create mission: ' + response.status;
                    if (response.status === 401 || response.status === 403) errorMsg = 'Permission denied. Please log in.';
                    throw new Error(errorMsg);
                }
                if (titleEl) titleEl.value = '';
                if (contentEl) contentEl.value = '';
                this.loadMissions();
                this.showNotification('Mission created!', 'success');
            } catch (error) {
                console.error('Create mission error:', error);
                this.showNotification(error.message, 'error');
            }
        },

        deleteMission: async function(missionId) {
            if (!confirm('Delete this mission?')) return;
            try {
                var response = await fetch(this.config.restUrl + 'missions/' + missionId, {
                    method: 'DELETE', headers: this.getHeaders()
                });
                if (!response.ok) throw new Error('Failed to delete: ' + response.status);
                var detailCard = document.getElementById('mission-detail-card');
                if (detailCard) detailCard.style.display = 'none';
                this.showNotification('Mission deleted', 'success');
                this.loadMissions();
            } catch (error) {
                console.error('Delete mission error:', error);
                this.showNotification('Delete failed: ' + error.message, 'error');
            }
        },


        // === KANBAN ===
        loadKanban: function() {
            var self = this;
            fetch(this.config.restUrl + 'kanban', {
                headers: this.getHeaders()
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var tasks = data.tasks || [];
                var columns = ['inbox', 'backlog', 'doing', 'review', 'done', 'archived'];
                columns.forEach(function(status) {
                    var container = document.getElementById('kanban-' + status);
                    var columnTasks = tasks.filter(function(t) { return t.status === status; });
                    document.querySelector('.kanban-count[data-status="' + status + '"]').textContent = columnTasks.length;
                    if (container) {
                        container.innerHTML = columnTasks.map(function(t) {
                            return self.renderKanbanCard(t);
                        }).join('') || '<div style="padding:20px;color:var(--text-muted);font-size:0.8rem;text-align:center;">Empty</div>';
                    }
                });
                document.getElementById('kanban-count').textContent = tasks.length;
            })
            .catch(function(err) {
                console.error('Kanban load failed:', err);
            });
        },

        renderKanbanCard: function(task) {
            var priorityColors = { low: '#6a6a7a', normal: '#3b82f6', high: '#ef4444' };
            return '<div style="padding:10px;margin-bottom:8px;background:var(--bg-tertiary);border:1px solid var(--border-default);border-radius:6px;cursor:pointer;" onclick="WorkPage.showTaskDetail(' + task.id + ')">' +
                '<div style="font-weight:600;font-size:0.85rem;margin-bottom:4px;">' + this.escapeHtml(task.title) + '</div>' +
                '<div style="font-size:0.75rem;color:var(--text-muted);">' +
                (task.task_type ? task.task_type + ' · ' : '') +
                (task.assigned_to ? 'Assigned: ' + task.assigned_to : 'Unassigned') +
                '</div>' +
                '<div style="margin-top:6px;height:3px;border-radius:2px;background:' + (priorityColors[task.priority] || '#6a6a7a') + ';width:100%;"></div>' +
                '</div>';
        },
        // === UTILITIES ===
        showNotification: function(message, type) {
            var existing = document.querySelector('.notification');
            if (existing) existing.remove();
            var notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            notification.textContent = message;
            notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:10px 20px;border-radius:4px;z-index:9999;background:' +
                (type === 'success' ? '#4caf50' : '#f44336') + ';color:white;';
            document.body.appendChild(notification);
            setTimeout(function() { notification.remove(); }, 3000);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Auto-initialize
    document.addEventListener('DOMContentLoaded', function() {
        WorkPage.init();
    });

    global.WorkPage = WorkPage;
})(typeof window !== 'undefined' ? window : this);
