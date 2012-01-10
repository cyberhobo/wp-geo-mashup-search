/*
 * Geo Mashup customization for search results map
 */
GeoMashup.addAction( 'loadedMap', function( properties, map ) {
	var search_marker, icon; 
	// Double-check that we're customizing the right map
	if ( 'search-results-map' == properties.name && properties.search_text ) {
		// The blue dot goes in the center

		if ( 'google' == properties.map_api ) {

			icon = new google.maps.Icon();
			icon.image = properties.search_plugin_url_path + '/images/bluedot16.png';
			icon.shadow = properties.search_plugin_url_path + '/images/dotshadow.png';
			icon.iconSize = new google.maps.Size( 16, 16 );
			icon.shadowSize = new google.maps.Size( 25, 16 );
			icon.iconAnchor = new google.maps.Point( 8, 8 );
			search_marker = new google.maps.Marker( map.getCenter(), {
				icon: icon,
				title: properties.search_text
			} );
			map.addOverlay( search_marker );

		} else {

			// mxn
			search_marker = new mxn.Marker( map.getCenter() );
			search_marker.addData( {
				icon: properties.search_plugin_url_path + '/images/bluedot16.png',
				iconShadow: properties.search_plugin_url_path + '/images/dotshadow.png',
				iconSize: [ 16, 16 ],
				iconShadowSize: [ 25, 16 ],
				iconAnchor: [ 8, 8 ],
				label: properties.search_text
			} );
			map.addMarker( search_marker );
		}
	}
})