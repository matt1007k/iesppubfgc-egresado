<?php
/**
 * Meta oxes mamager
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Engine_CPT_Meta' ) ) {
	require jet_engine()->plugin_path( 'includes/components/meta-boxes/post.php' );
}

if ( ! class_exists( 'Jet_Engine_Options_Page_Factory' ) ) {

	/**
	 * Define Jet_Engine_Options_Page_Factory class
	 */
	class Jet_Engine_Options_Page_Factory extends Jet_Engine_CPT_Meta {

		/**
		 * Current page data
		 *
		 * @var null
		 */
		public $page = null;

		/**
		 * Current page slug
		 *
		 * @var null
		 */
		public $slug = null;

		/**
		 * Prepared fields array
		 *
		 * @var null
		 */
		public $prepared_fields = null;

		/**
		 * Holder for is page or not is page now prop
		 *
		 * @var null
		 */
		public $is_page_now = null;

		/**
		 * Inerface builder instance
		 *
		 * @var null
		 */
		public $builder = null;

		/**
		 * Saved options holder
		 *
		 * @var null
		 */
		public $options = null;

		/**
		 * Save trigger
		 *
		 * @var string
		 */
		public $save_action = 'jet-engine-op-save-settings';

		public $layout_now        = false;
		public $current_component = false;
		public $current_panel     = false;

		/**
		 * Constructor for the class
		 */
		public function __construct( $page ) {

			$this->page     = $page;
			$this->slug     = $page['slug'];
			$this->meta_box = $page['fields'];

			if ( empty( $this->page['position'] ) && 0 !== $this->page['position'] ) {
				$this->page['position'] = null;
			}

			add_action( 'admin_menu', array( $this, 'register_menu_page' ) );

			if ( $this->is_page_now() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'init_builder' ), 0 );
				add_action( 'admin_init', array( $this, 'save' ), 40 );
				add_action( 'admin_notices', array( $this, 'saved_notice' ) );
			}

		}

		/**
		 * Check if current options page is processed now
		 *
		 * @return boolean [description]
		 */
		public function is_page_now() {

			if ( null !== $this->is_page_now ) {
				return $this->is_page_now;
			}

			if ( isset( $_GET['page'] ) && $this->slug === $_GET['page'] ) {
				$this->is_page_now = true;
			} else {
				$this->is_page_now = false;
			}

			return $this->is_page_now;

		}

		/**
		 * Register avalable menu pages
		 *
		 * @return [type] [description]
		 */
		public function register_menu_page() {

			if ( ! empty( $this->page['parent'] ) ) {
				add_submenu_page(
					$this->page['parent'],
					$this->page['labels']['name'],
					$this->page['labels']['menu_name'],
					$this->page['capability'],
					$this->page['slug'],
					array( $this, 'render_page' )
				);
			} else {
				add_menu_page(
					$this->page['labels']['name'],
					$this->page['labels']['menu_name'],
					$this->page['capability'],
					$this->page['slug'],
					array( $this, 'render_page' ),
					$this->page['icon'],
					$this->page['position']
				);

			}
		}

		/**
		 * Process options saving
		 *
		 * @return [type] [description]
		 */
		public function save() {

			if ( ! isset( $_REQUEST['action'] ) || $this->save_action !== $_REQUEST['action'] ) {
				return;
			}

			$capability = $this->page['capability'];

			if ( ! current_user_can( $capability ) ) {
				return;
			}

			$current = get_option( $this->slug, array() );
			$data    = $_REQUEST;

			$fields = $this->get_prepared_fields();

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $key => $field ) {

					if ( isset( $data[ $key ] ) ) {

						$value = $data[ $key ];
						$value = $this->maybe_apply_sanitize_callback( $value, $field );

						if ( $this->to_timestamp( $field ) ) {
							$value = strtotime( $value );
						}

						$current[ $key ] = $value;

					}
				}
			}

			update_option( $this->slug, $current );

			$redirect = add_query_arg(
				array(
					'page'         => $this->slug,
					'dialog-saved' => true,
				),
				esc_url( admin_url( 'admin.php' ) )
			);

			wp_redirect( $redirect );
			die();

		}

		/**
		 * Is date field
		 *
		 * @param  [type]  $input_type [description]
		 * @return boolean             [description]
		 */
		public function to_timestamp( $field ) {

			if ( empty( $field['type'] ) ) {
				return false;
			}

			if ( empty( $field['is_timestamp'] ) ) {
				return false;
			}

			if ( ! in_array( $field['type'], array( 'date', 'datetime-local' ) ) ) {
				return false;
			}

			return ( true === $field['is_timestamp'] );

		}

		/**
		 * Maybe apply sanitize callback
		 *
		 * @param mixed $value
		 * @param array $field
		 *
		 * @return mixed
		 */
		public function maybe_apply_sanitize_callback( $value, $field ) {

			if ( is_array( $value ) && 'repeater' === $field['type'] && ! empty( $field['fields'] ) ) {
				foreach ( $value as $item_id => $item ) {
					foreach ( $item as $sub_item_id => $sub_item_value ) {
						$value[ $item_id ][ $sub_item_id ] = $this->maybe_apply_sanitize_callback( $sub_item_value, $field['fields'][ $sub_item_id ] );
					}
				}
			} elseif ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
				$value = call_user_func( $field['sanitize_callback'], $value, $field['name'], $field );
			}

			return $value;
		}

		/**
		 * Show saved notice
		 *
		 * @return bool
		 */
		public function saved_notice() {

			if ( ! isset( $_GET['dialog-saved'] ) ) {
				return false;
			}

			$message = __( 'Saved', 'jet-engine' );

			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', $message );

			return true;

		}

		/**
		 * Initialize page builder
		 *
		 * @return [type] [description]
		 */
		public function init_builder() {

			$builder_data = jet_engine()->framework->get_included_module_data( 'cherry-x-interface-builder.php' );

			$this->builder = new \CX_Interface_Builder(
				array(
					'path' => $builder_data['path'],
					'url'  => $builder_data['url'],
				)
			);

			$slug = $this->page['slug'];

			$this->builder->register_section(
				array(
					$slug => array(
						'type'   => 'section',
						'scroll' => false,
						'title'  => $this->page['labels']['name'],
					),
				)
			);

			$this->builder->register_form(
				array(
					$slug . '_form' => array(
						'type'   => 'form',
						'parent' => $slug,
						'action' => add_query_arg(
							array(
								'page'   => $slug,
								'action' => $this->save_action,
							),
							esc_url( admin_url( 'admin.php' ) )
						),
					),
				)
			);

			$this->builder->register_settings(
				array(
					'settings_top' => array(
						'type'   => 'settings',
						'parent' => $slug . '_form',
					),
					'settings_bottom' => array(
						'type'   => 'settings',
						'parent' => $slug . '_form',
					),
				)
			);

			if ( ! empty( $this->page['fields'] ) ) {

				$this->builder->register_control(
					$this->get_prepared_fields()
				);

			}

			$label = __( 'Save', 'jet-engine' );

			$this->builder->register_html(
				array(
					'save_button' => array(
						'type'   => 'html',
						'parent' => 'settings_bottom',
						'class'  => 'cx-component dialog-save',
						'html'   => '<button type="submit" class="cx-button cx-button-primary-style">' . $label . '</button>',
					),
				)
			);

		}

		/**
		 * Get saved options
		 *
		 * @param  [type]  $option [description]
		 * @param  boolean $default [description]
		 * @return [type]           [description]
		 */
		public function get( $option, $default = false ) {

			if ( null === $this->options ) {
				$this->options = get_option( $this->slug, array() );
			}

			return isset( $this->options[ $option ] ) ? wp_unslash( $this->options[ $option ] ) : $default;

		}

		/**
		 * Render options page
		 *
		 * @return [type] [description]
		 */
		public function render_page() {
			echo '<div class="jet-engine-options-page-wrap">';
			$this->builder->render();
			echo '</div>';
		}

		/**
		 * Prepare stored fields array to be registered in interface builder
		 *
		 * @return array
		 */
		public function get_prepared_fields() {

			if ( null !== $this->prepared_fields ) {
				return $this->prepared_fields;
			}

			$fields            = $this->page['fields'];
			$result            = array();
			$date_assets_added = false;

			foreach ( $fields as $field ) {

				if ( isset( $field['title'] ) ) {
					$title = $field['title'];
				} elseif ( isset( $field['label'] ) ) {
					$title = $field['label'];
				} else {
					$title = '';
				}

				$field_name = $field['name'];

				if ( ! empty( $field['object_type'] ) && 'field' !== $field['object_type'] ) {

					// process endpoint
					if ( 'endpoint' === $field['object_type'] ) {
						$this->current_component = false;
						$this->current_panel     = false;
						continue;
					}

					if ( $this->current_component && $this->layout_now !== $field['object_type'] ) {
						$this->current_component = false;
						$this->layout_now        = false;
					}

					// Start layout element
					if ( ! $this->current_component ) {

						$this->current_component = $field['name'] . '_' . $field['object_type'];
						$this->layout_now        = $field['object_type'];

						switch ( $field['object_type'] ) {
							case 'tab':
								$layout = ! empty( $field['tab_layout'] ) ? $field['tab_layout'] : 'horizontal';
								$type   = 'component-tab-' . $layout;
								break;

							case 'accordion':
								$type = 'component-accordion';
								break;
						}

						$result[ $this->current_component ] = array(
							'type'   => $type,
							'parent' => 'settings_top',
						);

					}

					// Start new group inside layout element
					$this->current_panel = $field['name'];

					$result[ $this->current_panel ] = array(
						'type'   => 'settings',
						'parent' => $this->current_component,
						'title'  => $title,
					);

					continue;

				}

				if ( ! empty( $this->current_panel ) ) {
					$parent = $this->current_panel;
				} else {
					$parent = 'settings_top';
				}

				$result[ $field['name'] ] = array(
					'type'    => $field['type'],
					'element' => 'control',
					'title'   => $title,
					'parent'  => $parent,
					'id'      => $field['name'],
					'name'    => $field['name'],
					'value'   => false,
				);

				if ( empty( $field['description'] ) ) {
					$result[ $field['name'] ]['description'] = __( 'Name: ', 'jet-engine' ) . $field['name'];
				} else {
					$result[ $field['name'] ]['description'] = $field['description'] . '<br>' . __( 'Name: ', 'jet-engine' ) . $field['name'];
				}

				if ( ! empty( $field['is_required'] ) ) {
					$result[ $field['name'] ]['required'] = true;
				}

				if ( ! empty( $field['default_val'] ) ) {
					$result[ $field['name'] ]['value'] = $field['default_val'];
				}

				if ( ! empty( $field['conditional_logic'] ) && filter_var( $field['conditional_logic'], FILTER_VALIDATE_BOOLEAN ) ) {
					$conditions = $this->prepare_field_conditions( $field );

					if ( ! empty( $conditions ) ) {
						$result[ $field['name'] ]['conditions'] = $conditions;
					}
				}

				switch ( $field['type'] ) {
					case 'select':

						if ( empty( $field['options'] ) ) {
							$field['options'] = array();
						}

						$prepared_options = $this->prepare_select_options( $field );

						$result[ $field['name'] ]['options'] = $prepared_options['options'];

						if ( ! empty( $prepared_options['default'] ) ) {
							$result[ $field['name'] ]['value'] = $prepared_options['default'];
						}

						$multiple = ! empty( $field['is_multiple'] ) ? $field['is_multiple'] : false;
						$multiple = filter_var( $multiple, FILTER_VALIDATE_BOOLEAN );

						if ( $multiple ) {
							$result[ $field['name'] ]['multiple'] = true;
						}

						break;

					case 'checkbox':

						if ( empty( $field['options'] ) ) {
							$field['options'] = array();
						}

						$prepared_options                    = $this->prepare_select_options( $field );
						$result[ $field['name'] ]['options'] = $prepared_options['options'];

						if ( ! empty( $prepared_options['default'] ) ) {
							$result[ $field['name'] ]['value'] = $prepared_options['default'];
						}

						if ( ! empty( $field['allow_custom'] ) && filter_var( $field['allow_custom'], FILTER_VALIDATE_BOOLEAN ) ) {
							$result[ $field['name'] ]['allow_custom_value'] = true;
						}

						break;

					case 'radio':

						if ( empty( $field['options'] ) ) {
							$field['options'] = array();
						}

						$prepared_options                    = $this->prepare_radio_options( $field['options'] );
						$result[ $field['name'] ]['options'] = $prepared_options['options'];

						if ( ! Jet_Engine_Tools::is_empty( $prepared_options['default'] ) ) {
							$result[ $field['name'] ]['value'] = $prepared_options['default'];
						}

						if ( ! empty( $field['allow_custom'] ) && filter_var( $field['allow_custom'], FILTER_VALIDATE_BOOLEAN ) ) {
							$result[ $field['name'] ]['allow_custom_value'] = true;
						}

						break;

					case 'repeater':

						if ( empty( $field['repeater-fields'] ) ) {
							$field['repeater-fields'] = array();
						}

						$result[ $field['name'] ]['fields'] = $this->prepare_repeater_fields(
							$field['repeater-fields']
						);

						break;

					case 'iconpicker':

						$result[ $field['name'] ]['icon_data'] = $this->get_icon_data();

						break;

					case 'wysiwyg':

						$result[ $field['name'] ]['sanitize_callback'] = 'jet_engine_sanitize_wysiwyg';

						break;

					case 'textarea':

						$result[ $field['name'] ]['sanitize_callback'] = 'jet_engine_sanitize_textarea';

						if ( ! empty( $field['max_length'] ) ) {
							$result[ $field['name'] ]['maxlength'] = absint( $field['max_length'] );
						}

						break;

					case 'text':

						if ( ! empty( $field['max_length'] ) ) {
							$result[ $field['name'] ]['maxlength'] = absint( $field['max_length'] );
						}

						break;

					case 'posts':

						$multiple = ! empty( $field['is_multiple'] ) ? $field['is_multiple'] : false;
						$multiple = filter_var( $multiple, FILTER_VALIDATE_BOOLEAN );

						$result[ $field['name'] ]['action']       = 'cx_search_posts';
						$result[ $field['name'] ]['post_type']    = $field['search_post_type'];
						$result[ $field['name'] ]['inline_style'] = 'width: 100%;';
						$result[ $field['name'] ]['multiple']     = $multiple;

						break;

					case 'media':

						$result[ $field['name'] ]['multi_upload'] = false;

						break;

					case 'gallery':

						$result[ $field['name'] ]['type']         = 'media';
						$result[ $field['name'] ]['multi_upload'] = 'add';

						break;

					case 'date':
					case 'time':
					case 'datetime':
					case 'datetime-local':

						$result[ $field['name'] ]['type']         = 'text';
						$result[ $field['name'] ]['input_type']   = $field['type'];
						$result[ $field['name'] ]['autocomplete'] = 'off';

						$field['is_timestamp'] = ! empty( $field['is_timestamp'] ) ? $field['is_timestamp'] : false;
						$field['is_timestamp'] = filter_var( $field['is_timestamp'], FILTER_VALIDATE_BOOLEAN );

						if ( $field['is_timestamp'] ) {

							$key                            = $field['name'];
							$result[ $key ]['is_timestamp'] = true;

							if ( is_numeric( $result[ $key ]['value'] ) ) {
								switch ( $field['type'] ) {
									case 'date':
										$result[ $key ]['value'] = date( 'Y-m-d', $result[ $key ]['value'] );
										break;

									case 'datetime-local':
										$result[ $key ]['value'] = date( 'Y-m-d\TH:i:s', $result[ $key ]['value'] );
										break;
								}
							}

						}

						if ( ! empty( $result[ $field['name'] ]['value'] ) ) {
							$val = strtotime( $result[ $field['name'] ]['value'] );

							if ( $val ) {
								$result[ $field['name'] ]['value'] = date( 'Y-m-d', $val );
							}

						}

						if ( ! $date_assets_added ) {
							$this->enqueue_date_assets();
							$date_assets_added = true;
						}

						break;

					case 'number':

						$result[ $field['name'] ]['type'] = 'stepper';

						if ( ! empty( $field['min_value'] ) ) {
							$result[ $field['name'] ]['min_value'] = $field['min_value'];
						}

						if ( ! empty( $field['max_value'] ) ) {
							$result[ $field['name'] ]['max_value'] = $field['max_value'];
						}

						if ( ! empty( $field['step_value'] ) ) {
							$result[ $field['name'] ]['step_value'] = $field['step_value'];
						}

						break;

					case 'switcher':

						// Set default value
						$result[ $field['name'] ]['value'] = false;

						break;

				}

				$result[ $field_name ]['value'] = $this->get( $field['name'], $result[ $field_name ]['value'] );

			}

			$this->prepared_fields = $result;

			return $result;

		}

		/**
		 * Enqueue date-related assets
		 *
		 * @return [type]       [description]
		 */
		public function enqueue_date_assets( $hook = false ) {

			wp_enqueue_style(
				'jet-engine-meta-boxes',
				jet_engine()->plugin_url( 'assets/css/admin/meta-boxes.css' ),
				array(),
				jet_engine()->get_version()
			);

			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-ui-slider' );

			wp_enqueue_script(
				'jquery-ui-timepicker-addon',
				jet_engine()->plugin_url( 'assets/lib/jquery-ui-timepicker/jquery-ui-timepicker-addon.min.js' ),
				array(),
				jet_engine()->get_version(),
				true
			);

			wp_enqueue_script(
				'jet-engine-meta-boxes',
				jet_engine()->plugin_url( 'assets/js/admin/meta-boxes.js' ),
				array( 'jquery' ),
				jet_engine()->get_version(),
				true
			);

			wp_enqueue_style(
				'jquery-ui-timepicker-addon',
				jet_engine()->plugin_url( 'assets/lib/jquery-ui-timepicker/jquery-ui-timepicker-addon.min.css' ),
				array(),
				jet_engine()->get_version()
			);

			wp_localize_script(
				'jet-engine-meta-boxes',
				'JetEngineMetaBoxesConfig',
				array(
					'isRTL' => is_rtl(),
				)
			);

		}

		/**
		 * Get options list for use as select options
		 *
		 * @return [type] [description]
		 */
		public function get_options_for_select() {

			$fields = array();

			if ( ! empty( $this->page['fields'] ) ) {
				foreach ( $this->page['fields'] as $field ) {

					$key = $this->slug . '::' . $field['name'];

					$fields[ $key ] = array(
						'title' => $field['title'],
						'type'  => ( 'field' === $field['object_type'] ) ? $field['type'] : $field['object_type'],
					);
				}
			}

			return array(
				'label'   => $this->page['labels']['name'],
				'options' => $fields,
			);

		}

	}

}
