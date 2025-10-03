<?php
/**
 * Plugin Name: AI Website Chatbot
 * Plugin URI: https://wordpress.org/plugins/ai-website-chatbot
 * GitHub Plugin URI: https://github.com/cblabs1/uxd-ai-website-chatbot.git
 * Description: An intelligent chatbot that learns from your website content and integrates with multiple AI platforms. GDPR compliant with privacy controls.
 * Version: 10.4.6
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-website-chatbot
 * Domain Path: /languages
 * Network: false
 * 
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'AI_CHATBOT_VERSION', '10.4.6' );
define( 'AI_CHATBOT_PLUGIN_FILE', __FILE__ );
define( 'AI_CHATBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check requirements and load the plugin
 */
function ai_chatbot_load_plugin() {
    // Check WordPress version
    if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
        add_action( 'admin_notices', 'ai_chatbot_wordpress_version_notice' );
        return;
    }

    // Check PHP version
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        add_action( 'admin_notices', 'ai_chatbot_php_version_notice' );
        return;
    }

    add_action('init', function() {
        load_plugin_textdomain(
            'ai-website-chatbot',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );
    }, 1);

    // Load the main plugin class on init
    add_action('init', function() {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot.php';
        AI_Chatbot::get_instance();
    }, 5);

    

	add_action('plugins_loaded', function() {
		$current_version = get_option('ai_chatbot_db_version', '1.0.0');
		$plugin_version = AI_CHATBOT_VERSION;
		if (is_admin()) {
			require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-activator.php';
			if (version_compare($current_version, '2.0.0', '<')) {
				AI_Chatbot_Activator::migrate_to_pro_schema();
			}
		}
		
	});

    
}

/**
 * WordPress version notice
 */
function ai_chatbot_wordpress_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: required WordPress version */
				esc_html__( 'AI Website Chatbot requires WordPress %s or higher.', 'ai-website-chatbot' ),
				'5.0'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * PHP version notice
 */
function ai_chatbot_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: required PHP version */
				esc_html__( 'AI Website Chatbot requires PHP %s or higher.', 'ai-website-chatbot' ),
				'7.4'
			);
			?>
		</p>
	</div>
	<?php
}

// Load the plugin
ai_chatbot_load_plugin();

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, 'ai_chatbot_activate_plugin' );

/**
 * Plugin activation function
 */
function ai_chatbot_activate_plugin() {
	// Load activation class
	require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-activator.php';
	AI_Chatbot_Activator::activate();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, 'ai_chatbot_deactivate_plugin' );

/**
 * Plugin deactivation function
 */
function ai_chatbot_deactivate_plugin() {
	// Load deactivation class
	require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-deactivator.php';
	AI_Chatbot_Deactivator::deactivate();
}

// Pro Feature Bypass Functions (TESTING ONLY)
if (!function_exists('ai_chatbot_has_feature')) {
    /**
     * Bypass function - always returns true for testing
     */
    function ai_chatbot_has_feature($feature) {
        // Always return true to bypass Pro checks
        return true;
    }
}

if (!function_exists('ai_chatbot_get_upgrade_url')) {
    /**
     * Bypass function for upgrade URL
     */
    function ai_chatbot_get_upgrade_url($feature = 'general') {
        return admin_url('admin.php?page=ai-chatbot');
    }
}

if (!function_exists('ai_chatbot_show_upgrade_notice')) {
    /**
     * Bypass function - don't show upgrade notices during testing
     */
    function ai_chatbot_show_upgrade_notice($feature, $description = '') {
        return false; // Don't show any upgrade notices
    }
}

if (!function_exists('ai_chatbot_is_pro')) {
    /**
     * Bypass function - always return true for testing
     */
    function ai_chatbot_is_pro() {
        return true;
    }
}

if (!function_exists('ai_chatbot_fs')) {
    /**
     * Mock Freemius function for testing
     */
    function ai_chatbot_fs() {
        return (object) array(
            'is_plan' => function($plan) { return true; },
            'is_premium' => function() { return true; },
            'can_use_premium_code' => function() { return true; }
        );
    }
}

if (!function_exists('ai_chatbot_get_license_info')) {
    /**
     * Mock license info for testing
     */
    function ai_chatbot_get_license_info() {
        return array(
            'type' => 'pro',
            'status' => 'active',
            'expires' => date('Y-m-d', strtotime('+1 year'))
        );
    }
}

// Set Pro testing mode option
if (!get_option('ai_chatbot_testing_mode')) {
    update_option('ai_chatbot_testing_mode', '1');
    update_option('ai_chatbot_pro_enabled_features', json_encode(array(
        'intelligence_engine',
        'context_builder', 
        'intent_recognition',
        'response_reasoning',
        'advanced_analytics',
        'conversation_insights',
        'lead_qualification',
        'custom_integrations',
        'priority_support',
        'smart_responses',
        'conversation_context',
        'audio_features'
    )));
}

/**
 * Plugin uninstall hook - in separate file as per WordPress.org guidelines
 * @see uninstall.php
 */