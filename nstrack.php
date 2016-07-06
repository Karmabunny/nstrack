<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


$docs = <<<DOCS

NAME
    NSTrack

SYNOPSIS
    php nstrack.php [options]

DESCRIPTION
    Reports on missing use statements and unknown class-like entities in code.
    Interfaces count as class-like entities, and traits do as well, although
    they haven't been tested.
    
    This is achieved by parsing all PHP files, and extracting the following
    data from each file:
        namespace; use statements; class definitions; class references.
    
    Files to be parsed are found by the UN*X "find" command, starting from the
    current working directory, or alternatively from a directory specified by
    a .nstrack.php config file.
    
OPTIONS
    -d, --no-colour, --no-color
        Disable terminal colours, which are enabled by default

    -h, --help
        Display this help information

    -l, --log-classes
        Write a log of all the classes found to the file nstrack_classes.log.

    -m, --missing
        Only searches for missing classes, not use statements. This mode is
        incompatible with --needs.

    -n, --needs
        Only searches for missing use statements, not unknown classes. This
        mode is incompatible with --missing.

    --targeted path
        Only search the specified path. All found files will still be parsed
        to extract the necessary class definitions. Supports multiple --targeted
        options.

    --git
        Target all changed files according to git (does a `git status`).

    -w, --write
        Run in write mode, where files are automatically overwritten with an
        updated list of use statements.

    --watch pattern
        Prints debug output when parsing or processing files with names which
        match the (glob-style) wildcard pattern, e.g. '*Controller.php'

DOCS;

if (in_array('-h', $argv) or in_array('--help', $argv) or in_array('-help', $argv)) die(ltrim($docs));


require __DIR__ . '/nstrack/inc.php';

$dir = realpath(getcwd()) . '/';
Config::setSourceDir($dir);
Config::load($dir . '.nstrack.php');

$cmdline = new CmdLine($dir, $argv);

$cmd = "find " . Config::dir() . " -name '*.php'";
$files = [];
$file_names = [];
exec($cmd, $file_names);

$cache_file = '.nstrack_cache.json';
if (file_exists($cache_file)) {
    $cache = json_decode(file_get_contents($cache_file), true);
    echo "Loading cached data for ", count($cache), " file", (count($cache) == 1 ? '' : 's'), "\n";
    foreach ($cache as $cache_data) {
        if (!file_exists($cache_data['file'])) continue;

        $files[$cache_data['file']] = load_cached_file($cache_data);
    }
}

$new_files_read = 0;
$file_count = 0;
foreach ($file_names as $file) {
    if (isset($files[$file]) and filemtime($file) == $files[$file]->mtime) {
        $data = $files[$file];
    } else {
        ++$new_files_read;
        $data = ParsedFile::parse($file);
        $data->mtime = filemtime($file);
        $files[$file] = $data;
    }

    if ($data->isEmpty()) continue;
    
    if (count($data->namespaces) > 1) {
        echo '*** ', $file, "\n";
        echo 'namespaces: ', implode(', ', $data->namespaces), "\n";
        die("ERROR: multiple namespace declarations. Don't do this!");
    }
    
    if ($cmdline->watch and fnmatch($cmdline->watch_pattern, $file)) {
        echo '*** ', $file, "\n";
        echo 'namespaces: ', implode(', ', $data->namespaces), "\n";
        echo 'uses: ', implode(', ', $data->uses), "\n";
        echo 'classes: ', implode(', ', $data->classes), "\n";
        echo 'refs: ', implode(', ', $data->refs), "\n";
        echo "\n";
    }
}

echo $new_files_read, ' file', ($new_files_read == 1 ? '' : 's'), " read\n";

// Cache results
if ($new_files_read > 0 and $json_file = fopen($cache_file, 'w')) {
    $cache = [];
    foreach ($files as $file) {
        $cache_data = [
            'mtime' => $file->mtime,
            'namespaces' => $file->namespaces,
            'classes' => $file->classes,
            'refs' => $file->refs,
            'uses' => $file->uses,
            'file' => $file->file,
        ];
        $cache[] = $cache_data;
    }
    $json = json_encode($cache);
    json_fail($json);
    fwrite($json_file, $json);
    fclose($json_file);
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

if ($cmdline->log_classes) {
    $full_classes_str = "[\n";
    foreach ($full_classes as $class => $ns_classes) {
        $full_classes_str .= $class . ' => [' . implode(', ', $ns_classes) . "],\n";
    }
    $full_classes_str .= "];\n";
    @file_put_contents('nstrack_classes.log', $full_classes_str);
}

$unknown_classes = [];

if (count($cmdline->target_paths) > 0) {
    $file_names = [];
    foreach ($cmdline->target_paths as $path) {
        $cmd = "find {$path} -name '*.php'";
        $tmp = [];
        exec($cmd, $tmp);
        $file_names = array_merge($file_names, $tmp);
    }
    $file_names = array_unique($file_names);
    if ($cmdline->use_colours) {
        echo "\033[1;32mRestricting changes to: ", implode(', ', $cmdline->target_paths), "\033[0m", PHP_EOL;
    } else {
        echo 'Restricting changes to: ', implode(', ', $cmdline->target_paths), PHP_EOL;
    }
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
    
    $referenced_uses = [];
    
    $debug_text = '';
    $missing = [];
    foreach ($used_classes as $ref_id => $class_ref) {
        $class = $class_ref->class;
        $line = $class_ref->line;
        
        $debug_text .= "Checking class ref: {$class} on line {$line}\n";
        $ignore_class = $class;

        if (!$class) {
            $err = "Broken class ref in {$filename} on line {$line}\n" . print_r($class_ref, true);
            $err .= "\nToken: " . token_str($file->tokens[$class_ref->key]) . "\n";
            if (!empty($used_classes[$ref_id - 1])) $err .= "Prev ref: " . print_r($used_classes[$ref_id - 1], true);
            if (!empty($used_classes[$ref_id + 1])) $err .= "Next ref: " . print_r($used_classes[$ref_id + 1], true);
            fwrite(STDERR, $err);
            continue;
        }

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
                if (!in_array($use, $referenced_uses)) $referenced_uses[] = $use;
                $debug_text .= "OK (matches use alias)\n\n";
                break;
            }
            $stripped = rm_class_ns($use->entity);
            $debug_text .= " stripped to {$stripped}... ";
            if ($stripped == $class) {
                $used = true;
                if (!in_array($use, $referenced_uses)) $referenced_uses[] = $use;
                $debug_text .= "OK (matches use class)\n\n";
                break;
            } else {
                $pos = strrpos($class, '\\');
                if ($pos !== false) {
                    $class_ns = substr($class, 0, $pos);
                    if ($stripped == $class_ns) {
                        $used = true;
                        if (!in_array($use, $referenced_uses)) $referenced_uses[] = $use;
                        $debug_text .= "OK (matches use NS)\n\n";
                        break;
                    }
                }
            }
            $debug_text .= "no good\n";
        }
        if ($used) continue;
        
        $debug_text .= "    Determining appropriate use statement for missing class\n\n";
        
        $base_class = rm_class_ns($class);
        $debug_text .= "    Class {$class} has base: {$base_class}\n\n";
        if (isset($full_classes[$base_class])) {
            $to_use = $full_classes[$base_class];
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
    
    // The list of use statements needs to be updated if:
    // - any of the existing use statements have no references
    // - new use statements are required
    $needs_match = true;
    foreach ($file->uses as $use) {
        if (!in_array($use, $referenced_uses)) {
            $needs_match = false;
            break;
        }
    }
    if (count($need) > 0) $needs_match = false;
    
    $debug = $display = false;
    if (!$needs_match and !$cmdline->missing_only) $display = true;
    if (count($missing) > 0 and !$cmdline->needs_only) $display = true;
    if ($cmdline->watch) {
        if (fnmatch($cmdline->watch_pattern, $filename)) {
            $debug = true;
            $display = true;
        } else {
            $display = false;
        }
    }
    
    if (!$display) continue;
    
    ++$count;
    if ($cmdline->use_colours) echo "\033[1;34m[{$count}] {$file->file}\033[0m", PHP_EOL;
    else echo "[{$count}] {$file->file}", PHP_EOL;
    
    if ($debug) {
        if ($namespace) echo "(namespace $namespace)\n";
        echo "Existing use statements:\n";
        foreach ($file->uses as $use_st) {
            echo "    use ", $use_st, "\n";
        }
        echo $debug_text;
    }

    if (count($missing) > 0 and !$cmdline->needs_only) {
        echo "MISSING:\n";

        // File content isn't available when running off cached NS refs, so read the file
        if (empty($file->content)) {
            $lines = file($file->file);
        } else {
            $lines = explode("\n", $file->content);
        }

        foreach ($missing as $class_ref) {
            if ($cmdline->use_colours) echo "\033[1;31m{$class_ref->class}\033[0m, line {$class_ref->line}: ";
            else echo "{$class_ref->class}, line {$class_ref->line}: ";
            echo trim($lines[$class_ref->line - 1]), "\n";
        }
    }
    
    if ($cmdline->missing_only) {
        echo PHP_EOL;
        continue;
    }
    
    if ($debug) {
        echo "Use: [" . implode(', ', $file->uses) . "]\n";
        echo "Actual use: [", implode(', ', $used_classes), "]\n";
        echo "Need:\n";
    }
    
    // Incorporate extant use statements to create a complete list
    foreach ($file->uses as $use_st) {
        if (!in_array($use_st, $referenced_uses)) {
            continue;
        }
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
        if ($class[0] == '\\') $class = substr($class, 1);
        $use_block .= $class . ";\n";
    }
    
    echo $use_block;
    if ($cmdline->write and $use_block) write_use_block($use_block, $file->file);
    echo "\n****************************************\n";
}
