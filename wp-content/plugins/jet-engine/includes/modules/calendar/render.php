<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Jet_Listing_Render_Calendar' ) ) {

	class Jet_Listing_Render_Calendar extends Jet_Engine_Render_Listing_Grid {

		public $is_first        = false;
		public $data            = false;
		public $first_day       = false;
		public $last_day        = false;
		public $multiday_events = array();
		public $posts_cache     = array();
		public $start_from      = false;

		public $prev_month_posts = array();
		public $next_month_posts = array();

		public function get_name() {
			return 'jet-listing-calendar';
		}

		public function default_settings() {
			return array(
				'lisitng_id'          => '',
				'group_by'            => 'post_date',
				'group_by_key'        => '',
				'allow_multiday'      => '',
				'end_date_key'        => '',
				'week_days_format'    => 'short',
				'custom_start_from'   => '',
				'start_from_month'    => date( 'F' ),
				'start_from_year'     => date( 'Y' ),
				'posts_query'         => array(),
				'meta_query_relation' => 'AND',
				'tax_query_relation'  => 'AND',
				'hide_widget_if'      => '',
				'caption_layout'      => 'layout-1',
			);
		}

		/**
		 * Get posts
		 *
		 * @param  array $settings
		 * @return array
		 */
		public function get_posts( $settings ) {

			add_filter( 'jet-engine/listing/grid/posts-query-args', array( $this, 'add_calendar_query' ) );
			$args  = $this->build_posts_query_args_array( $settings );
			remove_filter( 'jet-engine/listing/grid/posts-query-args', array( $this, 'add_calendar_query' ) );

			$query = new \WP_Query( $args );

			return $query->posts;

		}

		/**
		 * Prepare date query
		 *
		 * @return array
		 */
		public function add_calendar_query( $args ) {

			$settings       = $this->get_settings();
			$prepared_posts = array();
			$group_by       = $settings['group_by'];
			$meta_key       = false;

			if ( ! empty( $settings['custom_start_from'] ) ) {
				$this->start_from = ! empty( $settings['start_from_month'] ) ? $settings['start_from_month'] : date( 'F' );
				$this->start_from .= ' ';
				$this->start_from .= ! empty( $settings['start_from_year'] ) ? $settings['start_from_year'] : date( 'Y' );
			}

			switch ( $group_by ) {

				case 'post_date':
				case 'post_mod':

					if ( 'post_date' === $group_by ) {
						$db_column = 'post_date_gmt';
					} else {
						$db_column = 'post_modified_gmt';
					}

					if ( isset( $args['date_query'] ) ) {
						$date_query = $args['date_query'];
					} else {
						$date_query = array();
					}

					$month = $this->get_current_month();

					$date_query = array_merge( $date_query, array(
						array(
							'column' => $db_column,
							'year'   => date( 'Y', $month ),
							'month'  => date( 'm', $month ),
						),
					) );

					$args['date_query'] = $date_query;

					break;

				case 'meta_date':

					if ( $settings['group_by_key'] ) {
						$meta_key = esc_attr( $settings['group_by_key'] );
					}

					if ( isset( $args['meta_query'] ) ) {
						$meta_query = $args['meta_query'];
					} else {
						$meta_query = array();
					}

					if ( $meta_key ) {

						$meta_query = array_merge( $meta_query, array(
							array(
								'key'     => $meta_key,
								'value'   => array( $this->get_current_month(), $this->get_current_month( true ) ),
								'compare' => 'BETWEEN',
							),
						) );

					}

					if ( ! empty( $settings['allow_multiday'] ) && ! empty( $settings['end_date_key'] ) ) {

						$meta_query = array_merge( $meta_query, array(
							array(
								'key'     => esc_attr( $settings['end_date_key'] ),
								'value'   => array( $this->get_current_month(), $this->get_current_month( true ) ),
								'compare' => 'BETWEEN',
							),
							array(
								'relation' => 'AND',
								array(
									'key'     => $meta_key,
									'value'   => $this->get_current_month(),
									'compare' => '<'
								),
								array(
									'key'     => esc_attr( $settings['end_date_key'] ),
									'value'   => $this->get_current_month( true ),
									'compare' => '>'
								)
							),
						) );

						$meta_query['relation'] = 'OR';

					}

					$args['meta_query'] = $meta_query;

					break;

				default:
					$args = apply_filters( 'jet-engine/listing/calendar/query', $args, $group_by, $this );
					break;

			}

			$args['posts_per_page'] = -1;

			return $args;

		}

		/**
		 * Prepare posts for calendar
		 *
		 * @param array $query
		 * @param array $settings
		 * @param array $month
		 * @return array
		 */
		public function prepare_posts_for_calendar( $query, $settings, $month ) {

			$prepared_posts = array();
			$group_by       = $settings['group_by'];
			$key            = false;

			if ( empty( $query ) ) {
				return $prepared_posts;
			}

			foreach ( $query as $post ) {

				switch ( $group_by ) {

					case 'post_date':
						$key = strtotime( $post->post_date );
						break;

					case 'post_mod':
						$key = strtotime( $post->post_modified );
						break;

					case 'meta_date':

						$meta_key     = esc_attr( $settings['group_by_key'] );
						$key          = get_post_meta( $post->ID, $meta_key, true );
						$multiday     = isset( $settings['allow_multiday'] ) ? $settings['allow_multiday'] : '';
						$end_date_key = isset( $settings['end_date_key'] ) ? $settings['end_date_key'] : false;
						$end_date     = get_post_meta( $post->ID, $end_date_key, true );

						$last_day_of_prev_month = absint( date( 'j', $month['start'] - 1 ) );

						if ( $multiday && $end_date ) {

							if ( $key < $month['start'] ) {

								$from  = absint( date( 'j', $key ) );
								$until = absint( date( 'j', strtotime( date( 'Y-m-t', $key ) ) ) );

								if ( $last_day_of_prev_month > $until ) {
									$until = $last_day_of_prev_month;
								}

								for ( $j = $from; $j <= $until; $j++ ) {
									if ( empty( $this->prev_month_posts[ $j ] ) ) {
										$this->prev_month_posts[ $j ] = array( $post->ID );
									} else {
										$this->prev_month_posts[ $j ][] = $post->ID;
									}
								}

								$key = $month['start'];
							}

							if ( $end_date > $month['end'] ) {

								$from  = 1;
								$until = absint( date( 'j', $end_date ) );
								for ( $j = $from; $j <= $until; $j++ ) {
									if ( empty( $this->next_month_posts[ $j ] ) ) {
										$this->next_month_posts[ $j ] = array( $post->ID );
									} else {
										$this->next_month_posts[ $j ][] = $post->ID;
									}
								}

								$end_date = $month['end'];
							}

							$from  = absint( date( 'j', $key ) );
							$until = absint( date( 'j', $end_date ) );

							for ( $j = $from + 1; $j <= $until; $j++ ) {

								if ( empty( $this->multiday_events[ $j ] ) ) {
									$this->multiday_events[ $j ] = array( $post->ID );
								} else {
									$this->multiday_events[ $j ][] = $post->ID;
								}

								$this->posts_cache[ $post->ID ] = false;

							}

						}

						break;

					default:
						/**
						 * Should return timestamp of required month day
						 * @var int
						 */
						$key = apply_filters( 'jet-engine/listing/calendar/date-key', $key, $group_by, $this );
						break;

				}

				if ( is_numeric( $key ) ) {

					$key = date( 'j', $key );

					if ( isset( $prepared_posts[ $key ] ) ) {
						$prepared_posts[ $key ][ $post->ID ] = $post;
					} else {
						$prepared_posts[ $key ] = array( $post->ID => $post );
					}

				}

			}

			return $prepared_posts;

		}

		/**
		 * Returns current month
		 *
		 * @param  bool $last_day
		 * @return bool|false|int
		 */
		public function get_current_month( $last_day = false ) {

			if ( false !== $this->first_day && ! $last_day ) {
				return $this->first_day;
			}

			if ( false !== $this->last_day && $last_day ) {
				return $this->last_day;
			}

			if ( isset( $_REQUEST['month'] ) ) {
				$month = date( '1 F Y', strtotime( $_REQUEST['month'] ) );
			} elseif ( $this->start_from ) {
				$month = date( '1 F Y', strtotime( $this->start_from ) );
			} else {
				$month = date( '1 F Y', strtotime( 'this month' ) );
			}

			$month = strtotime( $month );

			if ( ! $last_day ) {
				$this->first_day = $month;
				return $this->first_day;
			} else {
				$this->last_day = strtotime( date( 'Y-m-t 23:59:59', $month ) );
				return $this->last_day;
			}

		}

		/**
		 * Get days number for passed month
		 *
		 * @return false|string
		 */
		public function get_days_num() {
			return date( 't', $this->get_current_month() );
		}

		/**
		 * Render posts template.
		 * Moved to separate function to be rewritten by other layouts
		 *
		 * @param  array  $query    Query array.
		 * @param  array  $settings Settings array.
		 * @return void
		 */
		public function posts_template( $query, $settings ) {

			$base_class       = $this->get_name();
			$current_month    = $this->get_current_month();
			$month            = array(
				'start' => $current_month,
				'end'   => $this->get_current_month( true ),
			);
			$prepared_posts   = $this->prepare_posts_for_calendar( $query, $settings, $month );
			$days_num         = $this->get_days_num();
			$week_begins      = (int) get_option( 'start_of_week' );
			$first_week       = true;
			$human_read_month = date( 'F Y', $current_month );
			$first_day        = date( 'w', $current_month );
			$inc              = 0;
			$pad              = $first_day - $week_begins;
			$prev_month       = strtotime( $human_read_month . ' - 1 month' );
			$human_read_prev  = date( 'F Y', $prev_month );
			$human_read_next  = date( 'F Y', strtotime( $human_read_month . ' + 1 month' ) );
			$prev_month       = date( 't', $prev_month );
			$days_format      = isset( $settings['week_days_format'] ) ? $settings['week_days_format'] : 'short';
			$multiday         = isset( $settings['allow_multiday'] ) ? $settings['allow_multiday'] : '';
			$end_date_key     = isset( $settings['end_date_key'] ) ? $settings['end_date_key'] : false;

			if ( 0 > $pad ) {
				$pad = 7 - abs( $pad );
			}

			$data_settings = array(
				'lisitng_id'          => isset( $settings['lisitng_id'] ) ? $settings['lisitng_id'] : false,
				'week_days_format'    => $days_format,
				'allow_multiday'      => $multiday,
				'end_date_key'        => $end_date_key,
				'group_by'            => isset( $settings['group_by'] ) ? $settings['group_by'] : false,
				'group_by_key'        => isset( $settings['group_by_key'] ) ? $settings['group_by_key'] : false,
				'posts_query'         => isset( $settings['posts_query'] ) ? $settings['posts_query'] : array(),
				'meta_query_relation' => isset( $settings['meta_query_relation'] ) ? $settings['meta_query_relation'] : false,
				'tax_query_relation'  => isset( $settings['tax_query_relation'] ) ? $settings['tax_query_relation'] : false,
				'hide_widget_if'      => isset( $settings['hide_widget_if'] ) ? $settings['hide_widget_if'] : false,
				'caption_layout'      => isset( $settings['caption_layout'] ) ? $settings['caption_layout'] : 'layout-1',
			);

			$data_settings = htmlspecialchars( json_encode( $data_settings ) );

			printf(
				'<div class="%1$s jet-calendar" data-settings="%2$s" data-post="%3$d">',
				$base_class, $data_settings, get_the_ID()
			);

			do_action( 'jet-engine/listing/grid/before', $this );

			do_action( 'jet-engine/listing/calendar/before', $this );

			echo '<table class="jet-calendar-grid" >';

			include jet_engine()->get_template( 'calendar/header.php' );

			echo '<tbody>';

			jet_engine()->frontend->set_listing( $settings['lisitng_id'] );

			$fallback = 1;

			// Add last days of previous month
			if ( 0 < $pad ) {

				for ( $i = 0; $i < $pad; $i++ ) {

					include jet_engine()->get_template( 'calendar/week-start.php' );

					$num                     = $prev_month - $pad + $i + 1;
					$posts                   = false;
					$padclass                = ' day-pad';
					$current_multiday_events = ! empty( $this->prev_month_posts[ $num ] ) ? $this->prev_month_posts[ $num ] : array();

					include jet_engine()->get_template( 'calendar/date.php' );
					include jet_engine()->get_template( 'calendar/week-end.php' );

					$inc++;
				}

			}

			// Current month
			for ( $i = 1; $i <= $days_num; $i++ ) {

				include jet_engine()->get_template( 'calendar/week-start.php' );

				$num      = $i;
				$posts    = ! empty( $prepared_posts[ $i ] ) ? $prepared_posts[ $i ] : array();
				$padclass = ! empty( $posts ) ? ' has-events' : '';

				$current_multiday_events = array();

				if ( ! empty( $this->multiday_events[ $i ] ) ) {
					$current_multiday_events = $this->multiday_events[ $i ];

					if ( ! $padclass ) {
						$padclass = ' has-events';
					}

				}

				include jet_engine()->get_template( 'calendar/date.php' );
				include jet_engine()->get_template( 'calendar/week-end.php' );

				$inc++;

			}

			// Add first days of next month
			$days_left = 7 - ( $inc % 7 );

			if ( 0 < $days_left ) {

				$fallback = $days_num;

				for ( $i = 1; $i <= $days_left; $i++ ) {

					include jet_engine()->get_template( 'calendar/week-start.php' );

					$num                     = $i;
					$posts                   = false;
					$padclass                = ' day-pad';
					$current_multiday_events = ! empty( $this->next_month_posts[ $num ] ) ? $this->next_month_posts[ $num ] : array();

					include jet_engine()->get_template( 'calendar/date.php' );
					include jet_engine()->get_template( 'calendar/week-end.php' );

					$inc++;

				}

			}

			$this->multiday_events   = array();
			$this->posts_cache       = array();
			$current_multiday_events = array();

			jet_engine()->frontend->reset_listing();

			echo '</tbody>';

			echo '</table>';

			do_action( 'jet-engine/listing/grid/after', $this );

			do_action( 'jet-engine/listing/calendar/after', $this );

			echo '</div>';

		}

	}

}
