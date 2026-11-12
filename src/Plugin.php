<?php


namespace DataUpdateTools;

use DataUpdateTools\Tools\JsonUpdater\JsonUpdater;
use DataUpdateTools\Tools\SampleRequestUpdate\SampleRequestUpdate;


class Plugin {
    public function boot() {

        // Register JsonUpdater tool
        (new JsonUpdater())->register();

        // Register SampleRequestUpdate tool
        (new SampleRequestUpdate())->register();

    }
}