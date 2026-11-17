<?php

namespace WP_CLI\I18n;

use Gettext\References;

/**
 * References that keep the order in which they were added.
 *
 * The default implementation groups all lines by file name, which loses the
 * order as soon as a file is referenced again after another one.
 */
class OrderedReferences extends References {

	/**
	 * @var array<string, array{0: string, 1: int|null}>
	 */
	protected $entries = [];

	public function add( string $filename, ?int $line = null ): References {
		$this->entries[ "{$filename}:{$line}" ] = [ $filename, $line ];

		return parent::add( $filename, $line );
	}

	/**
	 * Returns all references in the order they were added.
	 *
	 * @return array<int, array{0: string, 1: int|null}> List of file name and line number pairs.
	 */
	public function getEntries(): array {
		return array_values( $this->entries );
	}

	public function count(): int {
		return count( $this->entries );
	}

	public function mergeWith( References $references ): References {
		if ( ! $references instanceof self ) {
			return parent::mergeWith( $references );
		}

		$merged = clone $this;

		foreach ( $references->getEntries() as $entry ) {
			$merged->add( $entry[0], $entry[1] );
		}

		return $merged;
	}

	/**
	 * Returns the references of a translation in the order they were added.
	 *
	 * @param References $references References instance.
	 * @return array<int, array{0: string, 1: int|null}> List of file name and line number pairs.
	 */
	public static function entries( References $references ): array {
		if ( $references instanceof self ) {
			return $references->getEntries();
		}

		$entries = [];

		foreach ( $references->toArray() as $filename => $lines ) {
			if ( empty( $lines ) ) {
				$entries[] = [ (string) $filename, null ];
				continue;
			}

			foreach ( $lines as $line ) {
				$entries[] = [ (string) $filename, $line ];
			}
		}

		return $entries;
	}
}
