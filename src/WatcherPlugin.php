<?php
namespace Peridot\Plugin\Watcher;

use Evenement\EventEmitterInterface;
use Peridot\Configuration;
use Peridot\Console\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class WatcherPlugin
{
    /**
     * @var EventEmitterInterface
     */
    protected $emitter;

    /**
     * @var array
     */
    protected $events;

    /**
     * @var WatcherInterface
     */
    protected $watcher;

    /**
     * @var array
     */
    protected $trackedPaths = [];

    /**
     * @var array
     */
    protected $criteria = ['/\.php$/'];

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
     * @param Configuration $config
     */
    public function onPeridotConfigure(Configuration $config)
    {
        $this->track($config->getPath());
    }

    /**
     * @param Environment $environment
     */
    public function onPeridotStart(Environment $environment)
    {
        $definition = $environment->getDefinition();
        $definition->option('watch', null, InputOption::VALUE_NONE, "watch tests for changes and re-run them");
    }

    /**
     * @param $exitCode
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function onPeridotEnd($exitCode, InputInterface $input, OutputInterface $output)
    {
        $this->emitter->removeListener('peridot.end', [$this, 'onPeridotEnd']);

        if (!$input->getOption('watch')) {
            return;
        }

        $watcher = $this->getWatcher();
        $watcher->setInput($input);
        $watcher->setOutput($output);
        $watcher->setCriteria($this->getFileCriteria());

        $this->watch($watcher);
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
     * Watch file events and rerun peridot when changes are received
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function watch(WatcherInterface $watcher)
    {
        $events = $this->getEvents();
        $watcher->watch($this->getTrackedPaths(), $events, [$this, 'runPeridot']);
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
     * Run an isolated process of Peridot and feed results to the output interface.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function runPeridot(InputInterface $input, OutputInterface $output)
    {
        global $argv;
        $command = $this->joinCommand($argv);
        $process = new Process($command);
        $process->run(function($type, $buffer) use ($output) {
            $buffer = preg_replace('/\[([\d]{1,2})m/', "\033[$1m", $buffer);
            $output->write($buffer);
        });
    }

    /**
     * Join an array of arg parts into a command.
     *
     * @param array $parts
     * @return string
     */
    public function joinCommand(array $parts)
    {
        $command = 'php ' . implode(' ', $parts);
        $stripped = str_replace('--watch', '', $command);
        return trim($stripped);
    }

    /**
     * Return all tracked paths
     *
     * @return array
     */
    public function getTrackedPaths()
    {
        return $this->trackedPaths;
    }

    /**
     * Return an array of regular expressions used for
     * matching a file type
     *
     * @return array
     */
    public function getFileCriteria()
    {
        return $this->criteria;
    }

    /**
     * @param $pattern
     * @return $this
     */
    public function addFileCriteria($pattern)
    {
        $this->criteria[] = $pattern;
        return $this;
    }

    /**
     * Listen for Peridot events
     */
    private function listen()
    {
        $this->emitter->on('peridot.configure', [$this, 'onPeridotConfigure']);
        $this->emitter->on('peridot.start', [$this, 'onPeridotStart']);
        $this->emitter->on('peridot.end', [$this, 'onPeridotEnd']);
    }
} 
