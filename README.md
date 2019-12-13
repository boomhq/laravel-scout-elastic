# Laravel Scout Elasticsearch Driver

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Status build](https://api.travis-ci.org/boomhq/laravel-scout-elastic.svg?branch=master)]()

This package makes is the [Elasticsearch](https://www.elastic.co/products/elasticsearch) driver for Laravel Scout.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [Credits](#credits)
- [License](#license)

## Installation

You can install the package via composer:

``` bash
composer require boomhq/laravel-scout-elastic
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

### Custom Index
If you want to push a specific index you can declare ```elasticsearchIndex() ``` On your Model Before first Import (otherwise you need to delete index and reimport for create them) :

```php
    public function elasticsearchIndex()
    {
        return  [
                  "settings" => [
                        "analysis" => [
                           "analyzer" => [
                              "default" => [
                                 "tokenizer" => "my_tokenizer", 
                                 "filter" => [
                                    "lowercase" 
                                 ] 
                              ], 
                              "default_search" => [
                                       "tokenizer" => "my_tokenizer" 
                                    ] 
                           ], 
                           "tokenizer" => [
                                          "my_tokenizer" => [
                                             "type" => "edge_ngram", 
                                             "min_gram" => 3, 
                                             "max_gram" => 20, 
                                             "token_chars" => [
                                                "letter" 
                                             ], 
                                             "filter" => [
                                                   "lowercase", 
                                                   "asciifolding" 
                                                ] 
                                          ] 
                                       ] 
                        ], 
                        "max_ngram_diff" => "20" 
                     ] 
               ]; 
                
    }
```
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
