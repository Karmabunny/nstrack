# NStrack: for tracking namespace use in PHP

PHP 5.3 introduced namespaces, which are great for managing large codebases and integrating disparate projects.
However, they also introduce significant complexity.

NStrack allows you to search an entire codebase, find all class/interface references, and make sure they're referencing
classes that actually exist with appropriate use statements.

It's particularly useful if you have legacy code which you want to move into namespaces, and it's also useful for
managing new code to minimise runtime errors.
