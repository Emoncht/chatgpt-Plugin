<?php
/**
 * Admin Dashboard for OpenAI Chatbot
 * 
 * Handles the admin dashboard for conversations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Chatbot_Admin_Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_openai_chatbot_get_conversations', array($this, 'get_conversations'));
        add_action('wp_ajax_openai_chatbot_get_conversation', array($this, 'get_conversation'));
        add_action('wp_ajax_openai_chatbot_close_conversation', array($this, 'close_conversation'));
    }
    
    /**
     * Get active conversations
     */
    public function get_conversations() {
        // Check nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-admin-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        // Get page number and limit
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Get conversations
        $db_handler = new OpenAI_Chatbot_Database_Handler();
        $conversations = $db_handler->get_active_conversations($limit, $offset);
        
        // Prepare data for response
        $data = array();
        
        foreach ($conversations as $conversation) {
            // Get the last message
            $last_message = $db_handler->get_last_message($conversation->id);
            
            $data[] = array(
                'id' => $conversation->id,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
                'is_human_takeover' => (bool) $conversation->is_human_takeover,
                'last_message' => $last_message ? array(
                    'message' => $last_message->message,
                    'created_at' => $last_message->created_at
                ) : null
            );
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get a specific conversation
     */
    public function get_conversation() {
        // Check nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-admin-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        // Get conversation ID
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        
        if (empty($conversation_id)) {
            wp_send_json_error('Missing conversation ID');
        }
        
        // Get conversation handler
        $conversation_handler = new OpenAI_Chatbot_Conversation_Handler();
        $conversation_data = $conversation_handler->get_conversation_history($conversation_id);
        
        if (empty($conversation_data['conversation'])) {
            wp_send_json_error('Conversation not found');
        }
        
        // Prepare data for response
        $data = array(
            'id' => $conversation_data['conversation']->id,
            'created_at' => $conversation_data['conversation']->created_at,
            'updated_at' => $conversation_data['conversation']->updated_at,
            'is_human_takeover' => (bool) $conversation_data['conversation']->is_human_takeover,
            'messages' => array()
        );
        
        foreach ($conversation_data['messages'] as $message) {
            $data['messages'][] = array(
                'id' => $message->id,
                'message' => $message->message,
                'response' => $message->response,
                'is_from_human' => (bool) $message->is_from_human,
                'is_response_from_human' => (bool) $message->is_response_from_human,
                'created_at' => $message->created_at,
                'response_at' => $message->response_at
            );
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Close a conversation
     */
    public function close_conversation() {
        // Check nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'openai-chatbot-admin-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        // Get conversation ID
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        
        if (empty($conversation_id)) {
            wp_send_json_error('Missing conversation ID');
        }
        
        // Close the conversation
        $conversation_handler = new OpenAI_Chatbot_Conversation_Handler();
        $result = $conversation_handler->close_conversation($conversation_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to close conversation');
        }
    }
}

// Initialize the class
$openai_chatbot_admin_dashboard = new OpenAI_Chatbot_Admin_Dashboard();