<?php
/**
 * AI Chatbot Pro Audio Manager
 * Main coordinator for all audio features in Pro version
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
        add_action('init', array($this, 'init_audio_features'), 15);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_audio_assets'));
        add_filter('ai_chatbot_pro_config', array($this, 'add_audio_config'));
        add_filter('ai_chatbot_response_data', array($this, 'enhance_response_with_audio'));
    }

    /**
     * Initialize audio features
     */
    public function init_audio_features() {
        // Check if audio features are enabled
        if (!$this->is_audio_enabled()) {
            return;
        }

        // Load and initialize audio modules
        $this->load_audio_modules();
        $this->init_audio_modules();

        // Register audio-specific hooks
        $this->register_audio_hooks();
    }

    /**
     * Check if audio features are enabled
     */
    private function is_audio_enabled() {
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
    }

    /**
     * Load audio module files
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
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize audio modules
     */
    private function init_audio_modules() {
        // Voice Input
        if (get_option('ai_chatbot_voice_input_enabled', false) && class_exists('AI_Chatbot_Pro_Voice_Input')) {
            $this->modules['voice_input'] = new AI_Chatbot_Pro_Voice_Input();
        }

        // Text-to-Speech
        if (get_option('ai_chatbot_tts_enabled', false) && class_exists('AI_Chatbot_Pro_Text_To_Speech')) {
            $this->modules['text_to_speech'] = new AI_Chatbot_Pro_Text_To_Speech();
        }

        // Audio Mode
        if (get_option('ai_chatbot_audio_mode_enabled', false) && class_exists('AI_Chatbot_Pro_Audio_Mode')) {
            $this->modules['audio_mode'] = new AI_Chatbot_Pro_Audio_Mode();
        }

        // Voice Commands
        if (get_option('ai_chatbot_voice_commands_enabled', false) && class_exists('AI_Chatbot_Pro_Voice_Commands')) {
            $this->modules['voice_commands'] = new AI_Chatbot_Pro_Voice_Commands();
        }

        // Audio Settings
        if (class_exists('AI_Chatbot_Pro_Audio_Settings')) {
            $this->modules['audio_settings'] = new AI_Chatbot_Pro_Audio_Settings();
        }
    }

    /**
     * Register audio-specific hooks
     */
    private function register_audio_hooks() {
        // AJAX handlers
        add_action('wp_ajax_ai_chatbot_audio_message', array($this, 'handle_audio_message'));
        add_action('wp_ajax_nopriv_ai_chatbot_audio_message', array($this, 'handle_audio_message'));
        add_action('wp_ajax_ai_chatbot_save_audio_preferences', array($this, 'save_audio_preferences'));
        add_action('wp_ajax_nopriv_ai_chatbot_save_audio_preferences', array($this, 'save_audio_preferences'));

        // Filter response data for audio enhancement
        add_filter('ai_chatbot_message_response', array($this, 'add_audio_metadata'), 10, 2);
    }

    /**
     * Enqueue audio assets
     */
    public function enqueue_audio_assets() {
        if (!$this->is_audio_enabled()) {
            return;
        }

        // Core audio JavaScript
        wp_enqueue_script(
            'ai-chatbot-pro-audio-core',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/audio-core.js',
            array('jquery', 'ai-chatbot-pro-features'),
            AI_CHATBOT_VERSION,
            true
        );

        // Audio-specific modules
        if (get_option('ai_chatbot_voice_input_enabled', false)) {
            wp_enqueue_script(
                'ai-chatbot-pro-voice-enhanced',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/voice-enhanced.js',
                array('ai-chatbot-pro-audio-core'),
                AI_CHATBOT_VERSION,
                true
            );
        }

        if (get_option('ai_chatbot_tts_enabled', false)) {
            wp_enqueue_script(
                'ai-chatbot-pro-tts',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/text-to-speech.js',
                array('ai-chatbot-pro-audio-core'),
                AI_CHATBOT_VERSION,
                true
            );
        }

        if (get_option('ai_chatbot_audio_mode_enabled', false)) {
            wp_enqueue_script(
                'ai-chatbot-pro-audio-mode',
                AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/audio-mode.js',
                array('ai-chatbot-pro-audio-core'),
                AI_CHATBOT_VERSION,
                true
            );
        }

        // Audio CSS
        wp_enqueue_style(
            'ai-chatbot-pro-audio-controls',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/pro/audio/audio-controls.css',
            array('ai-chatbot-frontend'),
            AI_CHATBOT_VERSION
        );

        wp_enqueue_style(
            'ai-chatbot-pro-voice-modal',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/pro/audio/voice-modal.css',
            array('ai-chatbot-pro-audio-controls'),
            AI_CHATBOT_VERSION
        );

        // Localize audio configuration
        wp_localize_script('ai-chatbot-pro-audio-core', 'aiChatbotProAudio', array(
            'config' => $this->get_audio_configuration(),
            'nonce' => wp_create_nonce('ai_chatbot_audio_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => $this->get_audio_strings()
        ));
    }

    /**
     * Get audio configuration for frontend
     */
    private function get_audio_configuration() {
        return array(
            'voice_input' => array(
                'enabled' => get_option('ai_chatbot_voice_input_enabled', false),
                'language' => get_option('ai_chatbot_voice_language', 'en-US'),
                'continuous' => get_option('ai_chatbot_voice_continuous', false),
                'interim_results' => get_option('ai_chatbot_voice_interim_results', true)
            ),
            'text_to_speech' => array(
                'enabled' => get_option('ai_chatbot_tts_enabled', false),
                'auto_play' => get_option('ai_chatbot_tts_auto_play', false),
                'voice_name' => get_option('ai_chatbot_tts_voice_name', ''),
                'rate' => get_option('ai_chatbot_tts_rate', 1.0),
                'pitch' => get_option('ai_chatbot_tts_pitch', 1.0),
                'volume' => get_option('ai_chatbot_tts_volume', 0.8),
                'language' => get_option('ai_chatbot_tts_language', 'en-US')
            ),
            'audio_mode' => array(
                'enabled' => get_option('ai_chatbot_audio_mode_enabled', false),
                'auto_listen_after_response' => get_option('ai_chatbot_audio_auto_listen', true),
                'silence_detection' => get_option('ai_chatbot_audio_silence_detection', true),
                'conversation_timeout' => get_option('ai_chatbot_audio_timeout', 30)
            ),
            'voice_commands' => array(
                'enabled' => get_option('ai_chatbot_voice_commands_enabled', false),
                'commands' => $this->get_voice_commands()
            ),
            'audio_effects' => array(
                'notification_sounds' => get_option('ai_chatbot_notification_sounds', true),
                'response_chimes' => get_option('ai_chatbot_response_chimes', true)
            )
        );
    }

    /**
     * Get voice commands configuration
     */
    private function get_voice_commands() {
        return array(
            'clear_chat' => array(
                'phrases' => array('clear chat', 'clear conversation', 'start over'),
                'action' => 'clearChat'
            ),
            'repeat_last' => array(
                'phrases' => array('repeat that', 'say that again', 'repeat last message'),
                'action' => 'repeatLast'
            ),
            'stop_speaking' => array(
                'phrases' => array('stop talking', 'stop speaking', 'be quiet'),
                'action' => 'stopSpeaking'
            ),
            'toggle_audio_mode' => array(
                'phrases' => array('audio mode', 'voice mode', 'hands free mode'),
                'action' => 'toggleAudioMode'
            ),
            'help' => array(
                'phrases' => array('help me', 'what can you do', 'show help'),
                'action' => 'showHelp'
            )
        );
    }

    /**
     * Get audio strings for frontend
     */
    private function get_audio_strings() {
        return array(
            'listeningStart' => __('Listening...', 'ai-website-chatbot'),
            'listeningStop' => __('Processing...', 'ai-website-chatbot'),
            'voiceNotSupported' => __('Voice features not supported in your browser.', 'ai-website-chatbot'),
            'micPermissionDenied' => __('Microphone permission denied.', 'ai-website-chatbot'),
            'speechStart' => __('AI is speaking...', 'ai-website-chatbot'),
            'speechEnd' => __('Speech finished.', 'ai-website-chatbot'),
            'audioModeOn' => __('Audio mode enabled', 'ai-website-chatbot'),
            'audioModeOff' => __('Audio mode disabled', 'ai-website-chatbot'),
            'voiceCommandsHelp' => __('Voice commands available: "clear chat", "repeat that", "stop talking"', 'ai-website-chatbot')
        );
    }

    /**
     * Add audio configuration to Pro config
     */
    public function add_audio_config($config) {
        if ($this->is_audio_enabled()) {
            $config['audio'] = $this->get_audio_configuration();
        }
        return $config;
    }

    /**
     * Enhance response with audio metadata
     */
    public function enhance_response_with_audio($response_data) {
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
    }

    /**
     * Handle audio-enhanced messages
     */
    public function handle_audio_message() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $message = sanitize_textarea_field($_POST['message']);
        $audio_mode = isset($_POST['audio_mode']) ? (bool) $_POST['audio_mode'] : false;
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        // Process through existing message handler but enhance for audio
        $ajax_handler = new AI_Chatbot_Ajax();
        $response = $ajax_handler->process_message_internal($message, array(
            'session_id' => $session_id,
            'audio_mode' => $audio_mode
        ));

        if (!is_wp_error($response)) {
            $response = $this->enhance_response_with_audio($response);
        }

        wp_send_json($response);
    }

    /**
     * Save audio preferences
     */
    public function save_audio_preferences() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $preferences = $_POST['preferences'] ?? array();
        
        // Sanitize preferences
        $sanitized = array();
        if (isset($preferences['tts_enabled'])) {
            $sanitized['tts_enabled'] = (bool) $preferences['tts_enabled'];
        }
        if (isset($preferences['auto_play'])) {
            $sanitized['auto_play'] = (bool) $preferences['auto_play'];
        }
        if (isset($preferences['voice_rate'])) {
            $sanitized['voice_rate'] = max(0.1, min(2.0, (float) $preferences['voice_rate']));
        }
        if (isset($preferences['voice_volume'])) {
            $sanitized['voice_volume'] = max(0.0, min(1.0, (float) $preferences['voice_volume']));
        }

        // Save preferences
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'ai_chatbot_audio_preferences', $sanitized);
        } else {
            if (!session_id()) {
                session_start();
            }
            $_SESSION['ai_chatbot_audio_preferences'] = $sanitized;
        }

        wp_send_json_success(array('message' => 'Audio preferences saved successfully'));
    }

    /**
     * Add audio metadata to message response
     */
    public function add_audio_metadata($response, $message) {
        if (!$this->is_audio_enabled()) {
            return $response;
        }

        return $this->enhance_response_with_audio($response);
    }

    /**
     * Determine if response should be spoken
     */
    private function should_speak_response($response_data) {
        $tts_enabled = get_option('ai_chatbot_tts_enabled', false);
        $auto_play = get_option('ai_chatbot_tts_auto_play', false);
        
        if (!$tts_enabled) {
            return false;
        }

        // Check user preferences
        $user_prefs = $this->get_user_audio_preferences();
        if (isset($user_prefs['tts_enabled']) && !$user_prefs['tts_enabled']) {
            return false;
        }

        return $auto_play;
    }

    /**
     * Prepare text for speech synthesis
     */
    private function prepare_text_for_speech($text) {
        // Remove markdown formatting
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);
        $text = preg_replace('/`(.*?)`/', '$1', $text);
        
        // Remove HTML tags
        $text = wp_strip_all_tags($text);
        
        // Replace abbreviations for better pronunciation
        $replacements = array(
            'AI' => 'Artificial Intelligence',
            'FAQ' => 'Frequently Asked Questions',
            'URL' => 'web address',
            'API' => 'Application Programming Interface'
        );
        
        foreach ($replacements as $abbr => $full) {
            $text = str_ireplace($abbr, $full, $text);
        }
        
        // Clean up extra spaces
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Limit length for TTS
        if (strlen($text) > 1000) {
            $text = substr($text, 0, 997) . '...';
        }
        
        return $text;
    }

    /**
     * Get voice settings for response
     */
    private function get_response_voice_settings() {
        $user_prefs = $this->get_user_audio_preferences();
        
        return array(
            'rate' => $user_prefs['voice_rate'] ?? get_option('ai_chatbot_tts_rate', 1.0),
            'pitch' => get_option('ai_chatbot_tts_pitch', 1.0),
            'volume' => $user_prefs['voice_volume'] ?? get_option('ai_chatbot_tts_volume', 0.8),
            'voice' => get_option('ai_chatbot_tts_voice_name', ''),
            'language' => get_option('ai_chatbot_tts_language', 'en-US')
        );
    }

    /**
     * Get user audio preferences
     */
    private function get_user_audio_preferences() {
        if (is_user_logged_in()) {
            return get_user_meta(get_current_user_id(), 'ai_chatbot_audio_preferences', true) ?: array();
        } else {
            if (!session_id()) {
                session_start();
            }
            return $_SESSION['ai_chatbot_audio_preferences'] ?? array();
        }
    }

    /**
     * Get audio module instance
     */
    public function get_module($module_name) {
        return $this->modules[$module_name] ?? null;
    }

    /**
     * Get all loaded modules
     */
    public function get_modules() {
        return $this->modules;
    }
}