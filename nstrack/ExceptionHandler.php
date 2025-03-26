<?php

class ExceptionHandler
{

    /**
     * Setup error and exception handling
     */
    public static function init()
    {
        error_reporting(-1);
        set_error_handler([__CLASS__, 'errorToException']);
        set_exception_handler([__CLASS__, 'exceptionHandler']);
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
    public static function exceptionHandler(Throwable $ex): void
    {
        echo PHP_EOL;
        echo 'Uncaught Exception ', get_class($ex), PHP_EOL;
        echo $ex->getMessage(), PHP_EOL;
        echo ' at ', $ex->getFile() . ':', $ex->getLine(), PHP_EOL;
        echo 'Stack Trace:', PHP_EOL;
        echo $ex->getTraceAsString(), PHP_EOL, PHP_EOL;
        exit(1);
    }

}
