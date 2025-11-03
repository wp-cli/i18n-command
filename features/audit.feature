Feature: Audit strings in a WordPress project

  Background:
    Given a WP install

  Scenario: Audits a plugin for translation issues
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s', 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: The string "Hello %s" contains placeholders but has no "translators:" comment to clarify their meaning.
      """
    And STDERR should contain:
      """
      Warning: Found 1 issue.
      """
    And the return code should be 0

  Scenario: Audits a plugin and finds no issues
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello World', 'foo-plugin' );
      """

    When I run `wp i18n audit foo-plugin`
    Then STDOUT should contain:
      """
      Success: No issues found.
      """
    And STDERR should be empty

  Scenario: Outputs audit results as JSON
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s', 'foo-plugin' );
      """

    When I run `wp i18n audit foo-plugin --format=json`
    Then STDOUT should contain:
      """
      "file": "foo-plugin.php"
      """
    And STDOUT should contain:
      """
      "line": 7
      """
    And STDOUT should contain:
      """
      "message": "The string \"Hello %s\" contains placeholders but has no \"translators:\" comment to clarify their meaning."
      """
    And STDOUT should contain:
      """
      "code": "missing-translator-comment"
      """

  Scenario: Outputs audit results in GitHub Actions format
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s', 'foo-plugin' );
      """

    When I run `wp i18n audit foo-plugin --format=github-actions`
    Then STDOUT should contain:
      """
      ::warning file=foo-plugin.php,line=7::The string "Hello %s" contains placeholders but has no "translators:" comment to clarify their meaning.
      """

  Scenario: Detects multiple unordered placeholders
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s %s', 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: Multiple placeholders should be ordered.
      """

  Scenario: Detects strings without translatable content
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( '%s', 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: Found string without translatable content.
      """

  Scenario: Detects multiple translator comments
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      /* translators: Comment 1 */
      __( 'Hello World', 'foo-plugin' );

      /* translators: Comment 2 */
      __( 'Hello World', 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:
      """
    And STDERR should contain:
      """
      different translator comments
      """

  Scenario: Detects missing singular placeholder
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      _n( 'One comment', '%s Comments', $count, 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: Missing singular placeholder, needed for some languages.
      """

  Scenario: Detects mismatched placeholders in plural strings
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      _n( '%s Comment', '%d Comments', $count, 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: Mismatched placeholders for singular and plural string.
      """

  Scenario: Respects --ignore-domain flag
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s', 'different-domain' );
      """

    When I try `wp i18n audit foo-plugin --ignore-domain`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: The string "Hello %s" contains placeholders but has no "translators:" comment to clarify their meaning.
      """

  Scenario: Respects --skip-php flag
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s', 'foo-plugin' );
      """

    When I run `wp i18n audit foo-plugin --skip-php`
    Then STDOUT should contain:
      """
      Success: No issues found.
      """

  Scenario: Shows file before warning message in plaintext format
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Text Domain: foo-plugin
       */

      __( 'Hello %s', 'foo-plugin' );
      """

    When I try `wp i18n audit foo-plugin --format=plaintext`
    Then STDERR should contain:
      """
      Warning: foo-plugin.php:7: The string "Hello %s" contains placeholders but has no "translators:" comment to clarify their meaning.
      """
