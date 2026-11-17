<?php

// Test double for the real ResourcesApi: records calls instead of making HTTP requests.
class ResourcesApi {

    public static array $calls = [];
    public static ?Throwable $throw = null;
    public static array $events = [];

    public function updateResource( string $path, string $resourceType, string $brand, string $tokenGroup ) {
        self::$calls[] = compact( 'path', 'resourceType', 'brand', 'tokenGroup' );

        if ( self::$throw ) {
            throw self::$throw;
        }
    }

    public function refreshAllResources( ?callable $onEvent = null ): array {
        self::$calls[] = [ 'refreshAllResources' => true ];

        foreach ( self::$events as $event ) {
            if ( $onEvent !== null ) {
                $onEvent( $event );
            }
        }

        if ( self::$throw ) {
            throw self::$throw;
        }

        return self::$events;
    }
}
