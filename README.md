wp-cli/i18n-command
===================

Provides internationalization tools for WordPress projects.

[![Build Status](https://travis-ci.org/wp-cli/i18n-command.svg?branch=master)](https://travis-ci.org/wp-cli/i18n-command)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### wp i18n

Provides internationalization tools for WordPress projects.

~~~
wp i18n
~~~

**EXAMPLES**

    # Create a POT file for the WordPress plugin/theme in the current directory
    $ wp i18n make-pot . languages/my-plugin.pot



### wp i18n make-pot

Create a POT file for a WordPress plugin or theme.

~~~
wp i18n make-pot <source> [<destination>] [--slug=<slug>] [--domain=<domain>] [--ignore-domain] [--merge[=<file>]] [--exclude=<paths>] [--skip-js]
~~~

Scans PHP and JavaScript files, as well as theme stylesheets for translatable strings.

**OPTIONS**

	<source>
		Directory to scan for string extraction.

	[<destination>]
		Name of the resulting POT file.

	[--slug=<slug>]
		Plugin or theme slug. Defaults to the source directory's basename.

	[--domain=<domain>]
		Text domain to look for in the source code, unless the `--ignore-domain` option is used.
		By default, the "Text Domain" header of the plugin or theme is used.
		If none is provided, it falls back to the plugin/theme slug.

	[--ignore-domain]
		Ignore the text domain completely and extract strings with any text domain.

	[--merge[=<file>]]
		Existing POT file file whose content should be merged with the extracted strings.
		If left empty, defaults to the destination POT file.

	[--exclude=<paths>]
		Include additional ignored paths as CSV (e.g. 'tests,bin,.github').
		By default, the following files and folders are ignored: node_modules, .git, .svn, .CVS, .hg, vendor.
		Leading and trailing slashes are ignored, i.e. `/my/directory/` is the same as `my/directory`.

	[--skip-js]
		Skips JavaScript string extraction. Useful when this is done in another build step, e.g. through Babel.

**EXAMPLES**

    # Create a POT file for the WordPress plugin/theme in the current directory
    $ wp i18n make-pot . languages/my-plugin.pot

## Installing

This package is included with WP-CLI itself, no additional installation necessary.

To install the latest version of this package over what's included in WP-CLI, run:

    wp package install git@github.com:wp-cli/i18n-command.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/i18n-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/i18n-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/i18n-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
