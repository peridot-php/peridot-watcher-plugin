Peridot Watcher Plugin
======================

[![Build Status](https://travis-ci.org/peridot-php/peridot-watcher-plugin.png)](https://travis-ci.org/peridot-php/peridot-watcher-plugin) [![HHVM Status](http://hhvm.h4cc.de/badge/peridot-php/peridot-watcher-plugin.svg)](http://hhvm.h4cc.de/package/peridot-php/peridot-watcher-plugin)

Watch for changes in your Peridot tests and re run them when a change occurs.

![Peridot watcher](https://raw.github.com/peridot-php/peridot-watcher-plugin/master/watcher.gif "Peridot watcher in action")

##Usage

We recommend installing this plugin to your project via composer:

```
$ composer require --dev peridot-php/peridot-watcher-plugin:~1.3
```

You can register the plugin via your [peridot.php](http://peridot-php.github.io/#plugins) file.

```php
<?php
use Evenement\EventEmitterInterface;
use Peridot\Plugin\Watcher\WatcherPlugin;

return function(EventEmitterInterface $emitter) {
    $watcher = new WatcherPlugin($emitter);
};
```

Registering the plugin will make a `--watch` option available to the Peridot application:

```
vendor/bin/peridot specs/ --watch
```

##File events
By default, the watcher plugin will look for a "file changed" event, but you can configure the plugin to listen for the following events:

* WatcherInterface::CREATE_EVENT
* WatcherInterface::MODIFY_EVENT
* WatcherInterface::DELETE_EVENT
* WatcherInterface::ALL_EVENT

```php
<?php
use Evenement\EventEmitterInterface;
use Peridot\Plugin\Watcher\WatcherPlugin;
use Peridot\Plugin\Watcher\WatcherInterface;

return function(EventEmitterInterface $emitter) {
    $watcher = new WatcherPlugin($emitter);
    $watcher->setEvents([WatcherInterface::CREATE_EVENT, WatcherInterface::MODIFY_EVENT]);
};
```

##Tracking additional paths
By default, the watcher plugin just monitors the test path. If you want to track additional paths, you can do so in your peridot.php file:

```php
<?php
use Evenement\EventEmitterInterface;
use Peridot\Plugin\Watcher\WatcherPlugin;

return function(EventEmitterInterface $emitter) {
    $watcher = new WatcherPlugin($emitter);
    $watcher->track(__DIR__ . '/src');
};
```

##File criteria
The watcher will look for changes in files ending in .php by default. If you want to track additional
file types, you can add criteria as regular expressions in your peridot.php file:

```php
<?php
use Evenement\EventEmitterInterface;
use Peridot\Plugin\Watcher\WatcherPlugin;

return function(EventEmitterInterface $emitter) {
    $watcher = new WatcherPlugin($emitter);
    $watcher->track(__DIR__ . '/src');
    $watcher->addFileCriteria('/\.js$/');
};
```

Using the above, you can re run your tests when the source file changes. Since the Peridot watcher re runs your tests
in a sub-process, it will actually detect new changes in your source.

##Example specs

Feel free to play around with the example spec using the watch option:

```
$ vendor/bin/peridot -c example/peridot.php example/modifyme.spec.php --watch
```


##Running plugin tests

```
$ vendor/bin/peridot specs/
```

##Inotify support

The watcher plugin will leverage the [Inotify extension](http://php.net/manual/en/book.inotify.php) if it is available, otherwise it will use a recursive directory strategy to watch for changes.

##Note on IDEs

Some IDEs might choke on ANSI sequences being returned from the watcher process. PHPStorm does not render colors
from sub process output, but most terminals will. If using the terminal from your IDE is a must, you may want to run your
tests using the `--no-colors` option.
