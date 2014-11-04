<?php
namespace Peridot\Plugin\Watcher;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The WatcherInterface defines an interface for watchers
 * capable of re running a peridot suite
 *
 * @package Peridot\Plugin\Watcher
 */
interface WatcherInterface
{
    const CREATE_EVENT = 0;

    const MODIFY_EVENT = 1;

    const DELETE_EVENT = 2;

    const ALL_EVENT = 3;

    /**
     * Set an input interface for the watcher to re-use.
     *
     * @param InputInterface $input
     * @return mixed
     */
    public function setInput(InputInterface $input);

    /**
     * Set an output interface for the watcher to re-use
     *
     * @param OutputInterface $output
     * @return mixed
     */
    public function setOutput(OutputInterface $output);

    /**
     * Watch the path for changes specified by events, and call the
     * given listener when one of those events fires.
     *
     * @param string|array $path a single path or an array of paths
     * @param array $events
     * @param callable $listener
     * @return mixed
     */
    public function watch($path, array $events, callable $listener);
} 
