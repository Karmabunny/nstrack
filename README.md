# NStrack: for tracking namespace use in PHP

PHP 5.3 introduced namespaces, which are great for managing large codebases and integrating disparate projects.
However, they also introduce significant complexity.

NStrack allows you to search an entire codebase, find all class/interface references, and make sure they're referencing
classes that actually exist with appropriate use statements.

It's particularly useful if you have legacy code which you want to move into namespaces, and it's also useful for
managing new code to minimise runtime errors.

## Usage
Call `nstrack.php` from within your source code directory.

e.g.
```shell
php ~/git/nstrack/nstrack.php
```

There are also some flags which do additional processing:

* `--write`
  Write mode.
  Alter files rather than just listing changes.
  A little bit buggy sometimes.
  Don't use this if you're not using source control!
* `--verbose`
  Output extra information
* `--debug`
  Output debugging information
* `--targeted`
  Refines the output and side-effects (e.g. `--write`) to a certain directory or file.
* `--needs`
  Display only missing use statements.
* `--missing`
  Display only missing classes.
* `--colours`
  Enable coloured output.
