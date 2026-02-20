/**
 * BITCORE Chatbot JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Chatbot functionality
        var $chatbot = $('.bitcore-chatbot');
        var $messages = $('#chat-messages');
        var $input = $('#chat-input');
        var $sendBtn = $('#chat-send');
        var $conversationId = $('#chat-conversation-id');
        var $avatar = $('.bitflux-avatar');

        // Send message
        function sendMessage() {
            var message = $input.val().trim();
            if (!message) return;

            // Add user message
            addMessage(message, 'user');
            $input.val('');

            // Set avatar to listening
            $avatar.attr('data-state', 'listen');

            // Send to server
            $.ajax({
                url: bitcoreConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bitcore_chat',
                    nonce: bitcoreConfig.nonce,
                    message: message,
                    agent: $chatbot.data('agent'),
                    conversation_id: $conversationId.val()
                },
                success: function(response) {
                    if (response.success) {
                        // Set avatar to processing then back to welcome
                        $avatar.attr('data-state', 'process');
                        setTimeout(function() {
                            $avatar.attr('data-state', 'welcome');
                        }, 1000);

                        addMessage(response.data.message, 'agent');
                    } else {
                        addMessage('Error: ' + response.data, 'agent');
                        $avatar.attr('data-state', 'welcome');
                    }
                },
                error: function() {
                    addMessage('Connection error. Please try again.', 'agent');
                    $avatar.attr('data-state', 'welcome');
                }
            });
        }

        // Add message to chat
        function addMessage(text, sender) {
            var $message = $('<div class="chat-message ' + sender + '"></div>').text(text);
            $messages.append($message);
            $messages.scrollTop($messages[0].scrollHeight);
        }

        // Event listeners
        $sendBtn.on('click', sendMessage);
        $input.on('keypress', function(e) {
            if (e.which === 13) {
                sendMessage();
            }
        });

        // Listen for artifact injection
        window.addEventListener('artifact:inject', function(e) {
            var artifact = e.detail.artifact;
            addMessage('Loaded artifact: ' + artifact, 'agent');
        });
    });
})(jQuery);
