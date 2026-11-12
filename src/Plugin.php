<?php


namespace DataUpdateTools;

use DataUpdateTools\Tools\JsonUpdater;
use DataUpdateTools\Tools\SampleRequestUpdate;
use DataUpdateTools\Tools\ProductFinderUpdate;


class Plugin {
    public function boot() {

        // Register JsonUpdater tool
        (new JsonUpdater())->register();

        // Register SampleRequestUpdate tool
        (new SampleRequestUpdate())->register();

        // Register ProductFinderUpdate tool
        (new ProductFinderUpdate())->register();

    }
}