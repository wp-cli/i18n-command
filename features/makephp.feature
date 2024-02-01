Feature: Generate PHP files from PO files

  Background:
    Given an empty directory

  Scenario: Bail for invalid source directories
    When I try `wp i18n make-php foo`
    Then STDERR should contain:
      """
      Error: Source file or directory does not exist.
      """
    And the return code should be 1

  Scenario: Uses source folder as destination by default
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin-de_DE.po file:
      """
      # Copyright (C) 2018 Foo Plugin
      # This file is distributed under the same license as the Foo Plugin package.
      msgid ""
      msgstr ""
      "Project-Id-Version: Foo Plugin\n"
      "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/foo-plugin\n"
      "Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
      "Language-Team: LANGUAGE <LL@li.org>\n"
      "Language: de_DE\n"
      "MIME-Version: 1.0\n"
      "Content-Type: text/plain; charset=UTF-8\n"
      "Content-Transfer-Encoding: 8bit\n"
      "POT-Creation-Date: 2018-05-02T22:06:24+00:00\n"
      "PO-Revision-Date: 2018-05-02T22:06:24+00:00\n"
      "X-Domain: foo-plugin\n"
      "Plural-Forms: nplurals=2; plural=(n != 1);\n"

      #: foo-plugin.js:15
      msgid "Foo Plugin"
      msgstr "Foo Plugin"
      """

    When I run `wp i18n make-php foo-plugin`
    Then STDOUT should contain:
      """
      Success: Created 1 file.
      """
    And the return code should be 0
    And the foo-plugin/foo-plugin-de_DE.l10n.php file should exist

  Scenario: Allows setting custom destination directory
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin-de_DE.po file:
      """
      # Copyright (C) 2018 Foo Plugin
      # This file is distributed under the same license as the Foo Plugin package.
      msgid ""
      msgstr ""
      "Project-Id-Version: Foo Plugin\n"
      "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/foo-plugin\n"
      "Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
      "Language-Team: LANGUAGE <LL@li.org>\n"
      "Language: de_DE\n"
      "MIME-Version: 1.0\n"
      "Content-Type: text/plain; charset=UTF-8\n"
      "Content-Transfer-Encoding: 8bit\n"
      "POT-Creation-Date: 2018-05-02T22:06:24+00:00\n"
      "PO-Revision-Date: 2018-05-02T22:06:24+00:00\n"
      "X-Domain: foo-plugin\n"
      "Plural-Forms: nplurals=2; plural=(n != 1);\n"

      #: foo-plugin.js:15
      msgid "Foo Plugin"
      msgstr "Foo Plugin"
      """

    When I run `wp i18n make-php foo-plugin result`
    Then STDOUT should contain:
      """
      Success: Created 1 file.
      """
    And the return code should be 0
    And the result/foo-plugin-de_DE.l10n.php file should exist

  Scenario: Does include headers
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin-de_DE.po file:
      """
      # Copyright (C) 2018 Foo Plugin
      # This file is distributed under the same license as the Foo Plugin package.
      msgid ""
      msgstr ""
      "Project-Id-Version: Foo Plugin\n"
      "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/foo-plugin\n"
      "Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
      "Language-Team: LANGUAGE <LL@li.org>\n"
      "Language: de_DE\n"
      "MIME-Version: 1.0\n"
      "Content-Type: text/plain; charset=UTF-8\n"
      "Content-Transfer-Encoding: 8bit\n"
      "POT-Creation-Date: 2018-05-02T22:06:24+00:00\n"
      "PO-Revision-Date: 2018-05-02T22:06:24+00:00\n"
      "X-Domain: foo-plugin\n"
      "Plural-Forms: nplurals=2; plural=(n != 1);\n"

      #: foo-plugin.js:15
      msgid "Foo Plugin"
      msgstr "Foo Plugin"
      """

    When I run `wp i18n make-php foo-plugin`
    Then STDOUT should contain:
      """
      Success: Created 1 file.
      """
    And the return code should be 0
    And STDERR should be empty
    And the foo-plugin/foo-plugin-de_DE.l10n.php file should contain:
      """
      'language'=>'de_DE'
      """
    And the foo-plugin/foo-plugin-de_DE.l10n.php file should contain:
      """
      'domain'=>'foo-plugin'
      """
    And the foo-plugin/foo-plugin-de_DE.l10n.php file should contain:
      """
      'plural-forms'=>'nplurals=2; plural=(n != 1);'
      """
    And the foo-plugin/foo-plugin-de_DE.l10n.php file should contain:
      """
      'messages'=>['Foo Plugin'=>'Foo Plugin']
      """

  Scenario: Does include translations
    Given an empty foo-plugin directory
    And a foo-plugin/foo-plugin-de_DE.po file:
      """
      # Copyright (C) 2018 Foo Plugin
      # This file is distributed under the same license as the Foo Plugin package.
      msgid ""
      msgstr ""
      "Project-Id-Version: Foo Plugin\n"
      "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/foo-plugin\n"
      "Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
      "Language-Team: LANGUAGE <LL@li.org>\n"
      "Language: de_DE\n"
      "MIME-Version: 1.0\n"
      "Content-Type: text/plain; charset=UTF-8\n"
      "Content-Transfer-Encoding: 8bit\n"
      "POT-Creation-Date: 2018-05-02T22:06:24+00:00\n"
      "PO-Revision-Date: 2018-05-02T22:06:24+00:00\n"
      "X-Domain: foo-plugin\n"
      "Plural-Forms: nplurals=2; plural=(n != 1);\n"

      #: foo-plugin.js:15
      msgctxt "Plugin Name"
      msgid "Foo Plugin (EN)"
      msgstr "Foo Plugin (DE)"

      #: foo-plugin.js:15
      msgid "Foo Plugin"
      msgstr "Bar Plugin"

      #: foo-plugin.php:60
      msgid "You have %d new message"
      msgid_plural "You have %d new messages"
      msgstr[0] "Sie haben %d neue Nachricht"
      msgstr[1] "Sie haben %d neue Nachrichten"
      """

    When I run `wp i18n make-php foo-plugin`
    Then STDOUT should contain:
      """
      Success: Created 1 file.
      """
    And the return code should be 0
    And the foo-plugin/foo-plugin-de_DE.l10n.php file should contain:
      """
      return ['domain'=>'foo-plugin','plural-forms'=>'nplurals=2; plural=(n != 1);','messages'=>['Plugin NameFoo Plugin (EN)'=>'Foo Plugin (DE)','Foo Plugin'=>'Bar Plugin','You have %d new message'=>'Sie haben %d neue Nachricht' . "\0" . 'Sie haben %d neue Nachrichten'],'language'=>'de_DE'];
      """
