<?php

class ExceptionHandler
{

    /**
     * Setup error and exception handling
     */
    public static function init()
    {
        error_reporting(-1);
        set_exception_handler([__CLASS__, 'exceptionHandler']);
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
