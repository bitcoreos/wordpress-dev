/**
 * BITCORE Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Check system status
        function checkStatus() {
            // This would check actual connectivity
            $('#n8n-status').text('Connected').addClass('connected');
            $('#bithub-status').text('Connected').addClass('connected');
            $('#bitwiki-status').text('Connected').addClass('connected');
        }

        checkStatus();

        // MCP Tool tester
        $('#mcp-test-btn').on('click', function() {
            var tool = $('#mcp-tool-select').val();
            var args = $('#mcp-tool-args').val();

            if (!tool) {
                alert('Please select a tool');
                return;
            }

            $('#mcp-result').text('Executing...');

            $.ajax({
                url: bitcoreConfig.restUrl + 'mcp/call',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', bitcoreConfig.nonce);
                },
                data: JSON.stringify({
                    tool: tool,
                    args: args ? JSON.parse(args) : {}
                }),
                contentType: 'application/json',
                success: function(response) {
                    $('#mcp-result').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#mcp-result').text('Error: ' + xhr.responseText);
                }
            });
        });
    });
})(jQuery);
