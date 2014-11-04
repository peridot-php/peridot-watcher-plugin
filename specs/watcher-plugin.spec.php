<?php
use Evenement\EventEmitter;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Console\Environment;
use Peridot\Console\InputDefinition;
use Peridot\Plugin\Watcher\LurkerWatcher;
use Peridot\Plugin\Watcher\WatcherInterface;
use Peridot\Plugin\Watcher\WatcherPlugin;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

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

    $setupEnvironment = function() {
        $this->definition = new InputDefinition();
        $this->environment = new Environment($this->definition, $this->emitter, []);
    };

    context('when peridot.start event fires', function() use ($setupEnvironment) {

        beforeEach($setupEnvironment);

        beforeEach(function() {
            $this->application = new Application($this->environment);
            $this->emitter->emit('peridot.start', [$this->environment, $this->application]);
        });

        it('should add a watch option on the input defintion', function() {
            assert($this->definition->hasOption('watch'), "input definition should have watch option");
        });

        it('should store the peridot environment', function() {
            $env = $this->watcher->getEnvironment();
            assert($env === $this->environment, "watcher should have set environment");
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
            $expected = [WatcherInterface::MODIFY_EVENT];
            $events = $this->watcher->getEvents();
            assert($expected == $events, "expected create and modify events by default");
        });
    });

    $setupApplication = function() {
        $this->application = new StubApplication($this->environment);
        $this->input = new ArrayInput([]);
        $this->output = new BufferedOutput();
    };

    describe('->runPeridot()', function() use ($setupEnvironment, $setupApplication) {

        beforeEach($setupEnvironment);

        beforeEach($setupApplication);

        it('should clear the event emitter', function() {
            $this->emitter->on('ham', function() {  });
            $this->watcher->onPeridotStart($this->environment, $this->application);
            $this->watcher->runPeridot($this->input, $this->output);
            $listeners = $this->emitter->listeners('ham');
            assert(empty($listeners), "listeners should be cleared");
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
            $this->watcher->setEvents([WatcherInterface::CREATE_EVENT, 99, 43, "string"]);
            $actual = $this->watcher->getEvents();
            assert([WatcherInterface::CREATE_EVENT] == $actual, "should only set valid events");
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

    describe('->watch()', function() use ($setupEnvironment, $setupApplication) {
        beforeEach($setupEnvironment);

        beforeEach($setupApplication);

        it('should execute the watcher with file events', function() {
            $this->emitter->emit('peridot.configure', [new Configuration()]);
            $watcherInterface = new StubWatcher();
            $this->watcher->watch($watcherInterface);
            assert($watcherInterface->watched, "watcher interface should have begun watch");
        });
    });

    describe('->getWatcher()', function() {
        it('should return LurkerWatcher by default', function() {
            $watcher = $this->watcher->getWatcher();
            assert($watcher instanceof LurkerWatcher, "LurkerWatcher should be default WatcherInterface");
        });
    });

    describe('->onPeridotEnd()', function() use ($setupEnvironment, $setupApplication) {

        beforeEach(function() {
            $this->emitter = new EventEmitter();
            $this->watcher = new WatcherPlugin($this->emitter);
            $this->watcherInterface = new StubWatcher();
            $this->watcher->setWatcher($this->watcherInterface);

            $this->definition = new InputDefinition();
            $this->environment = new Environment($this->definition, $this->emitter, []);
            $this->application = new StubApplication($this->environment);

            $this->configuration = new Configuration();
            $this->configuration->setPath('path');
            $this->emitter->emit('peridot.configure', [$this->configuration]);
        });

        it('should set input and ouput on the watcher', function() {
            $this->watcher->onPeridotStart($this->environment, $this->application);
            $input = new ArrayInput(['--watch' => 1], $this->environment->getDefinition());
            $output = new BufferedOutput();
            $this->watcher->onPeridotEnd(0, $input, $output);
            assert($this->watcherInterface->input === $input, "should have set input");
            assert($this->watcherInterface->output === $output, "should have set output");
        });

        it('should remove the peridot.end listener', function() {
            $this->watcher->onPeridotStart($this->environment, $this->application);
            $this->watcher->onPeridotEnd(0, new ArrayInput(['--watch' => 1], $this->environment->getDefinition()), new NullOutput());
            $listeners = $this->emitter->listeners('peridot.end');
            assert(empty($listeners), "should have removed peridot.end");
        });
    });

    describe('->track()', function() {
        it('should store additional paths to track', function() {
            $this->watcher->track('src');
            $this->watcher->track('specs');
            $this->watcher->setConfiguration($this->configuration);
            $this->watcher->refreshPath();
            $expected = [$this->configuration->getPath(), 'src', 'specs'];
            assert($expected == $this->watcher->getTrackedPaths(), "expected all tracked paths");
        });
    });
});

class StubApplication extends Application
{
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $output->write("ran");
        return $output;
    }

}

class StubWatcher implements WatcherInterface
{
    public $input;

    public $output;

    public $watched = false;

    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function watch($path, array $events, callable $listener)
    {
        $this->watched = true;
    }
}
