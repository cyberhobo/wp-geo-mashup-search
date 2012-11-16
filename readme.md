Geo Mashup Search
=================

Contributors: cyberhobo
Donate Link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=11045324
Tags: map, maps, search, geo, location, geo-search, location-search
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 1.0

Originally created as an add-on to the [Geo Mashup plugin](http://wordpress.org/extend/plugins/geo-mashup/), this functionality was incorporated into the plugin as of [1.5 Beta 1](http://code.google.com/p/wordpress-geo-mashup/downloads/detail?name=geo-mashup-1.4.99.0.zip).

Description
-----------

The widget is a small search form for posts near a text location with optional category menu and 
radius menu.

Metric or US units can be selected.

Templates are used to allow customization of the widget form and search results.

Requires Geo Mashup 1.3.x or higher and PHP 5.

"Find Me" button requires Geo Mashup 1.4.6 or higher and uses [geoPlugin](http://www.geoplugin.com/).

### Translations 

* Italian by Daniele Raimondi added in version 1.2

Frequently Asked Questions
--------------------------

### Error on activation: "Parse error: syntax error, unexpected T_CLASS..." 

Make sure your host is running PHP 5. Add this line to wp-config.php to check:

`var_dump(PHP_VERSION);`

