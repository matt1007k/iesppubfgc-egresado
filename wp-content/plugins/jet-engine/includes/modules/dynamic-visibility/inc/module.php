<?php
namespace Jet_Engine\Modules\Dynamic_Visibility;

class Module {

	/**
	 * A reference to an instance of this class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Module
	 */
	private static $instance = null;

	public $slug = 'dynamic-visibility';

	/**
	 * @var Conditions\Manager
	 */
	public $conditions = null;

	/**
	 * Constructor for the class
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init module components
	 *
	 * @return [type] [description]
	 */
	public function init() {

		require jet_engine()->modules->modules_path( 'dynamic-visibility/inc/settings.php' );
		require jet_engine()->modules->modules_path( 'dynamic-visibility/inc/conditions/manager.php' );

		$this->conditions = new Conditions\Manager();

		new Settings();

		$el_types = array(
			'section',
			'column',
			'widget',
		);

		foreach ( $el_types as $el ) {
			add_filter( 'elementor/frontend/' . $el . '/should_render', array( $this, 'check_cond' ), 10, 2 );
		}

	}

	/**
	 * Check render conditions
	 *
	 * @param  [type] $result  [description]
	 * @param  [type] $element [description]
	 * @return [type]          [description]
	 */
	public function check_cond( $result, $element ) {

		$settings   = $element->get_settings();
		$is_enabled = ! empty( $settings['jedv_enabled'] ) ? $settings['jedv_enabled'] : false;
		$is_enabled = filter_var( $is_enabled, FILTER_VALIDATE_BOOLEAN );

		if ( ! $is_enabled ) {
			return $result;
		}

		$dynamic_settings = $element->get_settings_for_display();
		$conditions       = $dynamic_settings['jedv_conditions'];
		$relation         = ! empty( $settings['jedv_relation'] ) ? $settings['jedv_relation'] : 'AND';
		$is_or_relation   = 'OR' === $relation;
		$type             = ! empty( $settings['jedv_type'] ) ? $settings['jedv_type'] : 'show';
		$has_conditions   = false;

		$args = array(
			'type'      => $type,
			'condition' => null,
			'user_role' => null,
			'field'     => null,
			'value'     => null,
			'data_type' => null,
		);

		foreach ( $conditions as $index => $condition ) {
			foreach ( $args as $arg => $default ) {
				$key = 'jedv_' . $arg;
				$args[ $arg ] = ! empty( $condition[ $key ] ) ? $condition[ $key ] : $default;
			}

			$is_dynamic_field = isset( $settings['jedv_conditions'][ $index ]['__dynamic__']['jedv_field'] );
			$is_empty_field   = empty( $settings['jedv_conditions'][ $index ]['jedv_field'] );

			$args['field_raw'] = ( ! $is_dynamic_field && ! $is_empty_field ) ? $settings['jedv_conditions'][ $index ]['jedv_field'] : null;

			if ( empty( $args['condition'] ) ) {
				continue;
			}

			$condition          = $args['condition'];
			$condition_instance = $this->conditions->get_condition( $condition );

			if ( ! $condition_instance ) {
				continue;
			}

			if ( ! $has_conditions ) {
				$has_conditions = true;
			}

			$check = $condition_instance->check( $args );

			if ( 'show' === $type ) {
				if ( $is_or_relation ) {
					if ( $check ) {
						return true;
					}
				} elseif ( ! $check ) {
					return false;
				}
			} else {
				if ( $is_or_relation ) {
					if ( ! $check ) {
						return false;
					}
				} elseif ( $check ) {
					return true;
				}
			}
		}

		if ( ! $has_conditions ) {
			return $result;
		}

		$result = ( 'show' === $type ) ? ! $is_or_relation : $is_or_relation;

		return $result;
	}

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return Module
	 */
	public static function instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

}
