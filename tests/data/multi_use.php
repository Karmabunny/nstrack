<?php

trait A { function w(){} }

trait B { function x(){} }
trait C { function y(){} }

trait D { function same(){} }
trait E { function same(){} }



class MultipleUse
{

    // Single class in the USE statement
    use A;

    // Two classes in a compound USE statement
    use B, C;

    // Two classes with a method override
    use D, E {
        D::same insteadof E;
    }

    // And the same tests but with namespaces too
    use \Other\Ns\A;
    use \Other\Ns\B, \Other\Ns\C;
    use \Other\Ns\D, \Other\Ns\E {
        \Other\Ns\D::same insteadof \Other\Ns\E;
    }


    // Some other stuff, for testing the brace counting logic
    function xx() { }
    function yy() {    function(){}   }
}
