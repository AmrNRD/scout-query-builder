<?php

namespace Yource\ScoutQueryBuilder\Filters;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Builder;

class FiltersExact implements Filter
{
    protected $relationConstraints = [];

    public function __invoke(Builder $query, $value, string $property): Builder
    {
        if ($this->isNestedProperty($query, $property)) {
            return $this->withRelationConstraint($query, $value, $property);
        }

        if (is_array($value)) {
            return $query->whereIn($property, $value);
        }

        return $query->where($property, '=', $value);
    }

    protected function isNestedProperty(Builder $query, string $property): bool
    {
        if (! Str::contains($property, '.')) {
            return false;
        }

        if (in_array($property, $this->relationConstraints, true)) {
            return false;
        }

        return $this->getMappingTypeForProperty($query, $property) === 'nested';
    }

    protected function getMappingTypeForProperty(Builder $query, string $property): bool
    {
        $properties = explode('.', $property);

        $mapping = $query->model->getMapping();
        foreach ($properties as $property) {
            $mapping = $mapping['properties'][$property];
        }

        return $mapping['type'];
    }

    protected function withRelationConstraint(Builder $query, $value, string $property): Builder
    {
        [$relation, $property] = collect(explode('.', $property))
            ->pipe(function (Collection $parts) {
                return [
                    $parts->except(count($parts) - 1)->map([Str::class, 'camel'])->implode('.'),
                    $parts->last(),
                ];
            });

        return $query->whereHas($relation, function (Builder $query) use ($value, $relation, $property) {
            $this->relationConstraints[] = $property = $query->getModel()->getTable() . '.' . $property;

            $this->__invoke($query, $value, $property);
        });
    }
}
