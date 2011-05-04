<?php
/*
  Plugin Name: Geo Mashup Search
  Plugin URI: http://code.google.com/p/wordpress-geo-mashup/downloads
  Description: Requires the Geo Mashup plugin. Adds a geo search widget.
  Version: 1.0
  License: GPL2+
  Author: Dylan Kuhn
  Author URI: http://www.cyberhobo.net/
  Minimum WordPress Version Required: 3.0
 */

/*
  Copyright (c) 2005-2011 Dylan Kuhn

  This program is free software; you can redistribute it
  and/or modify it under the terms of the GNU General Public
  License as published by the Free Software Foundation;
  either version 2 of the License, or (at your option) any
  later version.

  This program is distributed in the hope that it will be
  useful, but WITHOUT ANY WARRANTY; without even the implied
  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the GNU General Public License for more
  details.
 */

/**
 * The Geo Mashup Search class.
 */
if ( !class_exists( 'GeoMashupSearch' ) ) {

	class GeoMashupSearch {
		const MILES_PER_KILOMETER = 0.621371;

		public $dir_path;
		public $url_path;
		public $basename;

		private $results;
		private $result;
		private $current_result;
		private $result_count;
		private $units;

		/**
		 * Static instance access.
		 * @static
		 */
		public static function get_instance() {
			static $the_instance = null;
			if ( is_null( $the_instance ) ) {
				$the_instance = new GeoMashupSearch();
			}
			return $the_instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {

			// Initialize members
			$this->dir_path = dirname( __FILE__ );
			$this->basename = plugin_basename( __FILE__ );
			$this->url_path = plugins_url( '', __FILE__ );
			load_plugin_textdomain( 'GeoMashupSearch', '', dirname( $this->basename ) );

			// Scan Geo Mashup after it has been loaded
			add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );

			// Initialize
			add_action( 'init', array( $this, 'action_init' ) );

			// Add the search widget
			add_action( 'widgets_init', array( $this, 'action_widgets_init' ) );
		}

		/**
		 * Once all plugins are loaded, we can examine Geo Mashup.
		 */
		public function action_plugins_loaded() {
			if ( !class_exists( 'GeoMashup' ) ) {
				add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
			}
		}

		/**
		 * Add hooks needed for the current request.
		 */
		public function action_init() {
			if ( isset( $_REQUEST['geo_mashup_search_submit'] ) ) {
				// Add search results to page content
				add_filter( 'the_content', array( $this, 'filter_the_content' ) );
			}
		}

		/**
		 * Register the search widget.
		 */
		public function action_widgets_init() {
			register_widget( 'GeoMashupSearchWidget' );
		}

		/**
		 * Raise a notice and deactivate if dependencies are missing.
		 */
		public function action_admin_notices() {
			if ( !class_exists( 'GeoMashup' ) ) {
				echo '<div class="updated fade">';
				echo __( 'The Geo Mashup plugin must be installed and activated before Geo Mashup Search.', 'GeoMashupSearch' );
				echo '</div>';
				deactivate_plugins( $this->basename );
			}
		}

		/**
		 * WordPress filter to add geo mashup search results to page content
		 * when requested.
		 *
		 * @param string $content
		 * @return string Content including search results if requested.
		 */
		public function filter_the_content( $content ) {

			if ( !isset( $_REQUEST['geo_mashup_search_submit'] ) )
				return $content;

			// Remove this filter to prevent recursion
			remove_filter( 'the_content', array( $this, 'filter_the_content' ) );

			$this->results = array();
			$this->result_count = 0;
			$this->result = null;
			$this->current_result = -1;
			$this->units = isset( $_REQUEST['units'] ) ? $_REQUEST['units'] : 'km';
			$search_text = isset( $_REQUEST['location_text'] ) ? $_REQUEST['location_text'] : '';
			$units = $this->units; // Put $units in template scope
			$radius = isset( $_REQUEST['radius'] ) ? $_REQUEST['radius'] : '';
			$distance_factor = ( 'km' == $this->units ) ? 1 : self::MILES_PER_KILOMETER;
			$max_km = 20000;
			$geo_mashup_search = &$this;
			if ( !empty( $_REQUEST['location_text'] ) ) {
				$near_location = GeoMashupDB::blank_location( ARRAY_A );
				if ( GeoMashupDB::geocode( $_REQUEST['location_text'], $near_location ) ) {
					// A search center was found, we can continue
					$geo_query_args = array(
						'object_name' => 'post',
						'near_lat' => $near_location['lat'],
						'near_lng' => $near_location['lng'],
						'sort' => 'distance_km ASC'
					);
					$radius_km = $max_km;

					if ( isset( $_REQUEST['radius'] ) )
						$radius_km = absint( $_REQUEST['radius'] ) / $distance_factor;

					$geo_query_args['radius_km'] = $radius_km;

					if ( isset( $_REQUEST['map_cat'] ) )
						$geo_query_args['map_cat'] = $_REQUEST['map_cat'];

					$this->results = GeoMashupDB::get_object_locations( $geo_query_args );
					$this->result_count = count( $this->results );
					if ( $this->result_count > 0 )
							$max_km = $this->results[$this->result_count-1]->distance_km;
				}
			}
			$approximate_zoom = absint( log( 10000 / $max_km, 2 ) );

			// Buffer output from the template
			$template = locate_template( 'geo-mashup-search-results.php' );
			if ( !$template )
				$template = path_join( GeoMashupSearch::get_instance()->dir_path, 'search-results-default.php' );
			ob_start();
			require( $template );
			$content .= ob_get_clean();

			// This filter shouldn't run more than once per request, so don't bother adding it again

			return $content;
		}

		/**
		 * Whether there are more results to loop through.
		 *
		 * @return boolean True if there are more results, otherwise false.
		 */
		public function have_posts() {
			if ( $this->current_result + 1 < $this->result_count ) {
				return true;
			} elseif ( $this->current_result + 1 == $this->result_count and $this->result_count > 0 ) {
				wp_reset_postdata();
			}
			return false;
		}

		/**
		 * Get an array of the post IDs found.
		 *
		 * @return array Post IDs.
		 */
		public function get_the_IDs() {
			return wp_list_pluck( $this->results, 'object_id' );
		}

		/**
		 * Get a comma separated list of the post IDs found.
		 *
		 * @return string ID list.
		 */
		public function get_the_ID_list() {
			return implode( ',', $this->get_the_IDs() );
		}

		/**
		 * Set up the the current post to use in the results loop.
		 */
		public function the_post() {
			global $post;
			$this->current_result++;
			$this->result = $this->results[$this->current_result];
			if ( $this->result ) {
				$post = get_post( $this->result->object_id );
				setup_postdata( $post );
			}
		}

		/**
		 * Display or retrieve the distance from the search point to the current result.
		 *
		 * @param string|array $args Tag arguments
		 * @return null|string Null on failure or display, string if echo is false.
		 */
		public function the_distance( $args = '' ) {

			if ( empty( $this->result ) )
				return null;

			$default_args = array(
				'decimal_places' => 2,
				'append_units' => true,
				'echo' => true
			);
			$args = wp_parse_args( $args, $default_args );
			extract( $args );
			$factor = ( 'km' == $this->units ) ? 1 : self::MILES_PER_KILOMETER;
			$distance = round( $this->result->distance_km * $factor, $decimal_places );
			if ( $append_units )
				$distance .= ' ' . $this->units;
			if ( $echo )
				echo $distance;
			else
				return $distance;
		}

		public function action_wp_footer() {
			global $wp_scripts;
			// If jQuery is in use, enhance the search form(s) a bit
			?>
			<script type="text/javascript">
			if ( typeof jQuery !== 'undefined' ) {
				jQuery( function( $ ) {
					$( '.geo-mashup-search-input' ).focus( function() {
						$( this ).val('');
					} );
				})
			}
			</script>
			<?php
		}
	}

	// end Geo Mashup Search class
	// Instantiate
	GeoMashupSearch::get_instance();
} // end if Geo Mashup Search class exists

class GeoMashupSearchWidget extends WP_Widget {


	// Construct Widget
	function __construct() {
		$default_options = array(
			'description' => __( 'Search posts by Geo Mashup location.', 'GeoMashupSearch' )
		);
		parent::__construct( false, __( 'Geo Mashup Search', 'GeoMashupSearch' ), $default_options );
	}

	// Display Widget
	function widget( $args, $instance ) {
		// Add the footer script action
		add_action( 'wp_footer', array( GeoMashupSearch::get_instance(), 'action_wp_footer' ) );

		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $before_widget . $before_title . $title . $after_title;
		$results_page_id = intval( $instance['results_page_id'] );
		if ( !$results_page_id ) {
			echo '<p class="error">';
			_e( 'No Geo Mashup Search result page found - check widget settings.', 'GeoMashupSearch' );
			echo '</p>';
			return;
		}
		// Set up template variables 
		$widget = &$this;
		$action_url = get_permalink( $results_page_id );
		$categories = array( );
		if ( !empty( $instance['categories'] ) ) {
			$category_args = '';
			if ( 'all' != $instance['categories'] ) {
				$category_args = 'include=' . $instance['categories'];
			}
			$categories = get_categories( $category_args );
		}
		$radii = empty( $instance['radius_list'] ) ? array( ) : wp_parse_id_list( $instance['radius_list'] );

		// Load the template
		$template = locate_template( 'geo-mashup-search-form.php' );
		if ( !$template )
			$template = path_join( GeoMashupSearch::get_instance()->dir_path, 'search-form-default.php' );
		require( $template );

		echo $after_widget;
	}

	// Update Widget
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['default_search_text'] = sanitize_text_field( $new_instance['default_search_text'] );
		$instance['categories'] = sanitize_text_field( $new_instance['categories'] );
		$instance['units'] = in_array( $new_instance['units'], array( 'km', 'mi' ) ) ? $new_instance['units'] : 'km';
		$instance['radius_list'] = sanitize_text_field( $new_instance['radius_list'] );
		$instance['results_page_id'] = intval( $new_instance['results_page_id'] );

		return $instance;
	}

	// Default value logic
	function get_default_value( $instance, $field, $fallback = '', $escape_callback = 'esc_attr' ) {
		if ( isset( $instance[$field] ) )
			$value = $instance[$field];
		else
			$value = $fallback;

		if ( function_exists( $escape_callback ) )
			$value = call_user_func( $escape_callback, $value );

		return $value;
	}

	// Display Widget Control
	function form( $instance ) {
		$categories = get_categories( 'hide_empty=0' );
		$pages = get_pages();
?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"
					 title="<?php _e( 'Widget heading, leave blank to omit.', 'GeoMashupSearch' ); ?>">
				 <?php _e( 'Title:', 'GeoMashupSearch' ); ?>
		<span class="help-tip">?</span>
		<input class="widefat"
				 id="<?php echo $this->get_field_id( 'title' ); ?>"
				 name="<?php echo $this->get_field_name( 'title' ); ?>"
				 type="text"
				 value="<?php echo $this->get_default_value( $instance, 'title' ); ?>" />
	</label>
</p>
<p>
	<label for="<?php echo $this->get_field_id( 'default_search_text' ); ?>"
			 title="<?php _e( 'Default text in the search text box for use as a prompt, leave blank to omit.', 'GeoMashupSearch' ); ?>">
				 <?php _e( 'Default Search Text:', 'GeoMashupSearch' ); ?>
		<span class="help-tip">?</span>
		<input class="widefat"
				 id="<?php echo $this->get_field_id( 'default_search_text' ); ?>"
				 name="<?php echo $this->get_field_name( 'default_search_text' ); ?>"
				 type="text"
				 value="<?php echo $this->get_default_value( $instance, 'default_search_text', __( 'city, state or zip', 'GeoMashupSearch' ) ); ?>" />
	</label>
</p>
<p>
	<label for="<?php echo $this->get_field_id( 'categories' ); ?>"
			 title="<?php _e( 'Category dropdown contents. Blank to omit, \'all\' for all post categories, or comma separated category IDs to include.', 'GeoMashupSearch' ); ?>">
				 <?php _e( 'Category Menu:', 'GeoMashupSearch' ); ?>
		<span class="help-tip">?</span>
		<input class="widefat"
				 id="<?php echo $this->get_field_id( 'categories' ); ?>"
				 name="<?php echo $this->get_field_name( 'categories' ); ?>"
				 type="text"
				 value="<?php echo $this->get_default_value( $instance, 'categories' ); ?>" />
	</label>
</p>
<p>
	<label for="<?php echo $this->get_field_id( 'units' ); ?>">
		<?php _e( 'Units:', 'GeoMashupSearch' ); ?>
		 		<select id="<?php echo $this->get_field_id( 'units' ); ?>" name="<?php echo $this->get_field_name( 'units' ); ?>">
		 			<option value="mi"<?php echo 'mi' == $this->get_default_value( $instance, 'units' ) ? ' selected="selected"' : ''; ?>>
				<?php _e( 'miles', 'GeoMashupSearch' ); ?>
 			</option>
 			<option value="km"<?php echo 'km' == $this->get_default_value( $instance, 'units' ) ? ' selected="selected"' : ''; ?>>
				<?php _e( 'kilometers', 'GeoMashupSearch' ); ?>
 			</option>
 		</select>
 	</label>
 </p>
 <p>
 	<label for="<?php echo $this->get_field_id( 'radius_list' ); ?>"
 			 title="<?php _e( 'Radius dropdown contents. Blank to omit, or comma separated numeric distances in selected units.', 'GeoMashupSearch' ); ?>">
				 <?php _e( 'Radius Menu:', 'GeoMashupSearch' ); ?>
		<span class="help-tip">?</span>
		<input class="widefat"
				 id="<?php echo $this->get_field_id( 'radius_list' ); ?>"
				 name="<?php echo $this->get_field_name( 'radius_list' ); ?>"
				 type="text"
				 value="<?php echo $this->get_default_value( $instance, 'radius_list' ); ?>" />
	</label>
</p>
<p>
	<label for="<?php echo $this->get_field_id( 'results_page_id' ); ?>"
			 title="<?php _e( 'The page where search results should be displayed.', 'GeoMashupSearch' ); ?>">
				 <?php _e( 'Results Page:', 'GeoMashupSearch' ); ?>
	<select id="<?php echo $this->get_field_id( 'results_page_id' ); ?>" name="<?php echo $this->get_field_name( 'results_page_id' ); ?>">
		<?php foreach ( $pages as $page ) : ?>
				<option value="<?php echo $page->ID; ?>"<?php echo $page->ID == $this->get_default_value( $instance, 'results_page_id' ) ? ' selected="selected"' : ''; ?>>
			<?php echo $page->post_name; ?>
			</option>
		<?php endforeach; ?>
	</select>
	</label>
</p>
<?php
	 }

 }
