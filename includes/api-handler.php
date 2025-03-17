<?php
/**
 * API Handler for OpenAI Chatbot
 * 
 * Handles all communication with the OpenAI API including multiple responses
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Chatbot_API_Handler {
    /**
     * The OpenAI API endpoint
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * The OpenAI API key
     */
    private $api_key;
    
    /**
     * The OpenAI model name
     */
    private $model_name;
    
    /**
     * The system prompt for the AI
     */
    private $system_prompt;
    
    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('openai_chatbot_settings');
        
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $this->model_name = isset($options['model_name']) ? $options['model_name'] : 'ft:gpt-4o-mini-2024-07-18:hydra-gameshhop::B9c4goKH';
        $this->system_prompt = isset($options['system_prompt']) ? $options['system_prompt'] : 'You are a helpful and friendly chatbot for Gameheaven.net, You reply in Bangla language, even though user sens banglish/bangla response you will always reply in bangla, Within 40 words, If the response requires more than 40 words you will reply it in two response.';
    }
    
    /**
     * Send a message to the OpenAI API
     * 
     * @param string $message The user message
     * @param array $conversation Previous conversation messages
     * @return array|WP_Error The AI response(s) or an error
     */
    public function send_message($message, $conversation = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', __('OpenAI API key is not configured', 'openai-chatbot'));
        }
        
        // Prepare the messages for the API
        $messages = array(
            array(
                'role' => 'system',
                'content' => $this->system_prompt
            )
        );
        
        // Add conversation history (limited to last 10 messages to avoid token limits)
        $history_limit = 10;
        $conversation = array_slice($conversation, -$history_limit, $history_limit, true);
        
        foreach ($conversation as $item) {
            // Add user message
            $messages[] = array(
                'role' => 'user',
                'content' => $item->message
            );
            
            // Add AI response if available
            if (!empty($item->response)) {
                $messages[] = array(
                    'role' => 'assistant',
                    'content' => $item->response
                );
            }
        }
        
        // Add the current message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Prepare the request
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $this->model_name,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7
            )),
            'timeout' => 30
        );
        
        // Send the request
        $response = wp_remote_post($this->api_endpoint, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error(
                'invalid_response',
                isset($data['error']['message']) ? $data['error']['message'] : __('Invalid response from OpenAI', 'openai-chatbot')
            );
        }
        
        // Get the full response
        $fullResponse = trim($data['choices'][0]['message']['content']);
        
        // Process for multiple responses if needed
        return $this->process_multiple_responses($fullResponse);
    }
    
    /**
     * Process a response to handle multiple message parts
     * 
     * @param string $response The full response from OpenAI
     * @return array An array of response parts
     */
    private function process_multiple_responses($response) {
        // Check if the response has natural breaks (like paragraphs)
        if (strpos($response, "\n\n") !== false) {
            // Split by double newlines (paragraphs)
            $parts = explode("\n\n", $response);
            $parts = array_filter($parts, function($part) {
                return trim($part) !== '';
            });
            
            // If we have multiple substantial parts, return them as separate messages
            if (count($parts) > 1) {
                return array_values($parts);
            }
        }
        
        // Check if we should split based on the word count (over 40 words)
        $words = str_word_count($response, 0, 'àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿŠŽšžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßŠŽšžŸ');
        
        if ($words > 40) {
            // Find a good breaking point (sentence end) close to 40 words
            $sentences = preg_split('/(?<=[.!?।॥])\s+/', $response, -1, PREG_SPLIT_NO_EMPTY);
            
            if (count($sentences) > 1) {
                $parts = [];
                $currentPart = '';
                $currentWords = 0;
                
                foreach ($sentences as $sentence) {
                    $sentenceWords = str_word_count($sentence, 0, 'àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿŠŽšžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßŠŽšžŸ');
                    
                    // If adding this sentence would exceed ~40 words, create a new part
                    if ($currentWords + $sentenceWords > 40 && $currentPart !== '') {
                        $parts[] = trim($currentPart);
                        $currentPart = $sentence;
                        $currentWords = $sentenceWords;
                    } else {
                        $currentPart .= ($currentPart ? ' ' : '') . $sentence;
                        $currentWords += $sentenceWords;
                    }
                }
                
                // Add the last part if not empty
                if (trim($currentPart) !== '') {
                    $parts[] = trim($currentPart);
                }
                
                if (count($parts) > 1) {
                    return $parts;
                }
            }
        }
        
        // Default case: return the full response as a single message
        return [$response];
    }
}