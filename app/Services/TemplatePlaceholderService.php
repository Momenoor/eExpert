<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class TemplatePlaceholderService
{
    /**
     * Columns to always skip — system/internal fields.
     */
    protected array $skipColumns = [
        'id', 'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes', 'created_at', 'updated_at',
        'deleted_at', 'email_verified_at',
    ];

    /**
     * Relation types considered "singular" (safe to traverse automatically).
     */
    protected array $singularRelations = [
        BelongsTo::class,
        HasOne::class,
    ];

    /**
     * Build the full placeholder map for a model class.
     *
     * Returns a structured array:
     * [
     *   'matter' => [
     *     'variables' => [
     *       ['key' => '{{matter.reference}}', 'label' => 'Matter / Reference', 'type' => 'string'],
     *       ...
     *     ],
     *     'relations' => [
     *       'client' => [
     *         'variables' => [...],
     *         'relations' => [...],
     *       ],
     *     ],
     *   ],
     * ]
     *
     * @param  class-string<Model>  $modelClass
     * @param  int  $depth  How many relation levels deep to traverse (default 1)
     * @param  string|null  $prefix  Override the root prefix (defaults to snake_case model name)
     */
    public function discover(string $modelClass, int $depth = 1, ?string $prefix = null): array
    {
        $model = app($modelClass);
        $prefix = $prefix ?? Str::snake(class_basename($modelClass));

        return [
            $prefix => $this->analyzeModel($model, $prefix, $depth),
        ];
    }

    /**
     * Flatten the nested structure into a simple key => label list
     * suitable for a Filament Select or variable picker UI.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function flatten(string $modelClass, int $depth = 1, ?string $prefix = null): array
    {
        $nested = $this->discover($modelClass, $depth, $prefix);
        $flat = [];
        $this->flattenRecursive($nested, $flat);

        return $flat; // ['{{matter.reference}}' => 'Matter / Reference', ...]
    }

    /**
     * Resolve actual values from a model instance.
     * Returns ['{{matter.reference}}' => 'UAE-2024-001', ...]
     */
    public function resolve(Model $model, int $depth = 1, ?string $prefix = null): array
    {
        $prefix = $prefix ?? Str::snake(class_basename($model));
        $structure = $this->analyzeModel($model, $prefix, $depth);
        $values = [];
        $this->resolveValues($model, $structure, $values);

        return $values;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    protected function analyzeModel(Model $model, string $prefix, int $depth): array
    {
        $result = [
            'model' => get_class($model),
            'label' => Str::headline(class_basename($model)),
            'variables' => $this->extractColumns($model, $prefix),
            'relations' => [],
        ];

        if ($depth > 0) {
            foreach ($this->detectSingularRelations($model) as $relationName => $relationClass) {
                $relPrefix = "{$prefix}.{$relationName}";

                try {
                    $relatedModel = app($relationClass);
                    $result['relations'][$relationName] = $this->analyzeModel(
                        $relatedModel,
                        $relPrefix,
                        $depth - 1
                    );
                } catch (Throwable) {
                    // Skip unresolvable relations silently
                }
            }
        }

        return $result;
    }

    protected function extractColumns(Model $model, string $prefix): array
    {
        $variables = [];

        try {
            $columns = Schema::getColumnListing($model->getTable());
        } catch (Throwable) {
            return $variables;
        }

        $hidden = $model->getHidden();

        foreach ($columns as $column) {
            if (in_array($column, $this->skipColumns, true)) {
                continue;
            }
            if (in_array($column, $hidden, true)) {
                continue;
            }
            if (Str::endsWith($column, '_id')) {
                continue;
            } // FK columns — skip raw IDs

            $type = $this->getColumnType($model, $column);
            $label = Str::headline(class_basename($model)).' / '.Str::headline($column);

            $variables[] = [
                'key' => "{{{{{$prefix}.{$column}}}}}",
                'label' => $label,
                'type' => $type,
                'column' => $column,
            ];
        }

        // Also pick up public accessors (get*Attribute / Laravel 9+ Attribute casts)
        foreach ($this->detectAccessors($model) as $accessor) {
            $label = Str::headline(class_basename($model)).' / '.Str::headline($accessor).' (computed)';
            $variables[] = [
                'key' => "{{{{{$prefix}.{$accessor}}}}}",
                'label' => $label,
                'type' => 'computed',
                'column' => $accessor,
            ];
        }

        return $variables;
    }

    protected function detectSingularRelations(Model $model): array
    {
        $relations = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip inherited, constructor, snake_case (likely not relations), and magic
            if ($method->class !== get_class($model)) {
                continue;
            }
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }
            if (Str::startsWith($method->name, ['get', 'set', 'scope', 'boot', '__'])) {
                continue;
            }

            try {
                $result = $method->invoke($model);
            } catch (Throwable) {
                continue;
            }

            foreach ($this->singularRelations as $relationType) {
                if ($result instanceof $relationType) {
                    $relations[$method->name] = get_class($result->getRelated());
                    break;
                }
            }
        }

        return $relations;
    }

    protected function detectAccessors(Model $model): array
    {
        $accessors = [];
        $reflection = new ReflectionClass($model);
        $tableColumns = Schema::getColumnListing($model->getTable());

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== get_class($model)) {
                continue;
            }

            // Laravel 9+ style: methods returning Attribute
            $returnType = $method->getReturnType();
            if ($returnType && Str::endsWith((string) $returnType, 'Attribute')) {
                $name = Str::snake($method->name);
                if (! in_array($name, $tableColumns)) {
                    $accessors[] = $name;
                }

                continue;
            }

            // Laravel 8 style: getXxxAttribute
            if (preg_match('/^get(.+)Attribute$/', $method->name, $m)) {
                $name = Str::snake($m[1]);
                if (! in_array($name, $tableColumns)) {
                    $accessors[] = $name;
                }
            }
        }

        return $accessors;
    }

    protected function getColumnType(Model $model, string $column): string
    {
        try {
            return Schema::getColumnType($model->getTable(), $column);
        } catch (Throwable) {
            return 'string';
        }
    }

    protected function flattenRecursive(array $node, array &$flat, string $groupLabel = ''): void
    {
        foreach ($node as $prefix => $data) {
            $label = $data['label'] ?? $prefix;

            foreach ($data['variables'] ?? [] as $var) {
                $flat[$var['key']] = $var['label'];
            }

            foreach ($data['relations'] ?? [] as $relName => $relData) {
                $this->flattenRecursive([$relName => $relData], $flat, $label);
            }
        }
    }

    protected function resolveValues(Model $model, array $structure, array &$values): void
    {
        foreach ($structure['variables'] as $var) {
            try {
                $raw = $model->{$var['column']};
                $values[$var['key']] = match ($var['type']) {
                    'date', 'datetime' => $raw ? Carbon::parse($raw)->format('d/m/Y') : '',
                    'boolean' => $raw ? __('Yes') : __('No'),
                    default => (string) ($raw ?? ''),
                };
            } catch (Throwable) {
                $values[$var['key']] = '';
            }
        }

        foreach ($structure['relations'] as $relationName => $relStructure) {
            try {
                $related = $model->{$relationName};
                if ($related instanceof Model) {
                    $this->resolveValues($related, $relStructure, $values);
                }
            } catch (Throwable) {
                // relation not loaded or null
            }
        }
    }
}
