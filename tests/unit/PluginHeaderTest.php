<?php

namespace MonoRanks\Tests;

use PHPUnit\Framework\TestCase;

/** The footer and the User-Agent read the constant, WordPress reads the header: they must say the same version. */
final class PluginHeaderTest extends TestCase {

	public function test_header_and_constant_and_readme_agree() {
		$main   = file_get_contents( dirname( __DIR__, 2 ) . '/monoranks.php' );
		$readme = file_get_contents( dirname( __DIR__, 2 ) . '/readme.txt' );
		preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $header );
		preg_match( "/MONORANKS_CONNECTOR_VERSION', '([^']+)'/", $main, $constant );
		preg_match( '/^Stable tag: (\S+)/m', $readme, $stable );
		$this->assertSame( $header[1], $constant[1], 'plugin header and MONORANKS_CONNECTOR_VERSION differ' );
		$this->assertSame( $header[1], $stable[1], 'plugin header and readme.txt Stable tag differ' );
	}
}
