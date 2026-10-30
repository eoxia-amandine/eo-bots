<?php
/**
 * Translation build tool (development only, not loaded by WordPress).
 *
 * Usage, from the WordPress root: php wp-content/plugins/eo-bots/bin/i18n.php
 *
 * 1. Scans PHP and JS sources for strings of the `eo-bots` text domain and writes languages/eo-bots.pot.
 * 2. Merges them into each languages/eo-bots-{locale}.po, keeping existing translations.
 * 3. Compiles each .po into .l10n.php + .mo (PHP) and one JSON file per script handle (JavaScript).
 *
 * @package EoBots
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require getcwd() . '/wp-load.php';
require_once ABSPATH . WPINC . '/pomo/po.php';
require_once ABSPATH . WPINC . '/pomo/mo.php';

$root    = dirname( __DIR__ );
$lang    = $root . '/languages';
$handles = array(
	'assets/js/creator.js' => 'eobots-creator',
	'assets/js/widget.js'  => 'eobots-widget',
);

/* 1. Extract. */
$strings = array();
$files   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
	$path = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	if ( ! preg_match( '/\.(php|js)$/', $path ) || 0 === strpos( $path, 'bin/' ) ) {
		continue;
	}
	preg_match_all(
		"/\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'eo-bots'\s*\)/",
		file_get_contents( $file->getPathname() ),
		$matches
	);
	foreach ( $matches[1] as $raw ) {
		$text                       = stripcslashes( $raw );
		$strings[ $text ][ $path ] = true;
	}
}
ksort( $strings );

$pot = new PO();
$pot->set_header( 'Project-Id-Version', 'EO-BOTS' );
$pot->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
foreach ( $strings as $text => $paths ) {
	$pot->add_entry(
		new Translation_Entry(
			array(
				'singular'   => $text,
				'references' => array_keys( $paths ),
			)
		)
	);
}
$pot->export_to_file( $lang . '/eo-bots.pot' );
echo count( $strings ) . " strings extracted.\n";

/* 2 + 3. Merge and compile each locale. */
foreach ( glob( $lang . '/eo-bots-*.po' ) as $po_file ) {
	$locale = substr( basename( $po_file, '.po' ), strlen( 'eo-bots-' ) );
	$old    = new PO();
	$old->import_from_file( $po_file );

	$po          = new PO();
	$po->headers = $old->headers;
	$missing     = array();
	$php         = array();
	$js          = array();

	foreach ( $strings as $text => $paths ) {
		$translation = isset( $old->entries[ $text ] ) ? $old->entries[ $text ]->translations : array();
		$po->add_entry(
			new Translation_Entry(
				array(
					'singular'     => $text,
					'translations' => $translation,
					'references'   => array_keys( $paths ),
				)
			)
		);

		if ( empty( $translation[0] ) ) {
			$missing[] = $text;
			continue;
		}
		foreach ( array_keys( $paths ) as $path ) {
			if ( isset( $handles[ $path ] ) ) {
				$js[ $handles[ $path ] ][ $text ] = array( $translation[0] );
			} else {
				$php[ $text ] = $translation[0];
			}
		}
	}

	$po->export_to_file( $po_file );

	$mo          = new MO();
	$mo->headers = $po->headers;
	foreach ( $po->entries as $entry ) {
		if ( ! empty( $entry->translations[0] ) ) {
			$mo->add_entry( $entry );
		}
	}
	$mo->export_to_file( $lang . "/eo-bots-{$locale}.mo" );

	file_put_contents(
		$lang . "/eo-bots-{$locale}.l10n.php",
		'<?php' . "\nreturn " . var_export(
			array(
				'domain'       => 'eo-bots',
				'language'     => $locale,
				'plural-forms' => $po->headers['Plural-Forms'] ?? 'nplurals=2; plural=(n > 1);',
				'messages'     => $php,
			),
			true
		) . ";\n"
	);

	foreach ( $handles as $handle ) {
		$messages = array(
			'' => array(
				'domain'       => 'messages',
				'lang'         => $locale,
				'plural-forms' => $po->headers['Plural-Forms'] ?? 'nplurals=2; plural=(n > 1);',
			),
		) + ( $js[ $handle ] ?? array() );

		file_put_contents(
			$lang . "/eo-bots-{$locale}-{$handle}.json",
			wp_json_encode( array( 'locale_data' => array( 'messages' => $messages ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	echo "{$locale}: " . ( count( $strings ) - count( $missing ) ) . ' translated, ' . count( $missing ) . " missing.\n";
	foreach ( $missing as $text ) {
		echo "  - {$text}\n";
	}
}
