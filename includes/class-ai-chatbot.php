<?php
/**
 * Main AI Chatbot Class
 * 
 * This is your main plugin class that should be in: includes/class-ai-chatbot.php
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
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

        // Load public classes - THIS IS WHERE THE PUBLIC FILES ARE LOADED
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
        $this->load_pro_dependencies();
        $this->loader = new AI_Chatbot_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization
     */
    private function set_locale() {
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

        // Add admin menu - MAKE SURE THIS METHOD EXISTS
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');

        // Initialize admin settings
        $plugin_admin_settings = new AI_Chatbot_Admin_Settings();
        $this->loader->add_action('admin_init', $plugin_admin_settings, 'init');

        // Initialize admin dashboard
        $plugin_admin_dashboard = new AI_Chatbot_Admin_Dashboard();
        $this->loader->add_action('wp_dashboard_setup', $plugin_admin_dashboard, 'add_dashboard_widgets');

        // Initialize admin analytics
		$plugin_training = new AI_Chatbot_Admin_Training();
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
        $plugin_public = new AI_Chatbot_Frontend($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

        // Initialize AJAX handlers
        $plugin_ajax = new AI_Chatbot_Ajax($this->plugin_name, $this->version);
        // AJAX hooks will be defined in the Ajax class constructor

        // Initialize shortcodes
        $plugin_shortcodes = new AI_Chatbot_Shortcodes($this->plugin_name, $this->version);
        $this->loader->add_action('init', $plugin_shortcodes, 'register_shortcodes'); 
        // Shortcode hooks will be defined in the Shortcodes class constructor

        // Initialize widgets
        $plugin_widgets = new AI_Chatbot_Widgets();
        // Widget hooks will be defined in the Widgets class constructor

        // Initialize Gutenberg blocks if supported
        if (function_exists('register_block_type')) {
            $plugin_gutenberg = new AI_Chatbot_Gutenberg($this->plugin_name, $this->version);
            // Gutenberg hooks will be defined in the Gutenberg class constructor
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality
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
     * Load Pro dependencies if available and licensed
     * 
     * Add this to your load_dependencies() method
     */
    private function load_pro_dependencies() {
        // Check if Pro should be loaded
        if ($this->should_load_pro()) {
            
            // Load Pro main class
            require_once AI_CHATBOT_PLUGIN_DIR . 'includes/pro/class-ai-chatbot-pro.php';
            
            // Load Pro intelligence modules
            require_once AI_CHATBOT_PLUGIN_DIR . 'includes/pro/intelligence/class-embedding-reasoning.php';
            
            // Load Pro admin modules (only in admin)
            if (is_admin()) {
                require_once AI_CHATBOT_PLUGIN_DIR . 'includes/pro/admin/class-embedding-admin.php';
            }
            
            // Initialize Pro
            $this->init_pro_modules();
        }
    }

    /**
     * Check if Pro modules should be loaded
     */
    private function should_load_pro() {
        // Check if Freemius functions exist
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
     * Initialize Pro modules
     */
    private function init_pro_modules() {
        // Initialize the main Pro class
        if (class_exists('AI_Chatbot_Pro')) {
            AI_Chatbot_Pro::get_instance();
        }
        
        // Initialize embedding admin (admin only)
        if (is_admin() && class_exists('AI_Chatbot_Embedding_Admin')) {
            new AI_Chatbot_Embedding_Admin();
        }
    }

    /**
     * Enhanced response processing with Pro features
     * 
     * Add this method to integrate Pro into response processing
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
        'theme' => 'default',
        'position' => 'bottom-right',
        'ai_provider' => 'openai',
        'api_key' => '',
        'model' => 'gpt-3.5-turbo',
        'max_tokens' => 150,
        'temperature' => 0.7,
        'show_on_pages' => array('all'),
        'hide_on_pages' => array(),
        'enable_conversation_history' => 'yes',
        'enable_rate_limiting' => 'yes',
        'rate_limit_requests' => 10,
        'rate_limit_window' => 60,
        'enable_gdpr_compliance' => 'yes',
        'privacy_policy_url' => '',
        'data_retention_days' => 30,
        'enable_analytics' => 'yes',
        'custom_css' => '',
        'custom_js' => ''
    );
    
    $settings = get_option('ai_chatbot_settings', array());
    return wp_parse_args($settings, $default_settings);
}

/**
 * Helper function to check if chatbot should be displayed on current page
 */
function ai_chatbot_should_display() {
    $settings = ai_chatbot_get_settings();
    
    // Check if chatbot is enabled
    if ($settings['enabled'] !== 'yes') {
        return false;
    }
    
    // Check if current page is in hide list
    $current_page_id = get_the_ID();
    if (in_array($current_page_id, $settings['hide_on_pages'])) {
        return false;
    }
    
    // Check show on pages setting
    $show_on_pages = $settings['show_on_pages'];
    
    if (in_array('all', $show_on_pages)) {
        return true;
    }
    
    if (is_home() && in_array('home', $show_on_pages)) {
        return true;
    }
    
    if (is_page() && in_array('pages', $show_on_pages)) {
        return true;
    }
    
    if (is_single() && in_array('posts', $show_on_pages)) {
        return true;
    }
    
    if (function_exists('is_shop') && is_shop() && in_array('shop', $show_on_pages)) {
        return true;
    }
    
    if (in_array($current_page_id, $show_on_pages)) {
        return true;
    }
    
    return false;
}

/**
 * Helper function to generate conversation ID
 */
function ai_chatbot_generate_conversation_id() {
    return 'conv_' . time() . '_' . wp_generate_password(8, false);
}

/**
 * Helper function to log chatbot activity
 */
function ai_chatbot_log_activity($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[AI Chatbot] ' . $message);
    }
    
    // You can extend this to save to database for analytics
    $settings = ai_chatbot_get_settings();
    if ($settings['enable_analytics'] === 'yes') {
        // Save to database or external logging service
    }
}

/**
 * Helper function to sanitize chatbot message
 */
function ai_chatbot_sanitize_message($message) {
    // Remove HTML tags
    $message = strip_tags($message);
    
    // Remove extra whitespace
    $message = trim(preg_replace('/\s+/', ' ', $message));
    
    // Limit length
    if (strlen($message) > 1000) {
        $message = substr($message, 0, 1000) . '...';
    }
    
    return $message;
}

/**
 * Helper function to check rate limiting
 */
function ai_chatbot_check_rate_limit($user_identifier = null) {
    $settings = ai_chatbot_get_settings();
    
    if ($settings['enable_rate_limiting'] !== 'yes') {
        return true; // Rate limiting disabled
    }
    
    if (!$user_identifier) {
        $user_identifier = ai_chatbot_get_user_identifier();
    }
    
    $rate_limit_key = 'ai_chatbot_rate_limit_' . md5($user_identifier);
    $requests = get_transient($rate_limit_key);
    
    if (!$requests) {
        $requests = array();
    }
    
    $current_time = time();
    $window = intval($settings['rate_limit_window']);
    $max_requests = intval($settings['rate_limit_requests']);
    
    // Remove old requests outside the time window
    $requests = array_filter($requests, function($timestamp) use ($current_time, $window) {
        return ($current_time - $timestamp) < $window;
    });
    
    // Check if limit exceeded
    if (count($requests) >= $max_requests) {
        return false;
    }
    
    // Add current request
    $requests[] = $current_time;
    
    // Save updated requests
    set_transient($rate_limit_key, $requests, $window);
    
    return true;
}

/**
 * Helper function to get user identifier for rate limiting
 */
function ai_chatbot_get_user_identifier() {
    if (is_user_logged_in()) {
        return 'user_' . get_current_user_id();
    }
    
    // Use IP address for non-logged-in users
    $ip = ai_chatbot_get_client_ip();
    return 'ip_' . md5($ip);
}

/**
 * Helper function to get client IP address
 */
function ai_chatbot_get_client_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}