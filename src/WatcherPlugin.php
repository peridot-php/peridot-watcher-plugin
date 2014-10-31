<?php
namespace Peridot\Plugin\Watcher;

use Evenement\EventEmitterInterface;
use Lurker\Event\FilesystemEvent;
use Lurker\ResourceWatcher;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Console\Environment;
use Peridot\Runner\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WatcherPlugin
{
    const CREATE_EVENT = 0;

    const MODIFY_EVENT = 1;

    const DELETE_EVENT = 2;

    const ALL_EVENT = 3;

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
        return [WatcherPlugin::CREATE_EVENT, WatcherPlugin::MODIFY_EVENT];
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
        $supportedEvents = [self::CREATE_EVENT, self::MODIFY_EVENT, self::DELETE_EVENT, self::ALL_EVENT];
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

        $fileEvents = $this->getEventMap();
        $events = $this->getEvents();
        $watcher = new ResourceWatcher();
        foreach ($events as $event) {
            $watcher->track('peridot.tests', $this->configuration->getPath(), $fileEvents[$event]);
        }
        $watcher->addListener('peridot.tests', function() use ($input, $output) {
            $this->environment->getEventEmitter()->removeAllListeners();
            Context::getInstance()->getCurrentSuite()->setTests([]);
            $this->listen();
            $this->application->run($input, $output);
        });
        $watcher->start();
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

    /**
     * Maps watcher events to FilesystemEvents
     *
     * @return array
     */
    private function getEventMap()
    {
        return [
            self::CREATE_EVENT => FilesystemEvent::CREATE,
            self::MODIFY_EVENT => FilesystemEvent::MODIFY,
            self::DELETE_EVENT => FilesystemEvent::DELETE,
            self::ALL_EVENT => FilesystemEvent::ALL
        ];
    }
} 
