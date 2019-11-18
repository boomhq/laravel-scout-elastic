<?php

namespace ScoutEngines\Elasticsearch;

use Elasticsearch\ClientBuilder as ElasticBuilder;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ElasticsearchProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        app(EngineManager::class)->extend('elasticsearch', function ($app) {
            return new ElasticsearchEngine(
                ElasticBuilder::create()
                ->setHosts(config('scout.elasticsearch.hosts'))
                ->build(),
                config('scout.elasticsearch.index'),
                config('scout.elasticsearch.perModelIndex', false)
            );
        });
    }
}
