<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


/**
 * Config options when running nstrack
 */
class Config {
	private static $src_dir;
	private static $ignore = [];
	private static $sort_function;
	private static $group_function;
	
	/**
	 * @param array $a Classes associated with a use statement
	 * @param array $b Classes associated with a use statement
	 */
	static function default_sort($a, $b) {
		if (is_array($a)) $a = array_shortest($a);
		if (is_array($b)) $b = array_shortest($b);
		$a_has_ns = (strpos($a, '\\') !== false);
		$b_has_ns = (strpos($b, '\\') !== false);
		if (!$a_has_ns and $b_has_ns) return -1;
		if ($a_has_ns and !$b_has_ns) return 1;
		
		if ($a < $b) return -1;
		if ($a > $b) return 1;
		return 0;
	}
	
	/**
	 * @param array $classes Classes associated with a use statement
	 * @param bool $has_ns True if any of the classes include a namespace
	 *        separator \
	 * @return string Group name
	 */
	static function default_group(array $classes, $has_ns) {
		return '';
	}
	
	static function dir() { return self::$src_dir; }
	static function ignore() { return self::$ignore; }
	
	/**
	 * @return callable Function to sort use statements
	 */
	static function sort() {
		if (!empty(self::$sort_function)) return self::$sort_function;
		return self::default_sort;
	}
	
	/**
	 * @return callable Function to split use blocks into groups
	 */
	static function group() {
		if (!empty(self::$group_function)) return self::$group_function;
		return self::default_group;
	}
	
	/**
	 * Sets the directory which contains the source code
	 * @param string $dir
	 * @return void
	 */
	static function setSourceDir($dir) {
		self::$src_dir = realpath($dir) . '/';
	}
	
	/**
	 * Sets the list of classes to ignore
	 * @param array $ignore Class names (excluding namespaces)
	 * @return void
	 */
	static function setIgnore(array $ignore) {
		self::$ignore = $ignore;
	}
	
	/**
	 * Sets the list of classes to ignore
	 * @param array $ignore Class names (excluding namespaces)
	 * @return void
	 */
	static function setSort(callable $func) {
		self::$sort_function = $func;
	}
	
	static function setGroup(callable $func) {
		self::$group_function = $func;
	}
	
	/**
	 * Loads a config file.
	 * The file should call Config::set* methods
	 * @param string $file Path to the config file
	 * @return void
	 */
	static function load($file) {
		if (file_exists($file)) require $file;
	}
}
