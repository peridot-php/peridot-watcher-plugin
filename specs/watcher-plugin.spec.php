<?php
use Evenement\EventEmitter;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Console\Environment;
use Peridot\Console\InputDefinition;
use Peridot\Plugin\Watcher\WatcherPlugin;

describe('WatcherPlugin', function() {
    beforeEach(function() {
        $this->emitter = new EventEmitter();
        $this->watcher = new WatcherPlugin($this->emitter);
    });

    context('when peridot.configure event fires', function() {
        it('should store peridot configuration', function() {
            $configuration = new Configuration();
            $this->emitter->emit('peridot.configure', [$configuration]);
            assert($this->watcher->getConfiguration() === $configuration, "event should set watcher config");
        });
    });

    context('when peridot.start event fires', function() {

        beforeEach(function() {
            $this->definition = new InputDefinition();
            $this->environment = new Environment($this->definition, $this->emitter, []);
            $this->application = new Application($this->environment);
            $this->emitter->emit('peridot.start', [$this->environment, $this->application]);
        });

        it('should add a watch option on the input defintion', function() {
            assert($this->definition->hasOption('watch'), "input definition should have watch option");
        });

        it('should store the peridot application', function() {
            $app = $this->watcher->getApplication();
            assert($app === $this->application, "watcher should have set application");
        });
    });

    context('when runner.start event fires', function() {
        it('should set the path to watch to the configuration path', function() {
            $configuration = new Configuration();
            $configuration->setPath('mypath');
            $this->watcher->setConfiguration($configuration);
            $this->emitter->emit('runner.start', []);
            $expected = $configuration->getPath();
            $actual = $this->watcher->getPath();
            assert($expected == $actual, "expected $expected, got $actual");
        });
    });

    describe("->getEvents()", function() {
        it('should return create and modify events by default', function() {
            $expected = [WatcherPlugin::CREATE_EVENT, WatcherPlugin::MODIFY_EVENT];
            $events = $this->watcher->getEvents();
            assert($expected == $events, "expected create and modify events by default");
        });
    });

    describe('->setEvents()', function() {
        it('should set events to defaults if array is empty', function() {
            $this->watcher->setEvents([]);
            $defaults = $this->watcher->getDefaultEvents();
            $actual = $this->watcher->getEvents();
            assert($defaults == $actual, "empty array should set defaults");
        });

        it('should set events to only supported events', function() {
            $this->watcher->setEvents([WatcherPlugin::CREATE_EVENT, 99, 43, "string"]);
            $actual = $this->watcher->getEvents();
            assert([WatcherPlugin::CREATE_EVENT] == $actual, "should only set valid events");
        });

        context('when all values are filtered out', function() {
            it('should set events to defaults', function() {
                $this->watcher->setEvents([99, "string", 43]);
                $defaults = $this->watcher->getDefaultEvents();
                $actual = $this->watcher->getEvents();
                assert($defaults == $actual, "expected event defaults");
            });
        });
    });
});
