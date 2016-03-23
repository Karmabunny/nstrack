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
    public $content;
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
    
    function __construct($file) {
        $this->file = $file;
        $this->content = file_get_contents($file);
        $this->tokens = token_get_all($this->content);
    }
    
    static function parse($file) {
        $parsed_file = new ParsedFile($file);
        $num_tokens = count($parsed_file->tokens);
        $key = 0;
        while ($key < $num_tokens) {
            $token = $parsed_file->tokens[$key];
            if (is_string($token)) {
                ++$key;
                continue;
            }
            
            switch ($token[0]) {
                case T_NAMESPACE:
                    ++$key;
                    $parsed_file->handleNamespace($key);
                    break;
                
                case T_USE:
                    ++$key;
                    $parsed_file->handleUse($key);
                    break;
                
                case T_CLASS:
                case T_INTERFACE:
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
                
                default:
                    ++$key;
            }
        }
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
        $ok = [T_STRING, T_NS_C];
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
    
    function handleUse(&$key) {
        $ns = $this->extractEntity($key);
        $alias = '';
        if ($this->tokens[$key][0] == T_AS) {
            $key += 2;
            $alias = $this->extractEntity($key);
        }
        $this->uses[] = new UseStatement($ns, $alias);
    }
    
    function handleClass(&$key) {
        $this->classes[] = $this->extractEntity($key);
    }
    
    function handleRef(&$key) {
        $line = $this->tokens[$key][2];
        $class = $this->extractEntity($key, true);
        
        $this->addClassRef($class, $line, $key);
    }
    
    /**
     * @todo maybe handle weird cases like a static call on an array element,
     *       e.g. $arr['key']::someFunction(...)
     */
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
