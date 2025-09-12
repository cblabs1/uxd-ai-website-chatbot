<?php
/**
 * AI Chatbot Response Reasoning
 * 
 * File: includes/pro/intelligence/class-response-reasoning.php
 * 
 * Enhanced reasoning and response improvement for AI chatbot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Response Reasoning Class - Enhances and improves AI responses
 */
class AI_Chatbot_Response_Reasoning {
    
    /**
     * Reasoning modes
     */
    const MODE_ENHANCE = 'enhance';
    const MODE_VALIDATE = 'validate';
    const MODE_PERSONALIZE = 'personalize';
    
    /**
     * Response quality thresholds
     */
    private $quality_thresholds = array(
        'minimum_length' => 20,
        'maximum_length' => 1000,
        'helpfulness_score' => 0.7
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_reasoning_patterns();
    }
    
    /**
     * Enhance AI response with reasoning and personalization
     * 
     * @param string $response Original AI response
     * @param string $message User message
     * @param string $context Conversation context
     * @return string Enhanced response
     */
    public function enhance_response($response, $message, $context = '') {
        // Start with the original response

        error_log(print_r($response, true));

        $enhanced_response = $response['response'];
        
        // Apply reasoning enhancements
        $enhanced_response = $this->apply_contextual_reasoning($enhanced_response, $message, $context);
        $enhanced_response = $this->improve_response_structure($enhanced_response, $message);
        $enhanced_response = $this->add_personalization($enhanced_response, $message, $context);
        $enhanced_response = $this->add_helpful_suggestions($enhanced_response, $message);
        $enhanced_response = $this->ensure_response_completeness($enhanced_response, $message);
        
        // Validate and quality check
        $enhanced_response = $this->validate_response_quality($enhanced_response, $message);
        
        return $enhanced_response;
    }
    
    /**
     * Apply contextual reasoning to response
     * 
     * @param string $response Current response
     * @param string $message User message
     * @param string $context Context information
     * @return string Improved response
     */
    private function apply_contextual_reasoning($response, $message, $context) {
        // Analyze context for important details
        $context_insights = $this->analyze_context_insights($context);
        
        // Add context-aware elements
        if (!empty($context_insights['user_history'])) {
            $response = $this->add_historical_context($response, $context_insights['user_history']);
        }
        
        if (!empty($context_insights['business_context'])) {
            $response = $this->add_business_context($response, $context_insights['business_context']);
        }
        
        if (!empty($context_insights['temporal_context'])) {
            $response = $this->add_temporal_context($response, $context_insights['temporal_context']);
        }
        
        return $response;
    }
    
    /**
     * Improve response structure and clarity
     * 
     * @param string $response Current response
     * @param string $message User message
     * @return string Structured response
     */
    private function improve_response_structure($response, $message) {
        // Analyze message type to determine best structure
        $message_type = $this->classify_message_type($message);
        
        switch ($message_type) {
            case 'question':
                return $this->structure_as_answer($response, $message);
                
            case 'problem':
                return $this->structure_as_solution($response, $message);
                
            case 'request':
                return $this->structure_as_guidance($response, $message);
                
            case 'complaint':
                return $this->structure_as_empathetic_response($response, $message);
                
            default:
                return $this->structure_as_general_response($response, $message);
        }
    }
    
    /**
     * Add personalization to response
     * 
     * @param string $response Current response
     * @param string $message User message
     * @param string $context Context information
     * @return string Personalized response
     */
    private function add_personalization($response, $message, $context) {
        // Extract personalization cues
        $personalization_cues = $this->extract_personalization_cues($message, $context);
        
        // Apply name personalization
        if (!empty($personalization_cues['name'])) {
            $response = $this->add_name_personalization($response, $personalization_cues['name']);
        }
        
        // Apply tone matching
        $user_tone = $this->detect_user_tone($message);
        $response = $this->match_response_tone($response, $user_tone);
        
        // Apply expertise level matching
        $expertise_level = $this->detect_expertise_level($message, $context);
        $response = $this->adjust_technical_level($response, $expertise_level);
        
        return $response;
    }
    
    /**
     * Add helpful suggestions and next steps
     * 
     * @param string $response Current response
     * @param string $message User message
     * @return string Response with suggestions
     */
    private function add_helpful_suggestions($response, $message) {
        $suggestions = $this->generate_contextual_suggestions($message, $response);
        
        if (!empty($suggestions)) {
            $response .= "\n\n" . $this->format_suggestions($suggestions);
        }
        
        return $response;
    }
    
    /**
     * Ensure response completeness
     * 
     * @param string $response Current response
     * @param string $message User message
     * @return string Complete response
     */
    private function ensure_response_completeness($response, $message) {
        // Check if response addresses the main question
        if (!$this->addresses_main_question($response, $message)) {
            $response = $this->add_direct_answer($response, $message);
        }
        
        // Add missing elements
        $missing_elements = $this->identify_missing_elements($response, $message);
        
        foreach ($missing_elements as $element) {
            $response = $this->add_missing_element($response, $element, $message);
        }
        
        // Ensure proper ending
        $response = $this->ensure_proper_ending($response, $message);
        
        return $response;
    }
    
    /**
     * Validate response quality
     * 
     * @param string $response Response to validate
     * @param string $message Original message
     * @return string Validated response
     */
    private function validate_response_quality($response, $message) {
        $quality_score = $this->calculate_response_quality($response, $message);
        
        // If quality is below threshold, enhance further
        if ($quality_score < $this->quality_thresholds['helpfulness_score']) {
            $response = $this->emergency_quality_improvement($response, $message);
        }
        
        // Ensure length requirements
        if (strlen($response) < $this->quality_thresholds['minimum_length']) {
            $response = $this->expand_brief_response($response, $message);
        }
        
        if (strlen($response) > $this->quality_thresholds['maximum_length']) {
            $response = $this->condense_long_response($response, $message);
        }
        
        return $response;
    }
    
    /**
     * Analyze context for insights
     * 
     * @param string $context Context string
     * @return array Context insights
     */
    private function analyze_context_insights($context) {
        $insights = array(
            'user_history' => array(),
            'business_context' => array(),
            'temporal_context' => array()
        );
        
        // Extract user history
        if (preg_match_all('/User: (.+?)(?=AI:|$)/s', $context, $matches)) {
            $insights['user_history'] = array_slice($matches[1], -3); // Last 3 user messages
        }
        
        // Extract business information
        if (preg_match('/BUSINESS INFORMATION:(.*?)(?=\n[A-Z]+:|$)/s', $context, $matches)) {
            $insights['business_context'] = trim($matches[1]);
        }
        
        // Extract temporal context
        if (preg_match('/CURRENT CONTEXT:(.*?)(?=\n[A-Z]+:|$)/s', $context, $matches)) {
            $insights['temporal_context'] = trim($matches[1]);
        }
        
        return $insights;
    }
    
    /**
     * Classify message type for structural improvements
     * 
     * @param string $message User message
     * @return string Message type
     */
    private function classify_message_type($message) {
        $message_lower = strtolower($message);
        
        // Question indicators
        if (strpos($message, '?') !== false || 
            preg_match('/^(what|how|why|when|where|who|which|can|do|does|is|are)/i', $message)) {
            return 'question';
        }
        
        // Problem indicators
        if (preg_match('/(problem|issue|error|not working|broken|trouble|help)/i', $message)) {
            return 'problem';
        }
        
        // Request indicators
        if (preg_match('/(can you|could you|please|would you|i need|i want)/i', $message)) {
            return 'request';
        }
        
        // Complaint indicators
        if (preg_match('/(disappointed|frustrated|angry|terrible|awful|complaint)/i', $message)) {
            return 'complaint';
        }
        
        return 'general';
    }
    
    /**
     * Structure response as an answer
     * 
     * @param string $response Current response
     * @param string $message User question
     * @return string Structured answer
     */
    private function structure_as_answer($response, $message) {
        // Ensure direct answer at the beginning
        if (!$this->starts_with_direct_answer($response, $message)) {
            $direct_answer = $this->generate_direct_answer_intro($message);
            $response = $direct_answer . " " . $response;
        }
        
        return $response;
    }
    
    /**
     * Structure response as a solution
     * 
     * @param string $response Current response
     * @param string $message Problem description
     * @return string Structured solution
     */
    private function structure_as_solution($response, $message) {
        $structured_parts = array();
        
        // Acknowledgment
        $structured_parts[] = "I understand you're experiencing an issue.";
        
        // Solution steps
        if (!$this->contains_step_by_step($response)) {
            $structured_parts[] = $this->convert_to_steps($response);
        } else {
            $structured_parts[] = $response;
        }
        
        // Follow-up offer
        $structured_parts[] = "Let me know if you need any clarification on these steps!";
        
        return implode(" ", $structured_parts);
    }
    
    /**
     * Detect user tone from message
     * 
     * @param string $message User message
     * @return string Detected tone
     */
    private function detect_user_tone($message) {
        $message_lower = strtolower($message);
        
        // Formal indicators
        if (preg_match('/(dear|sincerely|regards|please|thank you|could you|would you)/i', $message)) {
            return 'formal';
        }
        
        // Casual indicators
        if (preg_match('/(hey|hi|thanks|thx|cool|awesome|yeah|nah)/i', $message)) {
            return 'casual';
        }
        
        // Urgent indicators
        if (preg_match('/(urgent|asap|immediately|quickly|help)/i', $message) ||
            substr_count($message, '!') > 1) {
            return 'urgent';
        }
        
        // Frustrated indicators
        if (preg_match('/(frustrated|angry|disappointed|terrible|awful)/i', $message)) {
            return 'empathetic';
        }
        
        return 'neutral';
    }
    
    /**
     * Match response tone to user tone
     * 
     * @param string $response Current response
     * @param string $user_tone Detected user tone
     * @return string Tone-matched response
     */
    private function match_response_tone($response, $user_tone) {

        $str_response = $response;

        switch ($user_tone) {
            case 'formal':
                return $this->make_response_formal($str_response);
                
            case 'casual':
                return $this->make_response_casual($str_response);
                
            case 'urgent':
                return $this->make_response_urgent($str_response);
                
            case 'empathetic':
                return $this->make_response_empathetic($str_response);
                
            default:
                return $str_response;
        }
    }
    
    /**
     * Generate contextual suggestions
     * 
     * @param string $message User message
     * @param string $response Current response
     * @return array Suggestions
     */
    private function generate_contextual_suggestions($message, $response) {
        $suggestions = array();
        $message_lower = strtolower($message);
        
        // Question-based suggestions
        if (strpos($message, '?') !== false) {
            $suggestions[] = "Would you like more specific information about any aspect?";
        }
        
        // Problem-based suggestions
        if (preg_match('/(problem|issue|error)/i', $message)) {
            $suggestions[] = "If this doesn't resolve your issue, please let me know what specific error messages you're seeing.";
        }
        
        // Contact suggestions
        if (preg_match('/(contact|email|phone|speak to someone)/i', $message)) {
            $contact_info = $this->get_contact_information();
            if (!empty($contact_info)) {
                $suggestions[] = "Here's our contact information: " . $contact_info;
            }
        }
        
        // General helpfulness
        if (empty($suggestions)) {
            $suggestions[] = "Is there anything else I can help you with?";
        }
        
        return $suggestions;
    }
    
    /**
     * Calculate response quality score
     * 
     * @param string $response Response to evaluate
     * @param string $message Original message
     * @return float Quality score (0-1)
     */
    private function calculate_response_quality($response, $message) {
        $score = 0;
        $max_score = 100;
        
        // Length appropriateness (20 points)
        $length = strlen($response);
        if ($length >= 20 && $length <= 1000) {
            $score += 20;
        } elseif ($length > 10) {
            $score += 10;
        }
        
        // Relevance to question (30 points)
        $relevance_score = $this->calculate_relevance($response, $message);
        $score += $relevance_score * 30;
        
        // Helpfulness indicators (25 points)
        $helpfulness_score = $this->calculate_helpfulness($response);
        $score += $helpfulness_score * 25;
        
        // Clarity and structure (15 points)
        $clarity_score = $this->calculate_clarity($response);
        $score += $clarity_score * 15;
        
        // Completeness (10 points)
        $completeness_score = $this->calculate_completeness($response, $message);
        $score += $completeness_score * 10;
        
        return min(1.0, $score / $max_score);
    }
    
    /**
     * Calculate relevance score
     * 
     * @param string $response Response text
     * @param string $message Original message
     * @return float Relevance score (0-1)
     */
    private function calculate_relevance($response, $message) {
        $message_keywords = $this->extract_keywords($message);
        $response_keywords = $this->extract_keywords($response);
        
        if (empty($message_keywords)) {
            return 0.5; // Neutral if no keywords
        }
        
        $matches = array_intersect($message_keywords, $response_keywords);
        return count($matches) / count($message_keywords);
    }
    
    /**
     * Calculate helpfulness score
     * 
     * @param string $response Response text
     * @return float Helpfulness score (0-1)
     */
    private function calculate_helpfulness($response) {
        $helpful_indicators = array(
            'actionable_words' => array('try', 'click', 'go to', 'visit', 'check', 'contact', 'call'),
            'explanatory_words' => array('because', 'since', 'due to', 'reason', 'explain'),
            'solution_words' => array('solution', 'fix', 'resolve', 'solve', 'help'),
            'guidance_words' => array('step', 'first', 'then', 'next', 'finally')
        );
        
        $score = 0;
        $response_lower = strtolower($response);
        
        foreach ($helpful_indicators as $category => $words) {
            foreach ($words as $word) {
                if (strpos($response_lower, $word) !== false) {
                    $score += 0.1;
                }
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Calculate clarity score
     * 
     * @param string $response Response text
     * @return float Clarity score (0-1)
     */
    private function calculate_clarity($response) {
        $score = 1.0;
        
        // Penalize very long sentences
        $sentences = preg_split('/[.!?]+/', $response);
        foreach ($sentences as $sentence) {
            if (strlen(trim($sentence)) > 150) {
                $score -= 0.1;
            }
        }
        
        // Reward proper punctuation
        if (preg_match('/[.!?]$/', trim($response))) {
            $score += 0.1;
        }
        
        // Penalize excessive repetition
        $words = str_word_count($response, 1);
        $unique_words = array_unique($words);
        if (count($words) > 0) {
            $uniqueness_ratio = count($unique_words) / count($words);
            if ($uniqueness_ratio < 0.7) {
                $score -= 0.2;
            }
        }
        
        return max(0, min(1.0, $score));
    }
    
    /**
     * Calculate completeness score
     * 
     * @param string $response Response text
     * @param string $message Original message
     * @return float Completeness score (0-1)
     */
    private function calculate_completeness($response, $message) {
        $score = 0.5; // Base score
        
        // Check if response addresses the main question
        if ($this->addresses_main_question($response, $message)) {
            $score += 0.3;
        }
        
        // Check for follow-up offer
        if (preg_match('/(let me know|feel free|any questions|anything else|help you)/i', $response)) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Extract keywords from text
     * 
     * @param string $text Text to analyze
     * @return array Keywords
     */
    private function extract_keywords($text) {
        $text_lower = strtolower($text);
        $words = preg_split('/\W+/', $text_lower);
        
        // Remove stop words
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by');
        $keywords = array_diff($words, $stop_words);
        
        // Remove short words
        $keywords = array_filter($keywords, function($word) {
            return strlen($word) > 2;
        });
        
        return array_values($keywords);
    }
    
    /**
     * Check if response addresses the main question
     * 
     * @param string $response Response text
     * @param string $message Original message
     * @return bool Whether main question is addressed
     */
    private function addresses_main_question($response, $message) {
        // Extract question words from message
        $question_words = array('what', 'how', 'why', 'when', 'where', 'who', 'which');
        $message_lower = strtolower($message);
        
        foreach ($question_words as $qword) {
            if (strpos($message_lower, $qword) === 0) {
                // Check if response contains relevant answer patterns
                $answer_patterns = array(
                    'what' => array('is', 'are', 'means', 'definition'),
                    'how' => array('by', 'through', 'step', 'process', 'way'),
                    'why' => array('because', 'since', 'due to', 'reason'),
                    'when' => array('time', 'date', 'schedule', 'hour'),
                    'where' => array('location', 'address', 'place', 'at'),
                    'who' => array('person', 'team', 'contact', 'responsible'),
                    'which' => array('option', 'choice', 'better', 'recommend')
                );
                
                if (isset($answer_patterns[$qword])) {
                    foreach ($answer_patterns[$qword] as $pattern) {
                        if (stripos($response, $pattern) !== false) {
                            return true;
                        }
                    }
                }
            }
        }
        
        // For non-question messages, check for keyword overlap
        $message_keywords = $this->extract_keywords($message);
        $response_keywords = $this->extract_keywords($response);
        $overlap = array_intersect($message_keywords, $response_keywords);
        
        return count($overlap) >= min(2, count($message_keywords) * 0.3);
    }
    
    /**
     * Make response more formal
     * 
     * @param string $response Current response
     * @return string Formal response
     */
    private function make_response_formal($response) {
        // Replace casual contractions
        $replacements = array(
            "don't" => "do not",
            "can't" => "cannot",
            "won't" => "will not",
            "it's" => "it is",
            "that's" => "that is",
            "we're" => "we are",
            "you're" => "you are"
        );
        
        $formal_response = str_ireplace(array_keys($replacements), array_values($replacements), $response);
        
        // Add formal closing if not present
        if (!preg_match('/(please|thank you|sincerely)/i', $formal_response)) {
            $formal_response .= " Please let me know if you require any additional assistance.";
        }
        
        return $formal_response;
    }
    
    /**
     * Make response more casual
     * 
     * @param string $response Current response
     * @return string Casual response
     */
    private function make_response_casual($response) {
        // Add casual elements if too formal
        if (strlen($response) > 100 && !preg_match('/(hey|hi|thanks|cool|awesome)/i', $response)) {
            $casual_openers = array("Hey! ", "Hi there! ", "Sure thing! ");
            $response = $casual_openers[array_rand($casual_openers)] . $response;
        }
        
        // Replace formal endings with casual ones
        $response = preg_replace('/Please let me know if you require any additional assistance\.?/i', 
                                'Let me know if you need anything else! 😊', $response);
        
        return $response;
    }
    
    /**
     * Make response more empathetic
     * 
     * @param string $response Current response
     * @return string Empathetic response
     */
    private function make_response_empathetic($response) {
        $empathy_openers = array(
            "I understand how frustrating this must be. ",
            "I can see why you'd be concerned about this. ",
            "I appreciate you bringing this to my attention. "
        );
        
        $opener = $empathy_openers[array_rand($empathy_openers)];
        return $opener . $response;
    }
    
    /**
     * Get contact information
     * 
     * @return string Contact info
     */
    private function get_contact_information() {
        $contact_parts = array();
        
        $phone = get_option('ai_chatbot_contact_phone', '');
        if (!empty($phone)) {
            $contact_parts[] = "Phone: " . $phone;
        }
        
        $email = get_option('ai_chatbot_contact_email', '');
        if (!empty($email)) {
            $contact_parts[] = "Email: " . $email;
        }
        
        return implode(', ', $contact_parts);
    }
    
    /**
     * Emergency quality improvement for poor responses
     * 
     * @param string $response Poor quality response
     * @param string $message Original message
     * @return string Improved response
     */
    private function emergency_quality_improvement($response, $message) {
        // Add helpful prefix
        $improved = "Let me help you with that. " . $response;
        
        // Ensure it ends helpfully
        if (!preg_match('/(help|assist|question)/i', $improved)) {
            $improved .= " Is there anything specific you'd like me to explain further?";
        }
        
        return $improved;
    }
    
    /**
     * Expand brief responses
     * 
     * @param string $response Brief response
     * @param string $message Original message
     * @return string Expanded response
     */
    private function expand_brief_response($response, $message) {
        $expanded_parts = array();
        
        // Add context
        $expanded_parts[] = "Thank you for your question.";
        $expanded_parts[] = $response;
        
        // Add helpful ending
        $expanded_parts[] = "Please let me know if you need any clarification or have additional questions!";
        
        return implode(" ", $expanded_parts);
    }
    
    /**
     * Initialize reasoning patterns
     */
    private function init_reasoning_patterns() {
        // Initialize any patterns or rules needed for reasoning
        // This could be expanded with more sophisticated reasoning rules
    }
    
    /**
     * Format suggestions nicely
     * 
     * @param array $suggestions List of suggestions
     * @return string Formatted suggestions
     */
    private function format_suggestions($suggestions) {
        if (count($suggestions) === 1) {
            return $suggestions[0];
        }
        
        return "Here are some additional suggestions:\n" . implode("\n", array_map(function($suggestion) {
            return "• " . $suggestion;
        }, $suggestions));
    }
    
    /**
     * Additional helper methods for completeness...
     */
    
    private function starts_with_direct_answer($response, $message) {
        return preg_match('/^(yes|no|the answer is|you can|you should|to do this)/i', trim($response));
    }
    
    private function generate_direct_answer_intro($message) {
        if (stripos($message, 'how') === 0) {
            return "To answer your question:";
        } elseif (stripos($message, 'what') === 0) {
            return "Here's what you need to know:";
        } elseif (stripos($message, 'why') === 0) {
            return "The reason is:";
        }
        return "Here's the answer:";
    }
    
    private function contains_step_by_step($response) {
        return preg_match('/(step|first|then|next|finally|\d+\.)/i', $response);
    }
    
    private function convert_to_steps($response) {
        // Simple conversion - could be made more sophisticated
        return "Here's how to resolve this:\n\n1. " . $response;
    }
    
    private function detect_expertise_level($message, $context) {
        $technical_terms = array('api', 'database', 'server', 'code', 'programming', 'sql', 'html', 'css', 'javascript');
        $message_lower = strtolower($message . ' ' . $context);
        
        $technical_count = 0;
        foreach ($technical_terms as $term) {
            if (strpos($message_lower, $term) !== false) {
                $technical_count++;
            }
        }
        
        if ($technical_count >= 2) {
            return 'advanced';
        } elseif ($technical_count >= 1) {
            return 'intermediate';
        }
        
        return 'beginner';
    }
    
    private function adjust_technical_level($response, $expertise_level) {
        switch ($expertise_level) {
            case 'beginner':
                return $this->simplify_technical_language($response);
            case 'advanced':
                return $this->add_technical_details($response);
            default:
                return $response;
        }
    }
    
    private function simplify_technical_language($response) {
        $technical_replacements = array(
            'API' => 'interface',
            'database' => 'data storage',
            'server' => 'computer system',
            'implementation' => 'setup'
        );
        
        return str_ireplace(array_keys($technical_replacements), array_values($technical_replacements), $response);
    }
    
    private function add_technical_details($response) {
        // Could add more technical specifics for advanced users
        return $response;
    }
    
    private function extract_personalization_cues($message, $context) {
        $cues = array();
        
        // Extract names
        if (preg_match('/(?:my name is|i\'m|i am|call me)\s+([A-Z][a-z]+)/i', $message . ' ' . $context, $matches)) {
            $cues['name'] = $matches[1];
        }
        
        return $cues;
    }
    
    private function add_name_personalization($response, $name) {
        return $name . ", " . $response;
    }
    
    private function identify_missing_elements($response, $message) {
        $missing = array();
        
        if (stripos($message, 'how much') !== false && stripos($response, 'cost') === false) {
            $missing[] = 'pricing';
        }
        
        if (stripos($message, 'when') !== false && !preg_match('/\d+|time|hour|day|week/', $response)) {
            $missing[] = 'timing';
        }
        
        return $missing;
    }
    
    private function add_missing_element($response, $element, $message) {
        switch ($element) {
            case 'pricing':
                return $response . " For specific pricing information, please contact our sales team.";
            case 'timing':
                return $response . " The timeframe can vary depending on your specific needs.";
            default:
                return $response;
        }
    }
    
    private function ensure_proper_ending($response, $message) {
        if (!preg_match('/[.!?]$/', trim($response))) {
            $response = trim($response) . ".";
        }
        
        return $response;
    }
    
    private function condense_long_response($response, $message) {
        // Simple condensing - keep first part and add summary
        $sentences = preg_split('/[.!?]+/', $response);
        $important_sentences = array_slice($sentences, 0, 4);
        
        $condensed = implode('. ', array_filter($important_sentences)) . '.';
        
        if (strlen($condensed) > 800) {
            $condensed = substr($condensed, 0, 800) . '...';
        }
        
        return $condensed;
    }
    
    private function add_historical_context($response, $history) {
        // Add reference to previous conversation if relevant
        return $response;
    }
    
    private function add_business_context($response, $business_context) {
        // Add business-specific context
        return $response;
    }
    
    private function add_temporal_context($response, $temporal_context) {
        // Add time-based context
        return $response;
    }
    
    private function structure_as_guidance($response, $message) {
        return "Here's how I can help: " . $response;
    }
    
    private function structure_as_empathetic_response($response, $message) {
        return "I understand your concern. " . $response . " We value your feedback and want to make this right.";
    }
    
    private function structure_as_general_response($response, $message) {
        return $response;
    }
    
    private function make_response_urgent($response) {
        return "I'll help you resolve this quickly. " . $response;
    }
    
    private function add_direct_answer($response, $message) {
        $intro = $this->generate_direct_answer_intro($message);
        return $intro . " " . $response;
    }
}