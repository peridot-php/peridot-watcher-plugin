<?php
namespace Peridot\Plugin\Watcher;

use Lurker\Event\FilesystemEvent;
use Lurker\ResourceWatcher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LurkerWatcher implements WatcherInterface
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    protected $criteria = [];

    /**
     * {@inheritdoc}
     *
     * @param InputInterface $input
     * @return mixed|void
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * {@inheritdoc}
     *
     * @param OutputInterface $output
     * @return mixed|void
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $criteria
     * @return mixed|void
     */
    public function setCriteria(array $criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * {@inheritdoc}
     *
     * @param $path
     * @param array $events
     * @param callable $listener
     * @return mixed|void
     */
    public function watch($path, array $events, callable $listener)
    {
        $fileEvents = $this->getEventMap();
        $watcher = new ResourceWatcher();
        $paths = is_array($path) ? $path : [$path];
        foreach ($paths as $path) {
            $this->trackPath($path, $events, $listener, $watcher, $fileEvents);
        }
        $watcher->start();
    }

    /**
     * Maps watcher events to FilesystemEvents
     *
     * @return array
     */
    private function getEventMap()
    {
        return [
            WatcherInterface::CREATE_EVENT => FilesystemEvent::CREATE,
            WatcherInterface::MODIFY_EVENT => FilesystemEvent::MODIFY,
            WatcherInterface::DELETE_EVENT => FilesystemEvent::DELETE,
            WatcherInterface::ALL_EVENT => FilesystemEvent::ALL
        ];
    }

    /**
     * @param $path
     * @param array $events
     * @param callable $listener
     * @param $watcher
     * @param $fileEvents
     */
    protected function trackPath($path, array $events, callable $listener, $watcher, $fileEvents)
    {
        foreach ($events as $event) {
            $trackId = "peridot.watcher.$event." . $path;
            $watcher->track($trackId, $path, $fileEvents[$event]);
            $watcher->addListener($trackId, function (FilesystemEvent $e) use ($listener) {
                if ($this->resourceMatchesCriteria($e->getResource())) {
                    $listener($this->input, $this->output);
                }
            });
        }
    }

    /**
     * @param string $resource
     * @return bool
     */
    protected function resourceMatchesCriteria($resource)
    {
        foreach ($this->criteria as $criteria) {
            if (preg_match($criteria, $resource)) {
                return true;
            }
        }
        return false;
    }
}
