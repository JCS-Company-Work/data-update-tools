<?php


namespace DataUpdateTools;

use DataUpdateTools\Tools\JsonUpdater\JsonUpdater;


class Plugin {
    public function boot() {

        // Register JsonUpdater tool
        (new JsonUpdater())->register();

    }
}