<?php

class ExceptionHandler
{
    private static $context = [];


    /**
     * Setup error and exception handling
     */
    public static function init()
    {
        error_reporting(-1);
        set_error_handler([__CLASS__, 'errorToException']);
        set_exception_handler([__CLASS__, 'handle']);
    }


    /**
     * Convert PHP errors into ErrorException exceptions
     */
    public static function errorToException(int $errno, string $errstr, string $errfile, int $errline)
    {
        if (!(error_reporting() & $errno)) {
            return;
        } else {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
    }


    /**
     * Handler for exceptions
     */
    public static function handle(Throwable $ex): void
    {
        echo PHP_EOL;
        echo 'Uncaught Exception ', get_class($ex), PHP_EOL;
        echo $ex->getMessage(), PHP_EOL;
        echo ' at ', $ex->getFile() . ':', $ex->getLine(), PHP_EOL;
        echo 'Stack Trace:', PHP_EOL;
        echo ' ', str_replace("\n", "\n ", $ex->getTraceAsString()), PHP_EOL;
        if (!empty(self::$context)) echo 'Context:', PHP_EOL;
        foreach (self::$context as $key => $val) {
            echo ' ', $key, ' = ', $val, PHP_EOL;
        }
        echo PHP_EOL;
        exit(1);
    }


    /**
     * Overwrite the context
     */
    public static function setContext(array $ctx)
    {
        self::$context = $ctx;
    }


    /**
     * Add or update context fields
     */
    public static function addContext(array $ctx)
    {
        self::$context = array_merge(self::$context, $ctx);
    }


    /**
     * Clear all context fields
     */
    public static function clearContext()
    {
        self::$context = [];
    }

}
