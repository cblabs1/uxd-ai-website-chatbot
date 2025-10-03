<?php
/**
 * FIXED: Audio Manager Class
 */
class AI_Chatbot_Pro_Audio_Manager {

    private static $instance = null;
    private $modules = array();
    private $initialized = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!$this->initialized) {
            // Only initialize if audio is enabled
            if ($this->is_audio_enabled()) {
                add_action('init', array($this, 'init_audio_features'), 15);
                add_action('wp_enqueue_scripts', array($this, 'enqueue_audio_assets'), 20);
                add_filter('ai_chatbot_pro_config', array($this, 'add_audio_config'), 10, 1);
            }
            $this->initialized = true;
        }
    }

    private function is_audio_enabled() {
       
        $settings = get_option('ai_chatbot_settings', array());
        
        // Check if any audio feature is enabled
        return !empty($settings['voice_input_enabled']) || 
               !empty($settings['tts_enabled']);
               
    }

    public function init_audio_features() {
        // Don't register audio-specific AJAX handlers
        // Audio will work through standard message handler
    }

    public function enqueue_audio_assets() {

        // Check if audio is enabled
        if (!$this->is_audio_enabled()) {
            return;
        }

        // Enqueue audio CSS
        wp_enqueue_style(
            'ai-chatbot-audio-css',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/pro/audio-features.css',
            array('ai-chatbot-frontend-css'),
            AI_CHATBOT_VERSION
        );

        // Enqueue audio JS
        wp_enqueue_script(
            'ai-chatbot-audio-features',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio-features.js',
            array('jquery', 'ai-chatbot-frontend-js'),
            AI_CHATBOT_VERSION,
            true
        );

        // Audio mode logic
        wp_enqueue_script(
            'ai-chatbot-audio-mode',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio-mode.js',
            array('jquery', 'ai-chatbot-audio-features'),
            AI_CHATBOT_VERSION,
            true
        );

        $audio_config = $this->get_audio_configuration();

        // Localize audio configuration
        wp_localize_script('ai-chatbot-audio-js', 'aiChatbotAudio', $this->get_audio_configuration());
    }

    /**
     * Get audio configuration - FIXED
     */
    private function get_audio_configuration() {
        $settings = get_option('ai_chatbot_settings', array());
        
        return array(
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'audio_enabled' => !empty($audio_settings['enabled']),
            'voice_input' => array(
                'enabled' => !empty($audio_settings['voice_input_enabled']),
                'language' => $audio_settings['voice_language'] ?? 'en-US',
                'continuous' => !empty($audio_settings['voice_continuous']),
                'interim_results' => isset($audio_settings['voice_interim_results']) ? !empty($audio_settings['voice_interim_results']) : true,
                'auto_send' => !empty($audio_settings['voice_auto_send']),
            ),
            'tts' => array(
                'enabled' => !empty($audio_settings['tts_enabled']),
                'auto_play' => !empty($audio_settings['tts_auto_play']),
                'rate' => floatval($audio_settings['tts_rate'] ?? 1.0),
                'pitch' => floatval($audio_settings['tts_pitch'] ?? 1.0),
                'volume' => floatval($audio_settings['tts_volume'] ?? 0.8),
                'language' => $audio_settings['voice_language'] ?? 'en-US',
            ),
            'audio_mode' => array(
                'enabled' => !empty($audio_settings['audio_mode_enabled']),
                'auto_listen' => true,
                'silence_timeout' => intval($audio_settings['audio_mode_silence_timeout'] ?? 30),
                'max_time' => intval($audio_settings['audio_mode_max_time'] ?? 300),
            ),
            'analytics_enabled' => true,
            'strings' => array(
                'listening' => __('Listening...', 'ai-website-chatbot'),
                'speaking' => __('Speaking...', 'ai-website-chatbot'),
                'processing' => __('Processing...', 'ai-website-chatbot'),
                'paused' => __('Paused', 'ai-website-chatbot'),
                'error' => __('Error occurred', 'ai-website-chatbot'),
            )
        );
    }
    
    public function add_audio_config($config) {
        if (!is_array($config)) {
            $config = array();
        }
        $config['audio'] = $this->get_audio_configuration();
        return $config;
    }
}