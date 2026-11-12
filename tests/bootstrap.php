<?php

define( 'ABSPATH', __DIR__ . '/fixtures/wp/' );
define( 'DUPT_URL', 'http://example.test/wp-content/plugins/data-update-tools/' );
define( 'DUPT_VERSION', 'test' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Test doubles for the legacy code the handlers load from the site root.
require_once ABSPATH . 'office/shared/ResourcesApi.php';
require_once ABSPATH . 'systems/tools/samples/src/Classes/GoogleSheets.php';
