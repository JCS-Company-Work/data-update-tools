<?php


namespace DataUpdateTools;

use DataUpdateTools\Tools\JsonUpdater;
use DataUpdateTools\Tools\SampleRequestUpdate;
use DataUpdateTools\Tools\ProductFinderUpdate;
use DataUpdateTools\Tools\FlorimukUpdate;


class Plugin {
    public function boot() {

        // Register JsonUpdater tool
        (new JsonUpdater())->register();

        // Register SampleRequestUpdate tool
        (new SampleRequestUpdate())->register();

        // Register ProductFinderUpdate tool
        (new ProductFinderUpdate())->register();

        // Register FlorimukUpdate tool
        (new FlorimukUpdate())->register();

    }
}