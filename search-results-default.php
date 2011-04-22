<?php
/**
 * Default Geo Mashup Search results template.
 *
 * THIS FILE WILL BE OVERWRITTEN BY AUTOMATIC UPGRADES
 * See the geo-mashup-search.php plugin file for license
 *
 * Copy this to a file named geo-mashup-search-results.php in your active theme folder
 * to customize. For bonus points delete this message in the copy!
 *
 * Variables in scope:
 * $geo_mashup_search  object   The managing search object
 * $search_text        string   The search text entered in the form
 * $radius             int      The search radius
 * $units              string   'mi' or 'km'
 *
 * Methods of $geo_mashup_search mimic WordPress Loop functions have_posts()
 * and the_post() (see http://codex.wordpress.org/The_Loop). This makes post
 * template functions like the_title() work as expected. For distance:
 *
 * $geo_mashup_search->the_distance();
 *
 * will echo the distance with units. Its output can be modified:
 *
 * $geo_mashup_search->the_distance( 'decimal_places=1&append_units=0&echo=0' );
 */
?>
<div id="geo-mashup-search-results">

	<h2><?php _e( 'Search results near', 'GeoMashupSearch' ); ?> "<?php echo $search_text; ?>"</h2>

	<?php if ( $geo_mashup_search->have_posts() ) : ?>

	<?php while ( $geo_mashup_search->have_posts() ) : $geo_mashup_search->the_post(); ?>
			<div class="search-result">
				<h3><a href="<?php the_permalink(); ?>" title=""><?php the_title(); ?></a></h3>
				<p><?php the_excerpt(); ?></p>
				<p>
			<?php _e( 'Distance', 'GeoMashupSearch' ); ?>:
			<?php $geo_mashup_search->the_distance(); ?>
		</p>
	</div>
	<?php endwhile; ?>

	<?php else : ?>

				<p><?php _e( 'No results found.', 'GeoMashupSearch' ); ?></p>

	<?php endif; ?>
</div>