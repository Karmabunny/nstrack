<?php
require_once __DIR__ . '/../nstrack/inc.php';

class nstrackTest extends PHPUnit_Framework_TestCase
{
    public function testTypeHints()
    {
        $file = ParsedFile::parse(__DIR__ . '/classes_type_hints.php');
        $this->assertEquals(3, count($file->refs));
    }
}
