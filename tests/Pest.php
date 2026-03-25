<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// Uses PHPUnit\Framework\TestCase by default for all tests.

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function rmdir_recursive(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? rmdir_recursive($path) : unlink($path);
    }
    rmdir($dir);
}
