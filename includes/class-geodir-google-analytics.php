<?php
/**
 * Google Analytics plugin main class.
 *
 * @package    GeoDir_Google_Analytics
 * @since      2.0.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoDir_Google_Analytics class.
 */
class GeoDir_Google_Analytics {

	/**
	 * The single instance of the class.
	 *
	 * @since 2.0.0
	 */
	private static $instance = null;

	/**
	 * Google Analytics Main Instance.
	 *
	 * Ensures only one instance of Google Analytics is loaded or can be loaded.
	 *
	 * @since 2.0.0
	 * @static
	 * @return Google Analytics - Main instance.
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof GeoDir_Google_Analytics ) ) {
			self::$instance = new GeoDir_Google_Analytics;
			self::$instance->setup_constants();

			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

			if ( ! class_exists( 'GeoDirectory' ) ) {
				add_action( 'admin_notices', array( self::$instance, 'geodirectory_notice' ) );

				return self::$instance;
			}

			self::$instance->includes();
			self::$instance->init_hooks();

			do_action( 'geodir_google_analytics_loaded' );
		}

		return self::$instance;
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @since 2.0.0
	 * @return void
	 */
	private function setup_constants() {
		if ( $this->is_request( 'test' ) ) {
			$plugin_path = dirname( GEODIR_GA_PLUGIN_FILE );
		} else {
			$plugin_path = plugin_dir_path( GEODIR_GA_PLUGIN_FILE );
		}

		$this->define( 'GEODIR_GA_PLUGIN_DIR', $plugin_path );
		$this->define( 'GEODIR_GA_PLUGIN_URL', untrailingslashit( plugins_url( '/', GEODIR_GA_PLUGIN_FILE ) ) );
		$this->define( 'GEODIR_GA_PLUGIN_BASENAME', plugin_basename( GEODIR_GA_PLUGIN_FILE ) );

		/**
		 * Frontend Analytics (OAuth 2.0 Web Application)
		 */
		$this->define( 'GEODIR_GA_CLIENTID', '160776539703-k9sdbacijmo233u2p4vj7gjcrfh7iro2.apps.googleusercontent.com' ); // Client ID
		$this->define( 'GEODIR_GA_CLIENTSECRET', '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' ); // Client secret
		$this->define( 'GEODIR_GA_REDIRECT', 'https://ayecode.io/wp-json/ayecode/v1/oauth2gacallback/' ); // Authorised redirect URI
		$this->define( 'GEODIR_GA_OAUTH2_AUTH_URL', 'https://accounts.google.com/o/oauth2/auth' ); // OAuth 2.0 auth URI
		$this->define( 'GEODIR_GA_SCOPE', 'https://www.googleapis.com/auth/analytics.readonly' ); // analytics.readonly
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 * @since 2.0.0
	 * @return void
	 */
	private function includes() {
		global $wp_version;

		/**
		 * Class autoloader.
		 */
		include_once( GEODIR_GA_PLUGIN_DIR . 'includes/class-geodir-google-analytics-autoloader.php' );

		GeoDir_Google_Analytics_AJAX::init();

		require_once( GEODIR_GA_PLUGIN_DIR . 'includes/functions.php' );

		if ( $this->is_request( 'admin' ) || $this->is_request( 'test' ) || $this->is_request( 'cli' ) ) {
			new GeoDir_Google_Analytics_Admin();

			require_once( GEODIR_GA_PLUGIN_DIR . 'includes/admin/class-geodir-google-analytics-api.php' );
			require_once( GEODIR_GA_PLUGIN_DIR . 'includes/admin/admin-functions.php' );

			GeoDir_Google_Analytics_Admin_Install::init();
		}
	}

	/**
	 * Hook into actions and filters.
	 * @since  2.0.0
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );

		if ( $this->is_request( 'frontend' ) ) {
			add_action( 'wp_head', 'geodir_ga_add_tracking_code' );
		}

		add_action( 'geodir_get_widgets', 'goedir_ga_register_widgets', 11, 1 );
		add_action( 'geodir_detail_page_widget_id_bases', array( $this, 'set_widget_id_bases' ), 10, 1 );
		add_action( 'geodir_get_post_package', array( $this, 'package_default_value' ), -1, 3 );
		add_filter( 'geodir_pricing_package_features', array( $this, 'pricing_package_features' ), 11, 4 );
		add_filter( 'geodir_params', array( $this, 'scripts_params' ), 10, 1 );
	}

	/**
	 * Initialise plugin when WordPress Initialises.
	 */
	public function init() {
		// Before init action.
		do_action( 'geodir_google_analytics_before_init' );

		// Init action.
		do_action( 'geodir_google_analytics_init' );
	}

	/**
	 * Loads the plugin language files
	 *
	 * @access public
	 * @since 2.0.0
	 * @return void
	 */
	public function load_textdomain() {
		$locale = determine_locale();

		/**
		 * Filter the plugin locale.
		 *
		 * @since   2.0.0
		 */
		$locale = apply_filters( 'plugin_locale', $locale, 'geodir-ga' );

		unload_textdomain( 'geodir-ga', true );
		load_textdomain( 'geodir-ga', WP_LANG_DIR . '/geodir-ga/geodir-ga-' . $locale . '.mo' );
		load_plugin_textdomain( 'geodir-ga', false, basename( dirname( GEODIR_GA_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Check plugin compatibility and show warning.
	 *
	 * @static
	 * @access private
	 * @since 2.0.0
	 * @return void
	 */
	public static function geodirectory_notice() {
		echo '<div class="error"><p>' . __( 'GeoDirectory plugin is required for the GeoDirectory Google Analytics plugin to work properly.', 'geodir-ga' ) . '</p></div>';
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string $name
	 * @param  string|bool $value
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Request type.
	 *
	 * @param  string $type admin, frontend, ajax, cron, test or CLI.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
				break;
			case 'ajax' :
				return wp_doing_ajax();
				break;
			case 'cli' :
				return ( defined( 'WP_CLI' ) && WP_CLI );
				break;
			case 'cron' :
				return wp_doing_cron();
				break;
			case 'frontend' :
				return ( ! is_admin() || wp_doing_ajax() ) && ! wp_doing_cron();
				break;
			case 'test' :
				return defined( 'GD_TESTING_MODE' );
				break;
		}
		
		return null;
	}

	public function scripts_params( $params ) {
		$params['ga_confirm_delete'] = __( 'Are you wish to deauthorize and disconnect analytics?', 'geodir-ga' );

		return $params;
	}

	public function package_default_value( $package, $post, $post_type ) {
		$package->google_analytics = 1;

		return $package;
	}

	public function set_widget_id_bases( $id_bases ) {
		$id_bases[] = 'gd_google_analytics';
		
		return $id_bases;
	}

	public static function pricing_package_features( $features, $package, $params, $args ) {
		$feature = array( 
			'order' => 8.4,
			'text' => __( 'Google Analytics', 'geodir-ga' )
		);

		if ( geodir_pricing_get_meta( $package->id, 'google_analytics', true ) ) {
			$feature['icon'] = $params['fa_icon_tick'];
			$feature['color'] = $params['color_highlight'];
		} else {
			$feature['icon'] = $params['fa_icon_untick'];
			$feature['color'] = $params['color_default'];
		}

		$features['google_analytics'] = $feature;

		return $features;
	}
}