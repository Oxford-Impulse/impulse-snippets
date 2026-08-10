<?php
/**
 * Standalone logic tests for Impulse Snippets — no WordPress install needed.
 *
 * Run:  php tests/run-tests.php   (any PHP 7.4+ CLI)
 * Exit code 0 = all pass, 1 = failures (CI-friendly).
 *
 * These cover the plugin's pure logic (code wrapping, targeting decode/match,
 * list summaries) by loading the real plugin files with minimal WordPress
 * stubs. Anything that needs a real WordPress (admin screens, REST, saving)
 * is out of scope here — that's what a WordPress Playground walkthrough is for.
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === $number ? $single : $plural;
}

// Controllable page-state stubs for Wpci_Conditions::matches().
$GLOBALS['wp_state'] = array();
function wpci_test_state( $overrides = array() ) {
	$GLOBALS['wp_state'] = array_merge(
		array(
			'logged_in'  => false,
			'front_page' => false,
			'is_404'     => false,
			'is_search'  => false,
			'singular'   => false,
			'post_id'    => 0,
		),
		$overrides
	);
}
function is_user_logged_in() {
	return $GLOBALS['wp_state']['logged_in'];
}
function is_front_page() {
	return $GLOBALS['wp_state']['front_page'];
}
function is_404() {
	return $GLOBALS['wp_state']['is_404'];
}
function is_search() {
	return $GLOBALS['wp_state']['is_search'];
}
function is_singular( $types = null ) {
	return $GLOBALS['wp_state']['singular'];
}
function get_the_ID() {
	return $GLOBALS['wp_state']['post_id'];
}
function has_category( $terms, $post_id ) {
	return false;
}

require dirname( __DIR__ ) . '/impulse-snippets/includes/functions-helpers.php';
require dirname( __DIR__ ) . '/impulse-snippets/includes/class-wpci-conditions.php';

$pass = 0;
$fail = 0;

function check( $desc, $expected, $actual ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		echo "PASS  $desc\n";
	} else {
		$fail++;
		echo "FAIL  $desc\n      expected: " . var_export( $expected, true ) . "\n      actual:   " . var_export( $actual, true ) . "\n";
	}
}

// ---------------------------------------------------------------------------
// wpci_maybe_wrap_code() — auto-detect wrapping (the 1.14.1 bug fix).
// ---------------------------------------------------------------------------
$wrap_cases = array(
	array( 'bare JS with < (the 1.14.1 bug)', 'for (i = 0; i < 10; i++) { console.log(i); }', 'auto', '<script>for (i = 0; i < 10; i++) { console.log(i); }</script>' ),
	array( 'bare JS without <', "alert('hi');", 'auto', "<script>alert('hi');</script>" ),
	array( 'full script block', '<script>alert(1)</script>', 'auto', '<script>alert(1)</script>' ),
	array( 'div HTML', "<div class='x'>Hi</div>", 'auto', "<div class='x'>Hi</div>" ),
	array( 'HTML comment', '<!-- note -->', 'auto', '<!-- note -->' ),
	array( 'meta tag uppercase', '<META name="a" content="b">', 'auto', '<META name="a" content="b">' ),
	array( 'closing tag first', '</div>', 'auto', '</div>' ),
	array( 'empty string', '', 'auto', '' ),
	array( 'whitespace only', "  \n  ", 'auto', '' ),
	array( 'loose text + tag (accepted edge case: wrapped)', 'Hello <b>world</b>', 'auto', '<script>Hello <b>world</b></script>' ),
	array( 'non-code lt sign treated as JS', '< 5 items', 'auto', '<script>< 5 items</script>' ),
	array( 'type script, bare', 'var a = 1;', 'script', '<script>var a = 1;</script>' ),
	array( 'type script, tagged', '<script>var a = 1;</script>', 'script', '<script>var a = 1;</script>' ),
	array( 'type style, bare', 'body { color: red; }', 'style', '<style>body { color: red; }</style>' ),
	array( 'type style, tagged', '<style>body{}</style>', 'style', '<style>body{}</style>' ),
	array( 'type html passthrough', 'anything <at> all', 'html', 'anything <at> all' ),
);
foreach ( $wrap_cases as $c ) {
	check( 'wrap: ' . $c[0], $c[3], wpci_maybe_wrap_code( $c[1], $c[2] ) );
}

// ---------------------------------------------------------------------------
// Wpci_Conditions::decode() — tolerant of missing data, strict on corruption.
// ---------------------------------------------------------------------------
check( 'decode: empty -> all', 'all', Wpci_Conditions::decode( '' )['type'] );
check( 'decode: garbage -> invalid', 'invalid', Wpci_Conditions::decode( '{{{' )['type'] );
check( 'decode: valid specific kept', 'specific', Wpci_Conditions::decode( '{"type":"specific","post_ids":[1]}' )['type'] );
check( 'decode: bad visitor -> invalid', 'invalid', Wpci_Conditions::decode( '{"type":"all","visitor":"weird"}' )['type'] );

// ---------------------------------------------------------------------------
// Wpci_Conditions::matches() — visitor gate, special pages, fail-closed.
// ---------------------------------------------------------------------------
$match_cases = array(
	array( 'all + everyone', '{"type":"all"}', array(), true ),
	array( 'logged-in gate blocks visitor', '{"type":"all","visitor":"logged_in"}', array(), false ),
	array( 'logged-in gate passes user', '{"type":"all","visitor":"logged_in"}', array( 'logged_in' => true ), true ),
	array( 'logged-out gate blocks user', '{"type":"all","visitor":"logged_out"}', array( 'logged_in' => true ), false ),
	array( 'logged-out gate passes visitor', '{"type":"all","visitor":"logged_out"}', array(), true ),
	array( 'bad visitor value fails closed', '{"type":"all","visitor":"weird"}', array(), false ),
	array( 'front page hits', '{"type":"special","pages":["front"]}', array( 'front_page' => true ), true ),
	array( 'front page misses elsewhere', '{"type":"special","pages":["front"]}', array( 'singular' => true ), false ),
	array( '404 hits', '{"type":"special","pages":["404","search"]}', array( 'is_404' => true ), true ),
	array( 'search hits', '{"type":"special","pages":["search"]}', array( 'is_search' => true ), true ),
	array( 'special with no pages never shows', '{"type":"special","pages":[]}', array( 'front_page' => true ), false ),
	array( 'special + visitor combine', '{"type":"special","pages":["front"],"visitor":"logged_out"}', array( 'front_page' => true, 'logged_in' => true ), false ),
	array( 'specific post hits', '{"type":"specific","post_ids":[7]}', array( 'singular' => true, 'post_id' => 7 ), true ),
	array( 'specific post misses', '{"type":"specific","post_ids":[7]}', array( 'singular' => true, 'post_id' => 8 ), false ),
	array( 'garbage fails closed', '{{{', array(), false ),
);
foreach ( $match_cases as $c ) {
	wpci_test_state( $c[2] );
	check( 'match: ' . $c[0], $c[3], Wpci_Conditions::matches( $c[1] ) );
}
wpci_test_state();

// ---------------------------------------------------------------------------
// wpci_get_conditions_summary() — honest list-table labels.
// ---------------------------------------------------------------------------
$summary_cases = array(
	array( 'empty meta -> All pages', '', 'All pages' ),
	array( 'explicit all -> All pages', '{"type":"all"}', 'All pages' ),
	array( 'garbage JSON -> invalid label', 'not-json-at-all', 'Invalid targeting — output disabled' ),
	array( 'unknown type -> invalid label', '{"type":"weird"}', 'Invalid targeting — output disabled' ),
	array( '2 specific posts', '{"type":"specific","post_ids":[4,9]}', '2 specific pages/posts' ),
	array( '1 post type', '{"type":"post_types","post_types":["page"]}', '1 post type' ),
	array( '2 special pages', '{"type":"special","pages":["front","404"]}', '2 special pages' ),
	array( 'visitor suffix logged-in', '{"type":"all","visitor":"logged_in"}', 'All pages — logged-in only' ),
	array( 'visitor suffix logged-out on specific', '{"type":"specific","post_ids":[1],"visitor":"logged_out"}', '1 specific page/post — logged-out only' ),
);
foreach ( $summary_cases as $c ) {
	check( 'summary: ' . $c[0], $c[2], wpci_get_conditions_summary( $c[1] ) );
}

// ---------------------------------------------------------------------------
echo "\n" . ( $fail ? "$fail FAILURE(S), $pass passed\n" : "ALL $pass TESTS PASSED\n" );
exit( $fail ? 1 : 0 );
