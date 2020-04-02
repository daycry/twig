# Twig

Twig for Codeigniter 4

## Installation

Use the package with composer install

```bash
composer require daycry/twig
```

## Usage

```php
$twig = new \Daycry\Twig\Twig();
$twig->display( 'file.html', [] );

```

## Add Globals

```php
$twig = new \Daycry\Twig\Twig();

$session = \Config\Services::session();
$session->set( array( 'name' => 'Daycry' ) );
$twig->addGlobal( 'session', $session );


```