<?php

require "load_root.php"; // PROJECT_ROOT
require PROJECT_ROOT . '/vendor/autoload.php';

if (\App\Core\SpwBase::CDN_USAGE) {
    // Use CDN
    $eruda = '<script src="https://cdn.jsdelivr.net/npm/eruda"></script><script>eruda.init();</script>';
} else {
    // Use local copy (make sure you saved it under /vendor/eruda/eruda.min.js)
    $eruda = '<script src="/vendor/eruda/eruda.min.js"></script><script>eruda.init();</script>';
}

//$eruda = '';
