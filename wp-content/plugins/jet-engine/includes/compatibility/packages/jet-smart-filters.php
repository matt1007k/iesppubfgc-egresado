<?php
/**
 * Popup compatibility package
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Engine_Smart_Filters_Package' ) ) {

	/**
	 * Define Jet_Engine_Smart_Filters_Package class
	 */
	class Jet_Engine_Smart_Filters_Package {

		public function __construct() {
			add_filter(
				'jet-smart-filters/providers/jet-engine/stored-settings',
				array( $this, 'store_layout_settings' ),
				10,
				2
			);
		}

		/**
		 * Store additional settings
		 *
		 * @param  [type] $stored_settings [description]
		 * @param  [type] $widget_settings [description]
		 * @return [type]                  [description]
		 */
		public function store_layout_settings( $stored_settings, $widget_settings ) {

			$settings_to_store = array(
				'inject_alternative_items',
				'injection_items',
				'use_load_more',
				'load_more_id',
			);

			foreach ( $settings_to_store as $setting ) {
				if ( isset( $widget_settings[ $setting ] ) )  {
					$stored_settings[ $setting ] = $widget_settings[ $setting ];
				}
			}

			return $stored_settings;
		}

	}

}

new Jet_Engine_Smart_Filters_Package();
