<?php

namespace Samples\Classes;

// Test double for the samples tool's GoogleSheets class.
class GoogleSheets {

    public static array $calls = [];
    public static string $result = 'Update successful';

    public function createJSON( string $brand ) {
        self::$calls[] = $brand;

        return self::$result;
    }
}
