<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


/**
 * A class reference within code
 * e.g. <code>$x = new Thing();</code> is a reference to the Thing class
 */
class ClassRef {
    public $class;
    public $line;
    public $key;
    
    /**
     * @param string $class The name of the referenced class
     * @param int $line The line number where the class is referenced
     * @param int $key Token key in the parsed file; see {@see token_get_all}
     */
    function __construct($class, $line, $key = -1) {
        $this->class = $class;
        $this->line = $line;
        $this->key = $key;
    }
    
    function __toString() {
        return $this->class . ' (line ' . $this->line . ')';
    }
}
