# php-opentimestamps

PHP 8.4 port of OpenTimestamps, organized as a PSR-4 compliant Composer library.

## Requirements

- PHP >= 8.4
- Composer

## Installation

```bash
composer require opentimestamps/php-opentimestamps
```

## Autoloading

The package uses PSR-4 autoloading:

- Namespace: `OpenTimestamps\\`
- Source directory: `src/`

## Basic usage

```php
<?php

require 'vendor/autoload.php';

use OpenTimestamps\DetachedTimestampFile;
use OpenTimestamps\OpSHA256;

$data = array_values(unpack('C*', "hello world"));
$detached = DetachedTimestampFile::fromBytes(new OpSHA256(), $data);
```

## Project layout

- `src/` - PSR-4 classes (`OpenTimestamps\\*`)
- `composer.json` - package metadata and autoload config

## License

LGPL-3.0
