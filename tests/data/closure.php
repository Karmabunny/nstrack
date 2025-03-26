<?php

class HasClosure
{
    use A;


    function x()
    {
        $xxx = null;
        $func1 = function() use($xxx) {
        };
        $func2 = function() use(&$xxx) {
        }
        $func3 = function() use($func1, $func2) {
        }
        $func4 = function() use ($func1, $func2) {
        }
        $func5 = function() use ( $func1, $func2 ) {
        }
        $func6 = function() use ( &$func1 ) {
        }
        $func7 = function ( ) use ( &$func1 ) {
        }
        $func8 = function ( )
            use
            (
                &$func1
            )
        {
        }
    }

}
