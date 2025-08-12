<?php
/**
 *GeoDirectory Detail Google Analytics Widget.
 *
 * @package    GeoDir_Google_Analytics
 * @since      2.0.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoDir_Google_Analytics_Widget_Post_Analytics class.
 */
class GeoDir_Google_Analytics_Widget_Post_Analytics extends WP_Super_Duper {
	
	public $arguments;
	
	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {

		$options = array(
			'textdomain'     => GEODIRECTORY_TEXTDOMAIN,
			'block-icon'     => 'chart-bar',
			'block-category' => 'geodirectory',
			'block-keywords' => "['analytics','geodirectory','google']",
			'block-outputx'   => array(
				array(
					'element'   => 'div',
					'className' => '[%className%]',
					'style'     => '{background: "#eee", width: "100%", height: "64px", position: "relative", border: "solid 1px #e0e0e0", color: "#1d2327"}',
					array(
						'element'   => 'i',
						'className' => 'fas fa-chart-bar',
						'style'     => '{"paddingLeft": "12px", "verticalAlign": "middle", "lineHeight": "64px", "fontSize": "16px"}',
					),
					array(
						'element'   => 'span',
						'style'     => '{"fontWeight": "600", "verticalAlign": "middle", "lineHeight": "64px", width: "100%", "fontSize": "14px"}',
						'content'   => ' ' . __( 'GD > Google Analytics placeholder', 'geodir-ga' ),
					),
				),
			),
			'class_name'     => __CLASS__,
			'base_id'        => 'gd_google_analytics',
			'name'           => __( 'GD > Google Analytics', 'geodir-ga' ),
			'widget_ops'     => array(
				'classname'       => 'geodir-google-analytics' . ( geodir_design_style() ? ' bsui' : '' ),
				'description'     => esc_html__( 'Show google analytics stats for the listing.', 'geodir-ga' ),
				'geodirectory'    => true,
				'gd_wgt_showhide' => 'show_on',
				'gd_wgt_restrict' => array( 'gd-detail' ),
			)
		);

		parent::__construct( $options );
	}

	/**
	 * Widget arguments.
	 */
	public function set_arguments() {
		$design_style = geodir_design_style();

		$arguments = array(
			'title' => array(
				'title' => __( 'Title:', 'geodir-ga' ),
				'desc' => __( 'The widget title:', 'geodir-ga' ),
				'type' => 'text',
				'desc_tip' => true,
				'default' => '',
				'advanced' => false
			),
			'height' => array(
				'title' => __( 'Chart Height:', 'geodir-ga' ),
				'desc' => __( 'Chart height in px. Default: 200', 'geodir-ga' ),
				'type' => 'number',
				'desc_tip' => true,
				'default' => '200',
				'advanced' => true
			),
			'output' => array(
				'type' => 'select',
				'title' => __( 'Output Type:', 'geodir-ga' ),
				'desc' => __( 'Display type to render output.', 'geodir-ga' ),
				'options' => array(
					'' => __( 'Default (Button)','geodir-ga' ),
					'inline' => __( 'Inline','geodir-ga' )
				),
				'default' => '',
				'desc_tip' => true
			),
			'button_text' => array(
				'title' => __( 'Button text:', 'geodir-ga' ),
				'desc' => __( 'The text to use for the button to show the analytics:', 'geodir-ga' ),
				'type' => 'text',
				'placeholder' => __( 'Show Google Analytics', 'geodir-ga' ),
				'desc_tip' => true,
				'default' => '',
				'advanced' => true,
				'element_require' => '[%output%]!="inline"'
			),
			'user_roles' => array(
				'title' => __( 'Google Analytics visible to:', 'geodir-ga' ),
				'desc' => __( 'Google Analytics will be visible to selected users only.', 'geodir-ga' ),
				'type' => 'select',
				'options' => array(
					"owner,administrator" => __( 'Owner/Administrator', 'geodir-ga' ),
					"administrator" => __( 'Administrator', 'geodir-ga'),
					"all-logged-in" => __( 'Everyone logged in', 'geodir-ga' ),
					"all" => __('Everyone', 'geodir-ga'),
				),
				'desc_tip' => true,
				'advanced' => false
			),
			'location_level' => array(
				'type' => 'select',
				'title' => __( 'Geographic Dimensions:', 'geodir-ga' ),
				'desc' => __( 'Select geographic dimensions location level to display users count.', 'geodir-ga' ),
				'options' => array(
					'' => __( 'Default (Country & City)','geodir-ga' ),
					'country' => __( 'Country','geodir-ga' ),
					'city' => __( 'City','geodir-ga' )
				),
				'default' => '',
				'desc_tip' => true
			)
		);

		if ( $design_style ) {
			$arguments['btn_color'] = array(
				'type' => 'select',
				'title' => __( 'Button Color:', 'geodir-ga' ),
				'desc' => __( 'Analytics button color.', 'geodir-ga' ),
				'options' => array(
					'' => __( 'Default (primary)', 'geodir-ga' ),
				) + geodir_aui_colors(),
				'default' => '',
				'desc_tip' => true,
				'advanced' => false,
				'group' => __( 'Design', 'geodirectory' ),
				'element_require' => '[%output%]!="inline"'
			);

			$arguments['btn_size'] = array(
				'type' => 'select',
				'title' => __( 'Button Size:', 'geodir-ga' ),
				'desc' => __( 'Analytics button size.', 'geodir-ga' ),
				'options' => array(
					'' => __( 'Default (medium)', 'geodir-ga' ),
					'small' => __( 'Small', 'geodir-ga' ),
					'medium' => __( 'Medium', 'geodir-ga' ),
					'large' => __( 'Large', 'geodir-ga' ),
				),
				'default' => '',
				'desc_tip' => true,
				'advanced' => false,
				'group' => __( 'Design', 'geodirectory' ),
				'element_require' => '[%output%]!="inline"'
			);

			$arguments['btn_alignment'] = array(
				'type' => 'select',
				'title' => __( 'Button Position:', 'geodir-ga' ),
				'desc' => __( 'Analytics button alignment.', 'geodir-ga' ),
				'options' => array(
					'' => __( 'Default (left)', 'geodir-ga' ),
					'left' => __( 'Left', 'geodir-ga' ),
					'center' => __( 'Center', 'geodir-ga' ),
					'right' => __( 'Right', 'geodir-ga' ),
					'block' => __( 'Block', 'geodir-ga' ),
				),
				'default' => '',
				'desc_tip' => true,
				'advanced' => false,
				'group' => __( 'Design', 'geodirectory' ),
				'element_require' => '[%output%]!="inline"'
			);
		}

		return $arguments;
	}

	/**
	 * This is the output function for the widget, shortcode and block (front end).
	 *
	 * @param array $args The arguments values.
	 * @param array $widget_args The widget arguments when used.
	 * @param string $content The shortcode content argument
	 *
	 * @return string
	 */
	public function output( $args = array(), $widget_args = array(), $content = '' ) {
		global $post, $preview;

		$is_preview = $this->is_preview();
		$block_preview = $this->is_block_content_call() || $is_preview;

		if ( ! $block_preview && ( $preview || empty( $post ) ) ) {
			return;
		}

		$design_style = geodir_design_style();

		// options
		$defaults = array(
			'title' => '',
			'output' => '',
			'button_text' => '',
			'user_roles' => array( 'owner', 'administrator' ),
			'location_level' => '',
			'height' => 200,
			// AUI
			'btn_color' => '',
			'btn_size' => '',
			'btn_alignment' => ''
		);

		/**
		 * Parse incoming $args into an array and merge it with $defaults
		 */
		$options = wp_parse_args( $args, $defaults );
		$options['block_preview'] = $block_preview;

		/**
		 * Filters the widget title.
		 *
		 * @since 1.0.0
		 *
		 * @param string $title    The widget title. Default 'Pages'.
		 * @param array  $widget_args An array of the widget's settings.
		 * @param mixed  $id_base  The widget ID.
		 */
		// $title = apply_filters( 'widget_title', empty( $widget_args['title'] ) ? '' : $widget_args['title'], $widget_args, $this->id_base );

		$allow_roles = !empty( $options['user_roles'] ) ? $options['user_roles'] : array( 'owner', 'administrator' );

		if ( ! is_array( $allow_roles ) ) {
			$allow_roles = explode( ",", $allow_roles );
		}

		$allow_roles = apply_filters( 'geodir_ga_widget_user_roles', $allow_roles, $widget_args, $this->id_base );
		if ( empty( $allow_roles ) && ! $block_preview ) {
			return;
		}

		$options['user_roles'] = $allow_roles[0]; // @todo we need to make this work for arrays.

		if ( ! in_array( 'all', $allow_roles ) && ! $block_preview ) {
			if ( in_array( 'all-logged-in', $allow_roles ) ) {
				$user_id = is_user_logged_in() ? get_current_user_id() : 0;
				if ( empty( $user_id ) ) {
					return;
				}
			} else {
				$user_id = is_user_logged_in() ? get_current_user_id() : 0;
				if ( empty( $user_id ) ) {
					return;
				}

				$allow = false;
				if ( ! empty( $post->post_author ) && $post->post_author == $user_id && in_array( 'owner', $allow_roles ) ) {
					$allow = true; // Listing owner
				}

				if( function_exists( 'bp_is_my_profile' ) && bp_is_my_profile() && in_array( 'owner', $allow_roles ) ) {
					$allow = true; // buddypress profile owner
				}

				if( function_exists( 'is_uwp_profile_page' ) && is_uwp_profile_page() && in_array( 'owner', $allow_roles ) ) {
					$uwp_user = uwp_get_user_by_author_slug();
					if( $uwp_user && $uwp_user->ID === $user_id ) {
						$allow = true; // UWP profile owner
					}
				}

				if ( ! $allow ) {
					$user_data = get_userdata( $user_id );
					if ( empty( $user_data->roles ) ) {
						return;
					}

					$allow = false;
					foreach ( $user_data->roles as $user_role ) {
						if ( in_array( $user_role, $allow_roles ) ) {
							$allow = true;
							break;
						}
					}
				}

				if ( ! $allow ) {
					return;
				}
			}
		}

		ob_start();

		do_action( 'geodir_widget_before_gd_google_analytics' );
		
		geodir_ga_display_analytics( $options );
		
		do_action( 'geodir_widget_after_gd_google_analytics' );
		
		return ob_get_clean();
	}

}
