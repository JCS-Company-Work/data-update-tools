<?php

namespace DataUpdateTools;

use DataUpdateTools\Tools\DataUpdater;

class Plugin {
    public function boot() {
        (new DataUpdater())->register();
    }
}