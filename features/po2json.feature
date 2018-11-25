@require-php-5.4
Feature: Split PO files into multiple JSON files.

  Background:
    Given a WP install

  Scenario: Bail for invalid source file or directory
    When I try `wp i18n po2json foo`
    Then STDERR should contain:
      """
      Error: Source file or directory does not exist!
      """
    And the return code should be 1
