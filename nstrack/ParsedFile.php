<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/


/**
 * Represents all the content extracted by parsing a PHP file
 */
class ParsedFile {
    public $file;
    public $mtime;
    public $tokens = [];

    /** e.g. namespace A\B\C; */
    public $namespaces = [];

    /** Each element is a UseStatement, e.g. use A\B\C as D; */
    public $uses = [];

    /** e.g. class A\B\C */
    public $classes = [];

    /**
     * e.g.
     * A\B\C::D
     * new A\B\C
     * instanceof A\B\C
     * extends A\B\C
     * implements A\B\C
     * catch (A\B\C ...)
     */
    public $refs = [];

    private $brace_depth;


    function __construct($file) {
        $this->file = $file;
    }


    private function readTokens() {
        $content = file_get_contents($this->file);
        $this->tokens = token_get_all($content);
    }

    static function parse($file) {
        $parsed_file = new ParsedFile($file);
        $parsed_file->readTokens();
        $num_tokens = count($parsed_file->tokens);
        $key = 0;
        $parsed_file->brace_depth = 0;

        ExceptionHandler::setContext(['file' => $file]);

        while ($key < $num_tokens) {
            $token = $parsed_file->tokens[$key];

            if (is_string($token)) {
                ++$key;
                if ($token == '{') {
                    ++$parsed_file->brace_depth;
                } else if ($token == '}') {
                    --$parsed_file->brace_depth;
                }
                continue;
            }

            ExceptionHandler::addContext([
                'token' => token_name($token[0]),
                'line' => $token[2],
            ]);

            switch ($token[0]) {
                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                    ++$key;
                    ++$parsed_file->brace_depth;
                    break;

                case T_NAMESPACE:
                    ++$key;
                    $parsed_file->handleNamespace($key);
                    break;

                case T_USE:
                    ++$key;
                    if ($parsed_file->brace_depth > 0) {
                        $parsed_file->handleInsideClassUse($key);
                    } else {
                        $parsed_file->handleFileUse($key);
                    }
                    break;

                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                    ++$key;
                    $parsed_file->handleClass($key);
                    break;

                case T_NEW:
                case T_INSTANCEOF:
                case T_EXTENDS:
                case T_IMPLEMENTS:
                    ++$key;
                    $parsed_file->handleRef($key);
                    break;

                case T_DOUBLE_COLON:
                    $parsed_file->handleStatic($key);
                    break;

                case T_CATCH:
                    $parsed_file->handleCatch($key);
                    break;

                case T_FUNCTION:
                    $parsed_file->handleFunction($key);
                    break;

                default:
                    ++$key;
            }
        }

        ExceptionHandler::clearContext();

        if ($parsed_file->brace_depth != 0) {
            throw new Exception("Brace depth of {$parsed_file->brace_depth} not zero at end of file: {$file}");
        }
        unset($parsed_file->brace_depth);

        // This isn't needed anymore, so throw away and save lots of RAM.
        unset($parsed_file->tokens);

        return $parsed_file;
    }

    /**
     * Gets a class/interface/function name, possibly prefixed with a namespace
     * @param int $key Position of current token in token array
     * @param bool $is_ref True if the entity is a class reference, in which
     *        case, more tokens types can be part of the entity, e.g.
     *        $variables, self, parent, __CLASS__, and so on
     * @return string
     */
    function extractEntity(&$key, $is_ref = false) {
        $offset = -1;
        $ok = [T_STRING, T_NS_C, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED];
        $ref_ok = [T_VARIABLE, T_STRING, T_CLASS_C, T_STATIC];
        if ($is_ref) $ok = array_merge($ok, $ref_ok);

        $entity = '';
        $at_nss = false;
        while (true) {
            ++$offset;
            $tok = $this->tokens[$key + $offset];
            if ($tok[0] == T_NS_SEPARATOR) {
                $at_nss = true;
                $entity .= $tok[1];
            } else if (in_array($tok[0], $ok) and ($at_nss or $entity == '')) {
                $at_nss = false;
                $entity .= $tok[1];
            } else if ($tok[0] != T_WHITESPACE) {
                break;
            }
        }
        $key += $offset;
        return $entity;
    }


    function handleNamespace(&$key) {
        $this->namespaces[] = $this->extractEntity($key);
    }

    function handleFileUse(&$key) {
        $ns = $this->extractEntity($key);
        $alias = '';
        if ($this->tokens[$key][0] == T_AS) {
            $key += 2;
            $alias = $this->extractEntity($key);
        }
        $this->uses[] = new UseStatement($ns, $alias);
    }

    /**
     * Use statements inside of the class, either traits or closures
     */
    function handleInsideClassUse(&$key)
    {
        // Check for closures
        if (@$this->tokens[$key][0] === T_WHITESPACE) {
            ++$key;
        }
        if ($this->tokens[$key] === '(') {
            return;
        }

        $ns = $this->extractEntity($key);
        if ($this->tokens[$key] === ';') --$key;
        $line = $this->tokens[$key][2];
        $this->addClassRef($ns, $line, $key);
    }

    function handleClass(&$key) {
        $this->classes[] = $this->extractEntity($key);
    }

    function handleRef(&$key) {
        $line = $this->tokens[$key][2];
        $class = $this->extractEntity($key, true);

        $this->addClassRef($class, $line, $key);
    }

    function handleStatic(&$key) {
        $i = $key;
        $class = '';
        $ok = [T_STRING, T_NS_SEPARATOR, T_VARIABLE, T_VARIABLE, T_STRING,
            T_CLASS_C];
        $line = -1;
        while (true) {
            $tok = $this->tokens[--$i];
            if (in_array($tok[0], $ok)) {
                $class = $tok[1] . $class;
                $line = $tok[2];

            // ignore static::$var and static::func() calls
            } else if ($tok[0] == T_STATIC) {
                ++$key;
                return;

            } else if ($tok[0] != T_WHITESPACE) {
                break;
            }
        }
        ++$key;

        // Ignore weird cases like a static call on an array element, e.g. $arr['key']::someFunction(...)
        if (!$class) return;

        $this->addClassRef($class, $line, $key);
    }

    function handleCatch(&$key) {
        while (++$key) {
            $tok = $this->tokens[$key];
            if ($tok == '(') continue;
            if (is_string($tok)) {
                throw new Exception("Found {$tok} after catch");
            }
            if ($tok[0] == T_WHITESPACE) continue;
            break;
        }
        $line = $tok[2];
        $class = $this->extractEntity($key, true);

        $this->addClassRef($class, $line, $key);
    }

    function handleFunction(&$key) {
        static $scalar_typehints = ['string', 'int', 'float', 'bool', 'object', 'callable', 'iterable'];

        // Skip to function args
        do {
            ++$key;
        } while ($this->tokens[$key] !== '(');
        ++$key;

        $i = $key;

        $tok = $this->tokens[$i];
        $expect_type = true;
        while ($tok !== ')') {
            if ($tok === ',') {
                $expect_type = true;
            } else {
                if ($expect_type and $tok[0] == T_STRING) {
                    $line = $tok[2];
                    $class = $this->extractEntity($i, false);
                    if (!in_array(strtolower($class), $scalar_typehints)) {
                        $this->addClassRef($class, $line, $i);
                    }
                    $tok = $this->tokens[$i];
                    $expect_type = false;
                    continue;
                }
                if ($tok[0] != T_WHITESPACE) {
                    $expect_type = false;
                }
            }
            ++$i;
            $tok = $this->tokens[$i];
        }

        // Search after the arguments for a return typehint, and extract if found
        $has_return = false;
        while ($tok !== '{' and $tok !== ';') {
            if ($tok === ':') {
                $has_return = true;
            } elseif ($has_return and $tok[0] === T_STRING) {
                $line = $tok[2];
                $class = $this->extractEntity($i, false);
                if (!in_array(strtolower($class), $scalar_typehints) and strtolower($class) !== 'void') {
                    $this->addClassRef($class, $line, $i);
                }
                break;
            }
            ++$i;
            $tok = $this->tokens[$i];
        }
    }

    function isEmpty() {
        if (count($this->namespaces) > 0) return false;
        if (count($this->uses) > 0) return false;
        if (count($this->classes) > 0) return false;
        if (count($this->refs) > 0) return false;
        return true;
    }

    function addClassRef($class, $line, $key) {
        if (strpos($class, '$') !== false) return;
        if ($class == 'self' or $class == 'parent') return;
        if ($class == 'static' or $class == '__CLASS__') return;

        foreach ($this->refs as $ref) {
            if ($ref->class == $class) return;
        }
        $this->refs[] = new ClassRef($class, $line, $key);
    }
}
