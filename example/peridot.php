<?php
use Evenement\EventEmitterInterface;
use Peridot\Plugin\Watcher\WatcherInterface;
use Peridot\Plugin\Watcher\WatcherPlugin;

return function(EventEmitterInterface $emitter) {
    $watcher = new WatcherPlugin($emitter);
    $watcher->track(__DIR__ . '/../src');
    $watcher->setEvents([WatcherInterface::MODIFY_EVENT, WatcherInterface::CREATE_EVENT]);
};
