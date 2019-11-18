# Laravel Scout Elasticsearch Driver

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

This package makes is the [Elasticsearch](https://www.elastic.co/products/elasticsearch) driver for Laravel Scout.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [Credits](#credits)
- [License](#license)

## Installation

You can install the package via composer:

``` bash
composer require tamayo/laravel-scout-elastic
```

You must add the Scout service provider and the package service provider in your app.php config:

```php
// config/app.php
'providers' => [
    ...
    Laravel\Scout\ScoutServiceProvider::class,
    ...
    ScoutEngines\Elasticsearch\ElasticsearchProvider::class,
],
```

### Setting up Elasticsearch configuration
You must have a Elasticsearch server up and running with the index you want to use created

If you need help with this please refer to the [Elasticsearch documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)

After you've published the Laravel Scout package configuration:

```php
// config/scout.php
// Set your driver to elasticsearch
    'driver' => env('SCOUT_DRIVER', 'elasticsearch'),

...
    'elasticsearch' => [
        'index' => env('ELASTICSEARCH_INDEX', 'laravel'),
        'hosts' => [
            env('ELASTICSEARCH_HOST', 'http://localhost'),
        ],
//set If one index per model (use searchableAs Methode) (defaut false)
        'perModelIndex' => true,
    ],
...
```

## Usage

### Custom Query
On Model you can specify custom query by : 

```php

 public function customScoutQuerySearching($terms): array
    {
        return [
            'query' => [
                'multi_match' => [
                    'query' => (string) ($terms),
                    'fields' => [
                        '*'
                    ],
                    'fuzziness' => 'AUTO',
                    'type' => 'most_fields'
                ]
            ],
        ];
    }
```

Now you can use Laravel Scout as described in the [official documentation](https://laravel.com/docs/5.3/scout)
## Credits

- [Erick Tamayo](https://github.com/ericktamayo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT).
