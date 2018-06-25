@require-php-5.4
Feature: Generate a POT file of a WordPress plugin

  Background:
    Given a WP install

  Scenario: Bail for invalid source directories
    When I try `wp i18n make-pot foo bar/baz.pot`
    Then STDERR should contain:
      """
      Error: Not a valid source directory!
      """
    And the return code should be 1

  Scenario: Generates a POT file by default
    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    Then STDOUT should be:
      """
      Plugin file detected.
      Success: POT file successfully generated!
      """
    And STDERR should be empty
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should exist

  Scenario: Does include file headers.
    When I run `wp scaffold plugin hello-world --plugin_name="Hello World" --plugin_author="John Doe" --plugin_author_uri="https://example.com" --plugin_uri="https://foo.example.com"`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    Then the wp-content/plugins/hello-world/languages/hello-world.pot file should exist
    Then STDOUT should be:
      """
      Plugin file detected.
      Success: POT file successfully generated!
      """
    And STDERR should be empty
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should exist
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      msgid "Hello World"
      """
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      msgid "John Doe"
      """
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      msgid "https://example.com"
      """
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      msgid "https://foo.example.com"
      """

  Scenario: Does not include empty file headers.
    When I run `wp scaffold plugin hello-world --plugin_description=""`

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    Then the wp-content/plugins/hello-world/languages/hello-world.pot file should exist
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should not contain:
      """
      Description of the plugin
      """
  Scenario: Adds copyright comments
    When I run `wp scaffold plugin hello-world`

    When I run `date +"%Y"`
    Then STDOUT should not be empty
    And save STDOUT as {YEAR}

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      # Copyright (C) {YEAR} Hello World
      # This file is distributed under the same license as the Hello World package.
      """

  Scenario: Sets Project-Id-Version
    When I run `wp scaffold plugin hello-world`

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      "Project-Id-Version: Hello World 0.1.0\n"
      """

  Scenario: Sets Report-Msgid-Bugs-To
    When I run `wp scaffold plugin hello-world`

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/hello-world\n"
      """

  Scenario: Sets the last translator and the language team
    When I run `wp scaffold plugin hello-world`

    When I run `wp i18n make-pot wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      "Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
      "Language-Team: LANGUAGE <LL@li.org>\n"
      """

  Scenario: Ignores any other text domain
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Plugin URI:  https://example.com
       * Description:
       * Version:     0.1.0
       * Author:
       * Author URI:
       * License:     GPL-2.0+
       * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
       * Text Domain: foo-plugin
       * Domain Path: /languages
       */

       __( 'Hello World', 'foo-plugin' );

       __( 'Foo', 'bar' );

       __( 'bar' );
      """

    When I run `wp i18n make-pot foo-plugin foo-plugin.pot --domain=bar`
    And the foo-plugin.pot file should contain:
      """
      msgid "Foo"
      """
    And the foo-plugin.pot file should not contain:
      """
      msgid "Hello World"
      """
    And the foo-plugin.pot file should not contain:
      """
      msgid "bar"
      """

  Scenario: Bails when no plugin files are found
    Given an empty foo-plugin directory
    When I try `wp i18n make-pot foo-plugin foo-plugin.pot`
    Then STDERR should contain:
      """
      Error: No valid theme stylesheet or plugin file found!
      """
    And the return code should be 1

  Scenario: Bails when no main plugin file is found
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      """
    When I try `wp i18n make-pot foo-plugin foo-plugin.pot`
    Then STDERR should contain:
      """
      Error: No valid theme stylesheet or plugin file found!
      """
    And the return code should be 1

  Scenario: Adds relative paths to source file as comments.
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Plugin URI:  https://example.com
       * Description:
       * Version:     0.1.0
       * Author:
       * Author URI:
       * License:     GPL-2.0+
       * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
       * Text Domain: foo-plugin
       * Domain Path: /languages
       */

       __( 'Hello World', 'foo-plugin' );
      """

    When I run `wp i18n make-pot foo-plugin foo-plugin.pot`
    And the foo-plugin.pot file should contain:
      """
      #: foo-plugin.php:15
      """

  Scenario: Uses the current folder as destination path when none is set.
    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist

    When I run `wp i18n make-pot wp-content/plugins/hello-world`
    Then the wp-content/plugins/hello-world/languages/hello-world.pot file should exist

  Scenario: Uses Domain Path as destination path when none is set.
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       * Plugin URI:  https://example.com
       * Description:
       * Version:     0.1.0
       * Author:
       * Author URI:
       * License:     GPL-2.0+
       * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
       * Text Domain: foo-plugin
       * Domain Path: /languages
       */
      """

    When I run `wp i18n make-pot foo-plugin`
    Then STDOUT should be:
      """
      Plugin file detected.
      Success: POT file successfully generated!
      """
    And STDERR should be empty
    And the foo-plugin/languages/foo-plugin.pot file should exist

  Scenario: Extract all supported functions
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Plugin
       */
      __( '__', 'foo-plugin' );
      esc_attr__( 'esc_attr__', 'foo-plugin' );
      esc_html__( 'esc_html__', 'foo-plugin' );
      _e( '_e', 'foo-plugin' );
      esc_attr_e( 'esc_attr_e', 'foo-plugin' );
      esc_html_e( 'esc_html_e', 'foo-plugin' );
      _x( '_x', '_x_context', 'foo-plugin' );
      _ex( '_ex', '_ex_context', 'foo-plugin' );
      esc_attr_x( 'esc_attr_x', 'esc_attr_x_context', 'foo-plugin' );
      esc_html_x( 'esc_html_x', 'esc_html_x_context', 'foo-plugin' );
      _n( '_n_single', '_n_plural', $number, 'foo-plugin' );
      _nx( '_nx_single', '_nx_plural', $number, '_nx_context', 'foo-plugin' );
      _n_noop( '_n_noop_single', '_n_noop_plural', 'foo-plugin' );
      _nx_noop( '_nx_noop_single', '_nx_noop_plural', '_nx_noop_context', 'foo-plugin' );

      // Compat.
      _( '_', 'foo-plugin' );

      // Deprecated.
      _c( '_c', 'foo-plugin' );
      _nc( '_nc_single', '_nc_plural', $number, 'foo-plugin' );
      __ngettext( '__ngettext_single', '__ngettext_plural', $number, 'foo-plugin' );
      __ngettext_noop( '__ngettext_noop_single', '__ngettext_noop_plural', 'foo-plugin' );

      __unsupported_func( '__unsupported_func', 'foo-plugin' );
      __( 'wrong-domain', 'wrong-domain' );
      """

    When I run `wp i18n make-pot foo-plugin`
    Then STDOUT should be:
      """
      Plugin file detected.
      Success: POT file successfully generated!
      """
    And the foo-plugin/foo-plugin.pot file should exist
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "__"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "esc_attr__"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "esc_html__"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_e"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "esc_attr_e"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "esc_html_e"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_x"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgctxt "_x_context"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_ex"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgctxt "_ex_context"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "esc_attr_x"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgctxt "esc_attr_x_context"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "esc_html_x"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgctxt "esc_html_x_context"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_n_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "_n_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_nx_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "_nx_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgctxt "_nx_context"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_n_noop_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "_n_noop_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_nx_noop_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "_nx_noop_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgctxt "_nx_noop_context"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_c"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "_nc_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "_nc_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "__ngettext_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "__ngettext_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "__ngettext_noop_single"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid_plural "__ngettext_noop_plural"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "__"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "__"
      """
    And the foo-plugin/foo-plugin.pot file should not contain:
      """
      msgid "__unsupported_func"
      """
    And the foo-plugin/foo-plugin.pot file should not contain:
      """
      msgid "wrong-domain"
      """

  Scenario: Extract translator comments
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Plugin name
       */

      /* translators: Translators 1! */
      _e( 'hello world', 'foo-plugin' );

      /* Translators: Translators 2! */
      $foo = __( 'foo', 'foo-plugin' );

      /* translators: localized date and time format, see https://secure.php.net/date */
      __( 'F j, Y g:i a', 'foo-plugin' );

      // translators: let your ears fly!
      __( 'on', 'foo-plugin' );

      /*
       * Translators: If there are characters in your language that are not supported
       * by Lato, translate this to 'off'. Do not translate into your own language.
       */
       __( 'off', 'foo-plugin' );

       /* translators: this should get extracted. */ $foo = __( 'baba', 'foo-plugin' );

       /* translators: boo */ /* translators: this should get extracted too. */ /* some other comment */ $bar = g ( __( 'baba', 'foo-plugin' ) );
      """

    When I run `wp i18n make-pot foo-plugin`
    Then STDOUT should be:
      """
      Plugin file detected.
      Success: POT file successfully generated!
      """
    And the foo-plugin/foo-plugin.pot file should exist
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "Plugin name"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. translators: Translators 1!
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. Translators: Translators 2!
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      msgid "F j, Y g:i a"
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. translators: localized date and time format, see https://secure.php.net/date
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. translators: let your ears fly!
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. Translators: If there are characters in your language that are not supported by Lato, translate this to 'off'. Do not translate into your own language.
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. translators: this should get extracted.
      """
    And the foo-plugin/foo-plugin.pot file should contain:
      """
      #. translators: this should get extracted too.
      """

  Scenario: Generates a POT file for a child theme with no other files
    When I run `wp scaffold child-theme foobar --parent_theme=twentyseventeen --theme_name="Foo Bar" --author="Jane Doe" --author_uri="https://example.com" --theme_uri="https://foobar.example.com"`
    Then the wp-content/themes/foobar directory should exist
    And the wp-content/themes/foobar/style.css file should exist

    When I run `wp i18n make-pot wp-content/themes/foobar wp-content/themes/foobar/languages/foobar.pot`
    Then STDOUT should be:
      """
      Theme stylesheet detected.
      Success: POT file successfully generated!
      """
    And STDERR should be empty
    And the wp-content/themes/foobar/languages/foobar.pot file should exist
    And the wp-content/themes/foobar/languages/foobar.pot file should contain:
      """
      msgid "Foo Bar"
      """
    And the wp-content/themes/foobar/languages/foobar.pot file should contain:
      """
      msgid "Jane Doe"
      """
    And the wp-content/themes/foobar/languages/foobar.pot file should contain:
      """
      msgid "https://example.com"
      """
    And the wp-content/themes/foobar/languages/foobar.pot file should contain:
      """
      msgid "https://foobar.example.com"
      """

  Scenario: Prints a warning when two identical strings have different translator comments.
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Plugin name
       */

      /* translators: Translators 1! */
      __( 'Hello World', 'foo-plugin' );

      /* Translators: Translators 2! */
      __( 'Hello World', 'foo-plugin' );
      """

    When I run `wp i18n make-pot foo-plugin --debug`
    Then STDOUT should be:
      """
      Plugin file detected.
      Success: POT file successfully generated!
      """
    And STDERR should contain:
      """
      Warning: The string "Hello World" has 2 different translator comments.
      """
