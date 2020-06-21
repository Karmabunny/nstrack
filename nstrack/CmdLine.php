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
     * Searches an array for a set of terms, removes any instances of them, and returns true if any were found.
     *
     * N.B. The identical (===) comparison method is used to do the matching
     *
     * @param array $needles The terms to find in the array
     * @param array $haystack The array to search
     * @return bool True if at least one matching term was found
     */
    protected function arraySeekAndDestroy(array $needles, array &$haystack)
    {
        if (count($needles) == 0) return false;

        $found = false;
        foreach ($haystack as $key => $val) {
            foreach ($needles as $needle) {
                if ($val === $needle) {
                    $found = true;
                    unset($haystack[$key]);
                    break;
                }
            }
        }

        return $found;
    }


    /**
     * Parse arguments from command line into the class state
     *
     * @param array $argv Command-line arguments
     * @return void
     */
    public function parse(array $argv)
    {
        $this->write = $this->arraySeekAndDestroy(['-w', '--write'], $argv);
        $this->missing_only = $this->arraySeekAndDestroy(['-m', '--missing'], $argv);
        $this->needs_only = $this->arraySeekAndDestroy(['-n', '--needs'], $argv);
        $this->use_colours = !$this->arraySeekAndDestroy(['-d', '--no-colour', '--no-color'], $argv);
        $this->log_classes = $this->arraySeekAndDestroy(['-l', '--log-classes'], $argv);

        if ($this->missing_only and $this->needs_only) {
            die('You can\'t have it both ways. Pick one or none: -m or -n' . PHP_EOL);
        }

        $this->target_paths = array();
        if ($this->arraySeekAndDestroy(['--git'], $argv)) {
            $this->addGitTargets();
        }

        // Remove self-reference and reset array indexes
        array_shift($argv);
        $argc = count($argv);

        $this->watch = array_search('--watch', $argv);
        if ($this->watch !== false) {
            if ($argc <= $this->watch + 1) die('--watch requires a pattern argument' . PHP_EOL);

            $this->watch_pattern = $argv[$this->watch + 1];
            unset($argv[$this->watch + 1], $argv[$this->watch]);
            $this->watch = true;
        } else {
            $this->watch_pattern = null;
        }

        // Treat all remaining args as targeted file paths
        foreach ($argv as $index => $arg) {
            if ($arg == '--targeted') continue;

            if (substr($arg, 0, strlen($this->dir)) == $this->dir) {
                $this->target_paths[] = escapeshellarg($arg);
            } else {
                $this->target_paths[] = escapeshellarg($this->dir . $arg);
            }
        }

    }


    private static function findGitDir()
    {
        $dir = realpath(getcwd()) . '/';
        while ($dir and is_dir($dir) and !is_dir($dir . '.git')) {
            $dir = preg_replace('#[^/]*/$#', '', $dir);
        }
        return $dir;
    }


    /**
     * Execute a "git status" and use that to add target paths
     * Note - doesn't currently parse flags in status, so may try to target deleted files
     */
    public function addGitTargets()
    {
        $config_dir = Config::dir();

        // git porcelain output is relative to the git root directory
        $git_dir = self::findGitDir();
        if (empty($git_dir)) {
            echo "Unable to find .git directory in any parent directory\n";
            exit(1);
        }

        $git_log = shell_exec('git status -z --porcelain');
        $git_log = array_filter(explode("\0", $git_log));

        foreach ($git_log as $ln) {
            $flags = substr($ln, 0, 2);
            $filename = substr($ln, 3);

            $pos = strpos($filename, ' -> ');
            if ($pos !== false) {
                $filename = substr($filename, $pos + 4);
            }

            if ($filename[0] == '"') {
                $filename = trim($filename, '"');
                $filename = stripslashes($filename);
            }

            if (!preg_match('/\.php$/', $filename)) {
                continue;
            }

            // Ensure only files within the source dir get parsed
            $filename = $git_dir . $filename;
            if (substr($filename, 0, strlen($config_dir)) != $config_dir) {
                continue;
            }

            $this->target_paths[] = escapeshellarg($filename);
        }
    }

}
