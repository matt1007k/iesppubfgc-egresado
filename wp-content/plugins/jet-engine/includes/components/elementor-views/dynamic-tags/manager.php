<?php
/**
 * Class description
 *
 * @package   package_name
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Engine_Dynamic_Tags_Manager' ) ) {

	/**
	 * Define Jet_Engine_Dynamic_Tags_Manager class
	 */
	class Jet_Engine_Dynamic_Tags_Manager {

		/**
		 * Constructor for the class
		 */
		function __construct() {

			add_action( 'elementor/init', array( $this, 'init_module' ) );

			add_filter(
				'jet-engine/elementor-views/frontend/listing-content',
				array( $this, 'add_listing_item_dynamic_css' ), 10, 2
			);

			add_filter(
				'jet-engine/compatibility/popup-package/the_content',
				array( $this, 'add_popup_item_dynamic_css' ), 10, 2
			);

			add_action( 'elementor/element/before_parse_css', array( $this, 'fix_missing_bg_properties' ), 10, 2 );

			// Prevent enqueue default dynamic CSS for listings templates
			add_action( 'elementor/css-file/post/enqueue', array( $this, 'remove_enqueue_default_dynamic_css' ), 9 );
			add_action( 'elementor/css-file/post/enqueue', array( $this, 'add_enqueue_default_dynamic_css' ),11 );
		}

		/**
		 * Initialize module
		 *
		 * @return void
		 */
		public function init_module() {
			require jet_engine()->plugin_path( 'includes/components/elementor-views/dynamic-tags/module.php' );
			new Jet_Engine_Dynamic_Tags_Module();
		}

		/**
		 * Add dynamic CSS to the listing item
		 *
		 * @param $content
		 * @param $listing_id
		 *
		 * @return string
		 */
		public function add_listing_item_dynamic_css( $content, $listing_id ) {

			if ( ! class_exists( 'Elementor\Core\DynamicTags\Dynamic_CSS' ) ) {
				return $content;
			}

			$object = jet_engine()->listings->data->get_current_object();
			$class  = get_class( $object );

			switch ( $class ) {
				case 'WP_Post':
				case 'WP_User':
					$post_id = $object->ID;
					break;

				case 'WP_Term':
					$post_id = $object->term_id;
					break;

				default:
					$post_id = get_the_ID();
			}

			$css_file = Elementor\Core\DynamicTags\Dynamic_CSS::create( $post_id, $listing_id );
			$css      = $css_file->get_content();

			if ( empty( $css ) ) {
				return $content;
			}


			$css = str_replace( '.elementor-' . $post_id, '.jet-listing-dynamic-post-' . $post_id, $css );
			$css = sprintf( '<style type="text/css">%s</style>', $css );

			return $css . $content;
		}

		/**
		 * Add dynamic CSS to the popup item
		 *
		 * @param $content
		 * @param $popup_data
		 *
		 * @return string
		 */
		public function add_popup_item_dynamic_css( $content, $popup_data ) {

			if ( ! class_exists( 'Elementor\Core\DynamicTags\Dynamic_CSS' ) ) {
				return $content;
			}

			if ( empty( $popup_data['popup_id'] ) ) {
				return $content;
			}

			$css_file = Elementor\Core\DynamicTags\Dynamic_CSS::create( $popup_data['popup_id'], $popup_data['popup_id'] );
			$css      = $css_file->get_content();

			if ( empty( $css ) ) {
				return $content;
			}

			$css = sprintf( '<style type="text/css">%s</style>', $css );

			return $css . $content;
		}

		/**
		 * Fix missing background properties
		 *
		 * @param Elementor\Core\Files\CSS\Post $post_css
		 * @param Elementor\Controls_Stack $element
		 */
		public function fix_missing_bg_properties( $post_css, $element ) {

			if ( wp_doing_ajax() && ! $this->is_editor_ajax() ) {
				return;
			}

			if ( $post_css instanceof Elementor\Core\DynamicTags\Dynamic_CSS ) {
				return;
			}

			if ( jet_engine()->post_type->slug() !== get_post_type( $post_css->get_post_id() ) ) {
				return;
			}

			$dynamic_settings = $element->get_settings( '__dynamic__' );

			if ( empty( $dynamic_settings ) ) {
				return;
			}

			$all_controls = $element->get_controls();
			$media_dynamic_settings = array();

			foreach ( $dynamic_settings as $setting => $tag ) {
				if ( Elementor\Controls_Manager::MEDIA !== $all_controls[ $setting ]['type'] ) {
					continue;
				}

				$media_dynamic_settings[] = $setting;
			}

			if ( empty( $media_dynamic_settings ) ) {
				return;
			}

			$media_conditions_keys = array_map( function ( $key ) {
				return $key . '[url]!';
			}, $media_dynamic_settings );

			foreach ( $all_controls as $control_id => $control ) {
				if ( empty( $control['selectors'] ) || empty( $control['condition'] ) ) {
					continue;
				}

				foreach ( $control['condition'] as $condition_key => $condition_value ) {
					if ( ! in_array( $condition_key, $media_conditions_keys ) ) {
						continue;
					}

					unset( $control['condition'][ $condition_key ] );

					$element->update_control( $control_id, array(
						'condition' => $control['condition'],
					) );
				}
			}

		}

		/**
		 * Remove action for enqueue default dynamic css
		 *
		 * @param Elementor\Core\Files\CSS\Post $css_file
		 */
		public function remove_enqueue_default_dynamic_css( $css_file ) {

			if ( $css_file instanceof Elementor\Core\DynamicTags\Dynamic_CSS ) {
				return;
			}

			if ( jet_engine()->post_type->slug() !== get_post_type( $css_file->get_post_id() ) ) {
				return;
			}

			$dynamic_tags = Elementor\Plugin::instance()->dynamic_tags;

			remove_action( 'elementor/css-file/post/enqueue', array( $dynamic_tags, 'after_enqueue_post_css' ) );
		}

		/**
		 * Add action for enqueue default dynamic css
		 *
		 * @param Elementor\Core\Files\CSS\Post $css_file
		 */
		public function add_enqueue_default_dynamic_css( $css_file ) {

			if ( $css_file instanceof Elementor\Core\DynamicTags\Dynamic_CSS ) {
				return;
			}

			if ( jet_engine()->post_type->slug() !== get_post_type( $css_file->get_post_id() ) ) {
				return;
			}

			$dynamic_tags = Elementor\Plugin::instance()->dynamic_tags;

			add_action( 'elementor/css-file/post/enqueue', array( $dynamic_tags, 'after_enqueue_post_css' ) );
		}

		/**
		 * Is editor ajax.
		 *
		 * @return bool
		 */
		public function is_editor_ajax() {
			return is_admin() && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'];
		}

	}

}
