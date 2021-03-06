<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


class UseStatement {
    public $entity;
    public $alias;

    /**
     * @param string $entity The entity, e.g. AwesomeThings\SuperThing
     * @param string $alias The alias, i.e. 'as ___'
     */
    function __construct($entity, $alias) {
        $this->entity = $entity;
        $this->alias = $alias;
    }

    function __toString() {
        $str = $this->entity;
        if ($this->alias) $str .= ' as ' . $this->alias;
        return $str;
    }
}
