<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = \App\Models\Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $permissionGroups = self::getPermissionGroups();
        return $form
            ->schema(array_merge(
                    [
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignorable: fn($record) => $record)
                            ->columnSpanFull(),
                    ],
                    array_map(function ($label, $permissions) {
                        return Forms\Components\CheckboxList::make(Str::slug($label, '_'))
                            ->visible(fn() => ! request()->routeIs('filament.admin.resources.roles.create') )
                            ->label($label)
                            ->options(Permission::whereIn('name', $permissions)->pluck('name', 'id')->toArray())
                            ->columns(2) // Adjust the number of columns as needed
                            ->afterStateHydrated(function ($component, $state) {
                                if ($component->getRecord()) {
                                    $component->state(
                                        $component->getRecord()->permissions()->pluck('id')->toArray()
                                    );
                                }
                            });
                    }, array_keys($permissionGroups), $permissionGroups)


                )
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->toggleable()
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\RoleResource\Pages\ListRoles::route('/'),
            'create' => \App\Filament\Admin\Resources\RoleResource\Pages\CreateRole::route('/create'),
            'view' => \App\Filament\Admin\Resources\RoleResource\Pages\ViewRole::route('/{record}'),
            'edit' => \App\Filament\Admin\Resources\RoleResource\Pages\EditRole::route('/{record}/edit'),
        ];
    }

    private static function getPermissionGroups()
    {
        $permissions = Permission::all()->pluck('name')->toArray();
        $groupedPermissions = [];

        foreach ($permissions as $permission) {
            // Extract the group name from the permission name
            $groupName = Str::title(Str::before($permission, '-')) . ' Permissions';

            // Add the permission to the appropriate group
            $groupedPermissions[$groupName][] = $permission;
        }

        return $groupedPermissions;
    }
}
