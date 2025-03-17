/**
 * OpenAI Chatbot Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Check if we're on the conversations page
    const isConversationsPage = window.location.href.indexOf('openai-chatbot-conversations') > -1;
    
    // Initialize conversations dashboard
    if (isConversationsPage) {
        // Auto-refresh every 30 seconds
        setInterval(function() {
            refreshActiveConversations();
        }, 30000);
    }
    
    /**
     * Refresh active conversations
     */
    function refreshActiveConversations() {
        // Get the current selected conversation ID
        const selectedId = $('.openai-chatbot-controls').data('id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'openai_chatbot_get_conversations',
                nonce: openaiChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderConversationList(response.data);
                    
                    // If a conversation was selected, highlight it
                    if (selectedId) {
                        $(`.conversation-item[data-id="${selectedId}"]`).addClass('active');
                    }
                }
            }
        });
    }
    
    /**
     * Render conversation list
     */
    function renderConversationList(conversations) {
        if (!conversations || conversations.length === 0) {
            $('.openai-chatbot-conversation-list').html(
                '<div class="text-center my-3"><p>No active conversations found.</p></div>'
            );
            return;
        }
        
        let html = '';
        
        conversations.forEach(function(conversation) {
            const template = $('#conversation-item-template').html();
            const date = new Date(conversation.updated_at).toLocaleString();
            const message = conversation.last_message ? conversation.last_message.message : 'No messages';
            const status = conversation.is_human_takeover ? 'Human' : 'AI';
            const badgeColor = conversation.is_human_takeover ? 'danger' : 'success';
            
            html += template
                .replace('{id}', conversation.id)
                .replace('{date}', date)
                .replace('{message}', message)
                .replace('{status}', status)
                .replace('{badge-color}', badgeColor);
        });
        
        $('.openai-chatbot-conversation-list').html(html);
    }
    
    /**
     * Event handlers for API key visibility toggle
     */
    const $apiKeyField = $('#api_key');
    const $apiKeyToggle = $('<button type="button" class="button button-secondary api-key-toggle">Show</button>');
    
    if ($apiKeyField.length) {
        // Add the toggle button after the API key field
        $apiKeyField.after($apiKeyToggle);
        
        // Set the input type to password by default
        $apiKeyField.attr('type', 'password');
        
        // Toggle API key visibility
        $apiKeyToggle.on('click', function() {
            if ($apiKeyField.attr('type') === 'password') {
                $apiKeyField.attr('type', 'text');
                $apiKeyToggle.text('Hide');
            } else {
                $apiKeyField.attr('type', 'password');
                $apiKeyToggle.text('Show');
            }
        });
    }
    
    /**
     * Color picker for theme color
     */
    const $themeColorField = $('#theme_color');
    
    if ($themeColorField.length && typeof $.fn.wpColorPicker === 'function') {
        $themeColorField.wpColorPicker();
    }
    
    /**
     * Preview button for chat interface
     */
    const $previewButton = $('<button type="button" class="button button-secondary preview-chat">Preview Chat</button>');
    const $themeColorContainer = $themeColorField.closest('tr');
    
    if ($themeColorContainer.length) {
        $themeColorContainer.find('td').append($previewButton);
        
        $previewButton.on('click', function(e) {
            e.preventDefault();
            
            // Create a modal with a preview of the chat interface
            const $modal = $('<div class="openai-chatbot-preview-modal"></div>');
            const $overlay = $('<div class="openai-chatbot-preview-overlay"></div>');
            const $content = $('<div class="openai-chatbot-preview-content"></div>');
            const $close = $('<button type="button" class="openai-chatbot-preview-close">×</button>');
            
            // Get current settings
            const title = $('#chat_title').val() || 'Chat with us';
            const welcomeMessage = $('#welcome_message').val() || 'Hello! How can I help you today?';
            const themeColor = $themeColorField.val() || '#007bff';
            
            // Create the preview HTML
            const previewHTML = `
                <div class="openai-chatbot-preview">
                    <div class="openai-chatbot-window" style="display: block; position: relative; bottom: auto; left: auto; margin: 0 auto;">
                        <div class="openai-chatbot-header" style="background-color: ${themeColor};">
                            <h5>${title}</h5>
                            <button type="button" class="openai-chatbot-close">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="openai-chatbot-messages">
                            <div class="openai-chatbot-welcome">
                                ${welcomeMessage}
                            </div>
                            <div class="openai-chatbot-message openai-chatbot-message-user">
                                Hello, can you help me?
                                <div class="openai-chatbot-message-time">10:30 AM</div>
                            </div>
                            <div class="openai-chatbot-message openai-chatbot-message-assistant">
                                Yes, I'd be happy to help! What do you need assistance with?
                                <div class="openai-chatbot-message-time">10:31 AM</div>
                            </div>
                        </div>
                        <div class="openai-chatbot-input">
                            <form>
                                <div class="openai-chatbot-input-group">
                                    <input type="text" placeholder="Type your message..." disabled>
                                    <button type="button" style="background-color: ${themeColor};">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="22" y1="2" x2="11" y2="13"></line>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $content.html(previewHTML);
            $modal.append($close).append($content);
            $('body').append($overlay).append($modal);
            
            // Show the modal
            $modal.show();
            $overlay.show();
            
            // Handle close button and overlay click
            $close.on('click', function() {
                $modal.remove();
                $overlay.remove();
            });
            
            $overlay.on('click', function() {
                $modal.remove();
                $overlay.remove();
            });
        });
        
        // Add some CSS for the preview modal
        $('head').append(`
            <style>
                .openai-chatbot-preview-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 99999;
                }
                
                .openai-chatbot-preview-modal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background-color: white;
                    border-radius: 10px;
                    padding: 20px;
                    z-index: 100000;
                    max-width: 90%;
                    max-height: 90%;
                    overflow: auto;
                }
                
                .openai-chatbot-preview-close {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    font-size: 24px;
                    background: none;
                    border: none;
                    cursor: pointer;
                }
                
                .openai-chatbot-preview .openai-chatbot-window {
                    width: 350px;
                    height: 500px;
                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                }
                
                @media (max-width: 480px) {
                    .openai-chatbot-preview .openai-chatbot-window {
                        width: 100%;
                        height: 400px;
                    }
                }
            </style>
        `);
    }
    
})(jQuery);