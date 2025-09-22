<?php
/**
 * AI Chatbot Pro Audio Mode
 * Hands-free conversation mode for Pro version
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
 * AI_Chatbot_Pro_Audio_Mode class
 * Manages hands-free audio conversation mode
 */
class AI_Chatbot_Pro_Audio_Mode {

    /**
     * Audio session manager
     */
    private $session_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->session_manager = new AI_Chatbot_Audio_Session_Manager();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_audio_mode_scripts'));
        add_action('wp_ajax_ai_chatbot_audio_mode_toggle', array($this, 'toggle_audio_mode'));
        add_action('wp_ajax_nopriv_ai_chatbot_audio_mode_toggle', array($this, 'toggle_audio_mode'));
        add_action('wp_ajax_ai_chatbot_audio_mode_status', array($this, 'get_audio_mode_status'));
        add_action('wp_ajax_nopriv_ai_chatbot_audio_mode_status', array($this, 'get_audio_mode_status'));
        add_filter('ai_chatbot_response_data', array($this, 'enhance_for_audio_mode'), 15, 2);
    }

    /**
     * Enqueue audio mode scripts
     */
    public function enqueue_audio_mode_scripts() {
        if (!get_option('ai_chatbot_audio_mode_enabled', false)) {
            return;
        }

        wp_enqueue_script(
            'ai-chatbot-pro-audio-mode',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/audio-mode.js',
            array('ai-chatbot-pro-audio-core'),
            AI_CHATBOT_VERSION,
            true
        );

        // Audio mode configuration
        wp_localize_script('ai-chatbot-pro-audio-mode', 'aiChatbotAudioMode', array(
            'config' => $this->get_audio_mode_configuration(),
            'session' => $this->session_manager->get_current_session(),
            'states' => $this->get_audio_mode_states()
        ));
    }

    /**
     * Get audio mode configuration
     */
    private function get_audio_mode_configuration() {
        return array(
            'enabled' => get_option('ai_chatbot_audio_mode_enabled', false),
            'auto_listen_after_response' => get_option('ai_chatbot_audio_auto_listen', true),
            'silence_detection' => get_option('ai_chatbot_audio_silence_detection', true),
            'conversation_timeout' => get_option('ai_chatbot_audio_timeout', 30),
            'max_continuous_time' => get_option('ai_chatbot_audio_max_time', 300), // 5 minutes
            'voice_activation_phrase' => get_option('ai_chatbot_audio_activation_phrase', 'hey assistant'),
            'deactivation_phrase' => get_option('ai_chatbot_audio_deactivation_phrase', 'stop listening'),
            
            // Advanced settings
            'silence_threshold' => get_option('ai_chatbot_audio_silence_threshold', 2.0), // seconds
            'background_listening' => get_option('ai_chatbot_audio_background_listening', false),
            'wake_word_detection' => get_option('ai_chatbot_audio_wake_word', false),
            'auto_pause_on_page_blur' => get_option('ai_chatbot_audio_auto_pause', true),
            'energy_threshold' => get_option('ai_chatbot_audio_energy_threshold', 300),
            
            // User experience
            'confirmation_sounds' => get_option('ai_chatbot_audio_confirmation_sounds', true),
            'status_announcements' => get_option('ai_chatbot_audio_status_announcements', true),
            'visual_indicators' => get_option('ai_chatbot_audio_visual_indicators', true),
            'conversation_memory' => get_option('ai_chatbot_audio_conversation_memory', true)
        );
    }

    /**
     * Get audio mode states
     */
    private function get_audio_mode_states() {
        return array(
            'INACTIVE' => array(
                'name' => __('Inactive', 'ai-website-chatbot'),
                'description' => __('Audio mode is off', 'ai-website-chatbot'),
                'color' => '#6c757d'
            ),
            'ACTIVATING' => array(
                'name' => __('Activating', 'ai-website-chatbot'),
                'description' => __('Starting audio mode', 'ai-website-chatbot'),
                'color' => '#ffc107'
            ),
            'LISTENING' => array(
                'name' => __('Listening', 'ai-website-chatbot'),
                'description' => __('Ready for voice input', 'ai-website-chatbot'),
                'color' => '#28a745'
            ),
            'PROCESSING' => array(
                'name' => __('Processing', 'ai-website-chatbot'),
                'description' => __('Understanding your message', 'ai-website-chatbot'),
                'color' => '#17a2b8'
            ),
            'SPEAKING' => array(
                'name' => __('Speaking', 'ai-website-chatbot'),
                'description' => __('AI is responding', 'ai-website-chatbot'),
                'color' => '#007bff'
            ),
            'WAITING' => array(
                'name' => __('Waiting', 'ai-website-chatbot'),
                'description' => __('Waiting for your response', 'ai-website-chatbot'),
                'color' => '#6f42c1'
            ),
            'ERROR' => array(
                'name' => __('Error', 'ai-website-chatbot'),
                'description' => __('Audio mode error', 'ai-website-chatbot'),
                'color' => '#dc3545'
            ),
            'PAUSED' => array(
                'name' => __('Paused', 'ai-website-chatbot'),
                'description' => __('Audio mode paused', 'ai-website-chatbot'),
                'color' => '#fd7e14'
            )
        );
    }

    /**
     * Toggle audio mode
     */
    public function toggle_audio_mode() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $action = sanitize_text_field($_POST['action_type'] ?? 'toggle');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $user_id = get_current_user_id();

        switch ($action) {
            case 'activate':
                $result = $this->activate_audio_mode($session_id, $user_id);
                break;
            case 'deactivate':
                $result = $this->deactivate_audio_mode($session_id, $user_id);
                break;
            case 'pause':
                $result = $this->pause_audio_mode($session_id, $user_id);
                break;
            case 'resume':
                $result = $this->resume_audio_mode($session_id, $user_id);
                break;
            default:
                $result = $this->toggle_audio_mode_state($session_id, $user_id);
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Activate audio mode
     */
    private function activate_audio_mode($session_id, $user_id) {
        // Check if user can activate audio mode
        if (!$this->can_user_use_audio_mode($user_id)) {
            return array(
                'success' => false,
                'message' => __('Audio mode not available for your account.', 'ai-website-chatbot')
            );
        }

        // Create or update audio session
        $audio_session = $this->session_manager->create_audio_session($session_id, $user_id);
        
        if (!$audio_session) {
            return array(
                'success' => false,
                'message' => __('Failed to create audio session.', 'ai-website-chatbot')
            );
        }

        // Set initial state
        $this->session_manager->update_session_state($audio_session['id'], 'ACTIVATING');

        // Log activation
        $this->log_audio_mode_event('activate', $session_id, $user_id);

        return array(
            'success' => true,
            'message' => __('Audio mode activated.', 'ai-website-chatbot'),
            'session' => $audio_session,
            'state' => 'ACTIVATING',
            'instructions' => $this->get_activation_instructions()
        );
    }

    /**
     * Deactivate audio mode
     */
    private function deactivate_audio_mode($session_id, $user_id) {
        $audio_session = $this->session_manager->get_audio_session($session_id);
        
        if (!$audio_session) {
            return array(
                'success' => false,
                'message' => __('No active audio session found.', 'ai-website-chatbot')
            );
        }

        // Update session state
        $this->session_manager->update_session_state($audio_session['id'], 'INACTIVE');
        
        // Save session statistics
        $this->session_manager->close_audio_session($audio_session['id']);

        // Log deactivation
        $this->log_audio_mode_event('deactivate', $session_id, $user_id);

        return array(
            'success' => true,
            'message' => __('Audio mode deactivated.', 'ai-website-chatbot'),
            'state' => 'INACTIVE',
            'session_stats' => $this->session_manager->get_session_statistics($audio_session['id'])
        );
    }

    /**
     * Pause audio mode
     */
    private function pause_audio_mode($session_id, $user_id) {
        $audio_session = $this->session_manager->get_audio_session($session_id);
        
        if (!$audio_session || $audio_session['state'] === 'INACTIVE') {
            return array(
                'success' => false,
                'message' => __('No active audio session to pause.', 'ai-website-chatbot')
            );
        }

        $this->session_manager->update_session_state($audio_session['id'], 'PAUSED');
        $this->log_audio_mode_event('pause', $session_id, $user_id);

        return array(
            'success' => true,
            'message' => __('Audio mode paused.', 'ai-website-chatbot'),
            'state' => 'PAUSED'
        );
    }

    /**
     * Resume audio mode
     */
    private function resume_audio_mode($session_id, $user_id) {
        $audio_session = $this->session_manager->get_audio_session($session_id);
        
        if (!$audio_session || $audio_session['state'] !== 'PAUSED') {
            return array(
                'success' => false,
                'message' => __('No paused audio session to resume.', 'ai-website-chatbot')
            );
        }

        $this->session_manager->update_session_state($audio_session['id'], 'LISTENING');
        $this->log_audio_mode_event('resume', $session_id, $user_id);

        return array(
            'success' => true,
            'message' => __('Audio mode resumed.', 'ai-website-chatbot'),
            'state' => 'LISTENING'
        );
    }

    /**
     * Toggle audio mode state
     */
    private function toggle_audio_mode_state($session_id, $user_id) {
        $audio_session = $this->session_manager->get_audio_session($session_id);
        
        if (!$audio_session || $audio_session['state'] === 'INACTIVE') {
            return $this->activate_audio_mode($session_id, $user_id);
        } else {
            return $this->deactivate_audio_mode($session_id, $user_id);
        }
    }

    /**
     * Get audio mode status
     */
    public function get_audio_mode_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $audio_session = $this->session_manager->get_audio_session($session_id);

        $status = array(
            'active' => $audio_session && $audio_session['state'] !== 'INACTIVE',
            'state' => $audio_session['state'] ?? 'INACTIVE',
            'session_id' => $session_id,
            'uptime' => $audio_session ? $this->calculate_session_uptime($audio_session) : 0,
            'interactions' => $audio_session ? $audio_session['interaction_count'] : 0,
            'last_activity' => $audio_session ? $audio_session['last_activity'] : null
        );

        wp_send_json_success($status);
    }

    /**
     * Enhance response for audio mode
     */
    public function enhance_for_audio_mode($response_data, $message) {
        if (!is_array($response_data)) {
            return $response_data;
        }

        // Check if we're in audio mode
        $session_id = $response_data['session_id'] ?? '';
        $audio_session = $this->session_manager->get_audio_session($session_id);

        if (!$audio_session || $audio_session['state'] === 'INACTIVE') {
            return $response_data;
        }

        // Add audio mode specific enhancements
        $response_data['audio_mode'] = array(
            'active' => true,
            'auto_listen' => get_option('ai_chatbot_audio_auto_listen', true),
            'listen_delay' => $this->calculate_listen_delay($response_data),
            'conversation_context' => $this->get_conversation_context($audio_session),
            'session_state' => $audio_session['state'],
            'next_action' => $this->determine_next_action($response_data, $audio_session)
        );

        // Update session with new interaction
        $this->session_manager->record_interaction($audio_session['id'], $message, $response_data['response']);

        return $response_data;
    }

    /**
     * Calculate listen delay based on response
     */
    private function calculate_listen_delay($response_data) {
        $base_delay = 1.0; // 1 second base delay
        
        // Add delay based on response length
        $response_length = strlen($response_data['response'] ?? '');
        if ($response_length > 200) {
            $base_delay += 1.0;
        } elseif ($response_length > 500) {
            $base_delay += 2.0;
        }

        // Add delay if TTS is enabled
        if (isset($response_data['tts']) && $response_data['tts']['should_speak']) {
            $estimated_speech_time = $response_data['tts']['timing']['estimated_duration'] ?? 5;
            $base_delay += $estimated_speech_time + 0.5; // Add buffer
        }

        // Check for questions - shorter delay for questions
        if (substr(trim($response_data['response']), -1) === '?') {
            $base_delay = max(0.5, $base_delay - 1.0);
        }

        return $base_delay;
    }

    /**
     * Get conversation context for audio mode
     */
    private function get_conversation_context($audio_session) {
        return array(
            'session_duration' => $this->calculate_session_uptime($audio_session),
            'interaction_count' => $audio_session['interaction_count'],
            'last_topics' => $this->session_manager->get_recent_topics($audio_session['id'], 3),
            'user_preferences' => $this->session_manager->get_session_preferences($audio_session['id']),
            'conversation_flow' => $this->analyze_conversation_flow($audio_session)
        );
    }

    /**
     * Determine next action for audio mode
     */
    private function determine_next_action($response_data, $audio_session) {
        // If response asks a question, prepare for quick user response
        if (substr(trim($response_data['response']), -1) === '?') {
            return array(
                'action' => 'quick_listen',
                'timeout' => 10,
                'prompt' => __('Please respond...', 'ai-website-chatbot')
            );
        }

        // If response provides information, wait for acknowledgment
        if (strlen($response_data['response']) > 200) {
            return array(
                'action' => 'wait_acknowledgment',
                'timeout' => 15,
                'prompt' => __('Say "continue" for more or ask a question...', 'ai-website-chatbot')
            );
        }

        // Default: standard listening mode
        return array(
            'action' => 'standard_listen',
            'timeout' => get_option('ai_chatbot_audio_timeout', 30),
            'prompt' => __('How can I help you further?', 'ai-website-chatbot')
        );
    }

    /**
     * Check if user can use audio mode
     */
    private function can_user_use_audio_mode($user_id) {
        // Check Pro license
        if (!ai_chatbot_is_pro()) {
            return false;
        }

        // Check feature availability
        if (!ai_chatbot_has_feature('audio_features')) {
            return false;
        }

        // Check if audio mode is enabled
        if (!get_option('ai_chatbot_audio_mode_enabled', false)) {
            return false;
        }

        // Check user permissions (if any specific restrictions)
        $user_restrictions = get_user_meta($user_id, 'ai_chatbot_audio_restrictions', true);
        if ($user_restrictions && !empty($user_restrictions['audio_mode_disabled'])) {
            return false;
        }

        return true;
    }

    /**
     * Get activation instructions
     */
    private function get_activation_instructions() {
        return array(
            'welcome_message' => __('Audio mode is now active. You can speak naturally to chat with me.', 'ai-website-chatbot'),
            'commands' => array(
                __('Say "stop listening" to exit audio mode', 'ai-website-chatbot'),
                __('Say "pause" to temporarily pause audio mode', 'ai-website-chatbot'),
                __('Say "help" to learn about voice commands', 'ai-website-chatbot')
            ),
            'tips' => array(
                __('Speak clearly and wait for the listening indicator', 'ai-website-chatbot'),
                __('You can interrupt me at any time by speaking', 'ai-website-chatbot'),
                __('Background noise may affect recognition quality', 'ai-website-chatbot')
            )
        );
    }

    /**
     * Calculate session uptime
     */
    private function calculate_session_uptime($audio_session) {
        if (!$audio_session || !isset($audio_session['created_at'])) {
            return 0;
        }

        $start_time = strtotime($audio_session['created_at']);
        return time() - $start_time;
    }

    /**
     * Analyze conversation flow
     */
    private function analyze_conversation_flow($audio_session) {
        $interactions = $this->session_manager->get_session_interactions($audio_session['id']);
        
        if (empty($interactions)) {
            return array('flow_type' => 'initial', 'stage' => 'greeting');
        }

        $interaction_count = count($interactions);
        $recent_interactions = array_slice($interactions, -3);

        // Analyze flow patterns
        $question_count = 0;
        $answer_count = 0;
        
        foreach ($recent_interactions as $interaction) {
            if (substr(trim($interaction['user_message']), -1) === '?') {
                $question_count++;
            } else {
                $answer_count++;
            }
        }

        if ($question_count > $answer_count) {
            return array('flow_type' => 'inquiry', 'stage' => 'information_seeking');
        } elseif ($interaction_count > 10) {
            return array('flow_type' => 'extended', 'stage' => 'deep_conversation');
        } else {
            return array('flow_type' => 'standard', 'stage' => 'active_chat');
        }
    }

    /**
     * Log audio mode events
     */
    private function log_audio_mode_event($event_type, $session_id, $user_id) {
        $log_data = array(
            'event_type' => $event_type,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'timestamp' => current_time('mysql'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_user_ip()
        );

        // Store in custom log table or use WordPress logs
        error_log('AI Chatbot Audio Mode: ' . $event_type . ' - Session: ' . $session_id);
        
        // Update statistics
        $this->update_audio_mode_statistics($event_type);
    }

    /**
     * Update audio mode statistics
     */
    private function update_audio_mode_statistics($event_type) {
        $stats = get_option('ai_chatbot_audio_mode_stats', array());
        
        $stats[$event_type] = ($stats[$event_type] ?? 0) + 1;
        $stats['last_activity'] = current_time('mysql');
        
        update_option('ai_chatbot_audio_mode_stats', $stats);
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Audio Session Manager helper class
 */
class AI_Chatbot_Audio_Session_Manager {
    
    /**
     * Create audio session
     */
    public function create_audio_session($session_id, $user_id) {
        // Implementation for creating audio session
        // This would typically interact with database
        
        $audio_session = array(
            'id' => wp_generate_uuid4(),
            'session_id' => $session_id,
            'user_id' => $user_id,
            'state' => 'ACTIVATING',
            'created_at' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'interaction_count' => 0,
            'settings' => $this->get_default_session_settings()
        );

        // Store session (implement database storage)
        $this->store_audio_session($audio_session);
        
        return $audio_session;
    }

    /**
     * Get current session
     */
    public function get_current_session() {
        // Return current audio session data
        return array(
            'active' => false,
            'state' => 'INACTIVE',
            'uptime' => 0
        );
    }

    /**
     * Store audio session
     */
    private function store_audio_session($session) {
        // Store in session or database
        if (!session_id()) {
            session_start();
        }
        $_SESSION['ai_chatbot_audio_session'] = $session;
    }

    /**
     * Get audio session
     */
    public function get_audio_session($session_id) {
        // Retrieve from session or database
        if (!session_id()) {
            session_start();
        }
        return $_SESSION['ai_chatbot_audio_session'] ?? null;
    }

    /**
     * Update session state
     */
    public function update_session_state($session_id, $new_state) {
        $session = $this->get_audio_session($session_id);
        if ($session) {
            $session['state'] = $new_state;
            $session['last_activity'] = current_time('mysql');
            $this->store_audio_session($session);
        }
    }

    /**
     * Get default session settings
     */
    private function get_default_session_settings() {
        return array(
            'language' => get_option('ai_chatbot_voice_language', 'en-US'),
            'auto_listen' => get_option('ai_chatbot_audio_auto_listen', true),
            'timeout' => get_option('ai_chatbot_audio_timeout', 30)
        );
    }

    /**
     * Record interaction
     */
    public function record_interaction($session_id, $user_message, $ai_response) {
        $session = $this->get_audio_session($session_id);
        if ($session) {
            $session['interaction_count']++;
            $session['last_activity'] = current_time('mysql');
            
            // Store interaction (simplified - in production, use database)
            if (!isset($session['interactions'])) {
                $session['interactions'] = array();
            }
            
            $session['interactions'][] = array(
                'user_message' => $user_message,
                'ai_response' => $ai_response,
                'timestamp' => current_time('mysql')
            );
            
            // Keep only last 10 interactions in session
            if (count($session['interactions']) > 10) {
                $session['interactions'] = array_slice($session['interactions'], -10);
            }
            
            $this->store_audio_session($session);
        }
    }

    /**
     * Close audio session
     */
    public function close_audio_session($session_id) {
        $session = $this->get_audio_session($session_id);
        if ($session) {
            $session['state'] = 'INACTIVE';
            $session['ended_at'] = current_time('mysql');
            $this->store_audio_session($session);
        }
    }

    /**
     * Get session statistics
     */
    public function get_session_statistics($session_id) {
        $session = $this->get_audio_session($session_id);
        if (!$session) {
            return array();
        }

        $duration = 0;
        if (isset($session['created_at'])) {
            $start = strtotime($session['created_at']);
            $end = isset($session['ended_at']) ? strtotime($session['ended_at']) : time();
            $duration = $end - $start;
        }

        return array(
            'duration' => $duration,
            'interactions' => $session['interaction_count'] ?? 0,
            'average_response_time' => $this->calculate_average_response_time($session),
            'user_satisfaction' => 'unknown' // Could be implemented with feedback
        );
    }

    /**
     * Get recent topics
     */
    public function get_recent_topics($session_id, $limit = 3) {
        // Simplified implementation - extract topics from recent interactions
        return array('general', 'help', 'information');
    }

    /**
     * Get session preferences
     */
    public function get_session_preferences($session_id) {
        $session = $this->get_audio_session($session_id);
        return $session['settings'] ?? array();
    }

    /**
     * Get session interactions
     */
    public function get_session_interactions($session_id) {
        $session = $this->get_audio_session($session_id);
        return $session['interactions'] ?? array();
    }

    /**
     * Calculate average response time
     */
    private function calculate_average_response_time($session) {
        // Implementation for calculating response times
        return 2.5; // Default placeholder
    }
}