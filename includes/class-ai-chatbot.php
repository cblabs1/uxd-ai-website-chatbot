<?php
/**
 * COMPLETE FIX: Replace your entire includes/class-ai-chatbot.php file with this
 * This properly instantiates the Pro AJAX handler so ai_chatbot_message_pro gets registered
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AI_Chatbot {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin loader instance
     */
    protected $loader;

    /**
     * Plugin name
     */
    protected $plugin_name;

    /**
     * Plugin version
     */
    protected $version;

    /**
     * Get plugin instance
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
    public function __construct() {
        $this->plugin_name = 'ai-website-chatbot';
        $this->version = AI_CHATBOT_VERSION;
        
        // CRITICAL: Load dependencies first (including Pro)
        $this->load_dependencies();

        add_action('init', array($this, 'set_locale'), 0);
        add_action('init', array($this, 'init'), 10);
    }

    /**
     * Initialize the plugin after locale is set
     */
    public function init() {
        $this->define_admin_hooks();
        $this->define_public_hooks();

        if (is_admin()) {
            require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-activator.php';
            AI_Chatbot_Activator::update_database_schema();
        }
    }

    /**
     * Load the required dependencies for this plugin
     */
    private function load_dependencies() {
        // Load the plugin class responsible for orchestrating the hooks
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-loader.php';

        // Load the plugin class responsible for defining internationalization functionality
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-i18n.php';

        // Load database operations class
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-database.php';

        // Load settings management class
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-settings.php';

        // Load security functions
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-security.php';

        // Load privacy compliance
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-privacy.php';

        // Load rate limiter
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-rate-limiter.php';

        // Load content synchronization
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-content-sync.php';

        // Load analytics tracking
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-analytics.php';

        // Load AI provider classes
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-provider-interface.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-provider-base.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-openai.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-claude.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-gemini.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/ai-providers/class-ai-chatbot-custom.php';

        // Load admin classes
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-settings.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-dashboard.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-analytics.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-training.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'admin/class-ai-chatbot-admin-conversations.php';

        // Load public classes
        require_once AI_CHATBOT_PLUGIN_DIR . 'public/class-ai-chatbot-frontend.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'public/class-ai-chatbot-ajax.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'public/class-ai-chatbot-shortcodes.php';
        require_once AI_CHATBOT_PLUGIN_DIR . 'public/class-ai-chatbot-widgets.php';

        if (is_admin()) {
            require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-testing.php';
        }
        
        // Load Gutenberg blocks if WordPress version supports it
        if (function_exists('register_block_type')) {
            require_once AI_CHATBOT_PLUGIN_DIR . 'public/class-ai-chatbot-gutenberg.php';
        }

        // Initialize the loader
        $this->loader = new AI_Chatbot_Loader();
        
        // CRITICAL: Load Pro dependencies AFTER loader is initialized
        $this->load_pro_dependencies();
    }

    /**
     * Load Pro dependencies if available and licensed
     */
    private function load_pro_dependencies() {

        if ($this->should_load_pro()) {
        
            // 1. Load Pro AJAX handler class
            if (file_exists(AI_CHATBOT_PLUGIN_DIR . 'includes/pro/class-ai-chatbot-pro.php')) {
                require_once AI_CHATBOT_PLUGIN_DIR . 'includes/pro/class-ai-chatbot-pro.php';
            }
            
            // 2. Load Pro Intelligence Classes - THE NEW CLASSES WE CREATED
            $pro_intelligence_classes = array(
                'includes/pro/intelligence/class-context-builder.php',
                'includes/pro/intelligence/class-intent-recognition.php', 
                'includes/pro/intelligence/class-response-reasoning.php',
                'includes/pro/intelligence/class-embedding-reasoning.php'
            );
            
            foreach ($pro_intelligence_classes as $class_file) {
                $full_path = AI_CHATBOT_PLUGIN_DIR . $class_file;
                if (file_exists($full_path)) {
                    require_once $full_path;
                } else {
                    error_log("AI Chatbot Pro: Missing class file - " . $class_file);
                }
            }

            // 3. Load Pro Audio Classes - NEW ADDITION
            if (ai_chatbot_has_feature('audio_features')) {
                $pro_audio_classes = array(
                    'includes/pro/audio/class-audio-manager.php',
                    'includes/pro/audio/class-voice-input.php',
                    'includes/pro/audio/class-text-to-speech.php',
                    'includes/pro/audio/class-audio-mode.php',
                    'includes/pro/audio/class-voice-commands.php',
                    'includes/pro/audio/class-audio-settings.php'
                );
                
                foreach ($pro_audio_classes as $class_file) {
                    $full_path = AI_CHATBOT_PLUGIN_DIR . $class_file;
                    if (file_exists($full_path)) {
                        require_once $full_path;
                    } else {
                        error_log("AI Chatbot Pro Audio: Missing class file - " . $class_file);
                    }
                }
            }
            
            // 4. Load Pro admin modules (only in admin)
            if (is_admin() && file_exists(AI_CHATBOT_PLUGIN_DIR . 'includes/pro/admin/class-embedding-admin.php')) {
                require_once AI_CHATBOT_PLUGIN_DIR . 'includes/pro/admin/class-embedding-admin.php';
            }
            
            // 4. Initialize Pro modules
            $this->init_pro_modules();
        }
    }

    /**
     * Check if Pro modules should be loaded
     */
    private function should_load_pro() {
        // For testing mode, always return true if testing functions exist
        if (function_exists('ai_chatbot_has_feature') && ai_chatbot_has_feature('intelligence_engine')) {
            return true;
        }
        
        // Check if Freemius functions exist (for production)
        if (!function_exists('ai_chatbot_fs') || !function_exists('ai_chatbot_is_pro')) {
            return false;
        }
        
        // Check if user has Pro license
        if (!ai_chatbot_is_pro()) {
            return false;
        }
        
        // Check if Pro files exist
        $pro_main_file = AI_CHATBOT_PLUGIN_DIR . 'includes/pro/class-ai-chatbot-pro.php';
        if (!file_exists($pro_main_file)) {
            return false;
        }
        
        return true;
    }

    /**
     * Initialize Pro modules - CRITICAL FIX HERE
     */
    private function init_pro_modules() {
        // Instantiate the Pro AJAX handler
        if (class_exists('AI_Chatbot_Pro')) {
            new AI_Chatbot_Pro(); // This registers the ai_chatbot_message_pro action!
        }

        // NEW: Initialize Audio Manager if available
        if (class_exists('AI_Chatbot_Pro_Audio_Manager')) {
            // Get settings from the unified array
            $settings = get_option('ai_chatbot_settings', array());
            
            // Check if any audio feature is enabled
            $audio_enabled = !empty($settings['audio_features']['voice_input_enabled']) || 
                            !empty($settings['audio_features']['tts_enabled']) ||
                            !empty($settings['audio_features']['audio_mode_enabled']) ||
                            !empty($settings['audio_features']['voice_commands_enabled']);
            
            if ($audio_enabled && function_exists('ai_chatbot_has_feature') && ai_chatbot_has_feature('audio_features')) {
                AI_Chatbot_Pro_Audio_Manager::get_instance();
            } else {
                error_log('AI Chatbot: Audio Manager NOT initialized - requirements not met');
            }
        } else {
            error_log('AI Chatbot: Audio Manager class not found');
        }
        
        // Initialize embedding admin (admin only)
        if (is_admin() && class_exists('AI_Chatbot_Embedding_Admin')) {
            new AI_Chatbot_Embedding_Admin();
        }
    }

    /**
     * Define the locale for this plugin for internationalization
     */
    public function set_locale() {
        $plugin_i18n = new AI_Chatbot_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks() {
        $plugin_admin = new AI_Chatbot_Admin($this->get_plugin_name(), $this->get_version());

        // Enqueue admin scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Add admin menu
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');

        // Initialize admin settings
        $plugin_admin_settings = new AI_Chatbot_Admin_Settings();
        $this->loader->add_action('admin_init', $plugin_admin_settings, 'init');

        // Initialize admin dashboard
        $plugin_admin_dashboard = new AI_Chatbot_Admin_Dashboard();
        $this->loader->add_action('wp_dashboard_setup', $plugin_admin_dashboard, 'add_dashboard_widgets');

        // Initialize admin analytics
        $plugin_admin_analytics = new AI_Chatbot_Admin_Analytics();
        $this->loader->add_action('admin_init', $plugin_admin_analytics, 'init');

        // Initialize admin training
        $plugin_admin_training = new AI_Chatbot_Admin_Training();
        $this->loader->add_action('admin_init', $plugin_admin_training, 'init');

        // Initialize admin conversations
        $plugin_admin_conversations = new AI_Chatbot_Admin_Conversations();
        $this->loader->add_action('admin_init', $plugin_admin_conversations, 'init');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     */
    private function define_public_hooks() {
        // Initialize frontend
        $plugin_public = new AI_Chatbot_Frontend();
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

        // Initialize AJAX handlers
        $plugin_ajax = new AI_Chatbot_Ajax($this->plugin_name, $this->version);
        // AJAX hooks are defined in the Ajax class constructor

        // Initialize shortcodes
        $plugin_shortcodes = new AI_Chatbot_Shortcodes($this->plugin_name, $this->version);
        $this->loader->add_action('init', $plugin_shortcodes, 'register_shortcodes'); 

        // Initialize widgets
        $plugin_widgets = new AI_Chatbot_Widgets();
        // Widget hooks are defined in the Widgets class constructor

        // Initialize Gutenberg blocks if supported
        if (function_exists('register_block_type')) {
            $plugin_gutenberg = new AI_Chatbot_Gutenberg($this->plugin_name, $this->version);
            // Gutenberg hooks are defined in the Gutenberg class constructor
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Enhanced response processing with Pro features
     */
    public function process_response_with_pro($message, $context = '', $conversation_id = null) {
        // Get the appropriate AI provider
        $provider_name = get_option('ai_chatbot_provider', 'openai');
        $provider = $this->get_provider_instance($provider_name);
        
        if (!$provider) {
            return new WP_Error('no_provider', 'AI provider not available');
        }
        
        // If Pro is available, enhance the context
        if (function_exists('ai_chatbot_pro') && ai_chatbot_pro()) {
            $context = apply_filters('ai_chatbot_context_building', $context, $message);
        }
        
        // Get response from provider
        $response = $provider->generate_response($message, $context);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // If Pro is available, enhance the response
        if (function_exists('ai_chatbot_pro') && ai_chatbot_pro()) {
            $response = apply_filters('ai_chatbot_response_processing', $response, $message, $context);
        }
        
        return $response;
    }

    /**
     * Get provider instance
     */
    private function get_provider_instance($provider_name) {
        switch ($provider_name) {
            case 'openai':
                return new AI_Chatbot_OpenAI();
            case 'claude':
                return new AI_Chatbot_Claude();
            case 'gemini':
                return new AI_Chatbot_Gemini();
            case 'custom':
                return new AI_Chatbot_Custom();
            default:
                return null;
        }
    }

    /**
     * Check if specific Pro feature is available
     */
    public function has_pro_feature($feature) {
        if (!function_exists('ai_chatbot_has_feature')) {
            return false;
        }
        
        return ai_chatbot_has_feature($feature);
    }

    /**
     * Get Pro upgrade URL for specific feature
     */
    public function get_feature_upgrade_url($feature = 'general') {
        if (function_exists('ai_chatbot_get_upgrade_url')) {
            return ai_chatbot_get_upgrade_url($feature);
        }
        
        return admin_url('admin.php?page=ai-chatbot-pricing');
    }

    /**
     * Display Pro feature upgrade notice
     */
    public function show_pro_feature_notice($feature, $description = '') {
        if ($this->has_pro_feature($feature)) {
            return false;
        }
        
        if (function_exists('ai_chatbot_show_upgrade_notice')) {
            return ai_chatbot_show_upgrade_notice($feature, $description);
        }
        
        return false;
    }

    /**
     * Get available features (both free and pro)
     */
    public function get_available_features() {
        $free_features = array(
            'basic_chatbot' => array(
                'name' => __('Basic Chatbot', 'ai-website-chatbot'),
                'available' => true
            ),
            'training_data' => array(
                'name' => __('Training Data', 'ai-website-chatbot'),
                'available' => true
            ),
            'multiple_providers' => array(
                'name' => __('Multiple AI Providers', 'ai-website-chatbot'),
                'available' => true
            ),
            'basic_analytics' => array(
                'name' => __('Basic Analytics', 'ai-website-chatbot'),
                'available' => true
            ),
            'customization' => array(
                'name' => __('Appearance Customization', 'ai-website-chatbot'),
                'available' => true
            )
        );
        
        $pro_features = array(
            'intelligence_engine' => array(
                'name' => __('Intelligence Engine', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('intelligence_engine')
            ),
            'intent_recognition' => array(
                'name' => __('Intent Recognition', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('intent_recognition')
            ),
            'advanced_analytics' => array(
                'name' => __('Advanced Analytics', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('advanced_analytics')
            ),
            'conversation_context' => array(
                'name' => __('Conversation Context', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('conversation_context')
            ),
            'smart_responses' => array(
                'name' => __('Smart Response Generation', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('smart_responses')
            ),
            'lead_qualification' => array(
                'name' => __('Lead Qualification', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('lead_qualification')
            ),
            'custom_integrations' => array(
                'name' => __('Custom Integrations', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('custom_integrations')
            ),
            'priority_support' => array(
                'name' => __('Priority Support', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('priority_support')
            ),
            'audio_features' => array(
                'name' => __('Audio Chat Features', 'ai-website-chatbot'),
                'available' => $this->has_pro_feature('audio_features'),
                'description' => __('Voice input, text-to-speech, hands-free conversation mode, and voice commands', 'ai-website-chatbot'),
                'modules' => array(
                    'voice_input_enhanced' => __('Enhanced Voice Input', 'ai-website-chatbot'),
                    'text_to_speech' => __('Text-to-Speech Responses', 'ai-website-chatbot'),
                    'audio_conversation_mode' => __('Hands-free Audio Mode', 'ai-website-chatbot'),
                    'voice_commands' => __('Voice Commands', 'ai-website-chatbot')
                )
            )
        );
        
        return array_merge($free_features, $pro_features);
    }

    /**
     * Get license information display
     */
    public function get_license_display() {
        if (function_exists('ai_chatbot_get_license_info')) {
            return ai_chatbot_get_license_info();
        }
        
        return array(
            'type' => 'free',
            'status' => 'active'
        );
    }
}

/**
 * Helper function to get chatbot settings
 */
function ai_chatbot_get_settings() {
    $default_settings = array(
        'enabled' => 'yes',
        'chatbot_name' => __('AI Assistant', 'ai-website-chatbot'),
        'welcome_message' => __('Hello! How can I help you today?', 'ai-website-chatbot'),
        'provider' => 'openai',
        'theme' => 'dark'
    );
    
    $saved_settings = get_option('ai_chatbot_settings', array());
    return wp_parse_args($saved_settings, $default_settings);
}