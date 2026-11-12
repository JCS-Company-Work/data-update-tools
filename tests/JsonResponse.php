<?php

namespace DataUpdateTools\Tests;

// Thrown by the stubbed wp_send_json_* functions, which normally terminate the request.
class JsonResponse extends \Exception {

    public function __construct( public bool $success, public array $data, public int $status ) {
        parent::__construct( $data['message'] ?? '' );
    }
}
