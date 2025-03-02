<?php
namespace Muradsoft\Filterable\Trait;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait Filterable
{
    function scopeFilter(Builder $builder): Builder
    {
        $filter_fields = $this->getFilterFields();
        return $this->applyFilter($builder, $filter_fields);
    }

    protected abstract function getFilterFields(): array;

    protected function applyFilter(Builder $query, array $filter_fields, array|null $filter_values = []): Builder
    {
        return $this->applyFilterToQuery($query, $filter_fields, $filter_values);
    }

    function applyFilterToQuery(Builder $query, array $filterFields, array|null $filterValues = []): Builder
    {
        $filterValues = $filterValues ?: request()->input();
        foreach ($filterFields as $filter) {
            if (is_string($filter)) {
                $filter = [
                    'name' => $filter,
                ];
            }
            $default = [
                'cond' => '=',
                'field' => $filter['name'],
            ];
            $filter += $default;

            $value = $filter['value'] ?? Arr::get($filterValues, $filter['name']);
            $filter['cond'] = strtolower($filter['cond']);

            if (!is_null($value)) {
                if ($filter['cond'] === 'like') {
                    $value = "%$value%";
                } elseif ($filter['cond'] === 'like%') {
                    $value = "$value%";
                } elseif ($filter['cond'] === '%like') {
                    $value = "%$value";
                }

                if (in_array($filter['cond'], ['like%', '%like'])) {
                    $filter['cond'] = 'like';
                }

                $method = $filter['method'] ?? 'where';

                if (isset($filter['relation'])) {
                    $query->whereHas($filter['relation']['name'], function ($q) use ($filter, $value, $method) {
                        $q->{$method}($filter['field'], $filter['cond'], $value);
                    });
                } elseif (isset($filter['query']) && is_callable($filter['query'])) {
                    call_user_func($filter['query'], $query, $value, $filterValues);
                } else {
                    $query->{$method}($filter['field'], $filter['cond'], $value);
                }
            }
        }
        return $query;
    }

}
