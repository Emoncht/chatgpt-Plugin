<?php
/**
 * Plugin Name: OpenAI Chatbot
 * Description: A conversational chatbot using OpenAI's fine-tuned models with human takeover capability
 * Version: 1.0.0
 * Author: Emon
 * Text Domain: openai-chatbot
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPENAI_CHATBOT_VERSION', '1.0.0');
define('OPENAI_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPENAI_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/admin-settings.php';
require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/database-handler.php';
require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/api-handler.php';
require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/conversation-handler.php';
require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/admin-dashboard.php';
require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/user-data-handler.php';

// Activation hook
register_activation_hook(__FILE__, 'openai_chatbot_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'openai_chatbot_deactivate');

/**
 * Plugin activation function
 */
function openai_chatbot_activate() {
    // Create necessary database tables
    require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/database-handler.php';
    $db_handler = new OpenAI_Chatbot_Database_Handler();
    $db_handler->create_tables();
    
    // Set default options
    $default_options = array(
        'model_name' => 'ft:gpt-4o-mini-2024-07-18:hydra-gameshhop::B9c4goKH',
        'api_key' => '',
        'system_prompt' => 'You are a helpful and friendly chatbot for Gameheaven.net, You reply in Bangla language, even though user sens banglish/bangla response you will always reply in bangla, Within 40 words, If the response requires more than 40 words you will reply it in two response.',
        'chat_enabled' => 'yes',
        'human_takeover_enabled' => 'yes',
        'theme_color' => '#4a51bf',
        'chat_title' => 'Chat with us',
        'welcome_message_logged_in' => 'Hello {name}, how can I help you today?',
        'welcome_message_guest' => 'Hi, Do you need help?'
    );
    
    add_option('openai_chatbot_settings', $default_options);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation function
 */
function openai_chatbot_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Enqueue frontend scripts and styles with Bootstrap scoped to chatbot only
 */
function openai_chatbot_enqueue_scripts() {
    // Plugin CSS (this will include our customized Bootstrap components)
    wp_enqueue_style('openai-chatbot-css', OPENAI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css', array(), OPENAI_CHATBOT_VERSION);
    
    // Bootstrap Bundle JS (includes Popper)
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
    
    // Plugin JS
    wp_enqueue_script('openai-chatbot-js', OPENAI_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js', array('jquery', 'bootstrap-js'), OPENAI_CHATBOT_VERSION, true);
    
    // Localize script with settings and AJAX URL
    $options = get_option('openai_chatbot_settings');
    
    wp_localize_script('openai-chatbot-js', 'openaiChatbot', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openai-chatbot-nonce'),
        'chatEnabled' => isset($options['chat_enabled']) ? $options['chat_enabled'] : 'yes',
        'chatTitle' => isset($options['chat_title']) ? $options['chat_title'] : 'Chat with us',
        'welcomeMessage' => isset($options['welcome_message']) ? $options['welcome_message'] : 'Hello! How can I help you today?',
        'themeColor' => isset($options['theme_color']) ? $options['theme_color'] : '#4a51bf',
        'i18n' => array(
            'sending' => __('Sending...', 'openai-chatbot'),
            'connectingAgent' => __('Connecting to a human agent...', 'openai-chatbot'),
            'errorMessage' => __('Sorry, there was an error. Please try again.', 'openai-chatbot'),
            'sendMessage' => __('Send message', 'openai-chatbot'),
            'typeMessage' => __('Type your message...', 'openai-chatbot'),
        )
    ));
}
add_action('wp_enqueue_scripts', 'openai_chatbot_enqueue_scripts');

/**
 * Add chatbot HTML to footer
 */
function openai_chatbot_add_to_footer() {
    include OPENAI_CHATBOT_PLUGIN_DIR . 'templates/chatbot.php';
}
add_action('wp_footer', 'openai_chatbot_add_to_footer');

/**
 * Ajax handler for sending messages
 */
function openai_chatbot_send_message() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Get message and conversation ID
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
    
    if (empty($message)) {
        wp_send_json_error('Message is required');
    }
    
    // Process the message
    require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/conversation-handler.php';
    $conversation_handler = new OpenAI_Chatbot_Conversation_Handler();
    $response = $conversation_handler->process_message($message, $conversation_id);
    
    wp_send_json_success($response);
}
add_action('wp_ajax_openai_chatbot_send_message', 'openai_chatbot_send_message');
add_action('wp_ajax_nopriv_openai_chatbot_send_message', 'openai_chatbot_send_message');

/**
 * Get user data for personalization
 */
function openai_chatbot_get_user_data() {
    $user_data = array(
        'is_logged_in' => is_user_logged_in(),
        'user_id' => 0,
        'display_name' => '',
        'orders' => array()
    );
    
    // If user is logged in, get additional data
    if ($user_data['is_logged_in']) {
        $current_user = wp_get_current_user();
        $user_data['user_id'] = $current_user->ID;
        $user_data['display_name'] = $current_user->display_name;
        
        // If WooCommerce is active, get order history
        if (class_exists('WooCommerce')) {
            $user_data_handler = new OpenAI_Chatbot_User_Data_Handler();
            $user_data['orders'] = $user_data_handler->get_user_orders($current_user->ID, 10);
        }
    }
    
    return $user_data;
}

/**
 * Ajax handler for getting user data
 */
function openai_chatbot_get_user_data_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $user_data = openai_chatbot_get_user_data();
    wp_send_json_success($user_data);
}
add_action('wp_ajax_openai_chatbot_get_user_data', 'openai_chatbot_get_user_data_ajax');
add_action('wp_ajax_nopriv_openai_chatbot_get_user_data', 'openai_chatbot_get_user_data_ajax');

/**
 * Ajax handler for human takeover
 */
function openai_chatbot_human_takeover() {
    // Check nonce and user capabilities
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-admin-nonce') || !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
    $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    if (empty($conversation_id) || empty($action)) {
        wp_send_json_error('Missing required parameters');
    }
    
    require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/conversation-handler.php';
    $conversation_handler = new OpenAI_Chatbot_Conversation_Handler();
    
    if ($action === 'takeover') {
        $result = $conversation_handler->human_takeover($conversation_id);
    } elseif ($action === 'return') {
        $result = $conversation_handler->return_to_ai($conversation_id);
    } else {
        wp_send_json_error('Invalid action');
    }
    
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to perform action');
    }
}
add_action('wp_ajax_openai_chatbot_human_takeover', 'openai_chatbot_human_takeover');

/**
 * Ajax handler for human response
 */
function openai_chatbot_human_response() {
    // Check nonce and user capabilities
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-admin-nonce') || !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    
    if (empty($conversation_id) || empty($message)) {
        wp_send_json_error('Missing required parameters');
    }
    
    require_once OPENAI_CHATBOT_PLUGIN_DIR . 'includes/conversation-handler.php';
    $conversation_handler = new OpenAI_Chatbot_Conversation_Handler();
    $result = $conversation_handler->add_human_response($conversation_id, $message);
    
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to send message');
    }
}
add_action('wp_ajax_openai_chatbot_human_response', 'openai_chatbot_human_response');