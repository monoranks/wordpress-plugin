<?php
// Stand-ins for the public classes of caching plugins and hosts, loaded only by the tests that need them (once loaded,
// class_exists() is true for the rest of the run).

class WPO_Page_Cache {
	public static $enabled = true;
	public static $result  = true;
	public static $calls   = array();
	public static function instance() {
		return new self();
	}
	public function is_enabled() {
		return self::$enabled;
	}
	public static function delete_cache_by_url( $url, $recursive = false ) {
		self::$calls[] = $url;
		return self::$result;
	}
}

class WpeCommon {
	public static $calls = array();
	public static function purge_varnish_cache( $post_id = null, $force = false ) {
		self::$calls[] = $post_id;
	}
}

class Breeze_PurgeCache {}

class MonoRanks_Test_Kinsta_Purge {
	public $calls = array();
	public function initiate_purge( $post_id, $type ) {
		$this->calls[] = array( 'post', $post_id, $type );
	}
	public function purge_complete_caches() {
		$this->calls[] = array( 'all' );
	}
}

class MonoRanks_Test_Nginx_Purger {
	public $calls = array();
	public function purge_url( $url, $feed = true ) {
		$this->calls[] = $url;
	}
}
