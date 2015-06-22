<?php

/**
 * A class reference within code
 * e.g. <code>$x = new Thing();</code> is a reference to the Thing class
 */
class ClassRef {
    public $class;
    public $line;
    
    /**
     * @param string $class The name of the referenced class
     * @param int $line The line number where the class is referenced
     */
    function __construct($class, $line) {
        $this->class = $class;
        $this->line = $line;
    }
    
    function __toString() {
        return $this->class . ' (line ' . $this->line . ')';
    }
}
