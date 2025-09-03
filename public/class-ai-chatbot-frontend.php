<?php
/**
 * Frontend functionality for AI Chatbot
 *
 * @package AI_Website_Chatbot
 * @subpackage Public
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class
 *
 * @since 1.0.0
 */
class AI_Chatbot_Frontend {

	/**
	 * Plugin name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $version;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 * @since 1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		if ( ! $this->should_load_chatbot() ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			AI_CHATBOT_ASSETS_URL . 'css/public/chatbot-frontend.css',
			array(),
			$this->version,
			'all'
		);

		// Add custom CSS if provided
		$custom_css = get_option( 'ai_chatbot_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( $this->plugin_name, wp_strip_all_tags( $custom_css ) );
		}

		// Add dynamic CSS for theme customization
		$this->add_dynamic_css();
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_load_chatbot() ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			AI_CHATBOT_ASSETS_URL . 'js/public/chatbot-frontend.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script with configuration and translations
		wp_localize_script( $this->plugin_name, 'aiChatbotConfig', $this->get_frontend_config() );
	}

	/**
	 * Render chatbot widget in footer
	 *
	 * @since 1.0.0
	 */
	public function render_chatbot() {
		if ( ! $this->should_load_chatbot() ) {
			return;
		}

		// Get template path
		$template_path = $this->get_template_path( 'chatbot-widget.php' );
		
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			$this->render_default_chatbot();
		}
	}

	/**
	 * Check if chatbot should be loaded on current page
	 *
	 * @return bool True if chatbot should be loaded.
	 * @since 1.0.0
	 */
	private function should_load_chatbot() {
		// Check if chatbot is enabled
		if ( ! get_option( 'ai_chatbot_enabled', false ) ) {
			return false;
		}

		// Check if admin page
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		// Check user permissions
		if ( ! $this->check_user_permissions() ) {
			return false;
		}

		// Check page restrictions
		if ( ! $this->check_page_restrictions() ) {
			return false;
		}

		// Check mobile restrictions
		if ( ! $this->check_mobile_restrictions() ) {
			return false;
		}

		return apply_filters( 'ai_chatbot_should_load', true );
	}

	/**
	 * Check user permissions
	 *
	 * @return bool True if user has permission to use chatbot.
	 * @since 1.0.0
	 */
	private function check_user_permissions() {
		$show_to_logged_users = get_option( 'ai_chatbot_show_to_logged_users', true );
		$show_to_guests = get_option( 'ai_chatbot_show_to_guests', true );

		if ( is_user_logged_in() ) {
			return $show_to_logged_users;
		} else {
			return $show_to_guests;
		}
	}

	/**
	 * Check page restrictions
	 *
	 * @return bool True if chatbot should show on current page.
	 * @since 1.0.0
	 */
	private function check_page_restrictions() {
		$current_page_id = get_queried_object_id();
		$show_on_pages = get_option( 'ai_chatbot_show_on_pages', array() );
		$hide_on_pages = get_option( 'ai_chatbot_hide_on_pages', array() );

		// If specific pages are set to show on, only show on those
		if ( ! empty( $show_on_pages ) ) {
			return in_array( $current_page_id, $show_on_pages, true );
		}

		// If specific pages are set to hide on, hide on those
		if ( ! empty( $hide_on_pages ) ) {
			return ! in_array( $current_page_id, $hide_on_pages, true );
		}

		return true;
	}

	/**
	 * Check mobile restrictions
	 *
	 * @return bool True if chatbot should show on current device.
	 * @since 1.0.0
	 */
	private function check_mobile_restrictions() {
		$show_on_mobile = get_option( 'ai_chatbot_show_on_mobile', true );
		
		if ( ! $show_on_mobile && wp_is_mobile() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get frontend configuration for JavaScript
	 *
	 * @return array Frontend configuration.
	 * @since 1.0.0
	 */
	private function get_frontend_config() {
		$privacy = new AI_Chatbot_Privacy();
		
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ai_chatbot_nonce' ),
			'sessionId' => $this->generate_session_id(),
			'settings' => array(
				'welcomeMessage' => get_option( 'ai_chatbot_welcome_message', __( 'Hello! How can I help you today?', 'ai-website-chatbot' ) ),
				'placeholderText' => get_option( 'ai_chatbot_placeholder_text', __( 'Type your message...', 'ai-website-chatbot' ) ),
				'sendButtonText' => get_option( 'ai_chatbot_send_button_text', __( 'Send', 'ai-website-chatbot' ) ),
				'widgetTitle' => get_option( 'ai_chatbot_widget_title', __( 'AI Assistant', 'ai-website-chatbot' ) ),
				'position' => get_option( 'ai_chatbot_position', 'bottom-right' ),
				'themeColor' => get_option( 'ai_chatbot_theme_color', '#0073aa' ),
				'enableRating' => get_option( 'ai_chatbot_enable_rating', true ),
				'maxMessageLength' => get_option( 'ai_chatbot_max_message_length', 1000 ),
			),
			'privacy' => array(
				'consentRequired' => $privacy->is_consent_required(),
				'policyUrl' => get_privacy_policy_url(),
				'retentionPolicy' => $privacy->get_retention_policy_text(),
			),
			'strings' => array(
				'send' => __( 'Send', 'ai-website-chatbot' ),
				'thinking' => __( 'Thinking...', 'ai-website-chatbot' ),
				'error' => __( 'Sorry, something went wrong. Please try again.', 'ai-website-chatbot' ),
				'networkError' => __( 'Network error. Please check your connection.', 'ai-website-chatbot' ),
				'rateLimitExceeded' => __( 'Too many messages. Please wait a moment.', 'ai-website-chatbot' ),
				'messageTooLong' => __( 'Message is too long. Please shorten it.', 'ai-website-chatbot' ),
				'close' => __( 'Close', 'ai-website-chatbot' ),
				'minimize' => __( 'Minimize', 'ai-website-chatbot' ),
				'maximize' => __( 'Open Chat', 'ai-website-chatbot' ),
				'typing' => __( 'AI is typing...', 'ai-website-chatbot' ),
				'offline' => __( 'Chatbot is currently offline', 'ai-website-chatbot' ),
				'poweredBy' => __( 'Powered by AI Website Chatbot', 'ai-website-chatbot' ),
				'consentRequired' => __( 'Please accept the privacy policy to continue.', 'ai-website-chatbot' ),
				'ratePositive' => __( 'Was this response helpful?', 'ai-website-chatbot' ),
				'rateThumbsUp' => __( 'Yes', 'ai-website-chatbot' ),
				'rateThumbsDown' => __( 'No', 'ai-website-chatbot' ),
				'thankYou' => __( 'Thank you for your feedback!', 'ai-website-chatbot' ),
			),
			'pageUrl' => get_permalink(),
			'pageTitle' => get_the_title(),
			'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
		);
	}

	/**
	 * Generate unique session ID
	 *
	 * @return string Session ID.
	 * @since 1.0.0
	 */
	private function generate_session_id() {
		$session_id = 'chatbot_' . uniqid() . '_' . time();
		
		// Store session ID in user meta if logged in
		if ( is_user_logged_in() ) {
			add_user_meta( get_current_user_id(), 'ai_chatbot_session_id', $session_id );
		}

		return $session_id;
	}

	/**
	 * Add dynamic CSS for theme customization
	 *
	 * @since 1.0.0
	 */
	private function add_dynamic_css() {
		$theme_color = get_option( 'ai_chatbot_theme_color', '#0073aa' );
		$position = get_option( 'ai_chatbot_position', 'bottom-right' );

		$css = "
			.ai-chatbot-widget {
				--chatbot-primary-color: {$theme_color};
				--chatbot-primary-hover: " . $this->adjust_brightness( $theme_color, -20 ) . ";
			}
			
			.ai-chatbot-widget.position-{$position} {
				" . $this->get_position_css( $position ) . "
			}
		";

		wp_add_inline_style( $this->plugin_name, $css );
	}

	/**
	 * Get CSS for chatbot position
	 *
	 * @param string $position Chatbot position.
	 * @return string CSS rules.
	 * @since 1.0.0
	 */
	private function get_position_css( $position ) {
		switch ( $position ) {
			case 'bottom-left':
				return 'bottom: 20px; left: 20px; right: auto;';
			case 'top-right':
				return 'top: 20px; right: 20px; bottom: auto;';
			case 'top-left':
				return 'top: 20px; left: 20px; bottom: auto; right: auto;';
			case 'bottom-right':
			default:
				return 'bottom: 20px; right: 20px;';
		}
	}

	/**
	 * Adjust color brightness
	 *
	 * @param string $hex Hex color.
	 * @param int    $steps Steps to adjust (-255 to 255).
	 * @return string Adjusted hex color.
	 * @since 1.0.0
	 */
	private function adjust_brightness( $hex, $steps ) {
		// Remove # if present
		$hex = ltrim( $hex, '#' );

		// Convert to RGB
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		// Adjust brightness
		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		// Convert back to hex
		return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Get template path with theme override support
	 *
	 * @param string $template Template file name.
	 * @return string Template path.
	 * @since 1.0.0
	 */
	private function get_template_path( $template ) {
		// Check theme directory first
		$theme_path = get_template_directory() . '/ai-chatbot/' . $template;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		// Check child theme directory
		$child_theme_path = get_stylesheet_directory() . '/ai-chatbot/' . $template;
		if ( file_exists( $child_theme_path ) ) {
			return $child_theme_path;
		}

		// Use plugin template
		return AI_CHATBOT_PLUGIN_DIR . 'public/partials/' . $template;
	}

	/**
	 * Render default chatbot widget
	 *
	 * @since 1.0.0
	 */
	private function render_default_chatbot() {
		$position_class = 'position-' . sanitize_html_class( get_option( 'ai_chatbot_position', 'bottom-right' ) );
		$widget_title = esc_html( get_option( 'ai_chatbot_widget_title', __( 'AI Assistant', 'ai-website-chatbot' ) ) );
		$show_powered_by = get_option( 'ai_chatbot_show_powered_by', true );
		
		?>
		<div id="ai-chatbot-widget" class="ai-chatbot-widget <?php echo esc_attr( $position_class ); ?>" style="display: none;">
			<!-- Chat Toggle Button -->
			<button id="ai-chatbot-toggle" class="ai-chatbot-toggle" aria-label="<?php esc_attr_e( 'Open Chat', 'ai-website-chatbot' ); ?>">
				<svg class="ai-chatbot-icon" viewBox="0 0 24 24" fill="currentColor">
					<path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
				</svg>
				<svg class="ai-chatbot-close-icon" viewBox="0 0 24 24" fill="currentColor" style="display: none;">
					<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
				</svg>
			</button>

			<!-- Chat Container -->
			<div id="ai-chatbot-container" class="ai-chatbot-container" style="display: none;">
				<!-- Header -->
				<div class="ai-chatbot-header">
					<h3 class="ai-chatbot-title"><?php echo esc_html( $widget_title ); ?></h3>
					<div class="ai-chatbot-header-actions">
						<button id="ai-chatbot-minimize" class="ai-chatbot-action-btn" aria-label="<?php esc_attr_e( 'Minimize', 'ai-website-chatbot' ); ?>">
							<svg viewBox="0 0 24 24" fill="currentColor">
								<path d="M19 13H5v-2h14v2z"/>
							</svg>
						</button>
					</div>
				</div>

				<!-- Messages Container -->
				<div id="ai-chatbot-messages" class="ai-chatbot-messages">
					<div class="ai-chatbot-welcome-message">
						<div class="ai-chatbot-message ai-chatbot-message-bot">
							<div class="ai-chatbot-message-content">
								<?php echo esc_html( get_option( 'ai_chatbot_welcome_message', __( 'Hello! How can I help you today?', 'ai-website-chatbot' ) ) ); ?>
							</div>
						</div>
					</div>
				</div>

				<!-- Typing Indicator -->
				<div id="ai-chatbot-typing" class="ai-chatbot-typing" style="display: none;">
					<div class="ai-chatbot-typing-dots">
						<span></span>
						<span></span>
						<span></span>
					</div>
					<span class="ai-chatbot-typing-text"><?php esc_html_e( 'AI is typing...', 'ai-website-chatbot' ); ?></span>
				</div>

				<!-- Privacy Consent (if required) -->
				<?php if ( ( new AI_Chatbot_Privacy() )->is_consent_required() ) : ?>
				<div id="ai-chatbot-consent" class="ai-chatbot-consent">
					<?php echo ( new AI_Chatbot_Privacy() )->get_consent_form_html(); ?>
				</div>
				<?php endif; ?>

				<!-- Input Container -->
				<div class="ai-chatbot-input-container">
					<div class="ai-chatbot-input-wrapper">
						<textarea 
							id="ai-chatbot-input" 
							class="ai-chatbot-input" 
							placeholder="<?php echo esc_attr( get_option( 'ai_chatbot_placeholder_text', __( 'Type your message...', 'ai-website-chatbot' ) ) ); ?>"
							rows="1"
							maxlength="<?php echo esc_attr( get_option( 'ai_chatbot_max_message_length', 1000 ) ); ?>"
						></textarea>
						<button 
							id="ai-chatbot-send" 
							class="ai-chatbot-send-btn" 
							aria-label="<?php echo esc_attr( get_option( 'ai_chatbot_send_button_text', __( 'Send', 'ai-website-chatbot' ) ) ); ?>"
						>
							<svg viewBox="0 0 24 24" fill="currentColor">
								<path d="M2,21L23,12L2,3V10L17,12L2,14V21Z"/>
							</svg>
						</button>
					</div>
					<div class="ai-chatbot-character-count" style="display: none;">
						<span id="ai-chatbot-char-count">0</span>/<span><?php echo esc_html( get_option( 'ai_chatbot_max_message_length', 1000 ) ); ?></span>
					</div>
				</div>

				<!-- Footer -->
				<?php if ( $show_powered_by ) : ?>
				<div class="ai-chatbot-footer">
					<small><?php esc_html_e( 'Powered by AI Website Chatbot', 'ai-website-chatbot' ); ?></small>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<?php
		// Add structured data for accessibility
		$this->add_structured_data();
	}

	/**
	 * Add structured data for the chatbot
	 *
	 * @since 1.0.0
	 */
	private function add_structured_data() {
		$structured_data = array(
			'@context' => 'https://schema.org',
			'@type' => 'SoftwareApplication',
			'name' => get_option( 'ai_chatbot_widget_title', __( 'AI Assistant', 'ai-website-chatbot' ) ),
			'applicationCategory' => 'BusinessApplication',
			'operatingSystem' => 'Web Browser',
			'description' => __( 'AI-powered chatbot assistant', 'ai-website-chatbot' ),
			'url' => home_url(),
			'publisher' => array(
				'@type' => 'Organization',
				'name' => get_bloginfo( 'name' ),
			),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $structured_data ) . '</script>';
	}

	/**
	 * Get chatbot shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 * @since 1.0.0
	 */
	public function chatbot_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'position' => 'inline',
				'width' => '100%',
				'height' => '400px',
				'title' => get_option( 'ai_chatbot_widget_title', __( 'AI Assistant', 'ai-website-chatbot' ) ),
			),
			$atts,
			'ai_chatbot'
		);

		// Enqueue scripts and styles if not already done
		$this->enqueue_styles();
		$this->enqueue_scripts();

		ob_start();
		?>
		<div class="ai-chatbot-inline" style="width: <?php echo esc_attr( $atts['width'] ); ?>; height: <?php echo esc_attr( $atts['height'] ); ?>;">
			<div class="ai-chatbot-container ai-chatbot-inline-container">
				<div class="ai-chatbot-header">
					<h3 class="ai-chatbot-title"><?php echo esc_html( $atts['title'] ); ?></h3>
				</div>
				<div class="ai-chatbot-messages ai-chatbot-inline-messages" style="height: calc(100% - 120px);">
					<div class="ai-chatbot-welcome-message">
						<div class="ai-chatbot-message ai-chatbot-message-bot">
							<div class="ai-chatbot-message-content">
								<?php echo esc_html( get_option( 'ai_chatbot_welcome_message', __( 'Hello! How can I help you today?', 'ai-website-chatbot' ) ) ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="ai-chatbot-input-container">
					<div class="ai-chatbot-input-wrapper">
						<textarea 
							class="ai-chatbot-input" 
							placeholder="<?php echo esc_attr( get_option( 'ai_chatbot_placeholder_text', __( 'Type your message...', 'ai-website-chatbot' ) ) ); ?>"
							rows="1"
						></textarea>
						<button class="ai-chatbot-send-btn" aria-label="<?php esc_attr_e( 'Send', 'ai-website-chatbot' ); ?>">
							<svg viewBox="0 0 24 24" fill="currentColor">
								<path d="M2,21L23,12L2,3V10L17,12L2,14V21Z"/>
							</svg>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register chatbot shortcode
	 *
	 * @since 1.0.0
	 */
	public function register_shortcode() {
		add_shortcode( 'ai_chatbot', array( $this, 'chatbot_shortcode' ) );
	}

	/**
	 * Add chatbot to content based on settings
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 * @since 1.0.0
	 */
	public function maybe_add_to_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$auto_insert = get_option( 'ai_chatbot_auto_insert', false );
		$insert_position = get_option( 'ai_chatbot_insert_position', 'after' );

		if ( ! $auto_insert ) {
			return $content;
		}

		$chatbot_html = $this->chatbot_shortcode( array( 'position' => 'inline' ) );

		switch ( $insert_position ) {
			case 'before':
				return $chatbot_html . $content;
			case 'after':
				return $content . $chatbot_html;
			case 'both':
				return $chatbot_html . $content . $chatbot_html;
			default:
				return $content;
		}
	}

	/**
	 * Add chatbot widget to sidebar
	 *
	 * @since 1.0.0
	 */
	public function register_widget() {
		require_once AI_CHATBOT_PUBLIC_PATH . 'class-ai-chatbot-widget.php';
		register_widget( 'AI_Chatbot_Widget' );
	}

	/**
	 * Get chatbot status for current user
	 *
	 * @return array Status information.
	 * @since 1.0.0
	 */
	public function get_chatbot_status() {
		$status = array(
			'enabled' => get_option( 'ai_chatbot_enabled', false ),
			'configured' => false,
			'available' => false,
			'message' => '',
		);

		if ( ! $status['enabled'] ) {
			$status['message'] = __( 'Chatbot is currently disabled.', 'ai-website-chatbot' );
			return $status;
		}

		// Check AI provider configuration
		$ai_provider = get_option( 'ai_chatbot_ai_provider', 'openai' );
		switch ( $ai_provider ) {
			case 'openai':
				$status['configured'] = ! empty( get_option( 'ai_chatbot_openai_api_key' ) );
				break;
			case 'claude':
				$status['configured'] = ! empty( get_option( 'ai_chatbot_claude_api_key' ) );
				break;
			case 'gemini':
				$status['configured'] = ! empty( get_option( 'ai_chatbot_gemini_api_key' ) );
				break;
		}

		if ( ! $status['configured'] ) {
			$status['message'] = __( 'Chatbot is not properly configured.', 'ai-website-chatbot' );
			return $status;
		}

		// Check user permissions and restrictions
		if ( ! $this->should_load_chatbot() ) {
			$status['message'] = __( 'Chatbot is not available on this page.', 'ai-website-chatbot' );
			return $status;
		}

		$status['available'] = true;
		$status['message'] = __( 'Chatbot is ready.', 'ai-website-chatbot' );

		return $status;
	}

	/**
	 * Add preload links for better performance
	 *
	 * @since 1.0.0
	 */
	public function add_preload_links() {
		if ( ! $this->should_load_chatbot() ) {
			return;
		}

		// Preload critical CSS
		echo '<link rel="preload" href="' . esc_url( AI_CHATBOT_ASSETS_URL . 'css/public/chatbot-frontend.css' ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";

		// Preload JavaScript
		echo '<link rel="preload" href="' . esc_url( AI_CHATBOT_ASSETS_URL . 'js/public/chatbot-frontend.js' ) . '" as="script">' . "\n";

		// DNS prefetch for AI provider APIs
		$ai_provider = get_option( 'ai_chatbot_ai_provider', 'openai' );
		switch ( $ai_provider ) {
			case 'openai':
				echo '<link rel="dns-prefetch" href="//api.openai.com">' . "\n";
				break;
			case 'claude':
				echo '<link rel="dns-prefetch" href="//api.anthropic.com">' . "\n";
				break;
			case 'gemini':
				echo '<link rel="dns-prefetch" href="//generativelanguage.googleapis.com">' . "\n";
				break;
		}
	}

	/**
	 * Add security headers for chatbot
	 *
	 * @since 1.0.0
	 */
	public function add_security_headers() {
		if ( ! $this->should_load_chatbot() ) {
			return;
		}

		// Add CSP header for inline scripts if needed
		$csp = "script-src 'self' 'unsafe-inline';";
		header( "Content-Security-Policy: $csp", false );
	}

	/**
	 * Handle chatbot accessibility
	 *
	 * @since 1.0.0
	 */
	public function add_accessibility_features() {
		if ( ! $this->should_load_chatbot() ) {
			return;
		}

		// Add ARIA live region for screen readers
		echo '<div id="ai-chatbot-sr-live" class="sr-only" aria-live="polite" aria-atomic="true"></div>' . "\n";

		// Add skip link for keyboard navigation
		echo '<a href="#ai-chatbot-input" class="sr-only sr-only-focusable">' . esc_html__( 'Skip to chatbot', 'ai-website-chatbot' ) . '</a>' . "\n";
	}

	/**
	 * Get debug information
	 *
	 * @return array Debug information.
	 * @since 1.0.0
	 */
	public function get_debug_info() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return array();
		}

		return array(
			'plugin_version' => AI_CHATBOT_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'should_load' => $this->should_load_chatbot(),
			'user_permissions' => $this->check_user_permissions(),
			'page_restrictions' => $this->check_page_restrictions(),
			'mobile_restrictions' => $this->check_mobile_restrictions(),
			'ai_provider' => get_option( 'ai_chatbot_ai_provider', 'openai' ),
			'current_page_id' => get_queried_object_id(),
			'is_mobile' => wp_is_mobile(),
			'user_logged_in' => is_user_logged_in(),
		);
	}
}