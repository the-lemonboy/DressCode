<?php
/**
 * Extract translatable strings (text domain: dresscode) into a POT file.
 */
$root = dirname( __DIR__ );
$files = array_merge(
	glob( $root . '/includes/class-dresscode-*.php' ),
	array(
		$root . '/includes/class-wordpress-plugin-template-settings.php',
		$root . '/includes/class-wordpress-plugin-template.php',
		$root . '/dresscode.php',
	)
);

$entries = array(); // string => list of "file:line"
$fn      = '(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|_)';

foreach ( $files as $f ) {
	$code   = file_get_contents( $f );
	$tokens = token_get_all( $code );
	$n      = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0 ] ) {
			continue;
		}
		if ( ! preg_match( '/^(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|__|_)$/', $tokens[ $i ][1] ) ) {
			continue;
		}
		// Find the opening paren, then the literal string arg, then the domain literal.
		$j = $i + 1;
		while ( $j < $n && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) { $j++; }
		if ( $j >= $n || ! is_string( $tokens[ $j ] ) || '(' !== $tokens[ $j ] ) {
			continue;
		}
		$j++;
		while ( $j < $n && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) { $j++; }
		if ( $j >= $n || ! is_array( $tokens[ $j ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $j ][0] ) {
			continue;
		}
		$first = $tokens[ $j ][1];
		$k = $j + 1;
		while ( $k < $n && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) { $k++; }
		if ( $k >= $n || ! is_string( $tokens[ $k ] ) || ',' !== $tokens[ $k ] ) {
			continue;
		}
		$k++;
		while ( $k < $n && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) { $k++; }
		if ( $k >= $n || ! is_array( $tokens[ $k ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $k ][0] ) {
			continue;
		}
		if ( "'dresscode'" !== $tokens[ $k ][1] ) {
			continue;
		}
		$str = eval( 'return ' . $first . ';' ); // string literal in source
		$loc = basename( $f ) . ':' . $tokens[ $i ][2];
		$entries[ $str ][] = $loc;
	}
}

ksort( $entries );
$pot  = "# DressCode Tool translation template.\n";
$pot .= 'msgid ""' . "\n";
$pot .= 'msgstr ""' . "\n";
$pot .= '"Project-Id-Version: DressCode 0.1.0\n"' . "\n";
$pot .= '"Language: \n"' . "\n";
$pot .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$pot .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$pot .= '"X-Domain: dresscode\n"' . "\n\n";
foreach ( $entries as $str => $locs ) {
	foreach ( array_unique( $locs ) as $loc ) {
		$pot .= '#: ' . $loc . "\n";
	}
	$pot .= 'msgid "' . str_replace( array( '\\', '"', "\n", "\r", "\t" ), array( '\\\\', '\\"', '\\n', '', '\\t' ), $str ) . '"' . "\n";
	$pot .= 'msgstr ""' . "\n\n";
}

file_put_contents( $root . '/lang/dresscode.pot', $pot );
echo count( $entries ) . " strings written to lang/dresscode.pot\n";
foreach ( array_keys( $entries ) as $s ) {
	echo '  - ' . $s . "\n";
}
