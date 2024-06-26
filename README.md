[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Twig, the flexible, fast, and secure template language for Codeigniter 4

Twig is a template language for PHP.

Twig uses a syntax similar to the Django and Jinja template languages which inspired the Twig runtime environment.

[![Build Status](https://github.com/daycry/twig/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/daycry/twig/actions/workflows/phpunit.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/twig/badge.svg?branch=master)](https://coveralls.io/github/daycry/twig?branch=master)
[![Downloads](https://poser.pugx.org/daycry/twig/downloads)](https://packagist.org/packages/daycry/twig)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/twig)](https://packagist.org/packages/daycry/twig)
[![GitHub stars](https://img.shields.io/github/stars/daycry/twig)](https://packagist.org/packages/daycry/twig)
[![GitHub license](https://img.shields.io/github/license/daycry/twig)](https://github.com/daycry/twig/blob/master/LICENSE)

## Installation via composer

Use the package with composer install

	> composer require daycry/twig


## Configuration

Run command:

	> php spark twig:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Twig.php`.


## Usage Loading Library

```php
$twig = new \Daycry\Twig\Twig();
$twig->display( 'file.html', [] );

```

## Usage as a Service

```php
$twig = \Config\Services::twig();
$twig->display( 'file.html', [] );

```

## Usage as a Helper

In your BaseController - $helpers array, add an element with your helper filename.

```php
protected $helpers = [ 'twig_helper' ];

```

And then you can use the helper

```php

$twig = twig_instance();
$twig->display( 'file.html', [] );

```

## Add Globals

```php
$twig = new \Daycry\Twig\Twig();

$session = \Config\Services::session();
$session->set( array( 'name' => 'Daycry' ) );
$twig->addGlobal( 'session', $session );
$twig->display( 'file.html', [] );

```

## File Example

```php

<!DOCTYPE html>
<html lang="es">  
    <head>    
        <title>Example</title>    
        <meta charset="UTF-8">
        <meta name="title" content="Example">
        <meta name="description" content="Example">   
    </head>  
    <body>
        <h1>Hi {{ name }}</h1>
        {{ dump( session.get( 'name' ) ) }}
    </body>  
</html>

```

## Collector

If you want to debug the data in twig templates.

Toolbar.php file
```php

    use Daycry\Twig\Debug\Toolbar\Collectors\Twig;
    
    public array $collectors = [
        ...
        //Views::class,
        Twig::class
    ];

```
