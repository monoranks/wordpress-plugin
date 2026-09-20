<?php
// PHP-Scoper configuration (php-scoper installed as a phar or global tool, PHP 8.2+): when the plugin gains Composer
// dependencies, `composer run scope` prefixes them under
// MonoRanks\Vendor so they cannot collide with other plugins' copies. WordPress and the plugin's own classes are excluded.
declare(strict_types=1);

return array(
	'prefix'                  => 'MonoRanks\\Vendor',
	'finders'                 => array(),
	'exclude-namespaces'      => array( 'MonoRanks' ),
	'exclude-classes'         => array( '/^WP_/', 'wpdb' ),
	'exclude-functions'       => array( '/^wp_/', '/^get_/', '/^add_/', '/^apply_filters$/', '/^do_action$/', '/^esc_/', '/^__$/', '/^_e$/', '/^sanitize_/' ),
	'exclude-constants'       => array( '/^WP_/', 'ABSPATH' ),
	'expose-global-constants' => true,
	'expose-global-classes'   => true,
	'expose-global-functions' => true,
);
