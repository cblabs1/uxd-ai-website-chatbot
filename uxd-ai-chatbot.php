<?php
/**
 * Plugin Name: UXD AI Website Chatbot
 * Plugin URI: https://wordpress.org/plugins/ai-website-chatbot
 * GitHub Plugin URI: https://github.com/cblabs1/uxd-ai-website-chatbot.git
 * Description: An intelligent chatbot that learns from your website content and integrates with multiple AI platforms. GDPR compliant with privacy controls.
 * Version: 11.5.8
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: UX Design Experts
 * Author URI: https://uxdesignexperts.com
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
define( 'AI_CHATBOT_VERSION', '11.5.8' );
define( 'AI_CHATBOT_PLUGIN_FILE', __FILE__ );
define( 'AI_CHATBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ============================================
// FREEMIUS INTEGRATION
// ============================================

if ( ! function_exists( 'uc_fs' ) ) {
	/**
	 * Create Freemius instance for the plugin
	 */
	function uc_fs() {
		global $uc_fs;

		if ( ! isset( $uc_fs ) ) {
			// Include Freemius SDK
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
			
			$uc_fs = fs_dynamic_init( array(
				'id'                  => '20670',
				'slug'                => 'uxd-ai-website-chatbot',
				'type'                => 'plugin',
				'public_key'          => 'pk_121341abe51c1e8acfafde5f0cef8',
				'is_premium'          => true,
				'is_premium_only'     => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'menu'                => array(
					'slug'           => 'uxdai-chatbotadmin',
					'support'        => false,
					'parent'         => array(
						'slug' => 'options-general.php',
					),
				),
			) );
		}

		return $uc_fs;
	}

	// Init Freemius
	uc_fs();
	
	// Signal that SDK was initiated
	do_action( 'uc_fs_loaded' );
}

// ============================================
// PLUGIN HELPER FUNCTIONS
// ============================================

if ( ! function_exists( 'ai_chatbot_fs' ) ) {
	/**
	 * Helper function to access Freemius instance
	 * 
	 * @return Freemius
	 */
	function ai_chatbot_fs() {
		return uc_fs();
	}
}

if ( ! function_exists( 'ai_chatbot_is_pro' ) ) {
	/**
	 * Check if user has Pro license
	 * 
	 * @return bool True if user has valid Pro license
	 */
	function ai_chatbot_is_pro() {
		// Check if testing mode is enabled (for development)
		if ( get_option( 'ai_chatbot_testing_mode', false ) ) {
			return true;
		}
		
		// Check Freemius license status
		if ( function_exists( 'uc_fs' ) ) {
			$fs = uc_fs();
			return $fs->is_premium() && $fs->can_use_premium_code();
		}
		
		return false;
	}
}

if ( ! function_exists( 'ai_chatbot_has_feature' ) ) {
	/**
	 * Check if a specific Pro feature is available
	 * 
	 * @param string $feature Feature slug to check
	 * @return bool True if feature is available
	 */
	function ai_chatbot_has_feature( $feature ) {
		// Check if testing mode is enabled
		if ( get_option( 'ai_chatbot_testing_mode', false ) ) {
			$enabled_features = json_decode( 
				get_option( 'ai_chatbot_pro_enabled_features', '[]' ), 
				true 
			);
			return in_array( $feature, $enabled_features );
		}
		
		// Check if user has Pro license
		if ( ! ai_chatbot_is_pro() ) {
			return false;
		}
		
		// All Pro features are available with Pro license
		// You can customize this for different tiers if needed
		return true;
	}
}

if ( ! function_exists( 'ai_chatbot_get_upgrade_url' ) ) {
	/**
	 * Get upgrade URL for a specific feature
	 * 
	 * @param string $feature Feature slug
	 * @return string Upgrade URL
	 */
	function ai_chatbot_get_upgrade_url( $feature = 'general' ) {
		if ( function_exists( 'uc_fs' ) ) {
			$fs = uc_fs();
			return $fs->get_upgrade_url();
		}
		
		return admin_url( 'admin.php?page=uxdai-chatbotadmin-pricing' );
	}
}

if ( ! function_exists( 'ai_chatbot_show_upgrade_notice' ) ) {
	/**
	 * Show upgrade notice in admin
	 * 
	 * @param string $feature Feature name
	 * @param string $description Feature description
	 * @return bool Always returns false
	 */
	function ai_chatbot_show_upgrade_notice( $feature, $description = '' ) {
		if ( ai_chatbot_is_pro() ) {
			return false;
		}
		
		$upgrade_url = ai_chatbot_get_upgrade_url( $feature );
		
		?>
		<div class="notice notice-info ai-chatbot-upgrade-notice">
			<p>
				<strong><?php _e( 'Premium Feature:', 'ai-website-chatbot' ); ?></strong>
				<?php echo esc_html( $feature ); ?>
				<?php if ( $description ) : ?>
					- <?php echo esc_html( $description ); ?>
				<?php endif; ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
					<?php _e( 'Upgrade to Pro', 'ai-website-chatbot' ); ?>
				</a>
			</p>
		</div>
		<?php
		
		return false;
	}
}

if ( ! function_exists( 'ai_chatbot_get_license_info' ) ) {
	/**
	 * Get license information for display
	 * 
	 * @return array License information
	 */
	function ai_chatbot_get_license_info() {
		if ( ! function_exists( 'uc_fs' ) ) {
			return array(
				'type'   => 'free',
				'status' => 'inactive',
			);
		}
		
		$fs = uc_fs();
		
		if ( ! $fs->is_premium() ) {
			return array(
				'type'   => 'free',
				'status' => 'active',
			);
		}
		
		$license = $fs->_get_license();
		
		return array(
			'type'    => $fs->get_plan_name(),
			'status'  => $license ? 'active' : 'inactive',
			'expires' => $license && $license->expiration ? date( 'Y-m-d', strtotime( $license->expiration ) ) : null,
		);
	}
}

// ============================================
// TESTING MODE (For Development Only)
// ============================================

/**
 * Enable testing mode via wp-config.php:
 * define( 'AI_CHATBOT_TESTING_MODE', true );
 */
if ( defined( 'AI_CHATBOT_TESTING_MODE' ) && AI_CHATBOT_TESTING_MODE ) {
	if ( ! get_option( 'ai_chatbot_testing_mode' ) ) {
		update_option( 'ai_chatbot_testing_mode', '1' );
		update_option( 'ai_chatbot_pro_enabled_features', json_encode( array(
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
			'audio_features',
		) ) );  
	}
    else {
        // Auto-disable testing mode if constant is not defined or is false
        // This ensures testing mode doesn't stay on accidentally
        if ( get_option( 'ai_chatbot_testing_mode' ) === '1' ) {
            update_option( 'ai_chatbot_testing_mode', '0' );
            delete_option( 'ai_chatbot_pro_enabled_features' );
        }
    }
}

// ============================================
// PLUGIN INITIALIZATION
// ============================================

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

	// Load text domain
	add_action( 'init', function() {
		load_plugin_textdomain(
			'ai-website-chatbot',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}, 1 );

	// Load the main plugin class
	add_action( 'init', function() {
		require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot.php';
		AI_Chatbot::get_instance();
	}, 5 );

	// Database migration check
	add_action( 'plugins_loaded', function() {
		$current_version = get_option( 'ai_chatbot_db_version', '1.0.0' );
		$plugin_version = AI_CHATBOT_VERSION;
		if ( is_admin() ) {
			require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-activator.php';
			if ( version_compare( $current_version, '2.0.0', '<' ) ) {
				AI_Chatbot_Activator::migrate_to_pro_schema();
			}
		}
	} );
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
	require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot-deactivator.php';
	AI_Chatbot_Deactivator::deactivate();
}