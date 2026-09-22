<?php
// Unit tests run without WordPress: Brain Monkey stubs the WordPress functions the classes call.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
define( 'ABSPATH', '/tmp/' );
define( 'MONORANKS_CONNECTOR_VERSION', 'test' );
define( 'MONORANKS_CONNECTOR_FILE', dirname( __DIR__, 2 ) . '/monoranks.php' );
define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
