<?php

// Test double for the real ResourcesApi: records calls instead of making HTTP requests.
class ResourcesApi {

    public static array $calls = [];
    public static ?Throwable $throw = null;

    public function updateResource( string $path, string $resourceType, string $brand, string $tokenGroup ) {
        self::$calls[] = compact( 'path', 'resourceType', 'brand', 'tokenGroup' );

        if ( self::$throw ) {
            throw self::$throw;
        }
    }
}
