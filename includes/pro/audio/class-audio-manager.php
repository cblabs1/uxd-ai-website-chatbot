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
                add_action('wp_enqueue_scripts', array($this, 'enqueue_audio_assets'));
                add_filter('ai_chatbot_pro_config', array($this, 'add_audio_config'), 10, 1);
            }
            $this->initialized = true;
        }
    }

    private function is_audio_enabled() {
        if (!function_exists('ai_chatbot_is_pro') || !function_exists('ai_chatbot_has_feature')) {
            return false;
        }

        if (!ai_chatbot_is_pro() || !ai_chatbot_has_feature('audio_features')) {
            return false;
        }

        return get_option('ai_chatbot_voice_input_enabled', false) ||
               get_option('ai_chatbot_tts_enabled', false);
    }

    public function init_audio_features() {
        // Don't register audio-specific AJAX handlers
        // Audio will work through standard message handler
    }

    public function enqueue_audio_assets() {
        // Enqueue audio CSS
        wp_enqueue_style(
            'ai-chatbot-audio-css',
            AI_CHATBOT_PLUGIN_URL . 'assets/css/public/audio-features.css',
            array('ai-chatbot-frontend-css'),
            AI_CHATBOT_VERSION
        );

        // Enqueue audio JS
        wp_enqueue_script(
            'ai-chatbot-audio-js',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/audio-features.js',
            array('jquery', 'ai-chatbot-frontend-js'),
            AI_CHATBOT_VERSION,
            true
        );

        // Localize audio configuration
        wp_localize_script('ai-chatbot-audio-js', 'ai_chatbot_audio', $this->get_audio_configuration());
    }

    private function get_audio_configuration() {
        // Get all settings from the main option
        $settings = get_option('ai_chatbot_settings', array());
        
        return array(
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'voice_input' => array(
                'enabled' => !empty($settings['voice_input_enabled']),
                'language' => $settings['voice_language'] ?? 'en-US',
                'continuous' => !empty($settings['voice_continuous']),
                'interim_results' => !empty($settings['voice_interim_results']),
                'auto_send' => !empty($settings['voice_auto_send'])
            ),
            'text_to_speech' => array(
                'enabled' => !empty($settings['tts_enabled']),
                'voice' => $settings['tts_voice'] ?? '',
                'rate' => floatval($settings['tts_rate'] ?? 1.0),
                'pitch' => floatval($settings['tts_pitch'] ?? 1.0),
                'volume' => floatval($settings['tts_volume'] ?? 0.8),
                'auto_play' => !empty($settings['tts_auto_play'])
            ),
            'strings' => array(
                'listening' => __('Listening...', 'ai-website-chatbot'),
                'processing' => __('Processing...', 'ai-website-chatbot'),
                'error' => __('Error occurred', 'ai-website-chatbot'),
                'permission_denied' => __('Microphone permission denied', 'ai-website-chatbot'),
                'not_supported' => __('Voice input not supported in your browser', 'ai-website-chatbot')
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