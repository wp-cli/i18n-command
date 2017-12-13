Feature: Generate a POT file of a WordPress project

  Scenario: Generates a POT file by default
    Given a WP install

    When I run `wp scaffold plugin hello-world`
    Then the wp-content/plugins/hello-world directory should exist
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/.travis.yml file should exist
    And the wp-content/plugins/hello-world/bin directory should exist

    When I run `wp makepot plugin wp-content/plugins/hello-world wp-content/plugins/hello-world/languages/hello-world.pot`
    Then STDOUT should be:
      """
      Success: POT file successfully generated!
      """
    And STDERR should be empty
    And the wp-content/plugins/languages/hello-world.pot file should exist

    When I run `wp plugin delete hello-world`
    Then the wp-content/plugins/hello-world directory should not exist
