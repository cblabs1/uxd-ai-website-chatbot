<?php
/**
 * FIXED: Audio Manager Class
 * File: includes/pro/audio/class-audio-manager.php
 */

/**
 * AI_Chatbot_Pro_Audio_Manager class
 * Coordinates all audio features and integrates with existing Pro system
 */
class AI_Chatbot_Pro_Audio_Manager {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Audio modules
     */
    private $modules = array();

    /**
     * Initialization flag to prevent recursive calls
     */
    private $initialized = false;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Only add hooks if not already initialized
        if (!$this->initialized) {
            add_action('init', array($this, 'init_audio_features'), 15);
            add_action('wp_enqueue_scripts', array($this, 'enqueue_audio_assets'));
            
            // FIXED: Only add filters if audio is enabled
            if ($this->is_audio_enabled()) {
                add_filter('ai_chatbot_pro_config', array($this, 'add_audio_config'), 10, 1);
                add_filter('ai_chatbot_response_data', array($this, 'enhance_response_with_audio'), 10, 1);
            }
            
            $this->initialized = true;
        }
    }

    /**
     * Check if audio features are enabled - FIXED with better error handling
     */
    private function is_audio_enabled() {
        try {
            // Check if functions exist first
            if (!function_exists('ai_chatbot_is_pro') || !function_exists('ai_chatbot_has_feature')) {
                return false;
            }

            // Check Pro license
            if (!ai_chatbot_is_pro()) {
                return false;
            }

            // Check if audio feature is enabled
            if (!ai_chatbot_has_feature('audio_features')) {
                return false;
            }

            // Check if any audio setting is enabled
            return get_option('ai_chatbot_voice_input_enabled', false) ||
                   get_option('ai_chatbot_tts_enabled', false) ||
                   get_option('ai_chatbot_audio_mode_enabled', false);

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error checking if audio enabled: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize audio features - FIXED
     */
    public function init_audio_features() {
        try {
            // Check if audio features are enabled
            if (!$this->is_audio_enabled()) {
                return;
            }

            // Load and initialize audio modules
            $this->load_audio_modules();
            $this->init_audio_modules();

            // Register audio-specific hooks
            $this->register_audio_hooks();

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error initializing audio features: ' . $e->getMessage());
        }
    }

    /**
     * Load audio module files - FIXED
     */
    private function load_audio_modules() {
        $audio_files = array(
            'class-voice-input.php',
            'class-text-to-speech.php',
            'class-audio-mode.php',
            'class-voice-commands.php',
            'class-audio-settings.php'
        );

        foreach ($audio_files as $file) {
            $file_path = AI_CHATBOT_PLUGIN_DIR . 'includes/pro/audio/' . $file;
            
            if (file_exists($file_path)) {
                try {
                    require_once $file_path;
                } catch (Exception $e) {
                    error_log('AI Chatbot Audio Manager: Error loading ' . $file . ': ' . $e->getMessage());
                }
            } else {
                error_log('AI Chatbot Audio Manager: Audio file not found: ' . $file_path);
            }
        }
    }

    /**
     * Initialize audio modules - FIXED
     */
    private function init_audio_modules() {
        try {
            // Initialize modules safely
            $module_classes = array(
                'voice_input' => 'AI_Chatbot_Pro_Voice_Input',
                'text_to_speech' => 'AI_Chatbot_Pro_Text_To_Speech',
                'audio_mode' => 'AI_Chatbot_Pro_Audio_Mode',
                'voice_commands' => 'AI_Chatbot_Pro_Voice_Commands',
                'audio_settings' => 'AI_Chatbot_Pro_Audio_Settings'
            );

            foreach ($module_classes as $module_key => $class_name) {
                if (class_exists($class_name)) {
                    try {
                        $this->modules[$module_key] = new $class_name();
                    } catch (Exception $e) {
                        error_log('AI Chatbot Audio Manager: Error initializing ' . $class_name . ': ' . $e->getMessage());
                    }
                }
            }

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error in init_audio_modules: ' . $e->getMessage());
        }
    }

    /**
     * Register audio-specific hooks - FIXED
     */
    private function register_audio_hooks() {
        try {
            // Audio AJAX handlers
            add_action('wp_ajax_ai_chatbot_audio_message', array($this, 'handle_audio_message'));
            add_action('wp_ajax_nopriv_ai_chatbot_audio_message', array($this, 'handle_audio_message'));
            
            add_action('wp_ajax_ai_chatbot_save_audio_preferences', array($this, 'save_audio_preferences'));
            add_action('wp_ajax_nopriv_ai_chatbot_save_audio_preferences', array($this, 'save_audio_preferences'));

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error registering audio hooks: ' . $e->getMessage());
        }
    }

    /**
     * Add audio configuration to Pro config - FIXED
     */
    public function add_audio_config($config) {
        try {
            if (!is_array($config)) {
                $config = array();
            }

            if ($this->is_audio_enabled()) {
                $config['audio'] = $this->get_audio_configuration();
            }

            return $config;

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error adding audio config: ' . $e->getMessage());
            return $config;
        }
    }

    /**
     * Get audio configuration safely - FIXED
     */
    private function get_audio_configuration() {
        try {
            return array(
                'voice_input' => array(
                    'enabled' => get_option('ai_chatbot_voice_input_enabled', false),
                    'language' => get_option('ai_chatbot_voice_language', 'en-US'),
                    'continuous' => get_option('ai_chatbot_voice_continuous', false),
                    'interim_results' => get_option('ai_chatbot_voice_interim_results', true)
                ),
                'text_to_speech' => array(
                    'enabled' => get_option('ai_chatbot_tts_enabled', false),
                    'voice' => get_option('ai_chatbot_tts_voice', 'default'),
                    'speed' => get_option('ai_chatbot_tts_speed', 1.0),
                    'pitch' => get_option('ai_chatbot_tts_pitch', 1.0),
                    'auto_play' => get_option('ai_chatbot_tts_auto_play', false)
                ),
                'audio_mode' => array(
                    'enabled' => get_option('ai_chatbot_audio_mode_enabled', false),
                    'auto_start' => get_option('ai_chatbot_audio_mode_auto_start', false)
                ),
                'voice_commands' => array(
                    'enabled' => get_option('ai_chatbot_voice_commands_enabled', false),
                    'commands' => $this->get_available_voice_commands()
                )
            );

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error getting audio configuration: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get available voice commands
     */
    private function get_available_voice_commands() {
        return array(
            'clear_chat' => __('Clear chat', 'ai-website-chatbot'),
            'repeat_last' => __('Repeat that', 'ai-website-chatbot'),
            'stop_talking' => __('Stop talking', 'ai-website-chatbot'),
            'start_audio_mode' => __('Start audio mode', 'ai-website-chatbot'),
            'stop_audio_mode' => __('Stop audio mode', 'ai-website-chatbot')
        );
    }

    /**
     * Enhance response with audio metadata - FIXED
     */
    public function enhance_response_with_audio($response_data) {
        try {
            if (!is_array($response_data) || !$this->is_audio_enabled()) {
                return $response_data;
            }

            // Add audio metadata to response
            $response_data['audio'] = array(
                'should_speak' => $this->should_speak_response($response_data),
                'speech_text' => $this->prepare_text_for_speech($response_data['response'] ?? ''),
                'voice_settings' => $this->get_response_voice_settings()
            );

            return $response_data;

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error enhancing response: ' . $e->getMessage());
            return $response_data;
        }
    }

    /**
     * Check if response should be spoken
     */
    private function should_speak_response($response_data) {
        if (!get_option('ai_chatbot_tts_enabled', false)) {
            return false;
        }

        if (!get_option('ai_chatbot_tts_auto_play', false)) {
            return false;
        }

        // Don't speak empty responses
        if (empty($response_data['response'])) {
            return false;
        }

        return true;
    }

    /**
     * Prepare text for speech synthesis
     */
    private function prepare_text_for_speech($text) {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Remove special characters that might cause issues
        $text = preg_replace('/[^\p{L}\p{N}\s\.,!?;:()-]/u', '', $text);
        
        // Limit length for speech
        if (strlen($text) > 500) {
            $text = substr($text, 0, 497) . '...';
        }
        
        return trim($text);
    }

    /**
     * Get voice settings for response
     */
    private function get_response_voice_settings() {
        return array(
            'voice' => get_option('ai_chatbot_tts_voice', 'default'),
            'speed' => floatval(get_option('ai_chatbot_tts_speed', 1.0)),
            'pitch' => floatval(get_option('ai_chatbot_tts_pitch', 1.0)),
            'volume' => floatval(get_option('ai_chatbot_tts_volume', 1.0))
        );
    }

    /**
     * Handle audio-enhanced messages - FIXED
     */
    public function handle_audio_message() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_nonce')) {
                wp_send_json_error('Security check failed');
                return;
            }

            $message = sanitize_textarea_field($_POST['message'] ?? '');
            $audio_mode = isset($_POST['audio_mode']) ? (bool) $_POST['audio_mode'] : false;
            $session_id = sanitize_text_field($_POST['session_id'] ?? '');

            if (empty($message)) {
                wp_send_json_error('Message cannot be empty');
                return;
            }

            // Use regular AJAX handler but enhance for audio
            if (class_exists('AI_Chatbot_Ajax')) {
                $ajax_handler = new AI_Chatbot_Ajax();
                // Call handle_send_message method if it exists
                if (method_exists($ajax_handler, 'handle_send_message')) {
                    // Capture output
                    ob_start();
                    $ajax_handler->handle_send_message();
                    $output = ob_get_clean();
                    
                    // If JSON response, decode and enhance
                    $response = json_decode($output, true);
                    if ($response && isset($response['data'])) {
                        $response['data'] = $this->enhance_response_with_audio($response['data']);
                        wp_send_json($response);
                        return;
                    }
                }
            }

            wp_send_json_error('Could not process audio message');

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error handling audio message: ' . $e->getMessage());
            wp_send_json_error('Audio message processing failed');
        }
    }

    /**
     * Save audio preferences - FIXED
     */
    public function save_audio_preferences() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_nonce')) {
                wp_send_json_error('Security check failed');
                return;
            }

            $preferences = $_POST['preferences'] ?? array();
            
            if (!is_array($preferences)) {
                wp_send_json_error('Invalid preferences data');
                return;
            }

            // Save preferences to user meta if logged in, or session if not
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'ai_chatbot_audio_preferences', $preferences);
            } else {
                // Save to session or cookie
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['ai_chatbot_audio_preferences'] = $preferences;
            }

            wp_send_json_success('Audio preferences saved');

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error saving audio preferences: ' . $e->getMessage());
            wp_send_json_error('Failed to save audio preferences');
        }
    }

    /**
     * Enqueue audio assets - FIXED
     */
    public function enqueue_audio_assets() {
        try {
            if (!$this->is_audio_enabled()) {
                return;
            }

            // Enqueue audio-specific CSS
            wp_enqueue_style(
                'ai-chatbot-audio-css',
                AI_CHATBOT_PLUGIN_URL . 'assets/css/public/pro/audio-controls.css',
                array(),
                AI_CHATBOT_VERSION
            );

            // Enqueue audio-specific JS
            wp_enqueue_script(
                'ai-chatbot-audio-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio-core.js',
                array('jquery'),
                AI_CHATBOT_VERSION,
                true
            );

             // Enqueue audio-specific JS
            wp_enqueue_script(
                'ai-chatbot-tts-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/text-to-speech.js',
                array('jquery'),
                AI_CHATBOT_VERSION,
                true
            );

            // Enqueue audio-specific JS
            wp_enqueue_script(
                'ai-chatbot-tts-js',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/voice-enhanced.js',
                array('jquery'),
                AI_CHATBOT_VERSION,
                true
            );

            // Localize audio script
            wp_localize_script('ai-chatbot-audio-js', 'ai_chatbot_audio', array(
                'nonce' => wp_create_nonce('ai_chatbot_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'config' => $this->get_audio_configuration()
            ));

        } catch (Exception $e) {
            error_log('AI Chatbot Audio Manager: Error enqueuing audio assets: ' . $e->getMessage());
        }
    }
}