/**
 * BITCORE Widget Manager
 * Drag-and-drop widget system with persistence
 * @version 1.0.0
 */

(function(window, document) {
    'use strict';

    const WidgetManager = function(containerSelector, options) {
        this.container = document.querySelector(containerSelector);
        if (!this.container) {
            console.error('[WidgetManager] Container not found:', containerSelector);
            return;
        }

        this.config = Object.assign({
            draggable: true,
            persistLayout: true,
            gridColumns: 12,
            animationDuration: 250
        }, options || {});

        this.widgets = [];
        this.draggedWidget = null;
        this.placeholder = null;

        this.init();
    };

    WidgetManager.prototype.init = function() {
        this.loadLayout();
        this.bindEvents();
        this.emit('ready');
    };

    WidgetManager.prototype.bindEvents = function() {
        if (!this.config.draggable) return;

        // Event delegation for drag handles
        this.container.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.container.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });

        // Global drag events
        document.addEventListener('mousemove', this.handleMouseMove.bind(this));
        document.addEventListener('mouseup', this.handleMouseUp.bind(this));
        document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        document.addEventListener('touchend', this.handleTouchEnd.bind(this));
    };

    WidgetManager.prototype.handleMouseDown = function(e) {
        const handle = e.target.closest('.bf-widget-handle');
        if (!handle) return;

        const widget = handle.closest('.bf-widget');
        if (!widget) return;

        e.preventDefault();
        this.startDrag(widget, e.clientX, e.clientY);
    };

    WidgetManager.prototype.handleTouchStart = function(e) {
        const handle = e.target.closest('.bf-widget-handle');
        if (!handle) return;

        const widget = handle.closest('.bf-widget');
        if (!widget) return;

        e.preventDefault();
        const touch = e.touches[0];
        this.startDrag(widget, touch.clientX, touch.clientY);
    };

    WidgetManager.prototype.startDrag = function(widget, x, y) {
        this.draggedWidget = widget;
        widget.classList.add('dragging');

        // Create placeholder
        this.placeholder = widget.cloneNode(true);
        this.placeholder.classList.add('drag-placeholder');
        widget.parentNode.insertBefore(this.placeholder, widget.nextSibling);

        // Store initial position
        this.dragStart = { x, y };
        this.dragOffset = {
            x: x - widget.getBoundingClientRect().left,
            y: y - widget.getBoundingClientRect().top
        };

        this.emit('dragStart', { widget });
    };

    WidgetManager.prototype.handleMouseMove = function(e) {
        if (!this.draggedWidget) return;
        this.updateDragPosition(e.clientX, e.clientY);
    };

    WidgetManager.prototype.handleTouchMove = function(e) {
        if (!this.draggedWidget) return;
        const touch = e.touches[0];
        this.updateDragPosition(touch.clientX, touch.clientY);
    };

    WidgetManager.prototype.updateDragPosition = function(x, y) {
        if (!this.draggedWidget) return;

        // Update dragged widget position
        this.draggedWidget.style.position = 'fixed';
        this.draggedWidget.style.zIndex = '1000';
        this.draggedWidget.style.left = (x - this.dragOffset.x) + 'px';
        this.draggedWidget.style.top = (y - this.dragOffset.y) + 'px';
        this.draggedWidget.style.width = this.placeholder.getBoundingClientRect().width + 'px';

        // Find insertion point
        const insertAfter = this.findInsertionPoint(x, y);
        if (insertAfter && insertAfter !== this.placeholder) {
            this.container.insertBefore(this.placeholder, insertAfter.nextSibling);
        }
    };

    WidgetManager.prototype.findInsertionPoint = function(x, y) {
        const widgets = Array.from(this.container.querySelectorAll('.bf-widget:not(.dragging):not(.drag-placeholder)'));

        for (let widget of widgets) {
            const rect = widget.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            if (y < centerY || (y < rect.bottom && x < centerX)) {
                return widget;
            }
        }

        return null;
    };

    WidgetManager.prototype.handleMouseUp = function(e) {
        this.endDrag();
    };

    WidgetManager.prototype.handleTouchEnd = function(e) {
        this.endDrag();
    };

    WidgetManager.prototype.endDrag = function() {
        if (!this.draggedWidget) return;

        // Reset styles
        this.draggedWidget.style.position = '';
        this.draggedWidget.style.zIndex = '';
        this.draggedWidget.style.left = '';
        this.draggedWidget.style.top = '';
        this.draggedWidget.style.width = '';
        this.draggedWidget.classList.remove('dragging');

        // Move to placeholder position
        this.container.insertBefore(this.draggedWidget, this.placeholder);
        this.placeholder.remove();
        this.placeholder = null;

        this.emit('dragEnd', { widget: this.draggedWidget });
        this.saveLayout();

        this.draggedWidget = null;
    };

    WidgetManager.prototype.saveLayout = function() {
        if (!this.config.persistLayout) return;

        const widgets = Array.from(this.container.querySelectorAll('.bf-widget'));
        const layout = widgets.map((widget, index) => ({
            id: widget.dataset.widgetId,
            type: widget.dataset.widgetType,
            position: index
        }));

        localStorage.setItem('bitcore_widget_layout', JSON.stringify(layout));
        this.emit('layoutSaved', { layout });
    };

    WidgetManager.prototype.loadLayout = function() {
        if (!this.config.persistLayout) return;

        const saved = localStorage.getItem('bitcore_widget_layout');
        if (!saved) return;

        try {
            const layout = JSON.parse(saved);
            this.emit('layoutLoaded', { layout });
        } catch (e) {
            console.error('[WidgetManager] Failed to load layout:', e);
        }
    };

    WidgetManager.prototype.emit = function(event, data) {
        this.container.dispatchEvent(new CustomEvent('widget:' + event, {
            detail: data,
            bubbles: true
        }));
    };

    // Expose globally
    window.WidgetManager = WidgetManager;

})(window, document);
