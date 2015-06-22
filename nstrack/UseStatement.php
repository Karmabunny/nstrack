<?php

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
