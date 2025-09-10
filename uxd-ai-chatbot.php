<?php
/**
 * Plugin Name: AI Website Chatbot
 * Plugin URI: https://wordpress.org/plugins/ai-website-chatbot
 * GitHub Plugin URI: https://github.com/cblabs1/uxd-ai-website-chatbot.git
 * Description: An intelligent chatbot that learns from your website content and integrates with multiple AI platforms. GDPR compliant with privacy controls.
 * Version: 10.0.3
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
define( 'AI_CHATBOT_VERSION', '10.0.3' );
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

/**
 * Plugin uninstall hook - in separate file as per WordPress.org guidelines
 * @see uninstall.php
 */