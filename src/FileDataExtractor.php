<?php

namespace WP_CLI\I18n;

class FileDataExtractor {
	/**
	 * Retrieves metadata from a file.
	 *
	 * Searches for metadata in the first 8kiB of a file, such as a plugin or theme.
	 * Each piece of metadata must be on its own line. Fields can not span multiple
	 * lines, the value will get cut at the end of the first line.
	 *
	 * If the file data is not within that first 8kiB, then the author should correct
	 * their plugin file and move the data headers to the top.
	 *
	 * @see get_file_data()
	 *
	 * @param string $file Path to the file.
	 * @param array $headers List of headers, in the format array('HeaderKey' => 'Header Name').
	 * @param bool $with_line_nums Whether to include line numbers in the returned data. Default false.
	 *
	 * @return array Array of file headers in `HeaderKey => Header Value` format, or
	 *               `HeaderKey => ['value' => Header Value, 'line' => Line Number]` when $with_line_nums is true.
	 */
	public static function get_file_data( $file, $headers, $with_line_nums = false ) {
		// We don't need to write to the file, so just open for reading.
		$fp = fopen( $file, 'rb' );

		// Pull only the first 8kiB of the file in.
		$file_data = fread( $fp, 8192 );

		// PHP will close file handle, but we are good citizens.
		fclose( $fp );

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		return static::get_file_data_from_string( $file_data, $headers, $with_line_nums );
	}

	/**
	 * Retrieves metadata from a string.
	 *
	 * @param string $text String to look for metadata in.
	 * @param array $headers List of headers.
	 * @param bool $with_line_nums Whether to include line numbers in the returned data. Default false.
	 *
	 * @return array Array of file headers in `HeaderKey => Header Value` format, or
	 *               `HeaderKey => ['value' => Header Value, 'line' => Line Number]` when $with_line_nums is true.
	 */
	public static function get_file_data_from_string( $text, $headers, $with_line_nums = false ) {
		foreach ( $headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $text, $match, PREG_OFFSET_CAPTURE ) && $match[1][0] ) {
				$value = static::_cleanup_header_comment( $match[1][0] );
				
				if ( $with_line_nums ) {
					// Calculate line number from the offset
					$line_num = substr_count( $text, "\n", 0, $match[0][1] ) + 1;
					$headers[ $field ] = [
						'value' => $value,
						'line'  => $line_num,
					];
				} else {
					$headers[ $field ] = $value;
				}
			} else {
				$headers[ $field ] = $with_line_nums ? [ 'value' => '', 'line' => 0 ] : '';
			}
		}

		return $headers;
	}

	/**
	 * Strip close comment and close php tags from file headers used by WP.
	 *
	 * @see _cleanup_header_comment()
	 *
	 * @param string $str Header comment to clean up.
	 *
	 * @return string
	 */
	protected static function _cleanup_header_comment( $str ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- Not changing because third-party commands might use/extend.
		return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
	}
}
