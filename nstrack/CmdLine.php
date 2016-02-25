<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


/**
 * Command line options parser and storage
 */
class CmdLine {
    private $dir;
    
    public $write;
    public $missing_only;
    public $needs_only;
    public $use_colours;
    public $log_classes;
    public $watch;
    public $watch_pattern;
    public $target_paths;


    /**
     * Just calls the {@see CmdLine::parse} method
     *
     * @param string $dir Base directory of the project
     * @param array $argv Command-line arguments
     */
    public function __construct($dir, array $argv)
    {
        $this->dir = $dir;
        $this->parse($argv);
    }


    /**
     * Parse arguments from command line into the class state
     *
     * @param array $argv Command-line arguments
     * @return void
     */
    public function parse(array $argv)
    {
        $argc = count($argv);

        $this->write = (in_array('-w', $argv) or in_array('--write', $argv));
        $this->missing_only = (in_array('-m', $argv) or in_array('--missing', $argv));
        $this->needs_only = (in_array('-n', $argv) or in_array('--needs', $argv));
        $this->use_colours = !(in_array('-d', $argv) or in_array('--no-colour', $argv) or in_array('--no-color', $argv));
        $this->log_classes = (in_array('-l', $argv) or in_array('--log-classes', $argv));

        if ($this->missing_only and $this->needs_only) {
            die('You can\'t have it both ways. Pick one or none: -m or -n' . PHP_EOL);
        }

        $this->target_paths = array();
        foreach ($argv as $index => $arg) {
            if ($arg == '--targeted') {
                if ($argc <= $index + 1) die('--targeted requires a path argument' . PHP_EOL);
                $this->target_paths[] = escapeshellarg($this->dir . $argv[$index + 1]);
            }
        }

        $this->watch = array_search('--watch', $argv);
        if ($this->watch) {
            if ($argc <= $this->watch + 1) die('--watch requires a pattern argument' . PHP_EOL);
            
            $this->watch_pattern = $argv[$watch + 1];
            $this->watch = true;
        } else {
            $this->watch_pattern = null;
        }
    }

}
