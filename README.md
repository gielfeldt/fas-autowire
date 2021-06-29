[![Build Status](https://github.com/gielfeldt/fas-autowire/actions/workflows/test.yml/badge.svg)][4]
![Test Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/gielfeldt/440b1c8beb1428b717ed23ee67310304/raw/fas-autowire__main.json)

[![Latest Stable Version](https://poser.pugx.org/fas/autowire/v/stable.svg)][1]
[![Latest Unstable Version](https://poser.pugx.org/fas/autowire/v/unstable.svg)][2]
[![License](https://poser.pugx.org/fas/autowire/license.svg)][3]
![Total Downloads](https://poser.pugx.org/fas/autowire/downloads.svg)


# Installation

```bash
composer require fas/autowire
```

# Introduction

This library introduces autowiring capabilities using any PSR-11 container.
A very simple container is also provided with this library.

# Usage

## Create object

```php

require __DIR__ . '/../vendor/autoload.php';

use Fas/Autowire/Autowire;

$autowire = new Autowire();

// Autowire all constructor arguments
$myObject = $autowire->new(MyClass::class);

// Override some constructor arguments
$myObject = $autowire->new(MyClass::class, ['some_argument' => 'test-value']);

// Call a method with no arguments (autowire all arguments)
$autowire->call(function (DateTime $datetime, MyClass $myObject) {
    // do stuff
});

// Override argument
$autowire->call(function (DateTime $datetime, MyClass $myObject) {
    // do stuff
}, ['datetime' => new DateTime('2021-07-01 12:34:56')]);

// Any callable will do
// Plain function
$upperCased  = $autowire->call('strtoupper', ['str' => 'something-in-lower-case']);

// Static method
$datetime = $autowire->call([DateTime::class, 'createFromFormat'], [
    'format' => 'Y-m-d H:i:s',
    'time' => '2021-01-02 12:34:56',
    'object' => new DateTimeZone('Europe/Copenhagen'),
]);

// Instance method
$autowire->call([new DateTime, 'setTime'], ['hour' => 0, 'minute' => 1, 'second' => 2, 'microseconds' => 123]);

// Invokable class
$autowire->call($myInvokableClass, ['my_param' => 'test']);

```


Using Autowiring for any psr-11 container:

```php

$container = new MyPsrContainer();
$autowire = new Autowire($container);

```

Using the fas/autowire container

```php

$container = new Container();
$container->set(LoggerInterface::class, NullLogger::class);

$autowire = new Autowire($container);
$autowire->call(function (LoggerInterface $logger) {
    $logger->info("Logging using whatever logger is defined for LoggerInterface in the container");
});

```

[1]:  https://packagist.org/packages/fas/autowire
[2]:  https://packagist.org/packages/fas/autowire#dev-main
[3]:  https://github.com/gielfeldt/fas-autowire/blob/main/LICENSE.md
[4]:  https://github.com/gielfeldt/fas-autowire/actions/workflows/test.yml
