<?php
/**
 * AI Chatbot Pro Voice Commands
 * Advanced voice command processing for Pro version
 *
 * @package AI_Website_Chatbot
 * @subpackage Pro\Audio
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_Chatbot_Pro_Voice_Commands class
 * Handles voice command recognition and execution
 */
class AI_Chatbot_Pro_Voice_Commands {

    /**
     * Command registry
     */
    private $commands = array();

    /**
     * Command aliases
     */
    private $aliases = array();

    /**
     * Command statistics
     */
    private $stats = array();

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_default_commands'));
        add_action('wp_ajax_ai_chatbot_voice_command', array($this, 'process_voice_command'));
        add_action('wp_ajax_nopriv_ai_chatbot_voice_command', array($this, 'process_voice_command'));
        add_action('wp_ajax_ai_chatbot_register_custom_command', array($this, 'register_custom_command'));
        add_filter('ai_chatbot_voice_input_processed', array($this, 'check_for_commands'), 5, 2);
        
        // Load command statistics
        $this->stats = get_option('ai_chatbot_voice_command_stats', array());
    }

    /**
     * Register default voice commands
     */
    public function register_default_commands() {
        // Basic navigation commands
        $this->register_command('clear_chat', array(
            'phrases' => array('clear chat', 'clear conversation', 'start over', 'new conversation'),
            'callback' => array($this, 'clear_chat_command'),
            'description' => __('Clear the current conversation', 'ai-website-chatbot'),
            'confirmation_required' => true,
            'enabled' => get_option('ai_chatbot_voice_cmd_clear_enabled', true)
        ));

        $this->register_command('close_chat', array(
            'phrases' => array('close chat', 'end conversation', 'goodbye', 'bye'),
            'callback' => array($this, 'close_chat_command'),
            'description' => __('Close the chatbot', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_close_enabled', true)
        ));

        $this->register_command('minimize_chat', array(
            'phrases' => array('minimize chat', 'minimize window', 'hide chat'),
            'callback' => array($this, 'minimize_chat_command'),
            'description' => __('Minimize the chat window', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_minimize_enabled', true)
        ));

        // Audio control commands
        $this->register_command('toggle_audio_mode', array(
            'phrases' => array('toggle audio mode', 'enable audio mode', 'disable audio mode', 'audio mode'),
            'callback' => array($this, 'toggle_audio_mode_command'),
            'description' => __('Toggle hands-free audio mode', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_audio_toggle_enabled', true)
        ));

        $this->register_command('toggle_voice_output', array(
            'phrases' => array('toggle voice output', 'enable voice', 'disable voice', 'mute voice'),
            'callback' => array($this, 'toggle_voice_output_command'),
            'description' => __('Toggle text-to-speech output', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_tts_toggle_enabled', true)
        ));

        // Content commands
        $this->register_command('repeat_last', array(
            'phrases' => array('repeat that', 'say that again', 'repeat last message'),
            'callback' => array($this, 'repeat_last_command'),
            'description' => __('Repeat the last AI response', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_repeat_enabled', true)
        ));

        $this->register_command('show_help', array(
            'phrases' => array('help', 'what can you do', 'show commands', 'voice commands'),
            'callback' => array($this, 'show_help_command'),
            'description' => __('Show available voice commands', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_help_enabled', true)
        ));

        // Conversation control
        $this->register_command('save_conversation', array(
            'phrases' => array('save conversation', 'export chat', 'download conversation'),
            'callback' => array($this, 'save_conversation_command'),
            'description' => __('Save the current conversation', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_save_enabled', true)
        ));

        $this->register_command('send_feedback', array(
            'phrases' => array('send feedback', 'report issue', 'leave feedback'),
            'callback' => array($this, 'send_feedback_command'),
            'description' => __('Send feedback about the chatbot', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_feedback_enabled', true)
        ));

        // Quick actions
        $this->register_command('contact_support', array(
            'phrases' => array('contact support', 'talk to human', 'human agent', 'live chat'),
            'callback' => array($this, 'contact_support_command'),
            'description' => __('Request human support', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_support_enabled', true)
        ));

        $this->register_command('show_business_hours', array(
            'phrases' => array('business hours', 'opening hours', 'when are you open'),
            'callback' => array($this, 'show_business_hours_command'),
            'description' => __('Show business hours', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_hours_enabled', true)
        ));

        // Settings commands
        $this->register_command('audio_settings', array(
            'phrases' => array('audio settings', 'voice settings', 'sound settings'),
            'callback' => array($this, 'audio_settings_command'),
            'description' => __('Open audio settings', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_settings_enabled', true)
        ));

        // Language commands
        $this->register_command('change_language', array(
            'phrases' => array('change language', 'switch language', 'language settings'),
            'callback' => array($this, 'change_language_command'),
            'description' => __('Change chat language', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => get_option('ai_chatbot_voice_cmd_language_enabled', true),
            'parameters' => array('language')
        ));

        // Custom command placeholder
        $this->register_command('custom_action', array(
            'phrases' => array(),
            'callback' => array($this, 'execute_custom_command'),
            'description' => __('Execute custom command', 'ai-website-chatbot'),
            'confirmation_required' => false,
            'enabled' => false,
            'custom' => true
        ));

        // Apply filters to allow custom commands
        $this->commands = apply_filters('ai_chatbot_voice_commands', $this->commands);
    }

    /**
     * Register a voice command
     */
    public function register_command($command_id, $command_data) {
        $this->commands[$command_id] = wp_parse_args($command_data, array(
            'phrases' => array(),
            'callback' => null,
            'description' => '',
            'confirmation_required' => false,
            'enabled' => true,
            'parameters' => array(),
            'custom' => false
        ));

        // Register aliases
        foreach ($command_data['phrases'] as $phrase) {
            $this->aliases[strtolower($phrase)] = $command_id;
        }
    }

    /**
     * Check voice input for commands
     */
    public function check_for_commands($input, $context = array()) {
        if (!get_option('ai_chatbot_voice_commands_enabled', false)) {
            return $input;
        }

        $detected_command = $this->detect_command($input);
        
        if ($detected_command) {
            $this->execute_command($detected_command['command'], $detected_command['parameters']);
            return null; // Command processed, don't send to AI
        }

        return $input;
    }

    /**
     * Detect command in voice input
     */
    public function detect_command($input) {
        $input_lower = strtolower(trim($input));
        
        // Direct phrase matching
        if (isset($this->aliases[$input_lower])) {
            return array(
                'command' => $this->aliases[$input_lower],
                'parameters' => array(),
                'confidence' => 1.0
            );
        }

        // Fuzzy matching for partial phrases
        foreach ($this->aliases as $phrase => $command_id) {
            if (!$this->commands[$command_id]['enabled']) {
                continue;
            }

            $similarity = $this->calculate_phrase_similarity($input_lower, $phrase);
            
            if ($similarity > 0.7) { // 70% similarity threshold
                return array(
                    'command' => $command_id,
                    'parameters' => $this->extract_parameters($input_lower, $phrase),
                    'confidence' => $similarity
                );
            }
        }

        return null;
    }

    /**
     * Calculate phrase similarity
     */
    private function calculate_phrase_similarity($input, $phrase) {
        // Use Levenshtein distance for similarity
        $max_length = max(strlen($input), strlen($phrase));
        $distance = levenshtein($input, $phrase);
        return 1 - ($distance / $max_length);
    }

    /**
     * Extract parameters from voice input
     */
    private function extract_parameters($input, $phrase) {
        // Simple parameter extraction
        $parameters = array();
        
        // Remove the command phrase from input to get parameters
        $remaining = str_replace($phrase, '', $input);
        $remaining = trim($remaining);
        
        if (!empty($remaining)) {
            $parameters['text'] = $remaining;
        }
        
        return $parameters;
    }

    /**
     * Execute command
     */
    public function execute_command($command_id, $parameters = array()) {
        if (!isset($this->commands[$command_id]) || !$this->commands[$command_id]['enabled']) {
            return array(
                'success' => false,
                'message' => __('Command not found or disabled', 'ai-website-chatbot')
            );
        }

        $command = $this->commands[$command_id];
        
        // Check if confirmation is required
        if ($command['confirmation_required']) {
            return array(
                'success' => false,
                'requires_confirmation' => true,
                'command' => $command_id,
                'parameters' => $parameters,
                'message' => sprintf(__('Are you sure you want to %s?', 'ai-website-chatbot'), $command['description'])
            );
        }

        // Execute the command
        if (is_callable($command['callback'])) {
            $result = call_user_func($command['callback'], $parameters);
            
            // Track command usage
            $this->track_command_usage($command_id);
            
            return $result;
        }

        return array(
            'success' => false,
            'message' => __('Command callback not found', 'ai-website-chatbot')
        );
    }

    /**
     * Track command usage statistics
     */
    private function track_command_usage($command_id) {
        if (!isset($this->stats[$command_id])) {
            $this->stats[$command_id] = array(
                'usage_count' => 0,
                'last_used' => null
            );
        }

        $this->stats[$command_id]['usage_count']++;
        $this->stats[$command_id]['last_used'] = current_time('mysql');
        
        update_option('ai_chatbot_voice_command_stats', $this->stats);
    }

    // ==========================================
    // COMMAND IMPLEMENTATIONS
    // ==========================================

    /**
     * Clear chat command
     */
    public function clear_chat_command($parameters = array()) {
        return array(
            'action' => 'clear_chat',
            'message' => __('Chat cleared successfully', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Close chat command
     */
    public function close_chat_command($parameters = array()) {
        return array(
            'action' => 'close_chat',
            'message' => __('Goodbye! Thanks for chatting.', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Minimize chat command
     */
    public function minimize_chat_command($parameters = array()) {
        return array(
            'action' => 'minimize_chat',
            'message' => __('Chat minimized', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Toggle audio mode command
     */
    public function toggle_audio_mode_command($parameters = array()) {
        $current_state = get_user_meta(get_current_user_id(), 'ai_chatbot_audio_mode_active', true);
        $new_state = !$current_state;
        
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'ai_chatbot_audio_mode_active', $new_state);
        }

        return array(
            'action' => 'toggle_audio_mode',
            'state' => $new_state,
            'message' => $new_state ? 
                __('Audio mode enabled', 'ai-website-chatbot') : 
                __('Audio mode disabled', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Toggle voice output command
     */
    public function toggle_voice_output_command($parameters = array()) {
        return array(
            'action' => 'toggle_voice_output',
            'message' => __('Voice output toggled', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Repeat last command
     */
    public function repeat_last_command($parameters = array()) {
        return array(
            'action' => 'repeat_last',
            'message' => __('Repeating last message', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Show help command
     */
    public function show_help_command($parameters = array()) {
        $available_commands = array_filter($this->commands, function($cmd) {
            return $cmd['enabled'] && !empty($cmd['phrases']);
        });

        $help_text = __('Available voice commands:', 'ai-website-chatbot') . "\n\n";
        
        foreach ($available_commands as $cmd_id => $cmd) {
            $help_text .= "• " . implode(', ', $cmd['phrases']) . " - " . $cmd['description'] . "\n";
        }

        return array(
            'action' => 'show_help',
            'message' => $help_text,
            'commands' => $available_commands,
            'success' => true
        );
    }

    /**
     * Save conversation command
     */
    public function save_conversation_command($parameters = array()) {
        return array(
            'action' => 'save_conversation',
            'message' => __('Conversation saved', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Send feedback command
     */
    public function send_feedback_command($parameters = array()) {
        return array(
            'action' => 'send_feedback',
            'message' => __('Feedback form opened', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Contact support command
     */
    public function contact_support_command($parameters = array()) {
        $contact_info = get_option('ai_chatbot_contact_email', '');
        
        return array(
            'action' => 'contact_support',
            'message' => !empty($contact_info) ? 
                sprintf(__('Contact support at: %s', 'ai-website-chatbot'), $contact_info) :
                __('Support contact information not available', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Show business hours command
     */
    public function show_business_hours_command($parameters = array()) {
        $business_hours = get_option('ai_chatbot_business_hours', '');
        
        return array(
            'action' => 'show_business_hours',
            'message' => !empty($business_hours) ? 
                sprintf(__('Business hours: %s', 'ai-website-chatbot'), $business_hours) :
                __('Business hours not configured', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Audio settings command
     */
    public function audio_settings_command($parameters = array()) {
        return array(
            'action' => 'audio_settings',
            'message' => __('Audio settings opened', 'ai-website-chatbot'),
            'success' => true
        );
    }

    /**
     * Change language command
     */
    public function change_language_command($parameters = array()) {
        return array(
            'action' => 'change_language',
            'message' => __('Language settings opened', 'ai-website-chatbot'),
            'parameters' => $parameters,
            'success' => true
        );
    }

    // ==========================================
    // AJAX HANDLERS
    // ==========================================

    /**
     * Process voice command via AJAX
     */
    public function process_voice_command() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'ai-website-chatbot')));
        }

        $command_text = sanitize_text_field($_POST['command'] ?? '');
        $context = $_POST['context'] ?? array();

        if (empty($command_text)) {
            wp_send_json_error(array('message' => __('No command provided', 'ai-website-chatbot')));
        }

        $detected_command = $this->detect_command($command_text);
        
        if (!$detected_command) {
            wp_send_json_error(array('message' => __('Command not recognized', 'ai-website-chatbot')));
        }

        $result = $this->execute_command($detected_command['command'], $detected_command['parameters']);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Register custom command via AJAX
     */
    public function register_custom_command() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-website-chatbot')));
        }

        $command_data = $_POST['command_data'] ?? array();
        $command_id = sanitize_key($command_data['id'] ?? '');

        if (empty($command_id)) {
            wp_send_json_error(array('message' => __('Command ID required', 'ai-website-chatbot')));
        }

        $this->commands[$command_id] = array(
            'phrases' => array_map('sanitize_text_field', $command_data['phrases'] ?? array()),
            'callback' => array($this, 'execute_custom_command'),
            'description' => sanitize_text_field($command_data['description'] ?? ''),
            'confirmation_required' => (bool) ($command_data['confirmation_required'] ?? false),
            'success_message' => sanitize_text_field($command_data['success_message'] ?? __('Custom command executed', 'ai-website-chatbot')),
            'enabled' => true,
            'custom' => true
        );

        wp_send_json_success(array('message' => __('Custom command registered', 'ai-website-chatbot')));
    }

    /**
     * Execute custom command
     */
    public function execute_custom_command($parameters = array()) {
        // Custom commands can be extended with user-defined actions
        return array(
            'action' => 'custom_command',
            'message' => __('Custom command executed', 'ai-website-chatbot'),
            'parameters' => $parameters,
            'success' => true
        );
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Get available commands
     */
    public function get_available_commands() {
        return array_filter($this->commands, function($command) {
            return $command['enabled'];
        });
    }

    /**
     * Get command statistics
     */
    public function get_command_statistics() {
        return $this->stats;
    }

    /**
     * Get most used commands
     */
    public function get_popular_commands($limit = 5) {
        uasort($this->stats, function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        return array_slice($this->stats, 0, $limit, true);
    }

    /**
     * Reset command statistics
     */
    public function reset_statistics() {
        $this->stats = array();
        update_option('ai_chatbot_voice_command_stats', $this->stats);
    }
}