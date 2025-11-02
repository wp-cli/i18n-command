<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;
use Gettext\Utils\ParsedComment;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

class AuditCommand extends WP_CLI_Command {
	/**
	 * @var string
	 */
	protected $source;

	/**
	 * @var array
	 */
	protected $include = [];

	/**
	 * @var array
	 */
	protected $exclude = [ 'node_modules', '.*', 'vendor', 'Gruntfile.js', 'webpack.config.js', '*.min.js', 'test', 'tests' ];

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var array
	 */
	protected $main_file_data = [];

	/**
	 * @var string
	 */
	protected $main_file_path;

	/**
	 * @var bool
	 */
	protected $skip_js = false;

	/**
	 * @var bool
	 */
	protected $skip_php = false;

	/**
	 * @var bool
	 */
	protected $skip_blade = false;

	/**
	 * @var bool
	 */
	protected $skip_block_json = false;

	/**
	 * @var bool
	 */
	protected $skip_theme_json = false;

	/**
	 * @var bool
	 */
	protected $location = true;

	/**
	 * @var string
	 */
	protected $domain;

	/**
	 * @var string
	 */
	protected $project_type = 'generic';

	/**
	 * @var string
	 */
	protected $format = 'plaintext';

	/**
	 * These Regexes copied from http://php.net/manual/en/function.sprintf.php#93552
	 * and adjusted for better precision and updated specs.
	 */
	const SPRINTF_PLACEHOLDER_REGEX = '/(?:
		(?<!%)                     # Don\'t match a literal % (%%).
		(
			%                          # Start of placeholder.
			(?:[0-9]+\$)?              # Optional ordering of the placeholders.
			[+-]?                      # Optional sign specifier.
			(?:
				(?:0|\'.)?                 # Optional padding specifier - excluding the space.
				-?                         # Optional alignment specifier.
				[0-9]*                     # Optional width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
				|                      # Only recognize the space as padding in combination with a width specifier.
				(?:[ ])?                   # Optional space padding specifier.
				-?                         # Optional alignment specifier.
				[0-9]+                     # Width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
			)
			[bcdeEfFgGosuxX]           # Type specifier.
		)
	)/x';

	/**
	 * "Unordered" means there's no position specifier: '%s', not '%2$s'.
	 */
	const UNORDERED_SPRINTF_PLACEHOLDER_REGEX = '/(?:
		(?<!%)                     # Don\'t match a literal % (%%).
		%                          # Start of placeholder.
		[+-]?                      # Optional sign specifier.
		(?:
			(?:0|\'.)?                 # Optional padding specifier - excluding the space.
			-?                         # Optional alignment specifier.
			[0-9]*                     # Optional width specifier.
			(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
			|                      # Only recognize the space as padding in combination with a width specifier.
			(?:[ ])?                   # Optional space padding specifier.
			-?                         # Optional alignment specifier.
			[0-9]+                     # Width specifier.
			(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
		)
		[bcdeEfFgGosuxX]           # Type specifier.
	)/x';

	/**
	 * Audit strings in a project.
	 *
	 * Scans PHP, Blade-PHP and JavaScript files for translatable strings to find possible mistakes.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Directory to scan for string extraction.
	 *
	 * [--slug=<slug>]
	 * : Plugin or theme slug. Defaults to the source directory's basename.
	 *
	 * [--domain=<domain>]
	 * : Text domain to look for in the source code, unless the `--ignore-domain` option is used.
	 * By default, the "Text Domain" header of the plugin or theme is used.
	 * If none is provided, it falls back to the project slug.
	 *
	 * [--ignore-domain]
	 * : Ignore the text domain completely and extract strings with any text domain.
	 *
	 * [--include=<paths>]
	 * : Comma-separated list of files and paths that should be used for string extraction.
	 * If provided, only these files and folders will be taken into account.
	 *
	 * [--exclude=<paths>]
	 * : Comma-separated list of files and paths that should be ignored for string extraction.
	 * Defaults to 'node_modules,.git,.svn,vendor,Gruntfile.js,webpack.config.js,*.min.js,test,tests'.
	 *
	 * [--skip-js]
	 * : Skips JavaScript string extraction.
	 *
	 * [--skip-php]
	 * : Skips PHP string extraction.
	 *
	 * [--skip-blade]
	 * : Skips Blade-PHP string extraction.
	 *
	 * [--skip-block-json]
	 * : Skips string extraction from block.json files.
	 *
	 * [--skip-theme-json]
	 * : Skips string extraction from theme.json files.
	 *
	 * [--format=<format>]
	 * : Output format for the audit results.
	 * ---
	 * default: plaintext
	 * options:
	 *   - plaintext
	 *   - json
	 *   - github-actions
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Audit a plugin for possible translation issues.
	 *     $ wp i18n audit wp-content/plugins/hello-world
	 *
	 *     # Audit a plugin and output results as JSON.
	 *     $ wp i18n audit wp-content/plugins/hello-world --format=json
	 *
	 *     # Audit a plugin with GitHub Actions annotations format.
	 *     $ wp i18n audit wp-content/plugins/hello-world --format=github-actions
	 *
	 * @when before_wp_load
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->source = realpath( $args[0] );
		if ( ! $this->source || ! is_dir( $this->source ) ) {
			WP_CLI::error( 'Not a valid source directory.' );
		}

		$this->slug              = Utils\get_flag_value( $assoc_args, 'slug', Utils\basename( $this->source ) );
		$this->domain            = Utils\get_flag_value( $assoc_args, 'domain', null );
		$this->skip_js           = Utils\get_flag_value( $assoc_args, 'skip-js', $this->skip_js );
		$this->skip_php          = Utils\get_flag_value( $assoc_args, 'skip-php', $this->skip_php );
		$this->skip_blade        = Utils\get_flag_value( $assoc_args, 'skip-blade', $this->skip_blade );
		$this->skip_block_json   = Utils\get_flag_value( $assoc_args, 'skip-block-json', $this->skip_block_json );
		$this->skip_theme_json   = Utils\get_flag_value( $assoc_args, 'skip-theme-json', $this->skip_theme_json );
		$this->format            = Utils\get_flag_value( $assoc_args, 'format', $this->format );
		$ignore_domain           = Utils\get_flag_value( $assoc_args, 'ignore-domain', false );

		$include = Utils\get_flag_value( $assoc_args, 'include', [] );
		if ( ! empty( $include ) ) {
			$this->include = array_map( 'trim', explode( ',', $include ) );
		}

		$exclude = Utils\get_flag_value( $assoc_args, 'exclude', [] );
		if ( ! empty( $exclude ) ) {
			$this->exclude = array_map( 'trim', explode( ',', $exclude ) );
		}

		$this->get_main_file_data();

		if ( null === $this->domain ) {
			if ( ! empty( $this->main_file_data['Text Domain'] ) && ! $ignore_domain ) {
				$this->domain = $this->main_file_data['Text Domain'];
			} else {
				$this->domain = $this->slug;
			}
		}

		WP_CLI::debug(
			sprintf(
				'Auditing strings for %s, using "%s" as text domain.',
				$this->slug,
				$this->domain
			),
			'audit'
		);

		$translations = $this->extract_strings();

		$issues = $this->audit_strings( $translations );

		$this->output_results( $issues );

		$issue_count = count( $issues );

		if ( $issue_count > 0 ) {
			WP_CLI::warning( sprintf( 'Found %d %s.', $issue_count, Utils\pluralize( 'issue', $issue_count ) ) );
		} else {
			WP_CLI::success( 'No issues found.' );
		}
	}

	/**
	 * Returns the file data of the main plugin or theme file.
	 *
	 * @return array
	 */
	protected function get_main_file_data() {
		if ( ! empty( $this->main_file_data ) ) {
			return $this->main_file_data;
		}

		$type = FileDataExtractor::get_file_type( $this->source );

		if ( 'unknown' === $type ) {
			WP_CLI::debug( 'Could not detect file type.', 'audit' );
		} else {
			WP_CLI::debug( sprintf( '%s file detected.', ucfirst( $type ) ), 'audit' );
		}

		$this->project_type = $type;

		$location = FileDataExtractor::find_main_file( $this->source, $type );

		if ( ! $location ) {
			return [];
		}

		$this->main_file_path = $location;

		return $this->main_file_data = FileDataExtractor::get_file_data( $location, $type );
	}

	/**
	 * Returns the file headers for a given project type.
	 *
	 * @param string $type Project type.
	 * @return array
	 */
	protected function get_file_headers( $type ) {
		switch ( $type ) {
			case 'plugin':
				return [
					'Plugin Name',
					'Plugin URI',
					'Description',
					'Author',
					'Author URI',
					'Version',
					'License',
					'Domain Path',
					'Text Domain',
				];
			case 'theme':
				return [
					'Theme Name',
					'Theme URI',
					'Description',
					'Author',
					'Author URI',
					'Version',
					'License',
					'Domain Path',
					'Text Domain',
				];
			default:
				return [];
		}
	}

	/**
	 * Extracts strings from source code.
	 *
	 * @return Translations A Translation set.
	 */
	protected function extract_strings() {
		$translations = new Translations();

		if ( $this->domain ) {
			$translations->setDomain( $this->domain );
		}

		$is_theme = isset( $this->main_file_data['Theme Name'] );

		try {
			if ( ! $this->skip_php ) {
				$options = [
					// Extract 'Template Name' headers in theme files.
					'wpExtractTemplates' => $is_theme,
					// Extract 'Title' and 'Description' headers from pattern files.
					'wpExtractPatterns'  => $is_theme,
					'include'            => $this->include,
					'exclude'            => $this->exclude,
					'extensions'         => [ 'php' ],
					'addReferences'      => $this->location,
				];
				PhpCodeExtractor::fromDirectory( $this->source, $translations, $options );
			}

			if ( ! $this->skip_blade ) {
				$options = [
					'include'       => $this->include,
					'exclude'       => $this->exclude,
					'extensions'    => [ 'blade.php' ],
					'addReferences' => $this->location,
				];
				BladeCodeExtractor::fromDirectory( $this->source, $translations, $options );
			}

			if ( ! $this->skip_js ) {
				JsCodeExtractor::fromDirectory(
					$this->source,
					$translations,
					[
						'include'       => $this->include,
						'exclude'       => $this->exclude,
						'extensions'    => [ 'js', 'jsx' ],
						'addReferences' => $this->location,
					]
				);

				MapCodeExtractor::fromDirectory(
					$this->source,
					$translations,
					[
						'include'       => $this->include,
						'exclude'       => $this->exclude,
						'extensions'    => [ 'map' ],
						'addReferences' => $this->location,
					]
				);
			}

			if ( ! $this->skip_block_json ) {
				BlockExtractor::fromDirectory(
					$this->source,
					$translations,
					[
						'schema'            => JsonSchemaExtractor::BLOCK_JSON_SOURCE,
						'schemaFallback'    => JsonSchemaExtractor::BLOCK_JSON_FALLBACK,
						// Only look for block.json files in any folder, nothing else.
						'restrictFileNames' => [ 'block.json' ],
						'include'           => $this->include,
						'exclude'           => $this->exclude,
						'extensions'        => [ 'json' ],
						'addReferences'     => $this->location,
					]
				);
			}

			if ( ! $this->skip_theme_json ) {
				JsonSchemaExtractor::fromDirectory(
					$this->source,
					$translations,
					[
						// Only look for theme.json files in any folder, nothing else.
						'restrictFileNames' => [ 'theme.json' ],
						'schema'            => JsonSchemaExtractor::THEME_JSON_SOURCE,
						'schemaFallback'    => JsonSchemaExtractor::THEME_JSON_FALLBACK,
						'include'           => $this->include,
						'exclude'           => $this->exclude,
						'extensions'        => [ 'json' ],
						'addReferences'     => $this->location,
					]
				);

				// Themes can have style variations in the top-level "styles" folder.
				// They're like theme.json but can have any name.
				if ( $is_theme ) {
					JsonSchemaExtractor::fromDirectory(
						$this->source,
						$translations,
						[
							'restrictDirectories' => [ 'styles' ],
							'schema'              => JsonSchemaExtractor::THEME_JSON_SOURCE,
							'schemaFallback'      => JsonSchemaExtractor::THEME_JSON_FALLBACK,
							'include'             => $this->include,
							'exclude'             => $this->exclude,
							'extensions'          => [ 'json' ],
							'addReferences'       => $this->location,
						]
					);
				}
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		return $translations;
	}

	/**
	 * Audits strings.
	 *
	 * Goes through all extracted strings to find possible mistakes.
	 *
	 * @param Translations $translations Translations object.
	 * @return array Array of issues found.
	 */
	protected function audit_strings( $translations ) {
		$issues = [];

		foreach ( $translations as $translation ) {
			/** @var Translation $translation */

			$references = $translation->getReferences();

			// File headers don't have any file references.
			if ( ! $translation->hasReferences() ) {
				continue;
			}

			$file     = $references[0][0];
			$line     = $references[0][1] ?? null;
			$location = $line ? "$file:$line" : $file;

			// Check 1: Flag strings with placeholders that should have translator comments.
			if (
				! $translation->hasExtractedComments() &&
				preg_match( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $placeholders ) >= 1
			) {
				$issues[] = [
					'file'    => $file,
					'line'    => $line,
					'message' => sprintf(
						'The string "%s" contains placeholders but has no "translators:" comment to clarify their meaning.',
						$translation->getOriginal()
					),
					'code'    => 'missing-translator-comment',
				];
			}

			// Check 2: Flag strings with different translator comments.
			if ( $translation->hasExtractedComments() ) {
				$comments = $translation->getExtractedComments();

				// Remove plugin header information from comments.
				$comments = array_filter(
					$comments,
					function ( $comment ) {
						/** @var ParsedComment|string $comment */
						/** @var string $file_header */
						foreach ( $this->get_file_headers( $this->project_type ) as $file_header ) {
							if ( 0 === strpos( ( $comment instanceof ParsedComment ? $comment->getComment() : $comment ), $file_header ) ) {
								return null;
							}
						}

						return $comment;
					}
				);

				$unique_comments = array();

				// Remove duplicate comments.
				$comments = array_filter(
					$comments,
					function ( $comment ) use ( &$unique_comments ) {
						/** @var ParsedComment|string $comment */
						if ( in_array( ( $comment instanceof ParsedComment ? $comment->getComment() : $comment ), $unique_comments, true ) ) {
							return null;
						}

						$unique_comments[] = ( $comment instanceof ParsedComment ? $comment->getComment() : $comment );

						return $comment;
					}
				);

				$comments_count = count( $comments );

				if ( $comments_count > 1 ) {
					$issues[] = [
						'file'    => $file,
						'line'    => $line,
						'message' => sprintf(
							"The string \"%s\" has %d different translator comments.\n%s",
							$translation->getOriginal(),
							$comments_count,
							implode( "\n", $unique_comments )
						),
						'code'    => 'multiple-translator-comments',
					];
				}
			}

			$non_placeholder_content = trim( preg_replace( '`^([\'"])(.*)\1$`Ds', '$2', $translation->getOriginal() ) );
			$non_placeholder_content = preg_replace( self::SPRINTF_PLACEHOLDER_REGEX, '', $non_placeholder_content );

			// Check 3: Flag empty strings without any translatable content.
			if ( '' === $non_placeholder_content ) {
				$issues[] = [
					'file'    => $file,
					'line'    => $line,
					'message' => 'Found string without translatable content.',
					'code'    => 'empty-string',
				];
			}

			// Check 4: Flag strings with multiple unordered placeholders (%s %s %s vs. %1$s %2$s %3$s).
			$unordered_matches_count = preg_match_all( self::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $unordered_matches );
			$unordered_matches       = $unordered_matches[0];

			if ( $unordered_matches_count >= 2 ) {
				$issues[] = [
					'file'    => $file,
					'line'    => $line,
					'message' => 'Multiple placeholders should be ordered.',
					'code'    => 'unordered-placeholders',
				];
			}

			if ( $translation->hasPlural() ) {
				preg_match_all( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $single_placeholders );
				$single_placeholders = $single_placeholders[0];

				preg_match_all( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getPlural(), $plural_placeholders );
				$plural_placeholders = $plural_placeholders[0];

				// see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals
				if ( count( $single_placeholders ) < count( $plural_placeholders ) ) {
					// Check 5: Flag things like _n( 'One comment', '%s Comments' )
					$issues[] = [
						'file'    => $file,
						'line'    => $line,
						'message' => 'Missing singular placeholder, needed for some languages. See https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals',
						'code'    => 'missing-singular-placeholder',
					];
				} else {
					// Reordering is fine, but mismatched placeholders is probably wrong.
					sort( $single_placeholders );
					sort( $plural_placeholders );

					// Check 6: Flag things like _n( '%s Comment (%d)', '%s Comments (%s)' )
					if ( $single_placeholders !== $plural_placeholders ) {
						$issues[] = [
							'file'    => $file,
							'line'    => $line,
							'message' => 'Mismatched placeholders for singular and plural string.',
							'code'    => 'mismatched-placeholders',
						];
					}
				}
			}
		}

		return $issues;
	}

	/**
	 * Outputs audit results in the specified format.
	 *
	 * @param array $issues Array of issues found.
	 */
	protected function output_results( $issues ) {
		if ( empty( $issues ) ) {
			return;
		}

		switch ( $this->format ) {
			case 'json':
				WP_CLI::line( json_encode( $issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				break;

			case 'github-actions':
				foreach ( $issues as $issue ) {
					$file    = $issue['file'];
					$line    = $issue['line'] ?? 1;
					$message = $issue['message'];

					WP_CLI::line( sprintf( '::warning file=%s,line=%d::%s', $file, $line, $message ) );
				}
				break;

			case 'plaintext':
			default:
				foreach ( $issues as $issue ) {
					$file    = $issue['file'];
					$line    = $issue['line'] ?? null;
					$message = $issue['message'];
					$location = $line ? "$file:$line" : $file;

					WP_CLI::warning( sprintf( '%s: %s', $location, $message ) );
				}
				break;
		}
	}
}
