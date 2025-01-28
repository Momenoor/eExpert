<?php

namespace App\Forms\Components;

use Closure;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CheckboxListTree extends CheckboxList
{
    protected string $view = 'forms.components.checkbox-list-tree';

    protected bool $withChildren = false;

//    public function setUp(): void
//    {
//        parent::setUp();
//        $this->afterStateHydrated(function () {
//            $parents = $this->getOptions();
//            $children = $this->getRelationship()->getModel()->query()->whereNotNull('parent_id')->whereIn('parent_id', array_keys($parents))->get()->groupBy('parent_id')->mapWithKeys(function ($items, $key) {
//                return [$key => $items->pluck($this->getRelationshipTitleAttribute(), 'id')->toArray()];
//            })->sortBy('parent_id');
//            $options = [];
//            foreach ($parents as $id => $parent) {
//                $options[$id] = [
//                    'label' => $parent,
//                    'value' => $id,
//                    'children' => $children[$id] ?? []
//                ];
//            }
//
//            $this->options($options);
//        });
//    }
    public function relationship(string|Closure|null $name = null, string|Closure|null $titleAttribute = null, ?Closure $modifyQueryUsing = null, ?bool $withChildren = false): static
    {
        $this->relationship = $name ?? $this->getName();
        $this->relationshipTitleAttribute = $titleAttribute;
        $this->withChildren = $withChildren ?? $this->getWithChildren();

        $this->options(static function (CheckboxListTree $component) use ($modifyQueryUsing): array {
            $relationship = Relation::noConstraints(fn() => $component->getRelationship());
            $childrenRelationship = Relation::noConstraints(fn() => $component->getRelationship());

// Create a fresh instance for the first query
            $relationshipJoiner = app(RelationshipJoiner::class);
            $relationshipQuery = $relationshipJoiner->prepareQueryForNoConstraints($relationship);

            $childrenRelationshipQuery = $relationshipJoiner->prepareQueryForNoConstraints($childrenRelationship);

            if ($modifyQueryUsing) {
                $relationshipQuery = $component->evaluate($modifyQueryUsing, [
                    'query' => $relationshipQuery,
                ]) ?? $relationshipQuery;
            }

            if ($component->hasOptionLabelFromRecordUsingCallback()) {
                return $relationshipQuery
                    ->get()
                    ->mapWithKeys(static fn(Model $record) => [
                        $record->{Str::afterLast($relationship->getQualifiedRelatedKeyName(), '.')} => $component->getOptionLabelFromRecord($record),
                    ])
                    ->toArray();
            }

            $relationshipTitleAttribute = $component->getRelationshipTitleAttribute();

            if (empty($relationshipQuery->getQuery()->orders)) {
                $relationshipQuery->orderBy($relationshipQuery->qualifyColumn($relationshipTitleAttribute));
            }

            if (str_contains($relationshipTitleAttribute, '->')) {
                if (!str_contains($relationshipTitleAttribute, ' as ')) {
                    $relationshipTitleAttribute .= " as {$relationshipTitleAttribute}";
                }
            } else {
                $relationshipTitleAttribute = $relationshipQuery->qualifyColumn($relationshipTitleAttribute);
            }

            // Fetch options
            $options = $relationshipQuery->whereNull('parent_id')
                ->pluck($relationshipTitleAttribute, $relationship->getQualifiedRelatedKeyName())
                ->toArray();

            // If withChildren is true, load children for each parent
            if ($component->withChildren) {
                $children = $childrenRelationshipQuery
                    ->whereNotNull('parent_id') // Assuming `parent_id` is the field identifying parent-child relationships
                    ->get()
                    ->groupBy('parent_id')
                    ->mapWithKeys(function ($items, $parentId) use ($relationshipTitleAttribute, $relationship) {
                        return [$parentId => $items->pluck(Str::afterLast($relationshipTitleAttribute, '.'), Str::afterLast($relationship->getQualifiedRelatedKeyName(), '.'))->toArray()];
                    })
                    ->toArray();

                // Combine options with children
                foreach ($options as $parentId => $label) {
                    $options[$parentId] = [
                        'label' => $label,
                        'children' => $children[$parentId] ?? [], // Attach children or empty array
                    ];
                }
            }

            return $options;
        });

        $this->loadStateFromRelationshipsUsing(static function (CheckboxListTree $component, ?array $state) use ($modifyQueryUsing): void {
            $relationship = $component->getRelationship();

            if ($modifyQueryUsing) {
                $component->evaluate($modifyQueryUsing, [
                    'query' => $relationship->getQuery(),
                ]);
            }

            /** @var Collection $relatedRecords */
            $relatedRecords = $relationship->getResults();
            $component->state(
            // Cast the related keys to a string, otherwise Livewire does not
            // know how to handle deselection.
            //
            // https://github.com/filamentphp/filament/issues/1111
                $relatedRecords
                    ->pluck($relationship->getRelatedKeyName())
                    ->map(static fn($key): string => strval($key))
                    ->all(),
            );

        });

        $this->saveRelationshipsUsing(static function (CheckboxListTree $component, ?array $state) use ($modifyQueryUsing) {
            $relationship = $component->getRelationship();

            if ($modifyQueryUsing) {
                $component->evaluate($modifyQueryUsing, [
                    'query' => $relationship->getQuery(),
                ]);
            }

            /** @var Collection $relatedRecords */
            $relatedRecords = $relationship->getResults();

            $recordsToDetach = array_diff(
                $relatedRecords
                    ->pluck($relationship->getRelatedKeyName())
                    ->map(static fn($key): string => strval($key))
                    ->all(),
                $state ?? [],
            );

            if (count($recordsToDetach) > 0) {
                $relationship->detach($recordsToDetach);
            }

            $pivotData = $component->getPivotData();

            if ($pivotData === []) {
                $relationship->sync($state ?? [], detaching: false);

                return;
            }

            $relationship->syncWithPivotValues($state ?? [], $pivotData, detaching: false);
        });

        $this->dehydrated(false);

        return $this;

    }

    public function getWithChildren()
    {
        return $this->withChildren ?? false;
    }

    public function withChildren(bool $withChildren = true): static
    {
        $this->withChildren = $withChildren;

        return $this;
    }


}
