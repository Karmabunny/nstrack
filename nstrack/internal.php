<?php
/*
* NSTrack, Copyright (C) 2015 Karmabunny Web Design
* Written by Benno Lang
* Released under the GPL v3 with NO WARRANTY
*/

// Keep a list of PHP's inbuilt classes/interfaces/traits to match against
// N.B. This is called before any other includes, so it won't find anything external
$internal_classes = get_declared_classes();
$internal_classes = array_merge($internal_classes, get_declared_interfaces());
$internal_classes = array_merge($internal_classes, get_declared_traits());

// Feel free to hack this list; it's fairly basic by default
$internal_classes = array_merge($internal_classes, [
    // Add classes from PHP modules which may not be installed
    'Gmagick',
    'Imagick',
    
    // And classes from PEAR or other such installed packages
    'Mail',
    'Mail_mime',
    'PEAR_Dependency2',
    'PHPUnit_Framework_TestCase',
    'TCPDF',
]);
