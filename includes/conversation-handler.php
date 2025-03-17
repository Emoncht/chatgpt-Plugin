<?php
/**
 * Conversation Handler for OpenAI Chatbot
 * 
 * Manages the conversation flow, including AI and human interactions with multiple response support
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Chatbot_Conversation_Handler {
    /**
     * Database handler instance
     */
    private $db_handler;
    
    /**
     * API handler instance
     */
    private $api_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db_handler = new OpenAI_Chatbot_Database_Handler();
        $this->api_handler = new OpenAI_Chatbot_API_Handler();
    }
    
    /**
     * Process a new message
     * 
     * @param string $message The user message
     * @param string $conversation_id The conversation ID (optional)
     * @return array The response data
     */
    public function process_message($message, $conversation_id = '') {
        // Create a new conversation if none exists
        if (empty($conversation_id)) {
            $conversation_id = $this->db_handler->create_conversation();
        } else {
            // Check if conversation exists
            $conversation = $this->db_handler->get_conversation($conversation_id);
            if (!$conversation) {
                $conversation_id = $this->db_handler->create_conversation();
            }
        }
        
        // Add message to database
        $message_id = $this->db_handler->add_message($conversation_id, $message);
        
        // Check if human takeover is active
        $conversation = $this->db_handler->get_conversation($conversation_id);
        
        if ($conversation && $conversation->is_human_takeover) {
            // Just store the message and return, admin will respond
            return array(
                'conversation_id' => $conversation_id,
                'message_id' => $message_id,
                'response' => null,
                'status' => 'waiting_for_human',
                'is_human' => true
            );
        }
        
        // Get conversation history
        $conversation_messages = $this->db_handler->get_conversation_messages($conversation_id);
        
        // Send to OpenAI
        $responses = $this->api_handler->send_message($message, $conversation_messages);
        
        if (is_wp_error($responses)) {
            // Return error
            return array(
                'conversation_id' => $conversation_id,
                'message_id' => $message_id,
                'response' => null,
                'status' => 'error',
                'error_message' => $responses->get_error_message()
            );
        }
        
        // Check if we have multiple responses
        if (is_array($responses) && count($responses) > 1) {
            // Store the first response
            $this->db_handler->add_response($message_id, $responses[0]);
            
            // Store additional responses as separate messages
            $additionalResponses = [];
            for ($i = 1; $i < count($responses); $i++) {
                $additional_message_id = $this->db_handler->add_system_message($conversation_id, $responses[$i]);
                $additionalResponses[] = [
                    'message_id' => $additional_message_id,
                    'content' => $responses[$i]
                ];
            }
            
            // Return success with multiple responses
            return array(
                'conversation_id' => $conversation_id,
                'message_id' => $message_id,
                'response' => $responses[0],
                'additional_responses' => $additionalResponses,
                'status' => 'success',
                'is_human' => false,
                'is_multiple' => true
            );
        } else {
            // Single response (either a string or single-item array)
            $response = is_array($responses) ? $responses[0] : $responses;
            
            // Store the response
            $this->db_handler->add_response($message_id, $response);
            
            // Return success
            return array(
                'conversation_id' => $conversation_id,
                'message_id' => $message_id,
                'response' => $response,
                'status' => 'success',
                'is_human' => false,
                'is_multiple' => false
            );
        }
    }
    
    /**
     * Human takeover for a conversation
     * 
     * @param string $conversation_id The conversation ID
     * @return bool Success or failure
     */
    public function human_takeover($conversation_id) {
        return $this->db_handler->set_human_takeover($conversation_id, true);
    }
    
    /**
     * Return a conversation to AI control
     * 
     * @param string $conversation_id The conversation ID
     * @return bool Success or failure
     */
    public function return_to_ai($conversation_id) {
        return $this->db_handler->set_human_takeover($conversation_id, false);
    }
    
    /**
     * Add a human response to a conversation
     * 
     * @param string $conversation_id The conversation ID
     * @param string $message The human response
     * @return bool Success or failure
     */
    public function add_human_response($conversation_id, $message) {
        // Get the last message
        $last_message = $this->db_handler->get_last_message($conversation_id);
        
        if ($last_message) {
            // Add the response to the last message
            return $this->db_handler->add_response($last_message->id, $message, true);
        }
        
        return false;
    }
    
    /**
     * Close a conversation
     * 
     * @param string $conversation_id The conversation ID
     * @return bool Success or failure
     */
    public function close_conversation($conversation_id) {
        return $this->db_handler->close_conversation($conversation_id);
    }
    
    /**
     * Get conversation history
     * 
     * @param string $conversation_id The conversation ID
     * @return array The conversation messages
     */
    public function get_conversation_history($conversation_id) {
        $messages = $this->db_handler->get_conversation_messages($conversation_id);
        $conversation = $this->db_handler->get_conversation($conversation_id);
        
        return array(
            'conversation' => $conversation,
            'messages' => $messages
        );
    }
}