<?php
/**
 * AI Chatbot Intent Recognition
 * 
 * File: includes/pro/intelligence/class-intent-recognition.php
 * 
 * Advanced intent recognition using pattern matching and context analysis
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Intent Recognition Class - Analyzes user intent from messages
 */
class AI_Chatbot_Intent_Recognition {
    
    /**
     * Intent confidence threshold
     */
    private $confidence_threshold = 0.6;
    
    /**
     * Intent patterns cache
     */
    private $intent_patterns = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $sensitivity = get_option('ai_chatbot_intent_sensitivity', 'medium');
        $this->confidence_threshold = $this->get_threshold_by_sensitivity($sensitivity);
    }
    
    /**
     * Analyze user intent from message and context
     * 
     * @param string $message User message
     * @param string $context Additional context
     * @return array Intent analysis results
     */
    public function analyze_intent($message, $context = '') {
        // Clean and prepare message
        $clean_message = $this->clean_message($message);
        
        // Analyze different types of intent
        $primary_intents = $this->analyze_primary_intent($clean_message);
        $emotional_intent = $this->analyze_emotional_intent($clean_message);
        $urgency_level = $this->analyze_urgency_level($clean_message);
        $entities = $this->extract_entities($clean_message);
        
        // Apply context weighting
        if (!empty($context)) {
            $primary_intents = $this->apply_context_weighting($primary_intents, $context);
        }
        
        // Sort intents by confidence
        arsort($primary_intents);
        
        $top_intent = key($primary_intents);
        $confidence = $primary_intents[$top_intent];
        
        return array(
            'primary_intent' => $top_intent,
            'confidence' => $confidence,
            'all_intents' => $primary_intents,
            'emotional_state' => $emotional_intent,
            'urgency_level' => $urgency_level,
            'entities' => $entities,
            'requires_human' => $this->requires_human_intervention($primary_intents, $emotional_intent, $urgency_level),
            'suggested_actions' => $this->suggest_actions($top_intent, $entities, $urgency_level)
        );
    }
    
    /**
     * Analyze primary intent categories
     * 
     * @param string $message Clean message
     * @return array Intent scores
     */
    private function analyze_primary_intent($message) {
        $intent_patterns = $this->get_intent_patterns();
        $intent_scores = array();
        
        foreach ($intent_patterns as $intent => $patterns) {
            $score = 0;
            $max_score = count($patterns['keywords']) + count($patterns['phrases']);
            
            // Check keywords
            foreach ($patterns['keywords'] as $keyword => $weight) {
                if (strpos($message, $keyword) !== false) {
                    $score += $weight;
                }
            }
            
            // Check phrases
            foreach ($patterns['phrases'] as $phrase => $weight) {
                if (strpos($message, $phrase) !== false) {
                    $score += $weight;
                }
            }
            
            // Check patterns (regex)
            if (isset($patterns['patterns'])) {
                foreach ($patterns['patterns'] as $pattern => $weight) {
                    if (preg_match($pattern, $message)) {
                        $score += $weight;
                    }
                }
            }
            
            // Calculate confidence (0-1)
            $confidence = $max_score > 0 ? min(1.0, $score / $max_score) : 0;
            
            if ($confidence > 0) {
                $intent_scores[$intent] = $confidence;
            }
        }
        
        // Add default general intent if no strong matches
        if (empty($intent_scores) || max($intent_scores) < $this->confidence_threshold) {
            $intent_scores['general'] = 0.5;
        }
        
        return $intent_scores;
    }
    
    /**
     * Get intent patterns for matching
     * 
     * @return array Intent patterns
     */
    private function get_intent_patterns() {
        if ($this->intent_patterns !== null) {
            return $this->intent_patterns;
        }
        
        $this->intent_patterns = array(
            'greeting' => array(
                'keywords' => array(
                    'hello' => 1.0,
                    'hi' => 1.0,
                    'hey' => 1.0,
                    'good morning' => 1.0,
                    'good afternoon' => 1.0,
                    'good evening' => 1.0,
                    'greetings' => 1.0
                ),
                'phrases' => array(
                    'how are you' => 0.8,
                    'nice to meet you' => 0.8
                ),
                'patterns' => array(
                    '/^(hi|hello|hey)\s*[!.]*$/i' => 1.0
                )
            ),
            
            'question' => array(
                'keywords' => array(
                    'what' => 0.8,
                    'how' => 0.8,
                    'why' => 0.8,
                    'when' => 0.8,
                    'where' => 0.8,
                    'who' => 0.8,
                    'which' => 0.8
                ),
                'phrases' => array(
                    'can you tell me' => 0.9,
                    'do you know' => 0.9,
                    'can you explain' => 0.9,
                    'i want to know' => 0.9
                ),
                'patterns' => array(
                    '/\?/' => 0.7
                )
            ),
            
            'support' => array(
                'keywords' => array(
                    'help' => 1.0,
                    'problem' => 0.9,
                    'issue' => 0.9,
                    'error' => 0.9,
                    'broken' => 0.8,
                    'not working' => 0.9,
                    'trouble' => 0.8,
                    'fix' => 0.8,
                    'support' => 1.0
                ),
                'phrases' => array(
                    'need help' => 1.0,
                    'having trouble' => 0.9,
                    'something wrong' => 0.8,
                    'not sure how' => 0.7
                )
            ),
            
            'complaint' => array(
                'keywords' => array(
                    'complaint' => 1.0,
                    'disappointed' => 0.9,
                    'frustrated' => 0.9,
                    'angry' => 0.9,
                    'terrible' => 0.8,
                    'awful' => 0.8,
                    'worst' => 0.8,
                    'hate' => 0.8
                ),
                'phrases' => array(
                    'not satisfied' => 0.9,
                    'not happy' => 0.8,
                    'very disappointed' => 1.0
                )
            ),
            
            'praise' => array(
                'keywords' => array(
                    'great' => 0.7,
                    'excellent' => 0.9,
                    'amazing' => 0.9,
                    'fantastic' => 0.9,
                    'love' => 0.8,
                    'perfect' => 0.9,
                    'wonderful' => 0.9,
                    'awesome' => 0.8
                ),
                'phrases' => array(
                    'really good' => 0.8,
                    'very happy' => 0.8,
                    'thank you' => 0.7
                )
            ),
            
            'contact' => array(
                'keywords' => array(
                    'contact' => 1.0,
                    'email' => 0.8,
                    'phone' => 0.8,
                    'call' => 0.8,
                    'address' => 0.8,
                    'location' => 0.8
                ),
                'phrases' => array(
                    'get in touch' => 1.0,
                    'contact information' => 1.0,
                    'how to reach' => 0.9,
                    'speak to someone' => 0.9
                )
            ),
            
            'pricing' => array(
                'keywords' => array(
                    'price' => 1.0,
                    'cost' => 1.0,
                    'expensive' => 0.8,
                    'cheap' => 0.8,
                    'fee' => 0.9,
                    'pricing' => 1.0,
                    'discount' => 0.8,
                    'offer' => 0.7
                ),
                'phrases' => array(
                    'how much' => 1.0,
                    'what does it cost' => 1.0,
                    'pricing information' => 1.0
                )
            ),
            
            'booking' => array(
                'keywords' => array(
                    'book' => 0.9,
                    'reserve' => 0.9,
                    'appointment' => 1.0,
                    'schedule' => 0.9,
                    'meeting' => 0.8,
                    'available' => 0.7
                ),
                'phrases' => array(
                    'make appointment' => 1.0,
                    'book a time' => 1.0,
                    'schedule meeting' => 1.0
                )
            ),
            
            'goodbye' => array(
                'keywords' => array(
                    'bye' => 1.0,
                    'goodbye' => 1.0,
                    'thanks' => 0.8,
                    'thank you' => 0.8
                ),
                'phrases' => array(
                    'talk later' => 0.9,
                    'see you' => 0.9,
                    'that\'s all' => 0.8
                )
            )
        );
        
        return $this->intent_patterns;
    }
    
    /**
     * Analyze emotional intent
     * 
     * @param string $message Clean message
     * @return array Emotional state
     */
    private function analyze_emotional_intent($message) {
        $emotions = array(
            'positive' => array(
                'keywords' => array('happy', 'great', 'excellent', 'love', 'amazing', 'perfect', 'wonderful'),
                'score' => 0
            ),
            'negative' => array(
                'keywords' => array('sad', 'angry', 'frustrated', 'disappointed', 'terrible', 'awful', 'hate', 'worst'),
                'score' => 0
            ),
            'neutral' => array(
                'keywords' => array('okay', 'fine', 'normal', 'average'),
                'score' => 0
            ),
            'urgent' => array(
                'keywords' => array('urgent', 'emergency', 'immediately', 'asap', 'quickly', 'now'),
                'score' => 0
            )
        );
        
        foreach ($emotions as $emotion => &$data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $data['score'] += 1;
                }
            }
        }
        
        // Find dominant emotion
        $dominant_emotion = 'neutral';
        $max_score = 0;
        
        foreach ($emotions as $emotion => $data) {
            if ($data['score'] > $max_score) {
                $max_score = $data['score'];
                $dominant_emotion = $emotion;
            }
        }
        
        return array(
            'dominant' => $dominant_emotion,
            'scores' => array_map(function($data) { return $data['score']; }, $emotions),
            'intensity' => min(1.0, $max_score / 3) // Normalize to 0-1
        );
    }
    
    /**
     * Analyze urgency level
     * 
     * @param string $message Clean message
     * @return string Urgency level
     */
    private function analyze_urgency_level($message) {
        $urgency_indicators = array(
            'high' => array('urgent', 'emergency', 'immediately', 'asap', 'critical', 'broken'),
            'medium' => array('soon', 'quickly', 'important', 'need help'),
            'low' => array('when possible', 'no rush', 'whenever')
        );
        
        foreach ($urgency_indicators as $level => $indicators) {
            foreach ($indicators as $indicator) {
                if (strpos($message, $indicator) !== false) {
                    return $level;
                }
            }
        }
        
        // Check for exclamation marks and capital letters
        $exclamation_count = substr_count($message, '!');
        $caps_ratio = $this->calculate_caps_ratio($message);
        
        if ($exclamation_count > 2 || $caps_ratio > 0.5) {
            return 'high';
        } elseif ($exclamation_count > 0 || $caps_ratio > 0.2) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Extract entities from message
     * 
     * @param string $message Clean message
     * @return array Extracted entities
     */
    private function extract_entities($message) {
        $entities = array(
            'emails' => $this->extract_emails($message),
            'phones' => $this->extract_phone_numbers($message),
            'urls' => $this->extract_urls($message),
            'dates' => $this->extract_dates($message),
            'names' => $this->extract_potential_names($message),
            'products' => $this->extract_product_references($message)
        );
        
        // Remove empty arrays
        return array_filter($entities, function($entity_list) {
            return !empty($entity_list);
        });
    }
    
    /**
     * Extract email addresses
     * 
     * @param string $message Message text
     * @return array Email addresses
     */
    private function extract_emails($message) {
        preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message, $matches);
        return $matches[0];
    }
    
    /**
     * Extract phone numbers
     * 
     * @param string $message Message text
     * @return array Phone numbers
     */
    private function extract_phone_numbers($message) {
        $patterns = array(
            '/\b\d{3}-\d{3}-\d{4}\b/',           // 123-456-7890
            '/\b\(\d{3}\)\s*\d{3}-\d{4}\b/',     // (123) 456-7890
            '/\b\d{3}\s\d{3}\s\d{4}\b/',         // 123 456 7890
            '/\b\d{10}\b/'                       // 1234567890
        );
        
        $phones = array();
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $message, $matches);
            $phones = array_merge($phones, $matches[0]);
        }
        
        return array_unique($phones);
    }
    
    /**
     * Extract URLs
     * 
     * @param string $message Message text
     * @return array URLs
     */
    private function extract_urls($message) {
        preg_match_all('/https?:\/\/[^\s]+/', $message, $matches);
        return $matches[0];
    }
    
    /**
     * Extract dates
     * 
     * @param string $message Message text
     * @return array Dates
     */
    private function extract_dates($message) {
        $patterns = array(
            '/\b\d{1,2}\/\d{1,2}\/\d{4}\b/',      // MM/DD/YYYY
            '/\b\d{4}-\d{2}-\d{2}\b/',            // YYYY-MM-DD
            '/\b(tomorrow|today|yesterday)\b/i',   // Relative dates
            '/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i' // Days
        );
        
        $dates = array();
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $message, $matches);
            $dates = array_merge($dates, $matches[0]);
        }
        
        return array_unique($dates);
    }
    
    /**
     * Extract potential names
     * 
     * @param string $message Message text
     * @return array Potential names
     */
    private function extract_potential_names($message) {
        // Look for patterns like "My name is X" or "I'm X"
        $patterns = array(
            '/(?:my name is|i\'m|i am|call me)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
            '/\b([A-Z][a-z]+\s+[A-Z][a-z]+)\b/' // Capitalized words (potential names)
        );
        
        $names = array();
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $message, $matches);
            if (isset($matches[1])) {
                $names = array_merge($names, $matches[1]);
            }
        }
        
        return array_unique($names);
    }
    
    /**
     * Extract product references
     * 
     * @param string $message Message text
     * @return array Product references
     */
    private function extract_product_references($message) {
        // This could be enhanced with your specific product catalog
        $common_products = array(
            'service', 'product', 'plan', 'package', 'subscription',
            'software', 'app', 'website', 'course', 'training'
        );
        
        $products = array();
        foreach ($common_products as $product) {
            if (stripos($message, $product) !== false) {
                $products[] = $product;
            }
        }
        
        return array_unique($products);
    }
    
    /**
     * Apply context weighting to intent scores
     * 
     * @param array $intents Current intent scores
     * @param string $context Context information
     * @return array Weighted intent scores
     */
    private function apply_context_weighting($intents, $context) {
        $context_lower = strtolower($context);
        
        // Boost certain intents based on context
        if (strpos($context_lower, 'support') !== false) {
            if (isset($intents['support'])) {
                $intents['support'] *= 1.3;
            }
        }
        
        if (strpos($context_lower, 'pricing') !== false || strpos($context_lower, 'cost') !== false) {
            if (isset($intents['pricing'])) {
                $intents['pricing'] *= 1.3;
            }
        }
        
        if (strpos($context_lower, 'contact') !== false) {
            if (isset($intents['contact'])) {
                $intents['contact'] *= 1.3;
            }
        }
        
        return $intents;
    }
    
    /**
     * Determine if human intervention is required
     * 
     * @param array $intents Intent scores
     * @param array $emotional_state Emotional analysis
     * @param string $urgency_level Urgency level
     * @return bool Whether human intervention is needed
     */
    private function requires_human_intervention($intents, $emotional_state, $urgency_level) {
        // High urgency always requires human attention
        if ($urgency_level === 'high') {
            return true;
        }
        
        // Strong negative emotions require human attention
        if ($emotional_state['dominant'] === 'negative' && $emotional_state['intensity'] > 0.7) {
            return true;
        }
        
        // Complaints require human attention
        if (isset($intents['complaint']) && $intents['complaint'] > 0.7) {
            return true;
        }
        
        // Complex support issues
        if (isset($intents['support']) && $intents['support'] > 0.8 && $urgency_level === 'medium') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Suggest actions based on intent analysis
     * 
     * @param string $primary_intent Primary intent
     * @param array $entities Extracted entities
     * @param string $urgency_level Urgency level
     * @return array Suggested actions
     */
    private function suggest_actions($primary_intent, $entities, $urgency_level) {
        $actions = array();
        
        switch ($primary_intent) {
            case 'greeting':
                $actions[] = 'respond_warmly';
                $actions[] = 'offer_help';
                break;
                
            case 'question':
                $actions[] = 'provide_information';
                $actions[] = 'search_knowledge_base';
                break;
                
            case 'support':
                $actions[] = 'troubleshoot';
                if ($urgency_level === 'high') {
                    $actions[] = 'escalate_to_human';
                }
                break;
                
            case 'complaint':
                $actions[] = 'acknowledge_concern';
                $actions[] = 'escalate_to_human';
                $actions[] = 'offer_resolution';
                break;
                
            case 'praise':
                $actions[] = 'thank_user';
                $actions[] = 'ask_for_review';
                break;
                
            case 'contact':
                $actions[] = 'provide_contact_info';
                break;
                
            case 'pricing':
                $actions[] = 'provide_pricing_info';
                $actions[] = 'suggest_consultation';
                break;
                
            case 'booking':
                $actions[] = 'show_availability';
                $actions[] = 'start_booking_process';
                break;
                
            case 'goodbye':
                $actions[] = 'polite_farewell';
                $actions[] = 'offer_future_help';
                break;
                
            default:
                $actions[] = 'general_assistance';
                break;
        }
        
        // Add entity-specific actions
        if (!empty($entities['emails'])) {
            $actions[] = 'acknowledge_email';
        }
        
        if (!empty($entities['phones'])) {
            $actions[] = 'acknowledge_phone';
        }
        
        if (!empty($entities['names'])) {
            $actions[] = 'personalize_response';
        }
        
        return array_unique($actions);
    }
    
    /**
     * Clean message for analysis
     * 
     * @param string $message Raw message
     * @return string Clean message
     */
    private function clean_message($message) {
        // Convert to lowercase
        $clean = strtolower(trim($message));
        
        // Remove extra whitespace
        $clean = preg_replace('/\s+/', ' ', $clean);
        
        // Remove special characters but keep punctuation that matters
        $clean = preg_replace('/[^\w\s\?\!\.\,\-\(\)\@]/', '', $clean);
        
        return $clean;
    }
    
    /**
     * Calculate caps ratio in message
     * 
     * @param string $message Message text
     * @return float Ratio of capital letters
     */
    private function calculate_caps_ratio($message) {
        $letters = preg_replace('/[^a-zA-Z]/', '', $message);
        if (empty($letters)) {
            return 0;
        }
        
        $caps = preg_replace('/[^A-Z]/', '', $letters);
        return strlen($caps) / strlen($letters);
    }
    
    /**
     * Get confidence threshold by sensitivity setting
     * 
     * @param string $sensitivity Sensitivity level
     * @return float Threshold value
     */
    private function get_threshold_by_sensitivity($sensitivity) {
        switch ($sensitivity) {
            case 'high':
                return 0.4; // More sensitive, lower threshold
            case 'low':
                return 0.8; // Less sensitive, higher threshold
            case 'medium':
            default:
                return 0.6; // Balanced
        }
    }
    
    /**
     * Get intent explanation for debugging
     * 
     * @param array $analysis Intent analysis result
     * @return string Human-readable explanation
     */
    public function get_intent_explanation($analysis) {
        $explanations = array();
        
        $explanations[] = sprintf(
            "Primary Intent: %s (%.1f%% confidence)",
            ucfirst($analysis['primary_intent']),
            $analysis['confidence'] * 100
        );
        
        if (!empty($analysis['emotional_state'])) {
            $explanations[] = sprintf(
                "Emotional State: %s (intensity: %.1f)",
                ucfirst($analysis['emotional_state']['dominant']),
                $analysis['emotional_state']['intensity']
            );
        }
        
        if ($analysis['urgency_level'] !== 'low') {
            $explanations[] = sprintf(
                "Urgency Level: %s",
                ucfirst($analysis['urgency_level'])
            );
        }
        
        if (!empty($analysis['entities'])) {
            $entity_types = array_keys($analysis['entities']);
            $explanations[] = "Entities Found: " . implode(', ', $entity_types);
        }
        
        if ($analysis['requires_human']) {
            $explanations[] = "⚠️ Human intervention recommended";
        }
        
        return implode(" | ", $explanations);
    }
    
    /**
     * Train intent recognition with new data
     * 
     * @param string $message Training message
     * @param string $correct_intent Correct intent
     * @param float $confidence Training confidence
     * @return bool Success status
     */
    public function train_intent($message, $correct_intent, $confidence = 1.0) {
        global $wpdb;
        
        $training_table = $wpdb->prefix . 'ai_chatbot_intent_training';
        
        // Create training table if it doesn't exist
        $this->create_training_table_if_needed();
        
        $result = $wpdb->insert(
            $training_table,
            array(
                'message' => $message,
                'intent' => $correct_intent,
                'confidence' => $confidence,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%f', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Create training table if needed
     */
    private function create_training_table_if_needed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_intent_training';
        $charset_collate = $wpdb->get_charset_collate();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                message text NOT NULL,
                intent varchar(100) NOT NULL,
                confidence decimal(3,2) NOT NULL DEFAULT 1.00,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_intent (intent),
                KEY idx_created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Get intent statistics
     * 
     * @return array Intent statistics
     */
    public function get_intent_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_chatbot_conversations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $stats = $wpdb->get_results(
            "SELECT intent, COUNT(*) as count, AVG(confidence_score) as avg_confidence
             FROM $table_name 
             WHERE intent IS NOT NULL 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY intent 
             ORDER BY count DESC",
            ARRAY_A
        );
        
        return $stats ? $stats : array();
    }
}