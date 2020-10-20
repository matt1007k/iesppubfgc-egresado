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

if ( ! class_exists( 'Jet_Engine_Listings_Ajax_Handlers' ) ) {

	class Jet_Engine_Listings_Ajax_Handlers {

		public function __construct() {
			add_action( 'wp_ajax_jet_engine_ajax', array( $this, 'handle_ajax' ) );
			add_action( 'wp_ajax_nopriv_jet_engine_ajax', array( $this, 'handle_ajax' ) );
		}

		/**
		 * Handle AJAX request
		 *
		 * @return [type] [description]
		 */
		public function handle_ajax() {

			if ( ! isset( $_REQUEST['handler'] ) || ! is_callable( array( $this, $_REQUEST['handler'] ) ) ) {
				return;
			}

			call_user_func( array( $this, $_REQUEST['handler'] ) );

		}

		/**
		 * Load more
		 * @return [type] [description]
		 */
		public function listing_load_more() {

			require jet_engine()->plugin_path( 'includes/components/elementor-views/ajax-handlers.php' );
			$elementor_ajax = new Jet_Engine_Elementor_Ajax_Handlers();
			$elementor_ajax->listing_load_more();

		}

		/**
		 * Get whole listing through AJAX
		 */
		public function get_listing() {

			$query           = ! empty( $_REQUEST['query'] ) ? $_REQUEST['query'] : array();
			$widget_settings = ! empty( $_REQUEST['widget_settings'] ) ? $_REQUEST['widget_settings'] : array();
			$post_id         = ! empty( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : false;
			$element_id      = ! empty( $_REQUEST['element_id'] ) ? $_REQUEST['element_id'] : false;

			if ( $post_id && $element_id ) {
				$elementor = \Elementor\Plugin::instance();
				$document = $elementor->documents->get( $post_id );

				if ( $document ) {
					$widget = $this->find_element_recursive( $document->get_elements_data(), $element_id );

					if ( $widget ) {
						$widget_settings   = $widget['settings'];
						$_REQUEST['query'] = array();
					}

				}
			}

			ob_start();

			$render_instance = jet_engine()->listings->get_render_instance( 'listing-grid', $widget_settings );

			$render_instance->render();

			wp_send_json_success( array( 'html' => ob_get_clean() ) );

		}

		public function find_element_recursive( $elements, $element_id ) {

			foreach ( $elements as $element ) {

				if ( $element_id === $element['id'] ) {
					return $element;
				}

				if ( ! empty( $element['elements'] ) ) {

					$element = $this->find_element_recursive( $element['elements'], $element_id );

					if ( $element ) {
						return $element;
					}
				}
			}

			return false;
		}

	}

}
