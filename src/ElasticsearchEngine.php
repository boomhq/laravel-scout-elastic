<?php

namespace ScoutEngines\Elasticsearch;

use Elasticsearch\Client as Elastic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ElasticsearchEngine extends Engine
{
    /**
     * Index where the models will be saved.
     *
     * @var string
     */
    protected $index;

    /**
     * If the index should be set per model.
     *
     * @var bool
     */
    protected $perModelIndex;
    /**
     * Elastic where the instance of Elastic|\Elasticsearch\Client is stored.
     *
     * @var object
     */
    protected $elastic;

    /**
     * Create a new engine instance.
     *
     * @param  Elastic  $elastic
     * @return void
     */
    public function __construct(Elastic $elastic, $index, $perModelIndex = false)
    {
        $this->elastic = $elastic;
        $this->index = $index;
        $this->perModelIndex = $perModelIndex;
    }

    /**
     * Update the given model in the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function update($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'update' => [
                    '_id' => $model->getKey(),
                    '_index' => $this->getIndex($model),
                    '_type' => $model->searchableAs(),
                ],
            ];
            $params['body'][] = [
                'doc' => $model->toSearchableArray(),
                'doc_as_upsert' => true,
            ];
        });

        $this->elastic->bulk($params);
    }

    /**
     * Retrieves the index for the given model.
     *
     * @param  Model  $model
     * @return string
     */
    protected function getIndex($model)
    {
        return ($this->perModelIndex ? $model->searchableAs() : $this->index);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $this->getIndex($model),
                    '_type' => $model->searchableAs(),
                ],
            ];
        });

        $this->elastic->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        if (method_exists($builder->model, 'customScoutQuerySearching')) {
            $queryBody = $builder->model->customScoutQuerySearching($builder->query);
        } else {
            $queryBody = [
                'query' => [
                    'multi_match' => [
                        'query' => (string) ($builder->query),
                        "fields" => [
                            "*",
                        ],
                        "fuzziness" => "AUTO",
                        "type" => "most_fields",
                    ],
                ],
                'track_scores' => true,

            ];
        }
        $params = [
            'index' => $builder->index ?: $this->getIndex($builder->model),
            'type' => $builder->index ?: $builder->model->searchableAs(),
            'body' => $queryBody,
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {

            $params['body']['query']['bool'] = array_merge($params['body']['query']['bool'],
                ['filter' => array_reduce($options['numericFilters'], 'array_merge', [])]);
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elastic,
                $builder->query,
                $params
            );
        }

        //dd($params);

        return $this->elastic->search($params);
    }

    /**
     * Generates the sort if theres any.
     *
     * @param  Builder  $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }

            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        $result['nbPages'] = $result['hits']['total']['value'] / $perPage;

        return $result;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  Builder  $builder
     * @param  mixed  $results
     * @param  Model  $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total']['value'] === 0) {
            return $model->newCollection();
        }

        $keys = collect($results['hits']['hits'])->pluck('_id')->values()->all();

        return $model->getScoutModelsByIds(
            $builder,
            $keys
        )->filter(function ($model) use ($keys) {
            return in_array($model->getScoutKey(), $keys);
        })->sortBy(function ($model) use ($keys) {
            return array_search($model->getScoutKey(), $keys);
        });
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  Model  $model
     * @return void
     */
    public function flush($model)
    {
        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->unsearchable();
    }
}
