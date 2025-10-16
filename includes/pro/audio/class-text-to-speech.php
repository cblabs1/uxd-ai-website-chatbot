<?php
/**
 * AI Chatbot Pro Text-to-Speech
 * Advanced TTS functionality for Pro version
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
 * AI_Chatbot_Pro_Text_To_Speech class
 * Handles advanced text-to-speech functionality
 */
class AI_Chatbot_Pro_Text_To_Speech {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tts_scripts'));
        add_filter('ai_chatbot_response_data', array($this, 'add_tts_metadata'), 10, 2);
        add_action('wp_ajax_ai_chatbot_tts_preview', array($this, 'preview_tts'));
        add_action('wp_ajax_nopriv_ai_chatbot_tts_preview', array($this, 'preview_tts'));
    }

    /**
     * Enqueue TTS scripts
     */
    public function enqueue_tts_scripts() {
        if (!get_option('ai_chatbot_tts_enabled', false)) {
            return;
        }

        wp_enqueue_script(
            'ai-chatbot-pro-tts',
            AI_CHATBOT_PLUGIN_URL . 'assets/js/public/pro/audio/text-to-speech.js',
            array('ai-chatbot-pro-audio-core'),
            AI_CHATBOT_VERSION,
            true
        );

        // TTS configuration
        wp_localize_script('ai-chatbot-pro-tts', 'aiChatbotTTS', array(
            'config' => $this->get_tts_configuration(),
            'voices' => $this->get_available_voices(),
            'ssml_support' => $this->check_ssml_support()
        ));
    }

    /**
     * Get TTS configuration
     */
    private function get_tts_configuration() {

        $settings = get_option('ai_chatbot_settings', array());
        $audio_settings = $settings['audio_features'] ?? array();

        return array(
            'enabled' => !empty($audio_settings['tts_enabled']),
            'auto_play' => !empty($audio_settings['tts_auto_play']),
            'voice_name' => $audio_settings['tts_voice_name'] ?? '',
            'rate' => $audio_settings['tts_rate'] ?? 1.0,
            'pitch' => $audio_settings['tts_pitch'] ?? 1.0,
            'volume' => $audio_settings['tts_volume'] ?? 0.8,
            'language' => $audio_settings['tts_language'] ?? 'en-US',
            
            // Pro features
            'emotional_tone' => $audio_settings['tts_emotional_tone'] ?? false,
            'speaking_style' => $audio_settings['tts_speaking_style'] ?? 'neutral',        
            'pause_detection' => $audio_settings['tts_pause_detection'] ?? true,
            'pronunciation_hints' => $audio_settings['tts_pronunciation_hints'] ?? true,
            'ssml_enabled' => $audio_settings['tts_ssml_enabled'] ?? false,
            'background_music' => $audio_settings['tts_background_music'] ?? false,

            'voice_selection' => array(
                'enabled' => !empty($audio_settings['voice_selection_enabled']),
                'admin_defaults' => array(
                    'gender' => $audio_settings['voice_gender'] ?? 'female',
                    'language' => $audio_settings['voice_language'] ?? 'en-US',
                    'specific_voice' => $audio_settings['specific_voice'] ?? '',
                    'personality' => $audio_settings['voice_personality'] ?? 'friendly',
                ),
            ),
            
            // Advanced settings
            'chunk_size' => $audio_settings['tts_chunk_size'] ?? 200,
            'chunk_pause' => $audio_settings['tts_chunk_pause'] ?? 0.5,
            'pause_between_chunks' => $audio_settings['tts_chunk_pause'] ?? 0.5,
            'emphasis_words' => $audio_settings['tts_emphasis_words'] ?? array(),
            'custom_pronunciations' => $audio_settings['tts_custom_pronunciations'] ?? array()
        );
    }

    /**
     * Get available voices
     */
    private function get_available_voices() {
        // This would be populated by JavaScript, but we can provide server-side defaults
        return array(
            'categories' => array(
                'male_voices' => __('Male Voices', 'ai-website-chatbot'),
                'female_voices' => __('Female Voices', 'ai-website-chatbot'),
                'neutral_voices' => __('Neutral Voices', 'ai-website-chatbot')
            ),
            'preferred_voices' => array(
                'en-US' => array('Google US English', 'Microsoft David', 'Microsoft Zira'),
                'en-GB' => array('Google UK English Male', 'Google UK English Female'),
                'es-ES' => array('Google español', 'Microsoft Helena'),
                'fr-FR' => array('Google français', 'Microsoft Hortense'),
                'de-DE' => array('Google Deutsch', 'Microsoft Hedda'),
                'it-IT' => array('Google italiano', 'Microsoft Elsa'),
                'ja-JP' => array('Google 日本語', 'Microsoft Haruka'),
                'ko-KR' => array('Google 한국의', 'Microsoft Heami'),
                'zh-CN' => array('Google 普通话（中国大陆）', 'Microsoft Huihui')
            )
        );
    }

    /**
     * Check SSML support
     */
    private function check_ssml_support() {
        return array(
            'supported_tags' => array(
                'speak', 'break', 'emphasis', 'prosody', 'say-as', 'phoneme', 'sub'
            ),
            'supported_attributes' => array(
                'rate', 'pitch', 'volume', 'voice', 'lang'
            )
        );
    }

    /**
     * Add TTS metadata to response
     */
    public function add_tts_metadata($response_data, $message) {
        if (!is_array($response_data) || !get_option('ai_chatbot_tts_enabled', false)) {
            return $response_data;
        }

        $response_text = $response_data['response'] ?? '';
        if (empty($response_text)) {
            return $response_data;
        }

        // Analyze response for TTS optimization
        $tts_data = array(
            'should_speak' => $this->should_speak_response($response_data, $message),
            'speech_text' => $this->prepare_text_for_speech($response_text),
            'voice_settings' => $this->get_optimized_voice_settings($response_text, $message),
            'voice_selection' => $this->get_voice_selection_settings(),
            'chunks' => $this->split_into_speech_chunks($response_text),
            'timing' => $this->calculate_speech_timing($response_text),
            'emotional_cues' => $this->detect_emotional_cues($response_text),
            'pronunciation_guide' => $this->generate_pronunciation_guide($response_text)
        );

        // Add SSML if enabled
        if (get_option('ai_chatbot_tts_ssml_enabled', false)) {
            $tts_data['ssml'] = $this->generate_ssml($response_text, $tts_data['emotional_cues']);
        }

        $response_data['tts'] = $tts_data;
        return $response_data;
    }

    /**
     * Determine if response should be spoken
     */
    private function should_speak_response($response_data, $message) {
        // Check basic TTS settings
        if (!get_option('ai_chatbot_tts_enabled', false)) {
            return false;
        }

        // Check user preferences
        $user_prefs = $this->get_user_tts_preferences();
        if (isset($user_prefs['tts_enabled']) && !$user_prefs['tts_enabled']) {
            return false;
        }

        // Check auto-play setting
        $auto_play = get_option('ai_chatbot_tts_auto_play', false);
        
        // Pro logic: Intelligent auto-play based on context
        if (!$auto_play && get_option('ai_chatbot_tts_smart_autoplay', false)) {
            $auto_play = $this->should_smart_autoplay($response_data, $message);
        }

        return $auto_play;
    }

    /**
     * Smart auto-play logic
     */
    private function should_smart_autoplay($response_data, $message) {
        // Auto-play for short responses
        $response_length = strlen($response_data['response'] ?? '');
        if ($response_length < 100) {
            return true;
        }

        // Auto-play for urgent/important responses
        $urgent_keywords = array('urgent', 'important', 'warning', 'error', 'help', 'emergency');
        foreach ($urgent_keywords as $keyword) {
            if (stripos($response_data['response'], $keyword) !== false) {
                return true;
            }
        }

        // Auto-play for questions
        if (substr(trim($response_data['response']), -1) === '?') {
            return true;
        }

        return false;
    }

    /**
     * Prepare text for speech with Pro enhancements
     */
    private function prepare_text_for_speech($text) {
        // Remove markdown and HTML
        $text = $this->clean_markup($text);
        
        // Apply pronunciation improvements
        $text = $this->apply_pronunciation_rules($text);
        
        // Handle special formatting
        $text = $this->handle_special_formatting($text);
        
        // Apply custom pronunciations
        $text = $this->apply_custom_pronunciations($text);
        
        // Clean and normalize
        $text = $this->normalize_for_speech($text);
        
        return $text;
    }

    /**
     * Clean markup from text
     */
    private function clean_markup($text) {
        // Remove markdown
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text); // Bold
        $text = preg_replace('/\*(.*?)\*/', '$1', $text); // Italic
        $text = preg_replace('/`(.*?)`/', '$1', $text); // Code
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // Links
        
        // Remove HTML
        $text = wp_strip_all_tags($text);
        
        return $text;
    }

    /**
     * Apply pronunciation rules
     */
    private function apply_pronunciation_rules($text) {
        // Common abbreviations and their pronunciations
        $pronunciations = array(
            'AI' => 'Artificial Intelligence',
            'API' => 'A P I',
            'URL' => 'U R L',
            'HTTP' => 'H T T P',
            'HTTPS' => 'H T T P S',
            'CSS' => 'C S S',
            'HTML' => 'H T M L',
            'JS' => 'JavaScript',
            'PHP' => 'P H P',
            'SQL' => 'S Q L',
            'UI' => 'User Interface',
            'UX' => 'User Experience',
            'FAQ' => 'Frequently Asked Questions',
            'CEO' => 'C E O',
            'CTO' => 'C T O',
            'SEO' => 'Search Engine Optimization',
            'SaaS' => 'Software as a Service',
            'B2B' => 'Business to Business',
            'B2C' => 'Business to Consumer',
            'ROI' => 'Return on Investment',
            'KPI' => 'Key Performance Indicator',
            'GDP' => 'G D P',
            'USA' => 'United States',
            'UK' => 'United Kingdom',
            'EU' => 'European Union',
            'UNESCO' => 'U N E S C O',
            'WHO' => 'World Health Organization',
            'NASA' => 'N A S A',
            'FBI' => 'F B I',
            'CIA' => 'C I A',
            'GPS' => 'G P S',
            'WiFi' => 'Wi-Fi',
            'Bluetooth' => 'Bluetooth',
            'iOS' => 'i O S',
            'macOS' => 'mac O S',
            'WordPress' => 'WordPress',
            'jQuery' => 'jQuery',
            'GitHub' => 'GitHub',
            'LinkedIn' => 'LinkedIn',
            'YouTube' => 'YouTube',
            'vs' => 'versus',
            'vs.' => 'versus',
            'etc' => 'etcetera',
            'etc.' => 'etcetera',
            'e.g.' => 'for example',
            'i.e.' => 'that is',
            'Mr.' => 'Mister',
            'Mrs.' => 'Missus',
            'Dr.' => 'Doctor',
            'Prof.' => 'Professor'
        );

        // Apply custom pronunciations from settings
        $custom_pronunciations = get_option('ai_chatbot_tts_custom_pronunciations', array());
        $pronunciations = array_merge($pronunciations, $custom_pronunciations);

        foreach ($pronunciations as $abbr => $pronunciation) {
            // Use word boundaries to avoid partial matches
            $pattern = '/\b' . preg_quote($abbr, '/') . '\b/';
            $text = preg_replace($pattern, $pronunciation, $text);
        }

        return $text;
    }

    /**
     * Get voice selection settings
     */
    private function get_voice_selection_settings() {
        $settings = get_option('ai_chatbot_settings', array());
        $audio_settings = $settings['audio_features'] ?? array();
        
        return array(
            'enabled' => !empty($audio_settings['voice_selection_enabled']),
            'admin_defaults' => array(
                'gender' => $audio_settings['voice_gender'] ?? 'female',
                'language' => $audio_settings['voice_language'] ?? 'en-US',
                'specific_voice' => $audio_settings['specific_voice'] ?? '',
                'personality' => $audio_settings['voice_personality'] ?? 'friendly',
            ),
            'user_preferences' => $this->get_user_voice_preferences(),
        );
    }

    /**
     * Get user voice preferences
     */
    private function get_user_voice_preferences() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return get_user_meta($user_id, 'ai_chatbot_voice_preferences', true) ?: array();
        } else {
            if (!session_id()) {
                session_start();
            }
            return $_SESSION['ai_chatbot_voice_preferences'] ?? array();
        }
    }

    /**
     * Handle special formatting
     */
    private function handle_special_formatting($text) {
        // Numbers and currencies
        $text = preg_replace('/\$(\d+(?:,\d{3})*(?:\.\d{2})?)/', '$1 dollars', $text);
        $text = preg_replace('/€(\d+(?:,\d{3})*(?:\.\d{2})?)/', '$1 euros', $text);
        $text = preg_replace('/£(\d+(?:,\d{3})*(?:\.\d{2})?)/', '$1 pounds', $text);
        
        // Percentages
        $text = preg_replace('/(\d+(?:\.\d+)?)%/', '$1 percent', $text);
        
        // Phone numbers
        $text = preg_replace('/\b\d{3}-\d{3}-\d{4}\b/', 'phone number', $text);
        
        // Email addresses
        $text = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', 'email address', $text);
        
        // URLs
        $text = preg_replace('/https?:\/\/[^\s]+/', 'web link', $text);
        
        // Dates
        $text = preg_replace('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', 'the date $1 $2 $3', $text);
        
        // Times
        $text = preg_replace('/\b(\d{1,2}):(\d{2})\s*(AM|PM)\b/i', '$1 $2 $3', $text);
        
        return $text;
    }

    /**
     * Apply custom pronunciations
     */
    private function apply_custom_pronunciations($text) {
        $custom_pronunciations = get_option('ai_chatbot_tts_custom_pronunciations', array());
        
        foreach ($custom_pronunciations as $word => $pronunciation) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            $text = preg_replace($pattern, $pronunciation, $text);
        }
        
        return $text;
    }

    /**
     * Normalize text for speech
     */
    private function normalize_for_speech($text) {
        // Clean up extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Ensure proper sentence endings
        $text = preg_replace('/([.!?])\s*([A-Z])/', '$1 $2', $text);
        
        // Add pauses for better speech flow
        if (get_option('ai_chatbot_tts_pause_detection', true)) {
            $text = $this->add_natural_pauses($text);
        }
        
        return trim($text);
    }

    /**
     * Add natural pauses
     */
    private function add_natural_pauses($text) {
        // Add short pauses after commas
        $text = str_replace(',', ', ', $text);
        
        // Add medium pauses after colons and semicolons
        $text = str_replace(':', ': ', $text);
        $text = str_replace(';', '; ', $text);
        
        // Add longer pauses after sentence endings
        $text = preg_replace('/([.!?])\s+/', '$1  ', $text);
        
        return $text;
    }

    /**
     * Get optimized voice settings
     */
    private function get_optimized_voice_settings($text, $message) {
        
        $settings = get_option('ai_chatbot_settings', array());
        $audio_settings = $settings['audio_features'] ?? array();

        $base_settings = array(
            'rate' => $audio_settings['tts_rate'] ?? 1.0,
            'pitch' => $audio_settings['tts_pitch'] ?? 1.0,
            'volume' => $audio_settings['tts_volume'] ?? 0.8,
            'voice' => $audio_settings['tts_voice_name'] ?? '',
            'language' => $audio_settings['tts_language'] ?? 'en-US',
        );

        // Optimize based on content type
        $content_type = $this->analyze_content_type($text);
        switch ($content_type) {
            case 'technical':
                $base_settings['rate'] = max(0.7, $base_settings['rate'] - 0.2);
                break;
            case 'urgent':
                $base_settings['rate'] = min(1.5, $base_settings['rate'] + 0.2);
                $base_settings['pitch'] = min(1.2, $base_settings['pitch'] + 0.1);
                break;
            case 'friendly':
                $base_settings['pitch'] = min(1.1, $base_settings['pitch'] + 0.05);
                break;
        }

        // Adjust for text length
        $text_length = strlen($text);
        if ($text_length > 500) {
            $base_settings['rate'] = min(1.2, $base_settings['rate'] + 0.1);
        } elseif ($text_length < 50) {
            $base_settings['rate'] = max(0.8, $base_settings['rate'] - 0.1);
        }

        return $base_settings;
    }

    /**
     * Analyze content type
     */
    private function analyze_content_type($text) {
        // Technical content indicators
        $technical_keywords = array('API', 'code', 'function', 'database', 'server', 'configure', 'install', 'debug');
        foreach ($technical_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'technical';
            }
        }

        // Urgent content indicators
        $urgent_keywords = array('urgent', 'important', 'warning', 'error', 'immediately', 'emergency', 'critical');
        foreach ($urgent_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'urgent';
            }
        }

        // Friendly content indicators
        $friendly_keywords = array('thank', 'welcome', 'please', 'help', 'happy', 'glad', 'appreciate');
        foreach ($friendly_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'friendly';
            }
        }

        return 'neutral';
    }

    /**
     * Split into speech chunks
     */
    private function split_into_speech_chunks($text) {

        $settings = get_option('ai_chatbot_settings', array());
        $audio_settings = $settings['audio_features'] ?? array();

        $chunk_size = $audio_settings['tts_chunk_size'] ?? 200;
        
        // Split by sentences first
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = array();
        $current_chunk = '';

        foreach ($sentences as $sentence) {
            if (strlen($current_chunk . $sentence) <= $chunk_size) {
                $current_chunk .= ($current_chunk ? ' ' : '') . $sentence;
            } else {
                if ($current_chunk) {
                    $chunks[] = trim($current_chunk);
                }
                $current_chunk = $sentence;
            }
        }

        if ($current_chunk) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Calculate speech timing
     */
    private function calculate_speech_timing($text) {
        $word_count = str_word_count($text);
        $rate = get_option('ai_chatbot_tts_rate', 1.0);
        
        
        // Average speaking rate: 150-200 words per minute
        $base_wpm = 175;
        $adjusted_wpm = $base_wpm * $rate;
        
        $duration_minutes = $word_count / $adjusted_wpm;
        $duration_seconds = $duration_minutes * 60;

        return array(
            'estimated_duration' => round($duration_seconds, 1),
            'word_count' => $word_count,
            'speaking_rate' => $adjusted_wpm,
            'chunks_count' => count($this->split_into_speech_chunks($text))
        );
    }

    /**
     * Detect emotional cues
     */
    private function detect_emotional_cues($text) {
        $emotions = array(
            'happy' => array('great', 'excellent', 'wonderful', 'fantastic', 'amazing', 'awesome', 'good'),
            'sad' => array('sorry', 'unfortunately', 'sad', 'disappointed', 'regret'),
            'excited' => array('exciting', 'incredible', 'wow', 'fantastic', 'amazing'),
            'concerned' => array('warning', 'careful', 'attention', 'important', 'serious'),
            'neutral' => array()
        );

        $detected_emotions = array();
        $text_lower = strtolower($text);

        foreach ($emotions as $emotion => $keywords) {
            $matches = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $matches++;
                }
            }
            if ($matches > 0) {
                $detected_emotions[$emotion] = $matches;
            }
        }

        // Return dominant emotion
        if (!empty($detected_emotions)) {
            arsort($detected_emotions);
            return array(
                'primary_emotion' => key($detected_emotions),
                'confidence' => reset($detected_emotions) / str_word_count($text),
                'all_emotions' => $detected_emotions
            );
        }

        return array(
            'primary_emotion' => 'neutral',
            'confidence' => 1.0,
            'all_emotions' => array('neutral' => 1)
        );
    }

    /**
     * Generate pronunciation guide
     */
    private function generate_pronunciation_guide($text) {
        $guide = array();
        
        // Find potentially difficult words
        $difficult_patterns = array(
            '/\b[A-Z]{2,}\b/', // Acronyms
            '/\b\w*[0-9]\w*\b/', // Words with numbers
            '/\b\w{10,}\b/', // Long words
            '/\b[A-Z][a-z]*[A-Z][a-z]*\b/' // CamelCase
        );

        foreach ($difficult_patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $guide[$match] = $this->get_pronunciation_hint($match);
            }
        }

        return $guide;
    }

    /**
     * Get pronunciation hint for a word
     */
    private function get_pronunciation_hint($word) {
        // Check custom pronunciations first
        $custom = get_option('ai_chatbot_tts_custom_pronunciations', array());
        if (isset($custom[$word])) {
            return $custom[$word];
        }

        // Basic pronunciation rules
        if (ctype_upper($word)) {
            // Acronym - spell out
            return implode(' ', str_split($word));
        }

        if (preg_match('/[0-9]/', $word)) {
            // Contains numbers - handle specially
            return preg_replace('/([0-9])/', ' $1 ', $word);
        }

        if (preg_match('/[A-Z][a-z]*[A-Z]/', $word)) {
            // CamelCase - split on capitals
            return preg_replace('/([a-z])([A-Z])/', '$1 $2', $word);
        }

        return $word; // No special pronunciation needed
    }

    /**
     * Generate SSML markup
     */
    private function generate_ssml($text, $emotional_cues) {
        if (!get_option('ai_chatbot_tts_ssml_enabled', false)) {
            return null;
        }

        $ssml = '<speak>';

        // Add voice selection if specified
        
        $voice_name = get_option('ai_chatbot_tts_voice_name', '');

        if ($voice_name) {
            $ssml .= '<voice name="' . esc_attr($voice_name) . '">';
        }

        // Apply emotional prosody
        $emotion = $emotional_cues['primary_emotion'] ?? 'neutral';
        $prosody_attrs = $this->get_emotion_prosody($emotion);
        
        if (!empty($prosody_attrs)) {
            $ssml .= '<prosody ' . implode(' ', $prosody_attrs) . '>';
            $ssml .= $this->add_ssml_markup($text);
            $ssml .= '</prosody>';
        } else {
            $ssml .= $this->add_ssml_markup($text);
        }

        if ($voice_name) {
            $ssml .= '</voice>';
        }

        $ssml .= '</speak>';

        return $ssml;
    }

    /**
     * Get prosody attributes for emotion
     */
    private function get_emotion_prosody($emotion) {
        $prosody_map = array(
            'happy' => array('rate="medium"', 'pitch="+10%"'),
            'sad' => array('rate="slow"', 'pitch="-10%"'),
            'excited' => array('rate="fast"', 'pitch="+20%"', 'volume="loud"'),
            'concerned' => array('rate="slow"', 'pitch="-5%"'),
            'neutral' => array()
        );

        return $prosody_map[$emotion] ?? array();
    }

    /**
     * Add SSML markup to text
     */
    private function add_ssml_markup($text) {
        // Add breaks for punctuation
        $text = preg_replace('/[,;]/', '<break time="0.3s"/>', $text);
        $text = preg_replace('/[.!?]/', '<break time="0.5s"/>', $text);

        // Emphasize certain words
        $emphasis_words = get_option('ai_chatbot_tts_emphasis_words', array());
        foreach ($emphasis_words as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            $text = preg_replace($pattern, '<emphasis level="strong">$0</emphasis>', $text);
        }

        // Handle numbers and dates with say-as
        $text = preg_replace('/\b(\d{4})\b/', '<say-as interpret-as="date" format="y">$1</say-as>', $text);
        $text = preg_replace('/\b(\d+)\b/', '<say-as interpret-as="number">$1</say-as>', $text);

        return $text;
    }

    /**
     * Preview TTS
     */
    public function preview_tts() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_chatbot_audio_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $text = sanitize_textarea_field($_POST['text'] ?? '');
        $voice_settings = $_POST['voice_settings'] ?? array();

        if (empty($text)) {
            wp_send_json_error('No text provided');
        }

        // Process text for TTS
        $processed_text = $this->prepare_text_for_speech($text);
        $chunks = $this->split_into_speech_chunks($processed_text);
        $timing = $this->calculate_speech_timing($processed_text);
        $emotional_cues = $this->detect_emotional_cues($processed_text);

        // Generate preview data
        $preview_data = array(
            'original_text' => $text,
            'processed_text' => $processed_text,
            'chunks' => $chunks,
            'timing' => $timing,
            'emotional_cues' => $emotional_cues,
            'voice_settings' => array_merge($this->get_tts_configuration(), $voice_settings)
        );

        // Add SSML if enabled
        if (get_option('ai_chatbot_tts_ssml_enabled', false)) {
            $preview_data['ssml'] = $this->generate_ssml($processed_text, $emotional_cues);
        }

        wp_send_json_success($preview_data);
    }

    /**
     * Get user TTS preferences
     */
    private function get_user_tts_preferences() {
        if (is_user_logged_in()) {
            return get_user_meta(get_current_user_id(), 'ai_chatbot_tts_preferences', true) ?: array();
        } else {
            if (!session_id()) {
                session_start();
            }
            return $_SESSION['ai_chatbot_tts_preferences'] ?? array();
        }
    }

    /**
     * Get TTS statistics
     */
    public function get_tts_statistics() {
        return array(
            'total_speeches' => get_option('ai_chatbot_tts_total_speeches', 0),
            'total_duration' => get_option('ai_chatbot_tts_total_duration', 0),
            'average_speech_length' => get_option('ai_chatbot_tts_avg_length', 0),
            'most_used_voice' => get_option('ai_chatbot_tts_popular_voice', ''),
            'emotion_distribution' => get_option('ai_chatbot_tts_emotion_stats', array()),
            'user_satisfaction' => get_option('ai_chatbot_tts_user_rating', 0)
        );
    }

    /**
     * Update TTS statistics
     */
    public function update_tts_statistics($text, $duration, $emotion) {
        // Update counters
        $total = get_option('ai_chatbot_tts_total_speeches', 0) + 1;
        update_option('ai_chatbot_tts_total_speeches', $total);

        $total_duration = get_option('ai_chatbot_tts_total_duration', 0) + $duration;
        update_option('ai_chatbot_tts_total_duration', $total_duration);

        $avg_length = $total_duration / $total;
        update_option('ai_chatbot_tts_avg_length', $avg_length);

        // Track emotion usage
        $emotion_stats = get_option('ai_chatbot_tts_emotion_stats', array());
        $emotion_stats[$emotion] = ($emotion_stats[$emotion] ?? 0) + 1;
        update_option('ai_chatbot_tts_emotion_stats', $emotion_stats);
    }
}