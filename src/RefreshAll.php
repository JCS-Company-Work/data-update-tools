<?php

namespace DataUpdateTools;

class RefreshAll {

    /**
     * Streams the refresh events for all resources.
     *
     * @return void
     */
    public static function stream(): void {

        // Pull in the Resources API for refreshing resources
        require_once ABSPATH . 'office/shared/ResourcesApi.php';

        // Set headers for streaming NDJSON
        header( 'Content-Type: application/x-ndjson; charset=UTF-8' );
        header( 'Cache-Control: no-cache, no-transform' );
        header( 'X-Accel-Buffering: no' );

        // Define a callback for sending refresh events as NDJSON
        $send = static function ( array $event ): void {

            // Encode the event as JSON
            $json = wp_json_encode( $event );
            
            // Check if JSON encoding was successful
            if ( $json === false ) {
                throw new \RuntimeException( 'Could not encode refresh event.' );
            }

            // Send the JSON-encoded event to the client
            echo $json . "\n";
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }

            // Flush the output buffer to ensure the event is sent immediately
            flush();
        };

        try {
            // Attempt to refresh all resources and stream events
            ( new \ResourcesApi() )->refreshAllResources( $send );
        } catch ( \Throwable $exception ) {
            // Log the error and send a failure event to the client
            error_log( 'All resources refresh failed: ' . $exception->__toString() );

            // Send a failure event to the client
            $send( [ 'event' => 'failed', 'message' => $exception->getMessage() ] );
        }

        wp_die();
    }
}
