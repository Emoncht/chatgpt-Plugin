<?php
/**
 * Database Handler for OpenAI Chatbot
 * 
 * Handles all database operations for the chatbot
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Chatbot_Database_Handler {
    
    /**
     * Create necessary database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for conversations
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        // Table for messages
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $sql_conversations = "CREATE TABLE $table_conversations (
            id varchar(36) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(255) NOT NULL,
            ip_address varchar(100) NOT NULL,
            user_agent text NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_human_takeover tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(36) NOT NULL,
            message text DEFAULT NULL,
            response text DEFAULT NULL,
            is_from_human tinyint(1) NOT NULL DEFAULT 0,
            is_response_from_human tinyint(1) NOT NULL DEFAULT 0,
            is_system_message tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            response_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_conversations);
        dbDelta($sql_messages);
    }
    
    /**
     * Create a new conversation
     */
    public function create_conversation() {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        // Generate a UUID v4
        $id = $this->generate_uuid_v4();
        
        // Get user info
        $user_id = get_current_user_id();
        $session_id = session_id() ?: md5(uniqid('openai_chatbot', true));
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        $data = array(
            'id' => $id,
            'user_id' => $user_id ?: null,
            'session_id' => $session_id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'is_active' => 1,
            'is_human_takeover' => 0
        );
        
        $wpdb->insert($table_conversations, $data);
        
        return $id;
    }
    
    /**
     * Add a new message to a conversation
     */
    public function add_message($conversation_id, $message) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $data = array(
            'conversation_id' => $conversation_id,
            'message' => $message,
            'is_from_human' => 0
        );
        
        $wpdb->insert($table_messages, $data);
        
        // Update conversation timestamp
        $this->update_conversation_timestamp($conversation_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Add a response to a message
     */
    public function add_response($message_id, $response, $is_from_human = false) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $data = array(
            'response' => $response,
            'is_response_from_human' => $is_from_human ? 1 : 0,
            'response_at' => current_time('mysql')
        );
        
        $where = array('id' => $message_id);
        
        $wpdb->update($table_messages, $data, $where);
        
        // Get conversation ID
        $conversation_id = $wpdb->get_var(
            $wpdb->prepare("SELECT conversation_id FROM $table_messages WHERE id = %d", $message_id)
        );
        
        // Update conversation timestamp
        if ($conversation_id) {
            $this->update_conversation_timestamp($conversation_id);
        }
        
        return true;
    }
    
    /**
     * Add a human message to a conversation
     */
    public function add_human_message($conversation_id, $message) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $data = array(
            'conversation_id' => $conversation_id,
            'message' => $message,
            'is_from_human' => 1,
            'response' => null,
            'is_response_from_human' => 0
        );
        
        $wpdb->insert($table_messages, $data);
        
        // Update conversation timestamp
        $this->update_conversation_timestamp($conversation_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Add a system message (for additional AI responses)
     * 
     * @param string $conversation_id The conversation ID
     * @param string $message The system message
     * @return int|false The message ID or false on failure
     */
    public function add_system_message($conversation_id, $message) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $data = array(
            'conversation_id' => $conversation_id,
            'message' => null, // No user message
            'response' => $message, // Response is the system message
            'is_from_human' => 0,
            'is_response_from_human' => 0,
            'is_system_message' => 1, // Flag as system message
            'created_at' => current_time('mysql'),
            'response_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_messages, $data);
        
        // Update conversation timestamp
        $this->update_conversation_timestamp($conversation_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get a conversation by ID
     */
    public function get_conversation($conversation_id) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_conversations WHERE id = %s", $conversation_id)
        );
    }
    
    /**
     * Get active conversations
     */
    public function get_active_conversations($limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_conversations WHERE is_active = 1 ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $limit, $offset
            )
        );
    }
    
    /**
     * Get messages for a conversation
     */
    public function get_conversation_messages($conversation_id) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_messages WHERE conversation_id = %s ORDER BY created_at ASC",
                $conversation_id
            )
        );
    }
    
    /**
     * Get the last message for a conversation
     */
    public function get_last_message($conversation_id) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_messages WHERE conversation_id = %s ORDER BY created_at DESC LIMIT 1",
                $conversation_id
            )
        );
    }
    
    /**
     * Set human takeover for a conversation
     */
    public function set_human_takeover($conversation_id, $takeover = true) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        $data = array('is_human_takeover' => $takeover ? 1 : 0);
        $where = array('id' => $conversation_id);
        
        return $wpdb->update($table_conversations, $data, $where);
    }
    
    /**
     * Close a conversation
     */
    public function close_conversation($conversation_id) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        $data = array('is_active' => 0);
        $where = array('id' => $conversation_id);
        
        return $wpdb->update($table_conversations, $data, $where);
    }
    
    /**
     * Get the number of active conversations
     */
    public function get_active_conversations_count() {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_conversations WHERE is_active = 1"
        );
    }
    
    /**
     * Get conversations requiring human attention
     */
    public function get_conversations_needing_attention($limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $query = $wpdb->prepare(
            "SELECT c.*, 
             (SELECT COUNT(*) FROM $table_messages WHERE conversation_id = c.id AND response IS NULL) as unanswered_count
             FROM $table_conversations c
             WHERE c.is_active = 1 
             AND (
                 c.is_human_takeover = 1 
                 OR EXISTS (SELECT 1 FROM $table_messages WHERE conversation_id = c.id AND response IS NULL)
             )
             ORDER BY unanswered_count DESC, c.updated_at DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Search conversations
     */
    public function search_conversations($search_term, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT c.* 
             FROM $table_conversations c
             JOIN $table_messages m ON c.id = m.conversation_id
             WHERE c.is_active = 1 
             AND (
                 m.message LIKE %s 
                 OR m.response LIKE %s 
                 OR c.ip_address LIKE %s
             )
             ORDER BY c.updated_at DESC
             LIMIT %d OFFSET %d",
            $search, $search, $search, $limit, $offset
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update conversation timestamp
     */
    private function update_conversation_timestamp($conversation_id) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        
        $wpdb->update(
            $table_conversations,
            array('updated_at' => current_time('mysql')),
            array('id' => $conversation_id)
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_address = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip_address = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP
        $ip_address = filter_var($ip_address, FILTER_VALIDATE_IP) ? $ip_address : '0.0.0.0';
        
        return $ip_address;
    }
    
    /**
     * Generate UUID v4
     */
    private function generate_uuid_v4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Get conversation statistics
     */
    public function get_conversation_statistics() {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        $total_conversations = $wpdb->get_var("SELECT COUNT(*) FROM $table_conversations");
        $active_conversations = $wpdb->get_var("SELECT COUNT(*) FROM $table_conversations WHERE is_active = 1");
        $human_takeover_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_conversations WHERE is_human_takeover = 1 AND is_active = 1");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table_messages");
        
        // Get daily conversation counts for the past 7 days
        $daily_counts = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM $table_conversations 
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
             GROUP BY DATE(created_at) 
             ORDER BY date"
        );
        
        return array(
            'total_conversations' => $total_conversations,
            'active_conversations' => $active_conversations,
            'human_takeover_count' => $human_takeover_count,
            'total_messages' => $total_messages,
            'daily_counts' => $daily_counts
        );
    }
    
    /**
     * Clean up old conversations
     * 
     * @param int $days Number of days to keep inactive conversations (default 30)
     */
    public function cleanup_old_conversations($days = 30) {
        global $wpdb;
        
        $table_conversations = $wpdb->prefix . 'openai_chatbot_conversations';
        $table_messages = $wpdb->prefix . 'openai_chatbot_messages';
        
        // Get old inactive conversations
        $old_conversations = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table_conversations 
                 WHERE is_active = 0 AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        
        if (empty($old_conversations)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($old_conversations), '%s'));
        
        // Delete messages first
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_messages WHERE conversation_id IN ($placeholders)",
                $old_conversations
            )
        );
        
        // Then delete conversations
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_conversations WHERE id IN ($placeholders)",
                $old_conversations
            )
        );
        
        return $deleted;
    }
}