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
     * @param $path
     * @param array $events
     * @param callable $listener
     * @return mixed|void
     */
    public function watch($path, array $events, callable $listener)
    {
        $fileEvents = $this->getEventMap();
        $watcher = new ResourceWatcher();
        foreach ($events as $event) {
            $watcher->track('peridot.tests', $path, $fileEvents[$event]);
        }
        $watcher->addListener('peridot.tests', function () use ($listener) {
            $listener($this->input, $this->output);
        });
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
}
