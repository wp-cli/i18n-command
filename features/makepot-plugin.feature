Feature: Generate a POT file of a WordPress plugin

  Background:
    Given a WP install

  Scenario: Bail for invalid source directories
    When I run `wp makepot plugin foo bar/baz.pot`
    Then STDERR should contain:
      """
      Error: Not a valid source directory!
      """
    And the return code should be 1

  Scenario: Generates a POT file by default
    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    Then STDOUT should be:
      """
      Success: POT file successfully generated!
      """
    And STDERR should be empty
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should exist

  Scenario: Does not include empty file headers.
    When I run `wp scaffold plugin hello-world --plugin_description=""`

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
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

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      # Copyright (C) {YEAR} Hello World
      # This file is distributed under the same license as the Hello World package.
      """

  Scenario: Sets Project-Id-Version
    When I run `wp scaffold plugin hello-world`

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      "Project-Id-Version: Hello World 0.1.0\n"
      """

  Scenario: Sets Report-Msgid-Bugs-To
    When I run `wp scaffold plugin hello-world`

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    And the wp-content/plugins/hello-world/languages/hello-world.pot file should contain:
      """
      "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/hello-world\n"
      """

  Scenario: Sets the last translator and the language team
    When I run `wp scaffold plugin hello-world`

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
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

    When I run `wp makepot plugin foo-plugin foo-plugin.pot --domain=bar`
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
    When I run `wp makepot plugin foo-plugin foo-plugin.pot`
    Then STDERR should contain:
      """
      Error: No plugin files found!
      """
    And the return code should be 1

  Scenario: Bails when no main plugin file is found
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin.php file:
      """
      """
    When I run `wp makepot plugin foo-plugin foo-plugin.pot`
    Then STDERR should contain:
      """
      Error: No main plugin file found!
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

    When I run `wp makepot plugin foo-plugin foo-plugin.pot`
    And the foo-plugin.pot file should contain:
      """
      #: foo-plugin.php:15
      """
