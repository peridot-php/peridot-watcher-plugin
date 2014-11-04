<?php
namespace Peridot\Plugin\Watcher;

use Evenement\EventEmitterInterface;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Console\Environment;
use Peridot\Runner\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WatcherPlugin
{
    /**
     * @var EventEmitterInterface
     */
    protected $emitter;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $events;

    /**
     * @var Application
     */
    protected $application;

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var WatcherInterface
     */
    protected $watcher;

    /**
     * @var array
     */
    protected $trackedPaths = [];

    /**
     * @param EventEmitterInterface $emitter
     */
    public function __construct(EventEmitterInterface $emitter)
    {
        $this->emitter = $emitter;
        $this->events = $this->getDefaultEvents();
        $this->listen();
    }

    /**
     * @param Configuration $configuration
     * @return $this
     */
    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
        return $this;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param Environment $environment
     */
    public function onPeridotStart(Environment $environment, Application $application)
    {
        $definition = $environment->getDefinition();
        $definition->option("--watch", null, InputOption::VALUE_NONE, "Watch files for changes and rerun tests");
        $this->environment = $environment;
        $this->application = $application;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function refreshPath()
    {
        $this->path = $this->configuration->getPath();
    }

    /**
     * Return an array of default events to watch for
     *
     * @return array
     */
    public function getDefaultEvents()
    {
        return [WatcherInterface::MODIFY_EVENT];
    }

    /**
     * Return the array of events to watch for
     *
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param array $events
     */
    public function setEvents(array $events)
    {
        $filtered = array_filter($events, [$this, 'supportsEvent']);
        $this->events = array_values($filtered);
        if (empty($this->events)) {
            $this->events = $this->getDefaultEvents();
        }
        return $this;
    }

    /**
     * See if the supplied event identifier is supported by Watcher
     *
     * @param $eventId
     */
    public function supportsEvent($eventId)
    {
        $supportedEvents = [WatcherInterface::CREATE_EVENT, WatcherInterface::MODIFY_EVENT, WatcherInterface::DELETE_EVENT, WatcherInterface::ALL_EVENT];
        return array_search($eventId, $supportedEvents, true) !== false;
    }

    /**
     * @return Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @return WatcherInterface
     */
    public function getWatcher()
    {
        if (is_null($this->watcher)) {
            $this->watcher = new LurkerWatcher();
        }
        return $this->watcher;
    }

    /**
     * @param WatcherInterface $watcher
     */
    public function setWatcher(WatcherInterface $watcher)
    {
        $this->watcher = $watcher;
        return $this;
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param $exitCode
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function onPeridotEnd($exitCode, InputInterface $input, OutputInterface $output)
    {
        if (! $input->getOption('watch')) {
            return;
        }

        $watcher = $this->getWatcher();
        $watcher->setInput($input);
        $watcher->setOutput($output);

        $this->watch($watcher);
    }

    /**
     * Runs the Peridot application after clearing the event emitter
     * and resetting the root suite.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function runPeridot(InputInterface $input, OutputInterface $output)
    {
        $this->environment->getEventEmitter()->removeAllListeners();
        Context::getInstance()->getCurrentSuite()->setTests([]);
        $this->listen();
        $this->application->run($input, $output);
    }

    /**
     * Watch file events and rerun peridot when changes are received
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function watch(WatcherInterface $watcher)
    {
        $events = $this->getEvents();
        $watcher->watch($this->configuration->getPath(), $events, [$this, 'runPeridot']);
    }

    /**
     * Track an additional path
     *
     * @param $path
     * @return $this
     */
    public function track($path)
    {
        $this->trackedPaths[] = $path;
        return $this;
    }

    /**
     * Return all tracked paths
     *
     * @return array
     */
    public function getTrackedPaths()
    {
        return array_merge([$this->getPath()], $this->trackedPaths);
    }

    /**
     * Listen for Peridot events
     */
    protected function listen()
    {
        $this->emitter->on('peridot.configure', [$this, 'setConfiguration']);
        $this->emitter->on('peridot.start', [$this, 'onPeridotStart']);
        $this->emitter->on('runner.start', [$this, 'refreshPath']);
        $this->emitter->on('peridot.end', [$this, 'onPeridotEnd']);
    }
} 
