<?php
/**
 * Admin Conversations UI
 * 
 * Provides the UI for viewing and managing conversations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

?>
<div class="wrap">
    <h1><?php _e('OpenAI Chatbot Conversations', 'openai-chatbot'); ?></h1>
    
    <div class="openai-chatbot-dashboard">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php _e('Active Conversations', 'openai-chatbot'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="openai-chatbot-conversation-list">
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden"><?php _e('Loading...', 'openai-chatbot'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0 conversation-title"><?php _e('Select a conversation', 'openai-chatbot'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="openai-chatbot-conversation-messages">
                            <div class="text-center">
                                <p><?php _e('Select a conversation from the list to view messages.', 'openai-chatbot'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="openai-chatbot-human-response">
                            <form id="openai-chatbot-human-response-form" class="d-none">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="openai-chatbot-human-message" placeholder="<?php _e('Type your response...', 'openai-chatbot'); ?>" required>
                                    <button class="btn btn-primary" type="submit"><?php _e('Send', 'openai-chatbot'); ?></button>
                                </div>
                            </form>
                        </div>
                        <div class="openai-chatbot-controls mt-3 d-none">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary takeover-btn"><?php _e('Take Over', 'openai-chatbot'); ?></button>
                                <button type="button" class="btn btn-outline-primary return-btn"><?php _e('Return to AI', 'openai-chatbot'); ?></button>
                                <button type="button" class="btn btn-outline-danger close-btn"><?php _e('Close Conversation', 'openai-chatbot'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="conversation-item-template">
    <div class="conversation-item" data-id="{id}">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1">{date}</h6>
                <p class="mb-1 text-truncate">{message}</p>
            </div>
            <div>
                <span class="badge bg-{badge-color}">{status}</span>
            </div>
        </div>
        <hr class="my-2">
    </div>
</template>

<template id="message-item-template">
    <div class="message-item {alignment}">
        <div class="message-bubble {bubble-class}">
            <div class="message-content">{message}</div>
            <div class="message-time">{time}</div>
        </div>
    </div>
</template>

<template id="response-item-template">
    <div class="message-item {alignment}">
        <div class="message-bubble {bubble-class}">
            <div class="message-content">{message}</div>
            <div class="message-time">{time} {human-badge}</div>
        </div>
    </div>
</template>

<template id="loading-template">
    <div class="d-flex justify-content-center my-3">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden"><?php _e('Loading...', 'openai-chatbot'); ?></span>
        </div>
    </div>
</template>

<template id="no-conversations-template">
    <div class="text-center my-3">
        <p><?php _e('No active conversations found.', 'openai-chatbot'); ?></p>
    </div>
</template>

<template id="error-template">
    <div class="alert alert-danger">
        <p>{message}</p>
    </div>
</template>

<script>
jQuery(document).ready(function($) {
    // Load conversations
    loadConversations();
    
    // Handle conversation item click
    $(document).on('click', '.conversation-item', function() {
        const conversationId = $(this).data('id');
        loadConversation(conversationId);
    });
    
    // Handle take over button
    $(document).on('click', '.takeover-btn', function() {
        const conversationId = $('.openai-chatbot-controls').data('id');
        takeoverConversation(conversationId);
    });
    
    // Handle return to AI button
    $(document).on('click', '.return-btn', function() {
        const conversationId = $('.openai-chatbot-controls').data('id');
        returnToAI(conversationId);
    });
    
    // Handle close button
    $(document).on('click', '.close-btn', function() {
        const conversationId = $('.openai-chatbot-controls').data('id');
        closeConversation(conversationId);
    });
    
    // Handle human response form
    $('#openai-chatbot-human-response-form').on('submit', function(e) {
        e.preventDefault();
        
        const conversationId = $('.openai-chatbot-controls').data('id');
        const message = $('#openai-chatbot-human-message').val();
        
        if (!conversationId || !message) {
            return;
        }
        
        sendHumanResponse(conversationId, message);
    });
    
    // Load conversations
    function loadConversations() {
        $('.openai-chatbot-conversation-list').html($('#loading-template').html());
        
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
                } else {
                    $('.openai-chatbot-conversation-list').html(
                        $('#error-template').html().replace('{message}', response.data || 'Error loading conversations')
                    );
                }
            },
            error: function() {
                $('.openai-chatbot-conversation-list').html(
                    $('#error-template').html().replace('{message}', 'Error connecting to server')
                );
            }
        });
    }
    
    // Render conversation list
    function renderConversationList(conversations) {
        if (!conversations || conversations.length === 0) {
            $('.openai-chatbot-conversation-list').html($('#no-conversations-template').html());
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
    
    // Load conversation
    function loadConversation(conversationId) {
        $('.openai-chatbot-conversation-messages').html($('#loading-template').html());
        $('.openai-chatbot-controls').addClass('d-none');
        $('#openai-chatbot-human-response-form').addClass('d-none');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'openai_chatbot_get_conversation',
                nonce: openaiChatbotAdmin.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (response.success) {
                    renderConversation(response.data);
                } else {
                    $('.openai-chatbot-conversation-messages').html(
                        $('#error-template').html().replace('{message}', response.data || 'Error loading conversation')
                    );
                }
            },
            error: function() {
                $('.openai-chatbot-conversation-messages').html(
                    $('#error-template').html().replace('{message}', 'Error connecting to server')
                );
            }
        });
    }
    
    // Render conversation
    function renderConversation(conversation) {
        $('.conversation-title').text('Conversation: ' + new Date(conversation.created_at).toLocaleString());
        
        let html = '';
        
        conversation.messages.forEach(function(message) {
            // User message
            const messageTemplate = $('#message-item-template').html();
            const messageTime = new Date(message.created_at).toLocaleString();
            
            html += messageTemplate
                .replace('{alignment}', 'message-right')
                .replace('{bubble-class}', 'message-bubble-user')
                .replace('{message}', message.message)
                .replace('{time}', messageTime);
            
            // Response if available
            if (message.response) {
                const responseTemplate = $('#response-item-template').html();
                const responseTime = message.response_at ? new Date(message.response_at).toLocaleString() : '';
                const humanBadge = message.is_response_from_human ? '<span class="badge bg-danger ms-1">Human</span>' : '';
                
                html += responseTemplate
                    .replace('{alignment}', 'message-left')
                    .replace('{bubble-class}', 'message-bubble-assistant')
                    .replace('{message}', message.response)
                    .replace('{time}', responseTime)
                    .replace('{human-badge}', humanBadge);
            }
        });
        
        $('.openai-chatbot-conversation-messages').html(html);
        
        // Scroll to bottom
        const messagesContainer = $('.openai-chatbot-conversation-messages');
        messagesContainer.scrollTop(messagesContainer.prop('scrollHeight'));
        
        // Show controls
        $('.openai-chatbot-controls').removeClass('d-none').data('id', conversation.id);
        
        // Update button states based on takeover status
        if (conversation.is_human_takeover) {
            $('.takeover-btn').addClass('d-none');
            $('.return-btn').removeClass('d-none');
            $('#openai-chatbot-human-response-form').removeClass('d-none');
        } else {
            $('.takeover-btn').removeClass('d-none');
            $('.return-btn').addClass('d-none');
            $('#openai-chatbot-human-response-form').addClass('d-none');
        }
    }
    
    // Take over conversation
    function takeoverConversation(conversationId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'openai_chatbot_human_takeover',
                nonce: openaiChatbotAdmin.nonce,
                conversation_id: conversationId,
                action_type: 'takeover'
            },
            success: function(response) {
                if (response.success) {
                    // Reload conversation
                    loadConversation(conversationId);
                    // Reload conversation list
                    loadConversations();
                } else {
                    alert(response.data || 'Error taking over conversation');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    }
    
    // Return to AI
    function returnToAI(conversationId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'openai_chatbot_human_takeover',
                nonce: openaiChatbotAdmin.nonce,
                conversation_id: conversationId,
                action_type: 'return'
            },
            success: function(response) {
                if (response.success) {
                    // Reload conversation
                    loadConversation(conversationId);
                    // Reload conversation list
                    loadConversations();
                } else {
                    alert(response.data || 'Error returning to AI');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    }
    
    // Close conversation
    function closeConversation(conversationId) {
        if (!confirm('Are you sure you want to close this conversation?')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'openai_chatbot_close_conversation',
                nonce: openaiChatbotAdmin.nonce,
                conversation_id: conversationId
            },
            success: function(response) {
                if (response.success) {
                    // Reload conversation list
                    loadConversations();
                    // Clear conversation view
                    $('.openai-chatbot-conversation-messages').html(
                        '<div class="text-center"><p>Select a conversation from the list to view messages.</p></div>'
                    );
                    $('.openai-chatbot-controls').addClass('d-none');
                    $('#openai-chatbot-human-response-form').addClass('d-none');
                } else {
                    alert(response.data || 'Error closing conversation');
                }
            },
            error: function() {
                alert('Error connecting to server');
            }
        });
    }
    
    // Send human response
    function sendHumanResponse(conversationId, message) {
        // Disable form
        const form = $('#openai-chatbot-human-response-form');
        const submitButton = form.find('button[type="submit"]');
        const input = $('#openai-chatbot-human-message');
        
        submitButton.prop('disabled', true);
        input.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'openai_chatbot_human_response',
                nonce: openaiChatbotAdmin.nonce,
                conversation_id: conversationId,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    // Clear input
                    input.val('');
                    // Reload conversation
                    loadConversation(conversationId);
                } else {
                    alert(response.data || 'Error sending response');
                }
                
                // Re-enable form
                submitButton.prop('disabled', false);
                input.prop('disabled', false);
                input.focus();
            },
            error: function() {
                alert('Error connecting to server');
                
                // Re-enable form
                submitButton.prop('disabled', false);
                input.prop('disabled', false);
                input.focus();
            }
        });
    }
});
</script>