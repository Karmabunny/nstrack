<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


/**
 * Reports on missing use statements found within a directory
 * 
 * This is achieved by parsing a bunch of PHP files (all files found by the
 * UN*X "find" command), and extracting the following data from each file:
 * - namespace
 * - use statements
 * - class definitions
 * - class references (code => actual)
 */


require __DIR__ . '/nstrack/inc.php';

$dir = realpath(getcwd()) . '/';
Config::setSourceDir($dir);
Config::load($dir . '.nstrack.php');

$write = (in_array('-w', $argv) or in_array('--write', $argv));
$verbose = (in_array('-v', $argv) or in_array('--verbose', $argv));
$debug = (in_array('-d', $argv) or in_array('--debug', $argv));
$missing_only = (in_array('-m', $argv) or in_array('--missing', $argv));
$needs_only = (in_array('-n', $argv) or in_array('--needs', $argv));
$use_colours = (in_array('-c', $argv) or in_array('--colours', $argv));
$targeted = array_search('--targeted', $argv);
$target_dir = null;
if ($debug) $verbose = true;

if ($missing_only and $needs_only) {
    die('You can\'t have it both ways. Pick one or none: -m or -n' . PHP_EOL);
}

if ($targeted) {
    if ($argc <= $targeted + 1) die('--targeted requires a directory argumemt' . PHP_EOL);
    
    $target_dir = escapeshellarg(Config::dir() . $argv[$targeted + 1]);
    $targeted = true;
}

$cmd = "find " . Config::dir() . " -name '*.php'";
$files = [];
$file_names = [];
exec($cmd, $file_names);

$file_count = 0;
foreach ($file_names as $file) {
    
    $data = ParsedFile::parse($file);
    $files[$file] = $data;
    
    if ($data->isEmpty()) continue;
    
    if (count($data->namespaces) > 1) {
        echo '*** ', $file, "\n";
        echo 'namespaces: ', implode(', ', $data->namespaces), "\n";
        die("ERROR: multiple namespace declarations. Don't do this!");
    }
    
    if ($verbose) {
        echo '*** ', $file, "\n";
        echo 'namespaces: ', implode(', ', $data->namespaces), "\n";
        echo 'uses: ', implode(', ', $data->uses), "\n";
        echo 'classes: ', implode(', ', $data->classes), "\n";
        echo 'refs: ', implode(', ', $data->refs), "\n";
        echo "\n";
    }
}

/** Mapping of base class name => array of namespaced classnames, e.g.
    'AwesomeClass' => [Awesome\\AwesomeClass, MoreDifferent\\AwesomeClass] */
$full_classes = [];
foreach ($internal_classes as $class) {
    if (!isset($full_classes[$class])) $full_classes[$class] = [];
    $full_classes[$class][] = $class;
}
foreach ($files as $file) {
    $namespace = '';
    if (count($file->namespaces) > 0) $namespace = $file->namespaces[0];
    foreach ($file->classes as $class) {
        $full_class = $class;
        if ($namespace) $full_class = "{$namespace}\\{$class}";
        if (!isset($full_classes[$class])) $full_classes[$class] = [];
        $full_classes[$class][] = $full_class;
    }
}

if ($debug) {
    $full_classes_str = "[\n";
    foreach ($full_classes as $class => $ns_classes) {
        $full_classes_str .= $class . ' => [' . implode(', ', $ns_classes) . "],\n";
    }
    $full_classes_str .= "];\n";
    @file_put_contents('nstrack_classes.log', $full_classes_str);
}

$unknown_classes = [];

if ($targeted) {
    $cmd = "find {$target_dir} -name '*.php'";
    $file_names = [];
    exec($cmd, $file_names);
    echo 'Restricting changes to: ', $target_dir, PHP_EOL;
}

// Compare use block with actual classes used
echo "\n****************************************\n";
$count = 0;
foreach ($file_names as $filename) {
    $file = $files[$filename];
    $used_classes = $file->refs;
    
    $need = [];
    $namespace = @$file->namespaces[0];
    
    // Always ignore these
    $ignore = Config::ignore();
    
    $debug_text = '';
    $missing = [];
    foreach ($used_classes as $class_ref) {
        $class = $class_ref->class;
        $line = $class_ref->line;
        
        $debug_text .= "Checking class ref: {$class} on line {$line}\n";
        $ignore_class = $class;
        if ($ignore_class[0] == '\\') $ignore_class = substr($ignore_class, 1);
        if (in_array($ignore_class, $ignore)) {
            $debug_text .= "Deliberately ignored\n";
            continue;
        }
        
        // Handle class with complete namespace reference,
        // e.g. \AwesomeProject\AwesomeThings\Awesome
        if ($class[0] == '\\') {
            $debug_text .= "    checking full class list... ";
            $full_ref = substr($class, 1);
            $stripped = rm_class_ns($full_ref);
            if (@in_array($full_ref, $full_classes[$stripped])) {
                $debug_text .= "OK (full reference)\n\n";
                continue;
            }
            $debug_text .= "no good\n";
        }
        
        // Determine class using use statements
        $used = false;
        foreach ($file->uses as $use) {
            $debug_text .= "    against use: {$use}... ";
            if ($use->alias == $class) {
                $used = true;
                $debug_text .= "OK (matches use alias)\n\n";
                break;
            }
            $stripped = rm_class_ns($use->entity);
            $debug_text .= " stripped to {$stripped}... ";
            if ($stripped == $class) {
                $used = true;
                $debug_text .= "OK (matches use class)\n\n";
                break;
            } else {
                $pos = strrpos($class, '\\');
                if ($pos !== false) {
                    $class_ns = substr($class, 0, $pos);
                    if ($stripped == $class_ns) {
                        $used = true;
                        $debug_text .= "OK (matches use NS)\n\n";
                        break;
                    }
                }
            }
            $debug_text .= "no good\n";
        }
        if ($used) continue;
        
        $debug_text .= "    Determining appropriate use statement for missing class\n\n";
        
        if (isset($full_classes[$class])) {
            $to_use = $full_classes[$class];
        } else {
            // Haven't seen class name at all in any of the parsed files
            $missing[] = $class_ref;
            continue;
        }
        $full_ref = $class;
        if ($namespace) $full_ref = $namespace . "\\" . $class;
        
        // There's no need to add a 'use' statement if the desired class
        // already exists in the same namespace
        if (in_array($full_ref, $to_use)) continue;
        
        // Only store first reference so that log isn't giant
        if (!in_array($to_use, $need)) $need[] = $to_use;
    }
    
    $display = false;
    if (count($need) > 0 and !$missing_only) $display = true;
    if (count($missing) > 0 and !$needs_only) $display = true;
    if ($debug) $display = true;
    
    if (!$display) continue;
    
    ++$count;
    if ($use_colours) echo "\033[1;34m[{$count}] {$file->file}\033[0m", PHP_EOL;
    else echo "[{$count}] {$file->file}", PHP_EOL;
    
    if ($namespace and $verbose) echo "(namespace $namespace)\n";
    if ($debug) {
        echo "Existing use statements:\n";
        foreach ($file->uses as $use_st) {
            echo "    use ", $use_st, "\n";
        }
        echo $debug_text;
    }

    if (count($missing) > 0 and !$needs_only) {
        echo "MISSING:\n";
        $lines = explode("\n", $file->content);
        foreach ($missing as $class_ref) {
            if ($use_colours) echo "\033[1;31m{$class_ref->class}\033[0m, line {$class_ref->line}: ";
            else echo "{$class_ref->class}, line {$class_ref->line}: ";
            echo trim($lines[$class_ref->line - 1]), "\n";
        }
    }
    
    if ($missing_only) {
        echo PHP_EOL;
        continue;
    }
    
    if ($verbose) {
        echo "Use: [" . implode(', ', $file->uses) . "]\n";
        echo "Actual use: [", implode(', ', $used_classes), "]\n";
        echo "Need:\n";
    }
    
    // Incorporate extant use statements to create a complete list
    foreach ($file->uses as $use_st) {
        $need[] = [(string) $use_st];
    }
    
    usort($need, Config::sort());
    
    $use_block = '';
    $section = '';
    $last_section = '';
    foreach ($need as $classes) {
        $has_ns = false;
        foreach ($classes as $class) {
            if (strpos($class, '\\') !== false) {
                $has_ns = true;
                break;
            }
        }
        
        $groupify = Config::group();
        $section = $groupify($classes, $has_ns);
        
        if ($section != $last_section) {
            if ($last_section != '') $use_block .= "\n";
            $last_section = $section;
        }
        
        $use_block .= "use ";
        $class = array_shortest($classes);
        if (@$class[0] == '\\') $class = substr($class, 1);
        $use_block .= $class . ";\n";
    }
    
    echo $use_block;
    if ($write and $use_block) write_use_block($use_block, $file->file);
    echo "\n****************************************\n";
}
