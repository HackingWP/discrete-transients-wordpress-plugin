Discrete Transients WordPress Plugin
====================================

Discrete Transients plugin puts you in charge of defining your own table where WordPress stores transients.

If you never heard of [Wordpress Transient API](http://codex.wordpress.org/Transients_API), *[Google it](https://www.google.com/search?q=transient+api+wordpress+cache)*, it's the new
kid on the block for the rest of us. If you want your WordPress site(s) to run a bit faster
(+ gaining some love from your visitors and Google from page loading speed as a side effect is always nice)
but don't need/want dedicated caching setups but, **transients** are here to save your day.

Even though I fell in love with the Transient API, I realized that the `wp_options` could go pretty wild
when not used correctly.

* There is no garbage collector in the Transient API keeping forgotten data in table
* There is a pretty chance of damaging your site when cleaning `wp_options` table by hand
* Speed gain can be harmed (maybe lost) as your table gets bigger (I assume)

Any way, having it all in a discrete table can do no harm. And if something goes wrong (I can't think of 
a single thing right now), just deactivate the plugin ang you are back to using built-in table.

### Installation

As with all WordPress plugins, copy all files to WordPress plugins folder

### Features

* Creates new table similar to `wp_options` table
* Flush all transients entirely without harm to `wp_options` via WordPress Admin plugins page link
* Add `define('DISCRETE_TRANSIENT_TABLE', 'my_transients_table');` in `wp_config.php` for custom table name
* Add `define('DISCRETE_TRANSIENT_LOG_QUERIES', true);` in `wp_config.php` to turn on logging of modified SQL queries

### Roadmap

* Register plugin in official WordPress Plugins repository
* Add option to flush transients only when already expired

If you like the plugin or just want to say hi find me on twitter.

*get lucky*

[@martin_adamko](http://twitter.com/martin_adamko)
