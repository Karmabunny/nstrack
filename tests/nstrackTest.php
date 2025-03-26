<?php
use PHPUnit\Framework\TestCase;


require_once __DIR__ . '/../nstrack/inc.php';


class nstrackTest extends TestCase
{

    public function testTypeHints()
    {
        $file = ParsedFile::parse(__DIR__ . '/data/classes_type_hints.php');
        $this->assertEquals(3, count($file->refs));
    }

    public function testParseNestedNs()
    {
        $file = ParsedFile::parse(__DIR__ . '/data/nested_ns.php');
        $this->assertEquals(['Nested\\Namespace\\Structure'], $file->namespaces);
    }

    public function testClosure()
    {
        $file = ParsedFile::parse(__DIR__ . '/data/closure.php');
        $this->assertEquals(1, count($file->refs));
    }

}
