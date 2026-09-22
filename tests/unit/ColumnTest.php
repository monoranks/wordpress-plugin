<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Column;
use MonoRanks\Insights;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ColumnTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias( static function ( $one, $many, $n ) { return 1 === $n ? $one : $many; } );
		Functions\when( 'number_format_i18n' )->alias( static function ( $n ) { return (string) $n; } );
		Functions\when( 'human_time_diff' )->justReturn( '2 days' );
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_the_column_comes_last() {
		$cols = Column::columns( array( 'cb' => '<input>', 'title' => 'Title', 'author' => 'Author', 'date' => 'Date' ) );
		$this->assertSame( array( 'cb', 'title', 'author', 'date', 'monoranks' ), array_keys( $cols ) );
	}

	public function test_the_line_under_the_scores_prefers_fixes_over_issues() {
		$base = array( 'fixes_ready' => 0, 'open_issues' => 0, 'audited_at' => '2026-09-20T04:10:00Z' );
		$this->assertSame( '3 fixes ready · audited 2 days ago', Column::cell_line( array_merge( $base, array( 'fixes_ready' => 3, 'open_issues' => 5 ) ) ) );
		$this->assertSame( '1 open issue · audited 2 days ago', Column::cell_line( array_merge( $base, array( 'open_issues' => 1 ) ) ) );
		$this->assertSame( 'No open issues · audited 2 days ago', Column::cell_line( $base ) );
		$this->assertSame( 'No open issues', Column::cell_line( array_merge( $base, array( 'audited_at' => '' ) ) ) );
	}

	public function test_sorting_joins_once_and_keeps_unscored_posts_at_the_end() {
		global $wpdb;
		$wpdb    = new FakeWpdb(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double
		$clauses = Column::sort_clauses( array( 'join' => '', 'orderby' => 'wp_posts.post_date DESC' ), new FakeQuery( 'DESC' ) );
		$this->assertSame( 1, substr_count( $clauses['join'], 'LEFT JOIN' ) );
		$this->assertStringContainsString( "monoranks_meta.meta_key = '_monoranks_health'", $clauses['join'] );
		$this->assertSame( 'monoranks_meta.meta_value IS NULL ASC, CAST(monoranks_meta.meta_value AS SIGNED) DESC', $clauses['orderby'] );
		$this->assertSame( array( 'join' => '', 'orderby' => 'x' ), Column::sort_clauses( array( 'join' => '', 'orderby' => 'x' ), new FakeQuery( '' ) ) );
	}
}

/** Just enough of WP_Query and wpdb for the sort clauses. */
class FakeQuery {
	private $dir;
	public function __construct( $dir ) {
		$this->dir = $dir;
	}
	public function get( $key ) {
		return 'monoranks_sort' === $key ? $this->dir : '';
	}
}

class FakeWpdb {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql, ...$args ) {
		return str_replace( '%s', "'" . $args[0] . "'", $sql );
	}
}
