<?php
/**
 * AI Chatbot Pro Voice Input
 * Enhanced voice input functionality for Pro version
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
 * AI_Chatbot_Pro_Voice_Input class
 * Extends basic voice input with Pro features
 */
class AI_Chatbot_Pro_Voice_Input {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_voice_scripts'));
        add_filter('ai_chatbot_voice_config', array($this, 'enhance_voice_config'));
        add_action('wp_ajax_ai_chatbot_voice_process', array($this, 'process_voice_input'));
        add_action('wp_ajax_nopriv_ai_chatbot_voice_process', array($this, 'process_voice_input'));
    }

    /**
     * Enqueue voice input scripts
     */
    public function enqueue_voice_scripts() {
        if (!get_option('ai_chatbot_voice_input_enabled', false)) {
            return;
        }

        wp_enqueue_script(
            'ai-chatbot-pro-voice-enhanced',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/voice-enhanced.js',
            array('ai-chatbot-pro-audio-core'),
            AI_CHATBOT_VERSION,
            true
        );

        // Voice input configuration
        wp_localize_script('ai-chatbot-pro-voice-enhanced', 'aiChatbotVoice', array(
            'config' => $this->get_voice_configuration(),
            'languages' => $this->get_supported_languages(),
            'commands' => $this->get_voice_processing_commands()
        ));
    }

    /**
     * Get voice configuration
     */
    private function get_voice_configuration() {
        return array(
            'language' => get_option('ai_chatbot_voice_language', 'en-US'),
            'continuous' => get_option('ai_chatbot_voice_continuous', false),
            'interim_results' => get_option('ai_chatbot_voice_interim_results', true),
            'max_alternatives' => get_option('ai_chatbot_voice_max_alternatives', 1),
            'noise_suppression' => get_option('ai_chatbot_voice_noise_suppression', true),
            'auto_gain_control' => get_option('ai_chatbot_voice_auto_gain_control', true),
            'echo_cancellation' => get_option('ai_chatbot_voice_echo_cancellation', true)
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
            'es-ES' => __('Spanish (Spain)', 'ai-website-chatbot'),
            'es-MX' => __('Spanish (Mexico)', 'ai-website-chatbot'),
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
            'ru-RU' => __('Russian', 'ai-website-chatbot'),
            'ar-SA' => __('Arabic', 'ai-website-chatbot'),
            'hi-IN' => __('Hindi', 'ai-website-chatbot'),
            'th-TH' => __('Thai', 'ai-website-chatbot'),
            'vi-VN' => __('Vietnamese', 'ai-website-chatbot')
        );
    }

    /**
     * Get voice processing commands
     */
    private function get_voice_processing_commands() {
        return array(
            'grammar' => array(
                'enabled' => get_option('ai_chatbot_voice_grammar_enabled', false),
                'corrections' => get_option('ai_chatbot_voice_auto_corrections', true)
            ),
            'punctuation' => array(
                'auto_punctuation' => get_option('ai_chatbot_voice_auto_punctuation', true),
                'smart_capitalization' => get_option('ai_chatbot_voice_smart_caps', true)
            ),
            'filtering' => array(
                'profanity_filter' => get_option('ai_chatbot_voice_profanity_filter', false),
                'noise_words_removal' => get_option('ai_chatbot_voice_noise_removal', true)
            )
        );
    }

    /**
     * Enhance voice configuration
     */
    public function enhance_voice_config($config) {
        // Add Pro-specific voice features
        $config['pro_features'] = array(
            'multi_language_support' => true,
            'advanced_noise_suppression' => true,
            'voice_command_recognition' => true,
            'custom_grammar_support' => true,
            'voice_biometrics' => get_option('ai_chatbot_voice_biometrics', false)
        );

        // Add enhanced settings
        $config['advanced'] = array(
            'silence_detection_timeout' => get_option('ai_chatbot_voice_silence_timeout', 2000),
            'confidence_threshold' => get_option('ai_chatbot_voice_confidence_threshold', 0.7),
            'background_noise_threshold' => get_option('ai_chatbot_voice_noise_threshold', 0.3)
        );

        return $config;
    }

    /**
     * Process voice input with Pro enhancements
     */
    public function process_voice_input() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $raw_transcript = sanitize_textarea_field($_POST['transcript'] ?? '');
        $confidence = floatval($_POST['confidence'] ?? 0.0);
        $language = sanitize_text_field($_POST['language'] ?? 'en-US');

        // Apply Pro voice processing
        $processed_transcript = $this->process_voice_transcript($raw_transcript, $confidence, $language);

        // Check for voice commands
        $command_result = $this->check_voice_commands($processed_transcript);
        if ($command_result) {
            wp_send_json_success(array(
                'type' => 'command',
                'command' => $command_result['command'],
                'action' => $command_result['action'],
                'processed_text' => $processed_transcript
            ));
        }

        // Return processed transcript
        wp_send_json_success(array(
            'type' => 'message',
            'processed_text' => $processed_transcript,
            'original_text' => $raw_transcript,
            'confidence' => $confidence,
            'language' => $language,
            'processing_applied' => $this->get_processing_applied($raw_transcript, $processed_transcript)
        ));
    }

    /**
     * Process voice transcript with Pro enhancements
     */
    private function process_voice_transcript($transcript, $confidence, $language) {
        // Start with original transcript
        $processed = $transcript;

        // Apply confidence-based filtering
        if ($confidence < get_option('ai_chatbot_voice_confidence_threshold', 0.7)) {
            // Low confidence - apply more aggressive processing
            $processed = $this->apply_confidence_corrections($processed);
        }

        // Auto-punctuation
        if (get_option('ai_chatbot_voice_auto_punctuation', true)) {
            $processed = $this->add_auto_punctuation($processed);
        }

        // Smart capitalization
        if (get_option('ai_chatbot_voice_smart_caps', true)) {
            $processed = $this->apply_smart_capitalization($processed);
        }

        // Grammar corrections
        if (get_option('ai_chatbot_voice_auto_corrections', true)) {
            $processed = $this->apply_grammar_corrections($processed, $language);
        }

        // Remove noise words
        if (get_option('ai_chatbot_voice_noise_removal', true)) {
            $processed = $this->remove_noise_words($processed);
        }

        // Profanity filter
        if (get_option('ai_chatbot_voice_profanity_filter', false)) {
            $processed = $this->apply_profanity_filter($processed);
        }

        return trim($processed);
    }

    /**
     * Apply confidence-based corrections
     */
    private function apply_confidence_corrections($text) {
        // Common misrecognitions and corrections
        $corrections = array(
            // Common speech-to-text errors
            'there' => 'their',
            'your' => 'you\'re',
            'to' => 'too',
            'its' => 'it\'s',
            // Add more based on common voice recognition errors
        );

        // Apply corrections with context awareness
        foreach ($corrections as $wrong => $right) {
            // Only apply if it makes contextual sense
            if ($this->should_apply_correction($text, $wrong, $right)) {
                $text = str_replace($wrong, $right, $text);
            }
        }

        return $text;
    }

    /**
     * Add automatic punctuation
     */
    private function add_auto_punctuation($text) {
        // Question patterns
        $question_patterns = array(
            '/^(what|who|when|where|why|how|is|are|can|could|would|will|do|does|did)\s/i',
            '/\b(what|who|when|where|why|how)\s.*$/i'
        );

        foreach ($question_patterns as $pattern) {
            if (preg_match($pattern, $text) && !preg_match('/[.!?]$/', $text)) {
                $text .= '?';
                break;
            }
        }

        // Exclamation patterns
        $exclamation_patterns = array(
            '/^(wow|amazing|great|awesome|fantastic|terrible|help|stop)\b/i',
            '/\b(thank you|thanks|please help|emergency)\b/i'
        );

        foreach ($exclamation_patterns as $pattern) {
            if (preg_match($pattern, $text) && !preg_match('/[.!?]$/', $text)) {
                $text .= '!';
                break;
            }
        }

        // Default to period if no punctuation
        if (!preg_match('/[.!?]$/', $text) && strlen($text) > 10) {
            $text .= '.';
        }

        return $text;
    }

    /**
     * Apply smart capitalization
     */
    private function apply_smart_capitalization($text) {
        // Capitalize first letter
        $text = ucfirst(strtolower($text));

        // Capitalize after punctuation
        $text = preg_replace('/([.!?]\s+)([a-z])/', '$1' . strtoupper('$2'), $text);

        // Capitalize proper nouns (basic implementation)
        $proper_nouns = array('I', 'AI', 'API', 'URL', 'HTML', 'CSS', 'JavaScript', 'WordPress');
        foreach ($proper_nouns as $noun) {
            $text = preg_replace('/\b' . strtolower($noun) . '\b/', $noun, $text);
        }

        return $text;
    }

    /**
     * Apply grammar corrections
     */
    private function apply_grammar_corrections($text, $language) {
        // Language-specific grammar rules
        switch ($language) {
            case 'en-US':
            case 'en-GB':
                return $this->apply_english_grammar($text);
            case 'es-ES':
            case 'es-MX':
                return $this->apply_spanish_grammar($text);
            default:
                return $text;
        }
    }

    /**
     * Apply English grammar corrections
     */
    private function apply_english_grammar($text) {
        // Basic English grammar corrections
        $corrections = array(
            '/\ba\s+([aeiou])/i' => 'an $1',  // a -> an before vowels
            '/\ban\s+([^aeiou])/i' => 'a $1',  // an -> a before consonants
            '/\bi\s+am\b/i' => 'I am',         // capitalize I
            '/\bi\s+/i' => 'I ',               // capitalize standalone I
        );

        foreach ($corrections as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Apply Spanish grammar corrections
     */
    private function apply_spanish_grammar($text) {
        // Basic Spanish grammar rules can be added here
        return $text;
    }

    /**
     * Remove noise words
     */
    private function remove_noise_words($text) {
        // Common filler words and vocal noises
        $noise_words = array(
            'um', 'uh', 'er', 'ah', 'hmm', 'like',
            'you know', 'I mean', 'sort of', 'kind of'
        );

        foreach ($noise_words as $noise) {
            $text = preg_replace('/\b' . preg_quote($noise) . '\b/i', '', $text);
        }

        // Clean up extra spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Apply profanity filter
     */
    private function apply_profanity_filter($text) {
        // Basic profanity filter - replace with asterisks
        $profanity_words = array(
            // Add profanity words as needed
            'damn', 'hell', 'crap'
        );

        foreach ($profanity_words as $word) {
            $replacement = str_repeat('*', strlen($word));
            $text = preg_replace('/\b' . preg_quote($word) . '\b/i', $replacement, $text);
        }

        return $text;
    }

    /**
     * Check for voice commands
     */
    private function check_voice_commands($text) {
        // Get voice commands from audio manager
        $audio_manager = AI_Chatbot_Pro_Audio_Manager::get_instance();
        $commands = $audio_manager->get_voice_commands();

        foreach ($commands as $command_key => $command_data) {
            foreach ($command_data['phrases'] as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    return array(
                        'command' => $command_key,
                        'action' => $command_data['action'],
                        'phrase' => $phrase,
                        'confidence' => $this->calculate_command_confidence($text, $phrase)
                    );
                }
            }
        }

        return false;
    }

    /**
     * Calculate command confidence
     */
    private function calculate_command_confidence($text, $phrase) {
        $text_words = explode(' ', strtolower($text));
        $phrase_words = explode(' ', strtolower($phrase));
        
        $matches = 0;
        foreach ($phrase_words as $word) {
            if (in_array($word, $text_words)) {
                $matches++;
            }
        }

        return $matches / count($phrase_words);
    }

    /**
     * Check if correction should be applied
     */
    private function should_apply_correction($text, $wrong, $right) {
        // Simple context check - can be enhanced with NLP
        $context_words = explode(' ', $text);
        $word_position = array_search($wrong, $context_words);
        
        if ($wrong === 'to' && $right === 'too') {
            // Check if "too" makes more sense in context
            $next_word = $context_words[$word_position + 1] ?? '';
            return in_array($next_word, array('much', 'many', 'late', 'early', 'fast', 'slow'));
        }

        return true; // Default to applying correction
    }

    /**
     * Get processing applied summary
     */
    private function get_processing_applied($original, $processed) {
        $applied = array();

        if ($original !== $processed) {
            if (strlen($processed) > strlen($original)) {
                $applied[] = 'punctuation_added';
            }
            if (ucfirst($processed) !== ucfirst($original)) {
                $applied[] = 'capitalization_applied';
            }
            if (str_word_count($processed) < str_word_count($original)) {
                $applied[] = 'noise_words_removed';
            }
            if (preg_match('/\*+/', $processed)) {
                $applied[] = 'profanity_filtered';
            }
        }

        return $applied;
    }

    /**
     * Get voice input statistics
     */
    public function get_voice_statistics() {
        return array(
            'total_voice_inputs' => get_option('ai_chatbot_voice_total_inputs', 0),
            'successful_recognitions' => get_option('ai_chatbot_voice_successful', 0),
            'average_confidence' => get_option('ai_chatbot_voice_avg_confidence', 0.0),
            'most_used_language' => get_option('ai_chatbot_voice_primary_language', 'en-US'),
            'command_usage' => get_option('ai_chatbot_voice_command_stats', array())
        );
    }

    /**
     * Update voice statistics
     */
    public function update_voice_statistics($transcript, $confidence, $language) {
        // Update counters
        $total = get_option('ai_chatbot_voice_total_inputs', 0) + 1;
        update_option('ai_chatbot_voice_total_inputs', $total);

        if ($confidence > 0.7) {
            $successful = get_option('ai_chatbot_voice_successful', 0) + 1;
            update_option('ai_chatbot_voice_successful', $successful);
        }

        // Update average confidence
        $current_avg = get_option('ai_chatbot_voice_avg_confidence', 0.0);
        $new_avg = (($current_avg * ($total - 1)) + $confidence) / $total;
        update_option('ai_chatbot_voice_avg_confidence', $new_avg);

        // Track language usage
        $language_stats = get_option('ai_chatbot_voice_language_stats', array());
        $language_stats[$language] = ($language_stats[$language] ?? 0) + 1;
        update_option('ai_chatbot_voice_language_stats', $language_stats);
    }
}