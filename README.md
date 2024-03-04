# Introduction
This library is a fork of daycry/twig, published by Mr. daycry, with a few added functionalities. We would like to express our gratitude to Mr. daycry. The added features are as follows:

- Added Toolbar Collectors to enable the function of the View column in the Toolbar.

## Twig, the flexible, fast, and secure template language for Codeigniter 4

Twig is a template language for PHP.

Twig uses a syntax similar to the Django and Jinja template languages which inspired the Twig runtime environment.

[![Build Status](https://github.com/kaleidpixel/codeigniter4-twig/workflows/PHP%20Tests/badge.svg)](https://github.com/kaleidpixel/codeigniter4-twig/actions?query=workflow%3A%22PHP+Tests%22)
[![Coverage Status](https://coveralls.io/repos/github/kaleidpixel/codeigniter4-twig/badge.svg?branch=master)](https://coveralls.io/github/kaleidpixel/codeigniter4-twig?branch=master)
[![Downloads](https://poser.pugx.org/kaleidpixel/codeigniter4-twig/downloads)](https://packagist.org/packages/kaleidpixel/codeigniter4-twig)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/kaleidpixel/codeigniter4-twig)](https://packagist.org/packages/kaleidpixel/codeigniter4-twig)
[![GitHub stars](https://img.shields.io/github/stars/kaleidpixel/codeigniter4-twig)](https://packagist.org/packages/kaleidpixel/codeigniter4-twig)
[![GitHub license](https://img.shields.io/github/license/kaleidpixel/codeigniter4-twig)](https://github.com/kaleidpixel/codeigniter4-twig/blob/master/LICENSE)

## Installation via composer

Use the package with composer install

	> composer require kaleidpixel/codeigniter4-twig

## Manual installation

Download this repo and then enable it by editing **app/Config/Autoload.php** and adding the **KaleidPixel\Codeigniter4Twig**
namespace to the **$psr4** array. For example, if you copied it into **app/ThirdParty**:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'KaleidPixel\Codeigniter4Twig' => APPPATH .'ThirdParty/twig/src',
];
```

## Configuration

Run command:

	> php spark twig:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Twig.php`.


## Usage Loading Library

```php
$twig = new \KaleidPixel\Codeigniter4Twig\Twig();
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
$twig = new \KaleidPixel\Codeigniter4Twig\Twig();

$session = \Config\Services::session();
$session->set( array( 'name' => 'Daycry' ) );
$twig->addGlobal( 'session', $session );
$twig->display( 'file.html', [] );

```

## File Example

```html
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

## How Run Tests

```bash
cd vendor\KaleidPixel\Codeigniter4Twig\
composer install
vendor\bin\phpunit

```
