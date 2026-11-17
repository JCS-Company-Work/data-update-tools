<?php

namespace DataUpdateTools\Tools;

class RefreshAll {

    public static function stream(): void {
        require_once ABSPATH . 'office/shared/ResourcesApi.php';

        header( 'Content-Type: application/x-ndjson; charset=UTF-8' );
        header( 'Cache-Control: no-cache, no-transform' );
        header( 'X-Accel-Buffering: no' );

        $send = static function ( array $event ): void {
            $json = wp_json_encode( $event );
            if ( $json === false ) {
                throw new \RuntimeException( 'Could not encode refresh event.' );
            }

            echo $json . "\n";
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }
            flush();
        };

        try {
            ( new \ResourcesApi() )->refreshAllResources( $send );
        } catch ( \Throwable $exception ) {
            error_log( 'All resources refresh failed: ' . $exception->__toString() );
            $send( [ 'event' => 'failed', 'message' => $exception->getMessage() ] );
        }

        wp_die();
    }
}
