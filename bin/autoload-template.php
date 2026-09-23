<?php

if (!defined('ABSPATH')) exit;

// Copied to packages/autoload.php when the zip is built (see .github/workflows/deploy.yml). The plugin has no
// third-party runtime packages, so a PSR-4 loader for src/ is all it needs; wp-scoper writes a richer one when
// a dependency is added, and that file wins because it is already there.
spl_autoload_register(function ($class) {
    if (strpos($class, 'MonoRanks\\') !== 0) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 10)) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});
