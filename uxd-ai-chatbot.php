<?php

/**
 * Plugin Name: UXD AI Website Chatbot Premium
 * Plugin URI: https://wordpress.org/plugins/ai-website-chatbot
 * GitHub Plugin URI: https://github.com/cblabs1/uxd-ai-website-chatbot.git
 * Description: An intelligent chatbot that learns from your website content and integrates with multiple AI platforms. GDPR compliant with privacy controls.
 * Version: 11.6.4
 * Update URI: https://api.freemius.com
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
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Define plugin constants
define( 'AI_CHATBOT_VERSION', '11.6.3' );
define( 'AI_CHATBOT_PLUGIN_FILE', __FILE__ );
define( 'AI_CHATBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
// ============================================
// FREEMIUS INTEGRATION
// ============================================
if ( !function_exists( 'uxd_ai_chatbot_fs' ) ) {
    function uxd_ai_chatbot_fs() {
        global $uc_fs;
        if ( !isset( $uc_fs ) ) {
            // Include Freemius SDK
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
            $uc_fs = fs_dynamic_init( array(
                'id'                => '21491',
                'slug'              => 'uxd-ai-chatbot',
                'premium_slug'      => 'uxd-ai-chatbot-premium',
                'type'              => 'plugin',
                'public_key'        => 'pk_f5113ebdebcba07f26a284ebd6fac',
                'is_premium'        => true,
                'is_premium_only'   => true,
                'has_addons'        => false,
                'has_paid_plans'    => true,
                'wp_org_gatekeeper' => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
                'menu'              => array(
                    'slug'    => 'ai-chatbot-license',
                    'support' => false,
                    'parent'  => array(
                        'slug' => 'ai-chatbot',
                    ),
                ),
                'is_live'           => true,
            ) );
        }
        return $uc_fs;
    }

    uxd_ai_chatbot_fs();
    // Signal that SDK was initiated
    do_action( 'uxd_ai_chatbot_fs_loaded' );
    uxd_ai_chatbot_fs()->add_action( 'after_uninstall', 'uxd_ai_chatbot_uninstall_cleanup' );
}
// ============================================
// PLUGIN HELPER FUNCTIONS
// ============================================
if ( !function_exists( 'ai_chatbot_fs' ) ) {
    /**
     * Helper function to access Freemius instance
     * 
     * @return Freemius
     */
    function ai_chatbot_fs() {
        if ( function_exists( 'uxd_ai_chatbot_fs' ) ) {
            return uxd_ai_chatbot_fs();
        }
        return null;
    }

}
if ( !function_exists( 'ai_chatbot_is_pro' ) ) {
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
        if ( !function_exists( 'uxd_ai_chatbot_fs' ) ) {
            return false;
        }
        
        try {
            $fs = uxd_ai_chatbot_fs();
            
            // Check if Freemius instance exists
            if ( !$fs ) {
                return false;
            }
            
            // Method 1: Check if premium and can use premium code
            if ( $fs->is_premium() && $fs->can_use_premium_code() ) {
                return true;
            }
            
            // Method 2: Alternative check - is user paying?
            if ( $fs->is_paying() ) {
                return true;
            }
            
            // Method 3: Check if has active license
            $license = $fs->_get_license();
            if ( $license && $license->is_active() && !$license->is_expired() ) {
                return true;
            }
            
        } catch ( Exception $e ) {
            error_log( 'AI Chatbot License Check Error: ' . $e->getMessage() );
            return false;
        }
        
        return false;
    }

}
if ( !function_exists( 'ai_chatbot_has_feature' ) ) {
    /**
     * Check if a specific Pro feature is available
     * 
     * @param string $feature Feature slug to check
     * @return bool True if feature is available
     */
    function ai_chatbot_has_feature( $feature ) {
        // Check if testing mode is enabled
        if ( get_option( 'ai_chatbot_testing_mode', false ) ) {
            $enabled_features = json_decode( get_option( 'ai_chatbot_pro_enabled_features', '[]' ), true );
            return in_array( $feature, $enabled_features );
        }

        // First check if user has Pro license
        if ( !ai_chatbot_is_pro() ) {
            return false;
        }
        
        // If Pro, check if we have plan-specific features configured
        if ( function_exists( 'uxd_ai_chatbot_fs' ) ) {
            $fs = uxd_ai_chatbot_fs();
            
            if ( $fs && method_exists( $fs, 'get_plan' ) ) {
                $plan = $fs->get_plan();
                
                // If you have plan-specific features, check them here
                // For now, all Pro users get all features
                if ( $plan ) {
                    return true;
                }
            }
        }
        
        // Default: If Pro, allow all features
        return true;
    }

}
if ( !function_exists( 'ai_chatbot_get_upgrade_url' ) ) {
    /**
     * Get upgrade URL for a specific feature
     * 
     * @param string $feature Feature slug
     * @return string Upgrade URL
     */
    function ai_chatbot_get_upgrade_url(  $feature = 'general'  ) {
        if ( function_exists( 'uc_fs' ) ) {
            $fs = uc_fs();
            return $fs->get_upgrade_url();
        }
        return admin_url( 'admin.php?page=ai-chatbot-license' );
    }

}
if ( !function_exists( 'ai_chatbot_show_upgrade_notice' ) ) {
    /**
     * Show upgrade notice in admin
     * 
     * @param string $feature Feature name
     * @param string $description Feature description
     * @return bool Always returns false
     */
    function ai_chatbot_show_upgrade_notice(  $feature, $description = ''  ) {
        if ( ai_chatbot_is_pro() ) {
            return false;
        }
        $upgrade_url = ai_chatbot_get_upgrade_url( $feature );
        ?>
		<div class="notice notice-info ai-chatbot-upgrade-notice">
			<p>
				<strong><?php 
        _e( 'Premium Feature:', 'ai-website-chatbot' );
        ?></strong>
				<?php 
        echo esc_html( $feature );
        ?>
				<?php 
        if ( $description ) {
            ?>
					- <?php 
            echo esc_html( $description );
            ?>
				<?php 
        }
        ?>
			</p>
			<p>
				<a href="<?php 
        echo esc_url( $upgrade_url );
        ?>" class="button button-primary">
					<?php 
        _e( 'Upgrade to Pro', 'ai-website-chatbot' );
        ?>
				</a>
			</p>
		</div>
		<?php 
        return false;
    }

}
if ( !function_exists( 'ai_chatbot_get_license_info' ) ) {
    /**
     * Get license information for display
     * 
     * @return array License information
     */
    function ai_chatbot_get_license_info() {
        if ( !function_exists( 'uc_fs' ) ) {
            return array(
                'type'   => 'free',
                'status' => 'inactive',
            );
        }
        $fs = uc_fs();
        if ( !$fs->is_premium() ) {
            return array(
                'type'   => 'free',
                'status' => 'active',
            );
        }
        $license = $fs->_get_license();
        return array(
            'type'    => $fs->get_plan_name(),
            'status'  => ( $license ? 'active' : 'inactive' ),
            'expires' => ( $license && $license->expiration ? date( 'Y-m-d', strtotime( $license->expiration ) ) : null ),
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
    if ( !get_option( 'ai_chatbot_testing_mode' ) ) {
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
            'audio_features'
        ) ) );
    } else {
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
    add_action( 'init', function () {
        load_plugin_textdomain( 'ai-website-chatbot', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }, 1 );
    // Load the main plugin class
    add_action( 'init', function () {
        require_once AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chatbot.php';
        AI_Chatbot::get_instance();
    }, 5 );
    // Database migration check
    add_action( 'plugins_loaded', function () {
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

/**
 * Hook into Freemius uninstall event
 * This runs AFTER Freemius collects feedback and reports uninstall
 */
uxd_ai_chatbot_fs()->add_action( 'after_uninstall', 'uxd_ai_chatbot_uninstall_cleanup' );
/**
 * Cleanup function that runs after Freemius uninstall tracking
 * This replaces the old uninstall.php file
 */
function uxd_ai_chatbot_uninstall_cleanup() {
    // Check if user wants to keep data
    if ( get_option( 'ai_chatbot_keep_data_on_uninstall', false ) ) {
        return;
    }
    // Remove database tables
    uxd_ai_chatbot_drop_database_tables();
    // Remove all plugin options
    uxd_ai_chatbot_remove_plugin_options();
    // Remove user meta data
    uxd_ai_chatbot_remove_user_meta();
    // Remove transients
    uxd_ai_chatbot_remove_transients();
    // Remove upload directory
    uxd_ai_chatbot_remove_upload_directory();
    // Clear scheduled events
    uxd_ai_chatbot_clear_scheduled_events();
}

/**
 * Drop all plugin database tables
 */
function uxd_ai_chatbot_drop_database_tables() {
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'ai_chatbot_conversations',
        $wpdb->prefix . 'ai_chatbot_content',
        $wpdb->prefix . 'ai_chatbot_training_sessions',
        $wpdb->prefix . 'ai_chatbot_training_data',
        $wpdb->prefix . 'ai_chatbot_analytics'
    );
    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
    }
}

/**
 * Remove all plugin options
 */
function uxd_ai_chatbot_remove_plugin_options() {
    global $wpdb;
    // Get all plugin options
    $options = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ai_chatbot_' ) . '%' ) );
    // Delete each option
    foreach ( $options as $option ) {
        delete_option( $option->option_name );
    }
    // Remove specific options
    $specific_options = array(
        'ai_chatbot_version',
        'ai_chatbot_db_version',
        'ai_chatbot_activation_redirect',
        'ai_chatbot_keep_data_on_uninstall',
        'ai_chatbot_settings'
    );
    foreach ( $specific_options as $option ) {
        delete_option( $option );
    }
}

/**
 * Remove user meta data
 */
function uxd_ai_chatbot_remove_user_meta() {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'ai_chatbot_' ) . '%' ) );
}

/**
 * Remove all plugin transients
 */
function uxd_ai_chatbot_remove_transients() {
    global $wpdb;
    // Delete transients
    $transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} \n            WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_ai_chatbot_' ) . '%', $wpdb->esc_like( '_transient_timeout_ai_chatbot_' ) . '%' ) );
    foreach ( $transients as $transient ) {
        delete_option( $transient->option_name );
    }
    // Delete site transients for multisite
    if ( is_multisite() ) {
        $site_transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} \n                WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_site_transient_ai_chatbot_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_ai_chatbot_' ) . '%' ) );
        foreach ( $site_transients as $transient ) {
            delete_site_option( str_replace( '_site_transient_', '', $transient->option_name ) );
        }
    }
}

/**
 * Remove plugin upload directory
 */
function uxd_ai_chatbot_remove_upload_directory() {
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/ai-chatbot';
    if ( is_dir( $plugin_dir ) ) {
        uxd_ai_chatbot_delete_directory_recursive( $plugin_dir );
    }
}

/**
 * Recursively delete directory
 */
function uxd_ai_chatbot_delete_directory_recursive(  $dir  ) {
    if ( !is_dir( $dir ) ) {
        return;
    }
    $files = array_diff( scandir( $dir ), array('.', '..') );
    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        if ( is_dir( $path ) ) {
            uxd_ai_chatbot_delete_directory_recursive( $path );
        } else {
            @unlink( $path );
        }
    }
    @rmdir( $dir );
}

/**
 * Clear scheduled events
 */
function uxd_ai_chatbot_clear_scheduled_events() {
    $scheduled_hooks = array(
        'ai_chatbot_daily_cleanup',
        'ai_chatbot_weekly_sync',
        'ai_chatbot_hourly_rate_limit_reset',
        'ai_chatbot_privacy_cleanup',
        'ai_chatbot_analytics_cleanup'
    );
    foreach ( $scheduled_hooks as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }
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
