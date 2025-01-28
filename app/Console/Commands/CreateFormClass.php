<?php

namespace App\Console\Commands;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateFormClass extends Command
{
    protected $signature = 'make:form {name} {--model=}';
    protected $description = 'Create an array of form schema for Filament Resource';

    protected array $skippedColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected string $table;
    protected Model $model;

    public function handle(): int
    {
        $this->name = $this->argument('name');
        $modelName = $this->option('model');
        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            $this->error("Model {$modelClass} does not exist.");
            return self::FAILURE;
        }
        $this->model = new $modelClass;
        [$columns, $relations] = $this->getModelFieldsAndRelations($modelClass);

        $path = app_path("Filament/Forms/{$this->name}.php");
        File::ensureDirectoryExists(dirname($path));

        if (File::exists($path)) {
            $this->error("Class {$this->name} already exists!");
            return self::FAILURE;
        }

        File::put($path, $this->generateFormClass($columns, $relations));

        $this->info("Class {$this->name} created successfully!");
        return self::SUCCESS;
    }

    protected function getModelFieldsAndRelations(string $modelClass): array
    {

        $this->table = $table = $this->model->getTable();

        if (!Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist.");
            return [[], []];
        }

        $columns = Schema::getColumnListing($table);
        $relations = $this->getModelRelationships();

        return [$columns, $relations];
    }

    protected function getModelRelationships(): array
    {
        $model = $this->model;
        $relationships = [];
        $methods = get_class_methods($model);

        foreach ($methods as $method) {
            $reflection = new \ReflectionMethod($model, $method);

            if ($this->isPublicMethodDefinedInModel($reflection, $model)) {
                try {
                    $return = $reflection->invoke($model);

                    if ($return instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $relationships[$method] = class_basename(get_class($return));
                    }
                } catch (\Throwable $e) {
                    // Ignore non-invokable methods
                }
            }
        }

        return $relationships;
    }

    protected function isPublicMethodDefinedInModel(\ReflectionMethod $reflection, $model): bool
    {
        return $reflection->isPublic()
            && $reflection->class === get_class($model)
            && $reflection->getNumberOfParameters() === 0;
    }

    protected function generateFormClass(array $columns, array $relations): string
    {
        $schema = $this->buildSchema($columns, $relations);

        $schemaString = implode(",\n            ", $schema);

        return <<<PHP
<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class {$this->name}
{
    public static function make(): array
    {
        return [
            {$schemaString}
        ];
    }
}

PHP;
    }

    protected function buildSchema(array $columns, array $relations): array
    {
        $schema = [];
        $finalColumns = array_diff($columns, $this->skippedColumns);

        foreach ($finalColumns as $column) {
            $schema[] = $this->generateFieldSchema($column, $columns, $relations);
        }

//        foreach ($relations as $relation => $type) {
//            if ($type === 'BelongsToMany') {
//                $schema[] = $this->generateRelationshipSchema($relation);
//            }
//        }

        return $schema;
    }

    protected function generateFieldSchema(string $column, array $columns, array $relations): string
    {
        if (Str::endsWith($column, '_id')) {
            return $this->generateFieldRelationshipSchema($column, $columns, $relations);
        }

        return $this->generateTextInputSchema($column);
    }

    protected function generateFieldRelationshipSchema(string $column, array $columns, array $relations): string
    {
        $relationName = Str::of($column)->before('_id')->camel()->toString();

        if (!isset($relations[$relationName])) {
            return $this->generateTextInputSchema($column);
        }

        if ($relationName === 'parent' && !in_array('name', $columns)) {
            return ''; // Do nothing for specific case
        }

        if ($relationName === 'entity') {
            return $this->buildEntitySchema();
        }

        $subForm = $this->getFormFromClass($relationName);
        $relationNameCapitalized = Str::ucfirst($relationName);
        $modelName = "App\\Models\\{$relationNameCapitalized}";
        $hasEntityRelation = $this->doesModelMethodExist($modelName, 'entity');

        return "Select::make('{$column}')"
            . "->relationship('{$relationName}', '" . ($hasEntityRelation ? 'id' : 'name') . "')"
            . ($this->isColumnRequired($column) ? "->required()" : "")
            . ((!empty($subForm)) ? "->createOptionForm({$subForm}::make())" : "")
            . ($hasEntityRelation
                ? "->getOptionLabelFromRecordUsing(fn (\$record) => \$record->entity ? \$record->entity->name : '')"
                : "");
    }

    protected function generateTextInputSchema(string $column): string
    {
        return "TextInput::make('{$column}')"
            . ($this->isColumnRequired($column) ? "->required()" : "");
    }

    protected function isColumnRequired(string $column): bool
    {
        try {
            $columns = Collect(Schema::getColumns($this->table));
            $isNullable = $columns->where('name', $column)->first()['nullable'];
            return !$isNullable;
        } catch (\Exception $e) {
            $this->warn("Column {$column} is not exist.");
            return false;
        }
    }

    protected function buildEntitySchema(): string
    {
        $entityForm = "App\\Filament\\Forms\\EntityForm";
        if (class_exists($entityForm)) {
            return <<<php
Group::make([
...\App\Filament\Forms\EntityForm::make()
])->relationship('entity')
php;

        } else {
            $entityModel = "App\\Models\\Entity";
            $entityColumns = Schema::getColumnListing('entities');
            $entityRelations = $this->getModelRelationships(new $entityModel);

            $nestedSchema = [];

            foreach (array_diff($entityColumns, $this->skippedColumns) as $entityColumn) {
                $nestedSchema[] = $this->generateTextInputSchema($entityColumn);
            }

            foreach ($entityRelations as $relation => $type) {
                if ($type === 'BelongsTo') {
                    $nestedSchema[] = "Select::make('{$relation}_id')->relationship('{$relation}', 'name')->required()";
                }
            }

            $nestedSchemaString = implode(",\n                ", $nestedSchema);

            return <<<PHP
Group::make([
                {$nestedSchemaString}
            ])->relationship('entity')
PHP;
        }
    }

    protected function getFormFromClass(string $relationName): string
    {
        if ($relationName === 'parent') {
            return '';
        }

        $relationNameCapitalized = Str::ucfirst($relationName);
        $className = "\\App\\Filament\\Forms\\{$relationNameCapitalized}Form";

        if (!class_exists($className)) {
            $this->error("The form class {$className} does not exist.");
            return '';
        }

        if (!method_exists($className, 'make')) {
            $this->error("The make() method is not defined in {$className}.");
            return '';
        }

        return $className;
    }

    protected function doesModelMethodExist($model, string $methodName): bool
    {
        if (!class_exists($model)) {
            return false;
        }

        $modelInstance = new $model();

        if (!method_exists($modelInstance, $methodName)) {
            return false;
        }

        $reflection = new \ReflectionMethod($modelInstance, $methodName);

        return $reflection->class === get_class($modelInstance);
    }

    protected function generateRelationshipSchema(string $relation): array
    {
        $schema = '';
        $model = $this->model;
        $relatedModel = $this->model->{$relation}()->getModel();
        $relatedModelMethods = get_class_methods($relatedModel);
        foreach ($relatedModelMethods as $method) {
            $reflectionMethod = new \ReflectionMethod($relatedModel, $method);

            if ($this->isPublicMethodDefinedInModel($reflectionMethod, $relatedModel)) {
                $result = $reflectionMethod->invoke($relatedModel);
                if ($result instanceof Relation) {
                    if (!is_string($model)) {
                        if (get_class($result->getRelated()) === get_class($model)) {
                            $schema = <<<php
\Filament\Forms\Components\Repeater::make('{$relation}')->schema([

])
php;

                        }
                    }
                }
            }
        }
        return $schema;
    }
}
