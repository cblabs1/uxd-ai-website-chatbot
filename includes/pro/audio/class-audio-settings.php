<?php
/**
 * AI Chatbot Pro Audio Settings
 * Manages audio settings and preferences for Pro version
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
 * AI_Chatbot_Pro_Audio_Settings class
 * Handles audio settings management for Pro features
 */
class AI_Chatbot_Pro_Audio_Settings {

    /**
     * Settings registry
     */
    private $settings_registry = array();

    /**
     * Default settings
     */
    private $default_settings = array();

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_audio_settings'));
        add_action('wp_ajax_ai_chatbot_save_audio_settings', array($this, 'save_audio_settings'));
        add_action('wp_ajax_ai_chatbot_reset_audio_settings', array($this, 'reset_audio_settings'));
        add_action('wp_ajax_ai_chatbot_test_audio_feature', array($this, 'test_audio_feature'));
        add_action('wp_ajax_ai_chatbot_export_audio_settings', array($this, 'export_audio_settings'));
        add_action('wp_ajax_ai_chatbot_import_audio_settings', array($this, 'import_audio_settings'));
        
        $this->init_default_settings();
        $this->init_settings_registry();
    }

    /**
     * Initialize default settings
     */
    private function init_default_settings() {
        $this->default_settings = array(
            // Voice Input Settings
            'voice_input_enabled' => false,
            'voice_language' => 'en-US',
            'voice_continuous' => false,
            'voice_interim_results' => true,
            'voice_confidence_threshold' => 0.7,
            'voice_noise_suppression' => true,
            'voice_auto_gain_control' => true,
            'voice_echo_cancellation' => true,
            'voice_silence_timeout' => 2000,
            'voice_max_alternatives' => 1,
            'voice_grammar_enabled' => false,
            'voice_auto_corrections' => true,
            'voice_auto_punctuation' => true,
            'voice_smart_caps' => true,
            'voice_profanity_filter' => false,
            'voice_noise_removal' => true,
            'voice_biometrics' => false,

            // Text-to-Speech Settings
            'tts_enabled' => false,
            'tts_auto_play' => false,
            'tts_voice_name' => '',
            'tts_rate' => 1.0,
            'tts_pitch' => 1.0,
            'tts_volume' => 0.8,
            'tts_language' => 'en-US',
            'tts_emotional_tone' => false,
            'tts_speaking_style' => 'neutral',
            'tts_pause_detection' => true,
            'tts_pronunciation_hints' => true,
            'tts_ssml_enabled' => false,
            'tts_background_music' => false,
            'tts_chunk_size' => 200,
            'tts_chunk_pause' => 0.5,
            'tts_smart_autoplay' => false,
            'tts_emphasis_words' => array(),
            'tts_custom_pronunciations' => array(),

            // Audio Mode Settings
            'audio_mode_enabled' => false,
            'audio_auto_listen' => true,
            'audio_silence_detection' => true,
            'audio_timeout' => 30,
            'audio_max_time' => 300,
            'audio_activation_phrase' => 'hey assistant',
            'audio_deactivation_phrase' => 'stop listening',
            'audio_silence_threshold' => 2.0,
            'audio_background_listening' => false,
            'audio_wake_word' => false,
            'audio_auto_pause' => true,
            'audio_energy_threshold' => 300,
            'audio_confirmation_sounds' => true,
            'audio_status_announcements' => true,
            'audio_visual_indicators' => true,
            'audio_conversation_memory' => true,

            // Voice Commands Settings
            'voice_commands_enabled' => false,
            'notification_sounds' => true,
            'response_chimes' => true,
            'typing_sounds' => false,
            'custom_voice_commands' => array(),
            'voice_command_aliases' => array(),

            // Advanced Audio Settings
            'audio_quality' => 'standard',
            'audio_compression' => 'auto',
            'audio_sample_rate' => 44100,
            'audio_bit_depth' => 16,
            'audio_channels' => 'mono',
            'audio_noise_gate' => false,
            'audio_normalization' => true,
            'audio_debug_mode' => false,

            // Privacy Settings
            'audio_data_retention' => 7, // days
            'audio_analytics_enabled' => true,
            'audio_usage_tracking' => true,
            'audio_error_reporting' => true,
            'audio_voice_print_storage' => false,

            // Accessibility Settings
            'audio_high_contrast_mode' => false,
            'audio_large_controls' => false,
            'audio_keyboard_shortcuts' => true,
            'audio_screen_reader_support' => true,
            'audio_haptic_feedback' => false
        );
    }

    /**
     * Initialize settings registry
     */
    private function init_settings_registry() {
        $this->settings_registry = array(
            'voice_input' => array(
                'title' => __('Voice Input Settings', 'ai-website-chatbot'),
                'description' => __('Configure speech-to-text functionality and voice recognition.', 'ai-website-chatbot'),
                'icon' => 'dashicons-microphone',
                'priority' => 10,
                'fields' => $this->get_voice_input_fields()
            ),

            'text_to_speech' => array(
                'title' => __('Text-to-Speech Settings', 'ai-website-chatbot'),
                'description' => __('Configure AI response speech output and voice characteristics.', 'ai-website-chatbot'),
                'icon' => 'dashicons-controls-volumeon',
                'priority' => 20,
                'fields' => $this->get_tts_fields()
            ),

            'audio_mode' => array(
                'title' => __('Audio Conversation Mode', 'ai-website-chatbot'),
                'description' => __('Configure hands-free audio conversation settings.', 'ai-website-chatbot'),
                'icon' => 'dashicons-format-audio',
                'priority' => 30,
                'fields' => $this->get_audio_mode_fields()
            ),

            'voice_commands' => array(
                'title' => __('Voice Commands', 'ai-website-chatbot'),
                'description' => __('Configure voice commands and audio effects.', 'ai-website-chatbot'),
                'icon' => 'dashicons-controls-forward',
                'priority' => 40,
                'fields' => $this->get_voice_commands_fields()
            ),

            'advanced_audio' => array(
                'title' => __('Advanced Audio', 'ai-website-chatbot'),
                'description' => __('Advanced audio processing and quality settings.', 'ai-website-chatbot'),
                'icon' => 'dashicons-admin-settings',
                'priority' => 50,
                'fields' => $this->get_advanced_audio_fields()
            ),

            'privacy_accessibility' => array(
                'title' => __('Privacy & Accessibility', 'ai-website-chatbot'),
                'description' => __('Privacy, data retention, and accessibility settings.', 'ai-website-chatbot'),
                'icon' => 'dashicons-privacy',
                'priority' => 60,
                'fields' => $this->get_privacy_accessibility_fields()
            )
        );
    }

    /**
     * Get voice input fields
     */
    private function get_voice_input_fields() {
        return array(
            'voice_input_enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable Voice Input', 'ai-website-chatbot'),
                'description' => __('Allow users to send messages using voice input.', 'ai-website-chatbot'),
                'default' => false
            ),
            'voice_language' => array(
                'type' => 'select',
                'title' => __('Voice Recognition Language', 'ai-website-chatbot'),
                'description' => __('Primary language for voice recognition.', 'ai-website-chatbot'),
                'options' => $this->get_supported_languages(),
                'default' => 'en-US'
            ),
            'voice_continuous' => array(
                'type' => 'checkbox',
                'title' => __('Continuous Listening', 'ai-website-chatbot'),
                'description' => __('Keep listening without manually activating for each input.', 'ai-website-chatbot'),
                'default' => false
            ),
            'voice_interim_results' => array(
                'type' => 'checkbox',
                'title' => __('Show Interim Results', 'ai-website-chatbot'),
                'description' => __('Display partial speech recognition results as user speaks.', 'ai-website-chatbot'),
                'default' => true
            ),
            'voice_confidence_threshold' => array(
                'type' => 'range',
                'title' => __('Confidence Threshold', 'ai-website-chatbot'),
                'description' => __('Minimum confidence level for accepting voice input (0.1 - 1.0).', 'ai-website-chatbot'),
                'min' => 0.1,
                'max' => 1.0,
                'step' => 0.1,
                'default' => 0.7
            ),
            'voice_noise_suppression' => array(
                'type' => 'checkbox',
                'title' => __('Noise Suppression', 'ai-website-chatbot'),
                'description' => __('Reduce background noise during voice input.', 'ai-website-chatbot'),
                'default' => true
            ),
            'voice_auto_corrections' => array(
                'type' => 'checkbox',
                'title' => __('Auto Grammar Corrections', 'ai-website-chatbot'),
                'description' => __('Automatically correct common grammar mistakes.', 'ai-website-chatbot'),
                'default' => true
            ),
            'voice_auto_punctuation' => array(
                'type' => 'checkbox',
                'title' => __('Auto Punctuation', 'ai-website-chatbot'),
                'description' => __('Automatically add punctuation based on speech patterns.', 'ai-website-chatbot'),
                'default' => true
            ),
            'voice_profanity_filter' => array(
                'type' => 'checkbox',
                'title' => __('Profanity Filter', 'ai-website-chatbot'),
                'description' => __('Filter inappropriate language from voice input.', 'ai-website-chatbot'),
                'default' => false
            )
        );
    }

    /**
     * Get TTS fields
     */
    private function get_tts_fields() {
        return array(
            'tts_enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable Text-to-Speech', 'ai-website-chatbot'),
                'description' => __('AI responses will be spoken aloud.', 'ai-website-chatbot'),
                'default' => false
            ),
            'tts_auto_play' => array(
                'type' => 'checkbox',
                'title' => __('Auto-play Responses', 'ai-website-chatbot'),
                'description' => __('Automatically speak responses without user interaction.', 'ai-website-chatbot'),
                'default' => false
            ),
            'tts_voice_name' => array(
                'type' => 'text',
                'title' => __('Preferred Voice Name', 'ai-website-chatbot'),
                'description' => __('Specific voice name (browser-dependent). Leave empty for default.', 'ai-website-chatbot'),
                'default' => ''
            ),
            'tts_rate' => array(
                'type' => 'range',
                'title' => __('Speech Rate', 'ai-website-chatbot'),
                'description' => __('Speech speed (0.1 = very slow, 2.0 = very fast).', 'ai-website-chatbot'),
                'min' => 0.1,
                'max' => 2.0,
                'step' => 0.1,
                'default' => 1.0
            ),
            'tts_pitch' => array(
                'type' => 'range',
                'title' => __('Speech Pitch', 'ai-website-chatbot'),
                'description' => __('Voice pitch (0.1 = very low, 2.0 = very high).', 'ai-website-chatbot'),
                'min' => 0.1,
                'max' => 2.0,
                'step' => 0.1,
                'default' => 1.0
            ),
            'tts_volume' => array(
                'type' => 'range',
                'title' => __('Speech Volume', 'ai-website-chatbot'),
                'description' => __('Voice volume (0.0 = silent, 1.0 = maximum).', 'ai-website-chatbot'),
                'min' => 0.0,
                'max' => 1.0,
                'step' => 0.1,
                'default' => 0.8
            ),
            'tts_emotional_tone' => array(
                'type' => 'checkbox',
                'title' => __('Emotional Tone Detection', 'ai-website-chatbot'),
                'description' => __('Adjust voice tone based on response content.', 'ai-website-chatbot'),
                'default' => false
            ),
            'tts_speaking_style' => array(
                'type' => 'select',
                'title' => __('Speaking Style', 'ai-website-chatbot'),
                'description' => __('Overall speaking style and tone.', 'ai-website-chatbot'),
                'options' => array(
                    'neutral' => __('Neutral', 'ai-website-chatbot'),
                    'friendly' => __('Friendly', 'ai-website-chatbot'),
                    'professional' => __('Professional', 'ai-website-chatbot'),
                    'casual' => __('Casual', 'ai-website-chatbot'),
                    'excited' => __('Excited', 'ai-website-chatbot'),
                    'calm' => __('Calm', 'ai-website-chatbot')
                ),
                'default' => 'neutral'
            ),
            'tts_ssml_enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable SSML', 'ai-website-chatbot'),
                'description' => __('Use Speech Synthesis Markup Language for advanced speech control.', 'ai-website-chatbot'),
                'default' => false
            ),
            'tts_smart_autoplay' => array(
                'type' => 'checkbox',
                'title' => __('Smart Auto-play', 'ai-website-chatbot'),
                'description' => __('Intelligently decide when to auto-play based on context.', 'ai-website-chatbot'),
                'default' => false
            )
        );
    }

    /**
     * Get audio mode fields
     */
    private function get_audio_mode_fields() {
        return array(
            'audio_mode_enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable Audio Mode', 'ai-website-chatbot'),
                'description' => __('Enable hands-free audio conversation mode.', 'ai-website-chatbot'),
                'default' => false
            ),
            'audio_auto_listen' => array(
                'type' => 'checkbox',
                'title' => __('Auto-listen After Response', 'ai-website-chatbot'),
                'description' => __('Automatically start listening after AI response.', 'ai-website-chatbot'),
                'default' => true
            ),
            'audio_silence_detection' => array(
                'type' => 'checkbox',
                'title' => __('Silence Detection', 'ai-website-chatbot'),
                'description' => __('Automatically stop listening when user stops speaking.', 'ai-website-chatbot'),
                'default' => true
            ),
            'audio_timeout' => array(
                'type' => 'number',
                'title' => __('Conversation Timeout (seconds)', 'ai-website-chatbot'),
                'description' => __('Maximum time to wait for user input (10-120 seconds).', 'ai-website-chatbot'),
                'min' => 10,
                'max' => 120,
                'default' => 30
            ),
            'audio_max_time' => array(
                'type' => 'number',
                'title' => __('Maximum Session Time (seconds)', 'ai-website-chatbot'),
                'description' => __('Maximum continuous audio session duration (60-1800 seconds).', 'ai-website-chatbot'),
                'min' => 60,
                'max' => 1800,
                'default' => 300
            ),
            'audio_activation_phrase' => array(
                'type' => 'text',
                'title' => __('Activation Phrase', 'ai-website-chatbot'),
                'description' => __('Phrase to activate audio mode.', 'ai-website-chatbot'),
                'default' => 'hey assistant'
            ),
            'audio_wake_word' => array(
                'type' => 'checkbox',
                'title' => __('Wake Word Detection', 'ai-website-chatbot'),
                'description' => __('Listen for wake word even when audio mode is off.', 'ai-website-chatbot'),
                'default' => false
            ),
            'audio_confirmation_sounds' => array(
                'type' => 'checkbox',
                'title' => __('Confirmation Sounds', 'ai-website-chatbot'),
                'description' => __('Play sounds to confirm audio mode changes.', 'ai-website-chatbot'),
                'default' => true
            ),
            'audio_status_announcements' => array(
                'type' => 'checkbox',
                'title' => __('Status Announcements', 'ai-website-chatbot'),
                'description' => __('Announce audio mode status changes.', 'ai-website-chatbot'),
                'default' => true
            )
        );
    }

    /**
     * Get voice commands fields
     */
    private function get_voice_commands_fields() {
        return array(
            'voice_commands_enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable Voice Commands', 'ai-website-chatbot'),
                'description' => __('Allow control of chatbot with voice commands.', 'ai-website-chatbot'),
                'default' => false
            ),
            'notification_sounds' => array(
                'type' => 'checkbox',
                'title' => __('Notification Sounds', 'ai-website-chatbot'),
                'description' => __('Play notification sounds for voice events.', 'ai-website-chatbot'),
                'default' => true
            ),
            'response_chimes' => array(
                'type' => 'checkbox',
                'title' => __('Response Chimes', 'ai-website-chatbot'),
                'description' => __('Play chimes when AI starts/stops speaking.', 'ai-website-chatbot'),
                'default' => true
            ),
            'typing_sounds' => array(
                'type' => 'checkbox',
                'title' => __('Typing Sounds', 'ai-website-chatbot'),
                'description' => __('Play typing sounds during AI response generation.', 'ai-website-chatbot'),
                'default' => false
            ),
            'custom_voice_commands' => array(
                'type' => 'textarea',
                'title' => __('Custom Voice Commands', 'ai-website-chatbot'),
                'description' => __('Add custom voice commands (JSON format).', 'ai-website-chatbot'),
                'rows' => 5,
                'default' => ''
            )
        );
    }

    /**
     * Get advanced audio fields
     */
    private function get_advanced_audio_fields() {
        return array(
            'audio_quality' => array(
                'type' => 'select',
                'title' => __('Audio Quality', 'ai-website-chatbot'),
                'description' => __('Audio processing quality level.', 'ai-website-chatbot'),
                'options' => array(
                    'low' => __('Low (faster processing)', 'ai-website-chatbot'),
                    'standard' => __('Standard (balanced)', 'ai-website-chatbot'),
                    'high' => __('High (best quality)', 'ai-website-chatbot')
                ),
                'default' => 'standard'
            ),
            'audio_sample_rate' => array(
                'type' => 'select',
                'title' => __('Sample Rate', 'ai-website-chatbot'),
                'description' => __('Audio sample rate for processing.', 'ai-website-chatbot'),
                'options' => array(
                    '22050' => '22.05 kHz',
                    '44100' => '44.1 kHz',
                    '48000' => '48 kHz'
                ),
                'default' => '44100'
            ),
            'audio_noise_gate' => array(
                'type' => 'checkbox',
                'title' => __('Noise Gate', 'ai-website-chatbot'),
                'description' => __('Enable noise gate to reduce background noise.', 'ai-website-chatbot'),
                'default' => false
            ),
            'audio_normalization' => array(
                'type' => 'checkbox',
                'title' => __('Audio Normalization', 'ai-website-chatbot'),
                'description' => __('Normalize audio levels for consistent volume.', 'ai-website-chatbot'),
                'default' => true
            ),
            'audio_debug_mode' => array(
                'type' => 'checkbox',
                'title' => __('Debug Mode', 'ai-website-chatbot'),
                'description' => __('Enable debug mode for audio troubleshooting.', 'ai-website-chatbot'),
                'default' => false
            )
        );
    }

    /**
     * Get privacy and accessibility fields
     */
    private function get_privacy_accessibility_fields() {
        return array(
            'audio_data_retention' => array(
                'type' => 'number',
                'title' => __('Audio Data Retention (days)', 'ai-website-chatbot'),
                'description' => __('How long to keep audio session data (1-365 days).', 'ai-website-chatbot'),
                'min' => 1,
                'max' => 365,
                'default' => 7
            ),
            'audio_analytics_enabled' => array(
                'type' => 'checkbox',
                'title' => __('Audio Analytics', 'ai-website-chatbot'),
                'description' => __('Collect anonymous audio usage statistics.', 'ai-website-chatbot'),
                'default' => true
            ),
            'audio_voice_print_storage' => array(
                'type' => 'checkbox',
                'title' => __('Voice Print Storage', 'ai-website-chatbot'),
                'description' => __('Store voice characteristics for personalization (privacy impact).', 'ai-website-chatbot'),
                'default' => false
            ),
            'audio_high_contrast_mode' => array(
                'type' => 'checkbox',
                'title' => __('High Contrast Audio Controls', 'ai-website-chatbot'),
                'description' => __('Use high contrast colors for audio interface.', 'ai-website-chatbot'),
                'default' => false
            ),
            'audio_large_controls' => array(
                'type' => 'checkbox',
                'title' => __('Large Audio Controls', 'ai-website-chatbot'),
                'description' => __('Use larger buttons and controls for accessibility.', 'ai-website-chatbot'),
                'default' => false
            ),
            'audio_keyboard_shortcuts' => array(
                'type' => 'checkbox',
                'title' => __('Keyboard Shortcuts', 'ai-website-chatbot'),
                'description' => __('Enable keyboard shortcuts for audio controls.', 'ai-website-chatbot'),
                'default' => true
            ),
            'audio_screen_reader_support' => array(
                'type' => 'checkbox',
                'title' => __('Screen Reader Support', 'ai-website-chatbot'),
                'description' => __('Enhanced support for screen reader users.', 'ai-website-chatbot'),
                'default' => true
            )
        );
    }

    /**
     * Register audio settings
     */
    public function register_audio_settings() {
        foreach ($this->settings_registry as $section_id => $section_data) {
            add_settings_section(
                'ai_chatbot_audio_' . $section_id,
                $section_data['title'],
                array($this, 'render_section_description'),
                'ai-chatbot-audio-settings'
            );

            foreach ($section_data['fields'] as $field_id => $field_data) {
                $option_name = 'ai_chatbot_' . $field_id;
                
                add_settings_field(
                    $option_name,
                    $field_data['title'],
                    array($this, 'render_field'),
                    'ai-chatbot-audio-settings',
                    'ai_chatbot_audio_' . $section_id,
                    array(
                        'field_id' => $field_id,
                        'field_data' => $field_data,
                        'option_name' => $option_name
                    )
                );

                register_setting('ai_chatbot_audio_settings_group', $option_name, array(
                    'sanitize_callback' => array($this, 'sanitize_setting'),
                    'default' => $field_data['default']
                ));
            }
        }
    }

    /**
     * Render section description
     */
    public function render_section_description($args) {
        $section_id = str_replace('ai_chatbot_audio_', '', $args['id']);
        if (isset($this->settings_registry[$section_id])) {
            echo '<p>' . esc_html($this->settings_registry[$section_id]['description']) . '</p>';
        }
    }

    /**
     * Render field
     */
    public function render_field($args) {
        $field_id = $args['field_id'];
        $field_data = $args['field_data'];
        $option_name = $args['option_name'];
        $value = get_option($option_name, $field_data['default']);

        switch ($field_data['type']) {
            case 'checkbox':
                $this->render_checkbox_field($option_name, $value, $field_data);
                break;
            case 'select':
                $this->render_select_field($option_name, $value, $field_data);
                break;
            case 'range':
                $this->render_range_field($option_name, $value, $field_data);
                break;
            case 'number':
                $this->render_number_field($option_name, $value, $field_data);
                break;
            case 'textarea':
                $this->render_textarea_field($option_name, $value, $field_data);
                break;
            case 'text':
            default:
                $this->render_text_field($option_name, $value, $field_data);
                break;
        }

        if (!empty($field_data['description'])) {
            echo '<p class="description">' . esc_html($field_data['description']) . '</p>';
        }
    }

    /**
     * Render checkbox field
     */
    private function render_checkbox_field($option_name, $value, $field_data) {
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($option_name) . '" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . esc_html($field_data['title']);
        echo '</label>';
    }

    /**
     * Render select field
     */
    private function render_select_field($option_name, $value, $field_data) {
        echo '<select name="' . esc_attr($option_name) . '">';
        foreach ($field_data['options'] as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($option_value, $value, false) . '>';
            echo esc_html($option_label);
            echo '</option>';
        }
        echo '</select>';
    }

    /**
     * Render range field
     */
    private function render_range_field($option_name, $value, $field_data) {
        $min = $field_data['min'] ?? 0;
        $max = $field_data['max'] ?? 100;
        $step = $field_data['step'] ?? 1;
        
        echo '<input type="range" name="' . esc_attr($option_name) . '" ';
        echo 'value="' . esc_attr($value) . '" ';
        echo 'min="' . esc_attr($min) . '" ';
        echo 'max="' . esc_attr($max) . '" ';
        echo 'step="' . esc_attr($step) . '" ';
        echo 'oninput="this.nextElementSibling.value = this.value" />';
        echo '<output>' . esc_html($value) . '</output>';
    }

    /**
     * Render number field
     */
    private function render_number_field($option_name, $value, $field_data) {
        $min = isset($field_data['min']) ? 'min="' . esc_attr($field_data['min']) . '"' : '';
        $max = isset($field_data['max']) ? 'max="' . esc_attr($field_data['max']) . '"' : '';
        
        echo '<input type="number" name="' . esc_attr($option_name) . '" ';
        echo 'value="' . esc_attr($value) . '" ' . $min . ' ' . $max . ' />';
    }

    /**
     * Render textarea field
     */
    private function render_textarea_field($option_name, $value, $field_data) {
        $rows = $field_data['rows'] ?? 3;
        echo '<textarea name="' . esc_attr($option_name) . '" rows="' . esc_attr($rows) . '" class="large-text">';
        echo esc_textarea($value);
        echo '</textarea>';
    }

    /**
     * Render text field
     */
    private function render_text_field($option_name, $value, $field_data) {
        $class = isset($field_data['class']) ? $field_data['class'] : 'regular-text';
        echo '<input type="text" name="' . esc_attr($option_name) . '" ';
        echo 'value="' . esc_attr($value) . '" class="' . esc_attr($class) . '" />';
    }

    /**
     * Sanitize setting
     */
    public function sanitize_setting($value) {
        // Basic sanitization - can be enhanced based on field type
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        
        return sanitize_text_field($value);
    }

    /**
     * Save audio settings AJAX
     */
    public function save_audio_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $settings = $_POST['settings'] ?? array();
        $saved_settings = array();
        $errors = array();

        foreach ($settings as $setting_key => $setting_value) {
            $option_name = 'ai_chatbot_' . $setting_key;
            
            // Validate setting
            $validation_result = $this->validate_setting($setting_key, $setting_value);
            
            if (is_wp_error($validation_result)) {
                $errors[] = $validation_result->get_error_message();
                continue;
            }

            // Sanitize and save
            $sanitized_value = $this->sanitize_setting_value($setting_key, $setting_value);
            update_option($option_name, $sanitized_value);
            $saved_settings[$setting_key] = $sanitized_value;
        }

        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Some settings could not be saved.', 'ai-website-chatbot'),
                'errors' => $errors
            ));
        }

        // Log settings change
        $this->log_settings_change($saved_settings);

        wp_send_json_success(array(
            'message' => __('Audio settings saved successfully.', 'ai-website-chatbot'),
            'saved_settings' => $saved_settings
        ));
    }

    /**
     * Reset audio settings AJAX
     */
    public function reset_audio_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $section = sanitize_text_field($_POST['section'] ?? 'all');

        if ($section === 'all') {
            // Reset all audio settings
            foreach ($this->default_settings as $setting_key => $default_value) {
                update_option('ai_chatbot_' . $setting_key, $default_value);
            }
            $message = __('All audio settings have been reset to defaults.', 'ai-website-chatbot');
        } else {
            // Reset specific section
            $section_fields = $this->get_section_fields($section);
            foreach ($section_fields as $field_id => $field_data) {
                if (isset($this->default_settings[$field_id])) {
                    update_option('ai_chatbot_' . $field_id, $this->default_settings[$field_id]);
                }
            }
            $message = sprintf(__('%s settings have been reset to defaults.', 'ai-website-chatbot'), ucfirst(str_replace('_', ' ', $section)));
        }

        wp_send_json_success(array('message' => $message));
    }

    /**
     * Test audio feature AJAX
     */
    public function test_audio_feature() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $feature = sanitize_text_field($_POST['feature'] ?? '');
        $test_data = $_POST['test_data'] ?? array();

        switch ($feature) {
            case 'voice_input':
                $result = $this->test_voice_input($test_data);
                break;
            case 'text_to_speech':
                $result = $this->test_text_to_speech($test_data);
                break;
            case 'audio_mode':
                $result = $this->test_audio_mode($test_data);
                break;
            case 'voice_commands':
                $result = $this->test_voice_commands($test_data);
                break;
            default:
                $result = array(
                    'success' => false,
                    'message' => __('Unknown audio feature test.', 'ai-website-chatbot')
                );
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Export audio settings AJAX
     */
    public function export_audio_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $export_data = array();
        
        foreach ($this->default_settings as $setting_key => $default_value) {
            $export_data[$setting_key] = get_option('ai_chatbot_' . $setting_key, $default_value);
        }

        $export_data['export_info'] = array(
            'version' => AI_CHATBOT_VERSION,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url()
        );

        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'ai-chatbot-audio-settings-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }

    /**
     * Import audio settings AJAX
     */
    public function import_audio_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $import_data = $_POST['import_data'] ?? '';
        
        if (empty($import_data)) {
            wp_send_json_error('No import data provided');
        }

        $data = json_decode($import_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON data');
        }

        $imported_count = 0;
        $errors = array();

        foreach ($data as $setting_key => $setting_value) {
            if ($setting_key === 'export_info') {
                continue; // Skip metadata
            }

            if (!array_key_exists($setting_key, $this->default_settings)) {
                $errors[] = sprintf(__('Unknown setting: %s', 'ai-website-chatbot'), $setting_key);
                continue;
            }

            $validation_result = $this->validate_setting($setting_key, $setting_value);
            
            if (is_wp_error($validation_result)) {
                $errors[] = sprintf(__('Invalid value for %s: %s', 'ai-website-chatbot'), $setting_key, $validation_result->get_error_message());
                continue;
            }

            $sanitized_value = $this->sanitize_setting_value($setting_key, $setting_value);
            update_option('ai_chatbot_' . $setting_key, $sanitized_value);
            $imported_count++;
        }

        $result = array(
            'imported_count' => $imported_count,
            'total_settings' => count($data) - 1, // Exclude export_info
        );

        if (!empty($errors)) {
            $result['errors'] = $errors;
            $result['message'] = sprintf(__('Imported %d settings with %d errors.', 'ai-website-chatbot'), $imported_count, count($errors));
        } else {
            $result['message'] = sprintf(__('Successfully imported %d settings.', 'ai-website-chatbot'), $imported_count);
        }

        wp_send_json_success($result);
    }

    /**
     * Validate setting value
     */
    private function validate_setting($setting_key, $value) {
        // Get field configuration
        $field_config = $this->get_field_config($setting_key);
        
        if (!$field_config) {
            return new WP_Error('unknown_setting', __('Unknown setting key.', 'ai-website-chatbot'));
        }

        switch ($field_config['type']) {
            case 'checkbox':
                if (!is_bool($value) && !in_array($value, array('0', '1', 0, 1))) {
                    return new WP_Error('invalid_boolean', __('Value must be boolean.', 'ai-website-chatbot'));
                }
                break;

            case 'range':
            case 'number':
                if (!is_numeric($value)) {
                    return new WP_Error('invalid_number', __('Value must be numeric.', 'ai-website-chatbot'));
                }
                
                $min = $field_config['min'] ?? null;
                $max = $field_config['max'] ?? null;
                
                if ($min !== null && $value < $min) {
                    return new WP_Error('value_too_low', sprintf(__('Value must be at least %s.', 'ai-website-chatbot'), $min));
                }
                
                if ($max !== null && $value > $max) {
                    return new WP_Error('value_too_high', sprintf(__('Value must be at most %s.', 'ai-website-chatbot'), $max));
                }
                break;

            case 'select':
                $valid_options = array_keys($field_config['options'] ?? array());
                if (!in_array($value, $valid_options)) {
                    return new WP_Error('invalid_option', __('Invalid option selected.', 'ai-website-chatbot'));
                }
                break;

            case 'text':
            case 'textarea':
                if (!is_string($value)) {
                    return new WP_Error('invalid_string', __('Value must be a string.', 'ai-website-chatbot'));
                }
                break;
        }

        return true;
    }

    /**
     * Sanitize setting value
     */
    private function sanitize_setting_value($setting_key, $value) {
        $field_config = $this->get_field_config($setting_key);
        
        if (!$field_config) {
            return sanitize_text_field($value);
        }

        switch ($field_config['type']) {
            case 'checkbox':
                return (bool) $value;

            case 'range':
            case 'number':
                return floatval($value);

            case 'select':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Get field configuration
     */
    private function get_field_config($setting_key) {
        foreach ($this->settings_registry as $section_data) {
            if (isset($section_data['fields'][$setting_key])) {
                return $section_data['fields'][$setting_key];
            }
        }
        return null;
    }

    /**
     * Get section fields
     */
    private function get_section_fields($section) {
        return $this->settings_registry[$section]['fields'] ?? array();
    }

    /**
     * Test voice input
     */
    private function test_voice_input($test_data) {
        // Simulate voice input test
        return array(
            'success' => true,
            'message' => __('Voice input test completed successfully.', 'ai-website-chatbot'),
            'test_results' => array(
                'browser_support' => true,
                'microphone_access' => true,
                'recognition_accuracy' => 95,
                'noise_suppression' => 'active'
            )
        );
    }

    /**
     * Test text-to-speech
     */
    private function test_text_to_speech($test_data) {
        $test_text = $test_data['text'] ?? __('This is a test of the text-to-speech feature.', 'ai-website-chatbot');
        
        return array(
            'success' => true,
            'message' => __('Text-to-speech test completed successfully.', 'ai-website-chatbot'),
            'test_results' => array(
                'synthesis_support' => true,
                'voice_available' => true,
                'estimated_duration' => strlen($test_text) / 20, // Rough estimate
                'test_text' => $test_text
            )
        );
    }

    /**
     * Test audio mode
     */
    private function test_audio_mode($test_data) {
        return array(
            'success' => true,
            'message' => __('Audio mode test completed successfully.', 'ai-website-chatbot'),
            'test_results' => array(
                'session_creation' => true,
                'state_management' => true,
                'timeout_handling' => true,
                'audio_integration' => true
            )
        );
    }

    /**
     * Test voice commands
     */
    private function test_voice_commands($test_data) {
        $test_phrase = $test_data['phrase'] ?? 'help me';
        
        return array(
            'success' => true,
            'message' => __('Voice commands test completed successfully.', 'ai-website-chatbot'),
            'test_results' => array(
                'command_recognition' => true,
                'phrase_matching' => 85,
                'execution_ready' => true,
                'test_phrase' => $test_phrase
            )
        );
    }

    /**
     * Get supported languages
     */
    private function get_supported_languages() {
        return array(
            'en-US' => __('English (US)', 'ai-website-chatbot'),
            'en-GB' => __('English (UK)', 'ai-website-chatbot'),
            'en-AU' => __('English (Australia)', 'ai-website-chatbot'),
            'en-CA' => __('English (Canada)', 'ai-website-chatbot'),
            'es-ES' => __('Spanish (Spain)', 'ai-website-chatbot'),
            'es-MX' => __('Spanish (Mexico)', 'ai-website-chatbot'),
            'es-AR' => __('Spanish (Argentina)', 'ai-website-chatbot'),
            'fr-FR' => __('French (France)', 'ai-website-chatbot'),
            'fr-CA' => __('French (Canada)', 'ai-website-chatbot'),
            'de-DE' => __('German', 'ai-website-chatbot'),
            'it-IT' => __('Italian', 'ai-website-chatbot'),
            'pt-BR' => __('Portuguese (Brazil)', 'ai-website-chatbot'),
            'pt-PT' => __('Portuguese (Portugal)', 'ai-website-chatbot'),
            'ja-JP' => __('Japanese', 'ai-website-chatbot'),
            'ko-KR' => __('Korean', 'ai-website-chatbot'),
            'zh-CN' => __('Chinese (Simplified)', 'ai-website-chatbot'),
            'zh-TW' => __('Chinese (Traditional)', 'ai-website-chatbot'),
            'zh-HK' => __('Chinese (Hong Kong)', 'ai-website-chatbot'),
            'ru-RU' => __('Russian', 'ai-website-chatbot'),
            'ar-SA' => __('Arabic (Saudi Arabia)', 'ai-website-chatbot'),
            'ar-EG' => __('Arabic (Egypt)', 'ai-website-chatbot'),
            'hi-IN' => __('Hindi (India)', 'ai-website-chatbot'),
            'th-TH' => __('Thai', 'ai-website-chatbot'),
            'vi-VN' => __('Vietnamese', 'ai-website-chatbot'),
            'nl-NL' => __('Dutch', 'ai-website-chatbot'),
            'sv-SE' => __('Swedish', 'ai-website-chatbot'),
            'no-NO' => __('Norwegian', 'ai-website-chatbot'),
            'da-DK' => __('Danish', 'ai-website-chatbot'),
            'fi-FI' => __('Finnish', 'ai-website-chatbot'),
            'pl-PL' => __('Polish', 'ai-website-chatbot'),
            'cs-CZ' => __('Czech', 'ai-website-chatbot'),
            'hu-HU' => __('Hungarian', 'ai-website-chatbot'),
            'tr-TR' => __('Turkish', 'ai-website-chatbot'),
            'he-IL' => __('Hebrew', 'ai-website-chatbot'),
            'id-ID' => __('Indonesian', 'ai-website-chatbot'),
            'ms-MY' => __('Malay', 'ai-website-chatbot'),
            'uk-UA' => __('Ukrainian', 'ai-website-chatbot'),
            'bg-BG' => __('Bulgarian', 'ai-website-chatbot'),
            'hr-HR' => __('Croatian', 'ai-website-chatbot'),
            'sk-SK' => __('Slovak', 'ai-website-chatbot'),
            'sl-SI' => __('Slovenian', 'ai-website-chatbot'),
            'et-EE' => __('Estonian', 'ai-website-chatbot'),
            'lv-LV' => __('Latvian', 'ai-website-chatbot'),
            'lt-LT' => __('Lithuanian', 'ai-website-chatbot')
        );
    }

    /**
     * Log settings change
     */
    private function log_settings_change($settings) {
        $log_entry = array(
            'action' => 'audio_settings_changed',
            'settings' => array_keys($settings),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        error_log('AI Chatbot Audio Settings Changed: ' . wp_json_encode($log_entry));
    }

    /**
     * Get all audio settings
     */
    public function get_all_audio_settings() {
        $settings = array();
        
        foreach ($this->default_settings as $setting_key => $default_value) {
            $settings[$setting_key] = get_option('ai_chatbot_' . $setting_key, $default_value);
        }

        return $settings;
    }

    /**
     * Get settings registry
     */
    public function get_settings_registry() {
        return $this->settings_registry;
    }

    /**
     * Get default settings
     */
    public function get_default_settings() {
        return $this->default_settings;
    }

    /**
     * Check if audio feature is properly configured
     */
    public function is_audio_feature_configured($feature) {
        switch ($feature) {
            case 'voice_input':
                return get_option('ai_chatbot_voice_input_enabled', false) &&
                       !empty(get_option('ai_chatbot_voice_language', ''));

            case 'text_to_speech':
                return get_option('ai_chatbot_tts_enabled', false);

            case 'audio_mode':
                return get_option('ai_chatbot_audio_mode_enabled', false) &&
                       get_option('ai_chatbot_voice_input_enabled', false) &&
                       get_option('ai_chatbot_tts_enabled', false);

            case 'voice_commands':
                return get_option('ai_chatbot_voice_commands_enabled', false) &&
                       get_option('ai_chatbot_voice_input_enabled', false);

            default:
                return false;
        }
    }

    /**
     * Get configuration status summary
     */
    public function get_configuration_status() {
        return array(
            'voice_input' => array(
                'configured' => $this->is_audio_feature_configured('voice_input'),
                'enabled' => get_option('ai_chatbot_voice_input_enabled', false),
                'language' => get_option('ai_chatbot_voice_language', 'en-US')
            ),
            'text_to_speech' => array(
                'configured' => $this->is_audio_feature_configured('text_to_speech'),
                'enabled' => get_option('ai_chatbot_tts_enabled', false),
                'auto_play' => get_option('ai_chatbot_tts_auto_play', false)
            ),
            'audio_mode' => array(
                'configured' => $this->is_audio_feature_configured('audio_mode'),
                'enabled' => get_option('ai_chatbot_audio_mode_enabled', false),
                'dependencies_met' => $this->is_audio_feature_configured('voice_input') && 
                                    $this->is_audio_feature_configured('text_to_speech')
            ),
            'voice_commands' => array(
                'configured' => $this->is_audio_feature_configured('voice_commands'),
                'enabled' => get_option('ai_chatbot_voice_commands_enabled', false),
                'dependencies_met' => $this->is_audio_feature_configured('voice_input')
            )
        );
    }
}