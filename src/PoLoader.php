<?php

namespace WP_CLI\I18n;

use Gettext\Loader\Loader;
use Gettext\Translation as GettextTranslation;
use Gettext\Translations as GettextTranslations;

/**
 * Loads translations from PO and POT files.
 *
 * Unlike the loader shipped with gettext/gettext, this keeps entries whose
 * original string is "0" and merges duplicate entries instead of replacing them.
 */
class PoLoader extends Loader {

	/**
	 * Loads one or more files into a single set of translations.
	 *
	 * @param string|array<string> $files A file path or a list of file paths.
	 * @return Translations
	 */
	public static function fromFiles( $files ): Translations {
		$loader       = new self();
		$translations = Translations::create();

		foreach ( (array) $files as $file ) {
			$loader->loadFile( $file, $translations );
		}

		return $translations;
	}

	public function loadString( string $text, ?GettextTranslations $translations = null ): GettextTranslations {
		$translations = parent::loadString( $text, $translations );

		$lines       = explode( "\n", $text );
		$count       = count( $lines );
		$translation = $this->createTranslation( null, '' );

		for ( $i = 0; $i < $count; ++$i ) {
			$line = trim( $lines[ $i ] );
			$line = self::fixMultiLines( $line, $lines, $i );

			// Treat empty comments as empty lines.
			if ( '#' === $line ) {
				$line = '';
			}

			if ( '' === $line ) {
				self::addTranslation( $translation, $translations );
				$translation = $this->createTranslation( null, '' );
				continue;
			}

			$split_line = preg_split( '/\s+/', $line, 2 ) ?: [];
			$key        = $split_line[0] ?? '';
			$data       = $split_line[1] ?? '';

			if ( '#~' === $key ) {
				$translation->disable();

				$split_line = preg_split( '/\s+/', $data, 2 ) ?: [];
				$key        = $split_line[0] ?? '';
				$data       = $split_line[1] ?? '';
			}

			switch ( $key ) {
				case '#':
					$translation->getComments()->add( $data );
					break;

				case '#.':
					$translation->getExtractedComments()->add( $data );
					break;

				case '#,':
					foreach ( array_map( 'trim', explode( ',', trim( $data ) ) ) as $flag ) {
						$translation->getFlags()->add( $flag );
					}
					break;

				case '#:':
					foreach ( preg_split( '/\s+/', trim( $data ) ) ?: [] as $reference ) {
						if ( preg_match( '/^(.+)(:(\d*))?$/U', $reference, $matches ) ) {
							$translation->getReferences()->add(
								$matches[1],
								isset( $matches[3] ) && '' !== $matches[3] ? (int) $matches[3] : null
							);
						}
					}
					break;

				case 'msgctxt':
					$translation = $translation->withContext( self::decode( $data ) );
					break;

				case 'msgid':
					$translation = $translation->withOriginal( self::decode( $data ) );
					break;

				case 'msgid_plural':
					$translation->setPlural( self::decode( $data ) );
					break;

				case 'msgstr':
				case 'msgstr[0]':
					$translation->translate( self::decode( $data ) );
					break;

				case 'msgstr[1]':
					$translation->translatePlural( self::decode( $data ) );
					break;

				default:
					if ( 0 === strpos( $key, 'msgstr[' ) ) {
						$plural_translations   = $translation->getPluralTranslations();
						$plural_translations[] = self::decode( $data );
						$translation->translatePlural( ...$plural_translations );
					}
					break;
			}
		}

		self::addTranslation( $translation, $translations );

		return $translations;
	}

	protected function createTranslations(): GettextTranslations {
		return Translations::create();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return GettextTranslation
	 */
	protected function createTranslation( ?string $context, string $original, ?string $plural = null ): ?GettextTranslation {
		$translation = Translation::create( $context, $original );

		if ( null !== $plural ) {
			$translation->setPlural( $plural );
		}

		return $translation;
	}

	/**
	 * Adds a parsed entry to the translations.
	 *
	 * The entry with an empty original string and no context holds the headers.
	 *
	 * @param GettextTranslation  $translation  The parsed entry.
	 * @param GettextTranslations $translations Translations instance to add the entry to.
	 * @return void
	 */
	private static function addTranslation( GettextTranslation $translation, GettextTranslations $translations ) {
		if ( '' !== $translation->getOriginal() ) {
			$translations->addOrMerge( $translation );
			return;
		}

		if ( null !== $translation->getContext() && '' !== $translation->getContext() ) {
			return;
		}

		self::extractHeaders( (string) $translation->getTranslation(), $translations );
	}

	/**
	 * Adds the headers found in the header entry to the translations.
	 *
	 * @param string              $headers      Translation of the header entry.
	 * @param GettextTranslations $translations Translations instance.
	 * @return void
	 */
	private static function extractHeaders( $headers, GettextTranslations $translations ) {
		$current_header = null;

		foreach ( explode( "\n", $headers ) as $line ) {
			$line = self::decode( $line );

			if ( '' === $line ) {
				continue;
			}

			// A header definition, as opposed to the continuation of the previous header.
			if ( preg_match( '/^[\w-]+:/', $line ) ) {
				list( $current_header, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
				$translations->getHeaders()->set( $current_header, $value );
				continue;
			}

			if ( null !== $current_header ) {
				$translations->getHeaders()->set(
					$current_header,
					$translations->getHeaders()->get( $current_header ) . $line
				);
			}
		}
	}

	/**
	 * Gets one string from multiline strings.
	 *
	 * @param string        $line  Current line.
	 * @param array<string> $lines All lines.
	 * @param int           $i     Index of the current line, moved forward to the last line that was consumed.
	 * @return string
	 */
	private static function fixMultiLines( $line, array $lines, &$i ) {
		for ( $j = $i, $t = count( $lines ); $j < $t; ++$j ) {
			if ( '"' === substr( $line, -1, 1 ) && isset( $lines[ $j + 1 ] ) ) {
				$next_line = trim( $lines[ $j + 1 ] );

				if ( '"' === substr( $next_line, 0, 1 ) ) {
					$line = substr( $line, 0, -1 ) . substr( $next_line, 1 );
					continue;
				}

				if ( '#~ "' === substr( $next_line, 0, 4 ) ) {
					$line = substr( $line, 0, -1 ) . substr( $next_line, 4 );
					continue;
				}
			}

			$i = $j;
			break;
		}

		return $line;
	}

	/**
	 * Converts a string from its PO representation.
	 *
	 * @param string $value String as found in a PO file.
	 * @return string
	 */
	public static function decode( $value ) {
		if ( ! $value ) {
			return '';
		}

		if ( '"' === $value[0] ) {
			$value = substr( $value, 1, -1 );
		}

		return strtr(
			$value,
			[
				'\\\\' => '\\',
				'\\a'  => "\x07",
				'\\b'  => "\x08",
				'\\t'  => "\t",
				'\\n'  => "\n",
				'\\v'  => "\x0b",
				'\\f'  => "\x0c",
				'\\r'  => "\r",
				'\\"'  => '"',
			]
		);
	}
}
