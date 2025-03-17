<?php
/**
 * Admin Settings for OpenAI Chatbot
 * 
 * Handles the plugin's admin settings page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Chatbot_Admin_Settings {
    /**
     * Constructor
     */
    public function __construct() {
        // Add menu page
        add_action('admin_menu', array($this, 'add_menu_page'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_menu_page(
            __('OpenAI Chatbot', 'openai-chatbot'),
            __('OpenAI Chatbot', 'openai-chatbot'),
            'manage_options',
            'openai-chatbot',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'openai-chatbot',
            __('Settings', 'openai-chatbot'),
            __('Settings', 'openai-chatbot'),
            'manage_options',
            'openai-chatbot',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'openai-chatbot',
            __('Conversations', 'openai-chatbot'),
            __('Conversations', 'openai-chatbot'),
            'manage_options',
            'openai-chatbot-conversations',
            array($this, 'render_conversations_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'openai_chatbot_settings',
            'openai_chatbot_settings',
            array($this, 'sanitize_settings')
        );
        
        // API Settings
        add_settings_section(
            'openai_chatbot_api_section',
            __('API Settings', 'openai-chatbot'),
            array($this, 'render_api_section'),
            'openai_chatbot_settings'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'openai-chatbot'),
            array($this, 'render_api_key_field'),
            'openai_chatbot_settings',
            'openai_chatbot_api_section'
        );
        
        add_settings_field(
            'model_name',
            __('Model Name', 'openai-chatbot'),
            array($this, 'render_model_name_field'),
            'openai_chatbot_settings',
            'openai_chatbot_api_section'
        );
        
        add_settings_field(
            'system_prompt',
            __('System Prompt', 'openai-chatbot'),
            array($this, 'render_system_prompt_field'),
            'openai_chatbot_settings',
            'openai_chatbot_api_section'
        );
        
        // Chat Settings
        add_settings_section(
            'openai_chatbot_chat_section',
            __('Chat Settings', 'openai-chatbot'),
            array($this, 'render_chat_section'),
            'openai_chatbot_settings'
        );
        
        add_settings_field(
            'chat_enabled',
            __('Enable Chat', 'openai-chatbot'),
            array($this, 'render_chat_enabled_field'),
            'openai_chatbot_settings',
            'openai_chatbot_chat_section'
        );
        
        add_settings_field(
            'human_takeover_enabled',
            __('Enable Human Takeover', 'openai-chatbot'),
            array($this, 'render_human_takeover_enabled_field'),
            'openai_chatbot_settings',
            'openai_chatbot_chat_section'
        );
        
        add_settings_field(
            'chat_title',
            __('Chat Title', 'openai-chatbot'),
            array($this, 'render_chat_title_field'),
            'openai_chatbot_settings',
            'openai_chatbot_chat_section'
        );
        
        add_settings_field(
            'welcome_message_logged_in',
            __('Welcome Message (Logged In)', 'openai-chatbot'),
            array($this, 'render_welcome_message_logged_in_field'),
            'openai_chatbot_settings',
            'openai_chatbot_chat_section'
        );
        
        add_settings_field(
            'welcome_message_guest',
            __('Welcome Message (Guest)', 'openai-chatbot'),
            array($this, 'render_welcome_message_guest_field'),
            'openai_chatbot_settings',
            'openai_chatbot_chat_section'
        );
        
        add_settings_field(
            'theme_color',
            __('Theme Color', 'openai-chatbot'),
            array($this, 'render_theme_color_field'),
            'openai_chatbot_settings',
            'openai_chatbot_chat_section'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // API Key
        $sanitized['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        
        // Model Name
        $sanitized['model_name'] = isset($input['model_name']) ? sanitize_text_field($input['model_name']) : 'ft:gpt-4o-mini-2024-07-18:hydra-gameshhop::B9c4goKH';
        
        // System Prompt
        $sanitized['system_prompt'] = isset($input['system_prompt']) ? sanitize_textarea_field($input['system_prompt']) : '';
        
        // Chat Enabled
        $sanitized['chat_enabled'] = isset($input['chat_enabled']) ? sanitize_text_field($input['chat_enabled']) : 'yes';
        
        // Human Takeover Enabled
        $sanitized['human_takeover_enabled'] = isset($input['human_takeover_enabled']) ? sanitize_text_field($input['human_takeover_enabled']) : 'yes';
        
        // Chat Title
        $sanitized['chat_title'] = isset($input['chat_title']) ? sanitize_text_field($input['chat_title']) : __('Chat with us', 'openai-chatbot');
        
        // Welcome Message for Logged In Users
        $sanitized['welcome_message_logged_in'] = isset($input['welcome_message_logged_in']) ? sanitize_textarea_field($input['welcome_message_logged_in']) : __('Hello {name}, how can I help you today?', 'openai-chatbot');
        
        // Welcome Message for Guests
        $sanitized['welcome_message_guest'] = isset($input['welcome_message_guest']) ? sanitize_textarea_field($input['welcome_message_guest']) : __('Hi, Do you need help?', 'openai-chatbot');
        
        // Theme Color
        $sanitized['theme_color'] = isset($input['theme_color']) ? sanitize_hex_color($input['theme_color']) : '#4a51bf';
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('openai_chatbot_settings');
                do_settings_sections('openai_chatbot_settings');
                submit_button(__('Save Settings', 'openai-chatbot'));
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render conversations page
     */
    public function render_conversations_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the conversations UI
        require_once OPENAI_CHATBOT_PLUGIN_DIR . 'admin/conversations.php';
    }
    
    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . __('Configure your OpenAI API settings here.', 'openai-chatbot') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $options = get_option('openai_chatbot_settings');
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        ?>
        <input type="text" id="api_key" name="openai_chatbot_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
        <p class="description"><?php _e('Your OpenAI API key. Keep this secure!', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render model name field
     */
    public function render_model_name_field() {
        $options = get_option('openai_chatbot_settings');
        $model_name = isset($options['model_name']) ? $options['model_name'] : 'ft:gpt-4o-mini-2024-07-18:hydra-gameshhop::B9c4goKH';
        
        ?>
        <input type="text" id="model_name" name="openai_chatbot_settings[model_name]" value="<?php echo esc_attr($model_name); ?>" class="regular-text" />
        <p class="description"><?php _e('The OpenAI model to use for chat completions.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render system prompt field
     */
    public function render_system_prompt_field() {
        $options = get_option('openai_chatbot_settings');
        $system_prompt = isset($options['system_prompt']) ? $options['system_prompt'] : 'You are a helpful and friendly chatbot for Gameheaven.net, You reply in Bangla language, even though user sens banglish/bangla response you will always reply in bangla, Within 40 words, If the response requires more than 40 words you will reply it in two response.';
        
        ?>
        <textarea id="system_prompt" name="openai_chatbot_settings[system_prompt]" rows="5" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
        <p class="description"><?php _e('The system prompt that defines the chatbot\'s personality and behavior.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render chat section
     */
    public function render_chat_section() {
        echo '<p>' . __('Configure the chat interface settings.', 'openai-chatbot') . '</p>';
    }
    
    /**
     * Render chat enabled field
     */
    public function render_chat_enabled_field() {
        $options = get_option('openai_chatbot_settings');
        $chat_enabled = isset($options['chat_enabled']) ? $options['chat_enabled'] : 'yes';
        
        ?>
        <select id="chat_enabled" name="openai_chatbot_settings[chat_enabled]">
            <option value="yes" <?php selected($chat_enabled, 'yes'); ?>><?php _e('Yes', 'openai-chatbot'); ?></option>
            <option value="no" <?php selected($chat_enabled, 'no'); ?>><?php _e('No', 'openai-chatbot'); ?></option>
        </select>
        <p class="description"><?php _e('Enable or disable the chat interface on your website.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render human takeover enabled field
     */
    public function render_human_takeover_enabled_field() {
        $options = get_option('openai_chatbot_settings');
        $human_takeover_enabled = isset($options['human_takeover_enabled']) ? $options['human_takeover_enabled'] : 'yes';
        
        ?>
        <select id="human_takeover_enabled" name="openai_chatbot_settings[human_takeover_enabled]">
            <option value="yes" <?php selected($human_takeover_enabled, 'yes'); ?>><?php _e('Yes', 'openai-chatbot'); ?></option>
            <option value="no" <?php selected($human_takeover_enabled, 'no'); ?>><?php _e('No', 'openai-chatbot'); ?></option>
        </select>
        <p class="description"><?php _e('Enable or disable the ability for humans to take over conversations.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render chat title field
     */
    public function render_chat_title_field() {
        $options = get_option('openai_chatbot_settings');
        $chat_title = isset($options['chat_title']) ? $options['chat_title'] : __('Chat with us', 'openai-chatbot');
        
        ?>
        <input type="text" id="chat_title" name="openai_chatbot_settings[chat_title]" value="<?php echo esc_attr($chat_title); ?>" class="regular-text" />
        <p class="description"><?php _e('The title displayed on the chat window.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render welcome message for logged in users field
     */
    public function render_welcome_message_logged_in_field() {
        $options = get_option('openai_chatbot_settings');
        $welcome_message_logged_in = isset($options['welcome_message_logged_in']) ? $options['welcome_message_logged_in'] : __('Hello {name}, how can I help you today?', 'openai-chatbot');
        
        ?>
        <textarea id="welcome_message_logged_in" name="openai_chatbot_settings[welcome_message_logged_in]" rows="3" class="large-text"><?php echo esc_textarea($welcome_message_logged_in); ?></textarea>
        <p class="description"><?php _e('The welcome message displayed for logged-in users. Use {name} to include the user\'s name.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render welcome message for guests field
     */
    public function render_welcome_message_guest_field() {
        $options = get_option('openai_chatbot_settings');
        $welcome_message_guest = isset($options['welcome_message_guest']) ? $options['welcome_message_guest'] : __('Hi, Do you need help?', 'openai-chatbot');
        
        ?>
        <textarea id="welcome_message_guest" name="openai_chatbot_settings[welcome_message_guest]" rows="3" class="large-text"><?php echo esc_textarea($welcome_message_guest); ?></textarea>
        <p class="description"><?php _e('The welcome message displayed for guest users.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Render theme color field
     */
    public function render_theme_color_field() {
        $options = get_option('openai_chatbot_settings');
        $theme_color = isset($options['theme_color']) ? $options['theme_color'] : '#4a51bf';
        
        ?>
        <input type="color" id="theme_color" name="openai_chatbot_settings[theme_color]" value="<?php echo esc_attr($theme_color); ?>" />
        <p class="description"><?php _e('The main color theme for the chat interface.', 'openai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'openai-chatbot') === false) {
            return;
        }
        
        // Bootstrap CSS
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
        
        // Admin CSS
        wp_enqueue_style('openai-chatbot-admin-css', OPENAI_CHATBOT_PLUGIN_URL . 'assets/css/admin.css', array('bootstrap-css'), OPENAI_CHATBOT_VERSION);
        
        // Bootstrap Bundle JS (includes Popper)
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
        
        // Admin JS
        wp_enqueue_script('openai-chatbot-admin-js', OPENAI_CHATBOT_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'bootstrap-js'), OPENAI_CHATBOT_VERSION, true);
        
        // Color Picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Localize script
        wp_localize_script('openai-chatbot-admin-js', 'openaiChatbotAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('openai-chatbot-admin-nonce')
        ));
    }
}

// Initialize the class
$openai_chatbot_admin_settings = new OpenAI_Chatbot_Admin_Settings();