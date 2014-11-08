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
        it('should add the test path to tracked paths', function() {
            $configuration = new Configuration();
            $configuration->setPath('/path/to/thing');
            $this->emitter->emit('peridot.configure', [$configuration]);
            assert($this->watcher->getTrackedPaths()[0] == $configuration->getPath(), "should track config path");
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

        it('should add a watch option on the input definition', function() {
            assert($this->definition->hasOption('watch'), "input definition should have watch option");
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

        it('should set input, ouput, and criteria on the watcher', function() {
            $this->watcher->onPeridotStart($this->environment, $this->application);
            $input = new ArrayInput(['--watch' => 1], $this->environment->getDefinition());
            $output = new BufferedOutput();
            $this->watcher->onPeridotEnd(0, $input, $output);
            assert($this->watcherInterface->input === $input, "should have set input");
            assert($this->watcherInterface->output === $output, "should have set output");
            assert($this->watcherInterface->criteria == ['/\.php$/'], "should have set criteria");
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
            $expected = ['src', 'specs'];
            assert($expected == $this->watcher->getTrackedPaths(), "expected all tracked paths");
        });
    });

    describe('->joinCommand()', function() {
        it('should join an arg array into a php command', function() {
            $command = $this->watcher->joinCommand(['bin/peridot', 'specs/']);
            assert($command == 'php bin/peridot specs/', "should join array into php command");
        });

        it('should strip the watch option if present', function() {
            $command = $this->watcher->joinCommand(['bin/peridot', 'specs/', '--watch']);
            $expected = 'php bin/peridot specs/';
            assert($command == $expected, "expected $expected, got $command");
        });
    });

    describe('->getFileCriteria()', function() {
        it('should return a pattern for php files by default', function() {
            $pattern = $this->watcher->getFileCriteria()[0];
            assert(preg_match($pattern, '/path/file.php'), 'should match php file');
        });
    });

    describe('->addFileCriteria()', function() {
        it('should add a pattern to file criteria', function() {
            $this->watcher->addFileCriteria('/\.js$/');
            $pattern = $this->watcher->getFileCriteria()[1];
            assert(preg_match($pattern, '/path/to/file.js'), "should match js file");
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

    public $criteria = [];

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

    public function setCriteria(array $criteria)
    {
        $this->criteria = $criteria;
    }
}
