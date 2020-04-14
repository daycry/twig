# Twig

Twig for Codeigniter 4

## Kudos

All kudos for this tool will be sent to https://github.com/daycry

## Installation via composer

Use the package with composer install

	> composer require ercobo/twig

## Manual installation

Download this repo and then enable it by editing **app/Config/Autoload.php** and adding the **ercobo\Twig**
namespace to the **$psr4** array. For example, if you copied it into **app/ThirdParty**:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'ercobo\Twig' => APPPATH .'ThirdParty/twig/src',
];
```

## Configuration

Run command:

	> php spark twig:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Twig.php`.


## Usage Loading Library

```php
$twig = new \ercobo\Twig\Twig();
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
$twig = new \ercobo\Twig\Twig();

$session = \Config\Services::session();
$session->set( array( 'name' => 'ercobo' ) );
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

## How Run Tests

```php

vendor\bin\phpunit vendor\ercobo\twig\tests

```
