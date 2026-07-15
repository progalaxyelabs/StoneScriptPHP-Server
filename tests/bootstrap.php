<?php

// Test bootstrap for the StoneScriptPHP-Server skeleton.
//
// Unlike the framework's own tests/bootstrap.php (which has to work around
// running from a framework checkout, not a vendor install), this project
// root IS the real production layout: vendor/progalaxyelabs/stonescriptphp's
// own src/bootstrap.php (loaded via composer's autoload.files) already
// guesses ROOT_PATH correctly as 4 levels up from
// vendor/progalaxyelabs/stonescriptphp — i.e. this project's root. So we
// just need to load the autoloader; no manual constant juggling required.

date_default_timezone_set('UTC');

require_once __DIR__ . '/../vendor/autoload.php';
