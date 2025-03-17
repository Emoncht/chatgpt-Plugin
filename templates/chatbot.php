<?php
/**
 * Frontend Chatbot Template
 * 
 * The HTML template for the chatbot interface with modern UI design
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get settings
$options = get_option('openai_chatbot_settings');
$chat_enabled = isset($options['chat_enabled']) ? $options['chat_enabled'] : 'yes';

// Don't output if chat is disabled
if ($chat_enabled === 'no') {
    return;
}

$chat_title = isset($options['chat_title']) ? $options['chat_title'] : __('Chat with us', 'openai-chatbot');
$theme_color = isset($options['theme_color']) ? $options['theme_color'] : '#007bff';
?>

<!-- OpenAI Chatbot -->
<div id="openai-chatbot" class="openai-chatbot">
    <!-- Chat button -->
    <div class="openai-chatbot-button" style="background-color: <?php echo esc_attr($theme_color); ?>;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="openai-chatbot-icon-chat">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="openai-chatbot-icon-close">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </div>
    
    <!-- Chat window -->
    <div class="openai-chatbot-window">
        <!-- Chat header -->
        <div class="openai-chatbot-header" style="background-color: <?php echo esc_attr($theme_color); ?>;">
            <h5><?php echo esc_html($chat_title); ?></h5>
            <div class="openai-chatbot-header-actions">
                <!-- Clear chat button -->
                <button type="button" class="openai-chatbot-clear" title="<?php esc_attr_e('Clear conversation', 'openai-chatbot'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
                <!-- Close button -->
                <button type="button" class="openai-chatbot-close" title="<?php esc_attr_e('Close chat', 'openai-chatbot'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Chat messages -->
        <div class="openai-chatbot-messages">
            <!-- Messages will be added here dynamically -->
        </div>
        
        <!-- Chat input -->
        <div class="openai-chatbot-input">
            <form id="openai-chatbot-form">
                <div class="openai-chatbot-input-group">
                    <input type="text" id="openai-chatbot-message" placeholder="<?php esc_attr_e('Type your message...', 'openai-chatbot'); ?>" required>
                    <button type="submit" style="background-color: <?php echo esc_attr($theme_color); ?>;">
                        <?php esc_html_e('Send', 'openai-chatbot'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>