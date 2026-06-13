---
name: Filament PHP v5 Development
description: Expert guidance for building admin panels and dashboards with Filament PHP v5. Covers resources, forms, tables, actions, widgets, relation managers, and comprehensive testing patterns.
compatible_agents:
  - Claude Code
  - Cursor
  - Gemini CLI
  - OpenCode
  - Codex
  - Copilot
  - Cline
  - Roo Code
  - Windsurf
  - Junie
tags:
  - filament
  - laravel
  - php
  - livewire
  - admin-panel
  - tall-stack
---

# Filament PHP v5 Expert

## When to Apply

Activate this skill when:

- Building admin panels or dashboards
- Creating or modifying Filament resources
- Working with forms, tables, or widgets
- Implementing authentication or user management
- Adding relation managers
- Customizing Filament pages or themes
- Debugging Filament-related issues

## Documentation

Use `search-docs` for detailed Filament v5 patterns and documentation.

## Context
You are an expert in Filament PHP v5, a Server-Driven UI (SDUI) framework for Laravel. Filament allows you to define user interfaces in PHP using structured configuration objects, built on top of Livewire 4, Alpine.js, and Tailwind CSS v4.

Your goal is to build robust, scalable, and polished applications that adhere to strict standards:
- **PHP 8.3+**: Full type declarations, constructor property promotion, readonly properties.
- **Laravel 12**: Utilizing the latest framework features.
- **Livewire 4**: Leveraging the latest reactivity patterns and "Islands" architecture.
- **PHPStan Level 9**: Enforcing maximum type safety.
- **Pest 4**: Writing architectural and feature tests for all components.

## Rules

### Core Development
- **Use `search-docs` First**: Always search version-specific Filament v5 documentation before implementing to avoid deprecated v3/v4 patterns.
- **Configuration Over Code**: Define UI declaratively using fluent configuration methods and static `make()` methods.
- **Strict Typing**: All methods must have return type declarations. Use specific types like `Schema`, `Table`, `Builder`, and `void` instead of generic `array` or `object`.
- **Localization**: Never hardcode strings. Use Laravel's `__('key')` helper for all labels, headings, helper text, and notifications.

### Filament v5 & Livewire 4 Specifics
- **Schema Signature**: Use `public static function form(Schema $schema): Schema` (not `Form $form`).
- **Top-Level Components**: Use `$schema->components([...])` at the top level of the form method.
- **Layout Components**: Continue using `->schema([...])` for layout components like `Section`, `Grid`, and `Tabs`.
- **Action Namespaces**: Always use `Filament\Actions\*` for all actions. Do NOT use `Filament\Tables\Actions\*` or `Filament\Forms\Actions\*`.
- **Component Tags**: Always close Livewire component tags (e.g., `<livewire:user-stats></livewire:user-stats>`).
- **Islands Architecture**: Use `@island` for isolated UI regions (charts, widgets) to improve performance by preventing full page re-renders.
- **Render Hooks**: Use `FilamentView::registerRenderHook()` to inject content (modals, scripts) into views without overriding them.

### Security & Visibility
- **File Visibility**: Always explicitly set `->visibility('public')` or `->visibility('private')` on `FileUpload` components.
- **Authorization**: Implement Policy methods (`viewAny`, `create`, `update`, `delete`) and use `->visible()` on actions to enforce permissions.

### UX & Polish
- **Layout Spanning**: Use `->columnSpanFull()` for fields/sections that should take full width.
- **Tabs with Icons**: Organize forms into logical tabs using `Tabs::make()` with `Tabs\Tab::make()` and icon support for visual hierarchy.
  - **Contained(false)**: Use `->contained(false)` when sections are inside tabs to remove unnecessary borders and padding.
  - **Scrollable**: Use `->scrollable()` for tabs with long content to enable horizontal scrolling on smaller screens.
  - **Vertical Layout**: Use `->vertical()` to stack tabs vertically instead of horizontally for mobile/complex layouts.
  - **Tab Persistence**: Use `->persistTabInQueryString()` to remember the active tab across page reloads and shareable links, or `->persistTab()` for session-based persistence.
  - **Tab Identification**: Use `->id('unique-tab-id')` to uniquely identify tab groups (required for persistence and JavaScript interaction).
- **Responsive Design**: Use `->columns(['default' => 1, 'sm' => 2, 'xl' => 3])` and `->columnSpan()` arrays for adaptive layouts
- **Column Ordering**: Use `->columnOrder()` to reorder fields for better mobile UX
- **Section Organization**: Use `Section::make()` with `->icon()`, `->description()`, `->collapsible()` for professional organization.
- **Grid Layout**: Use `Grid::make(columns: 2)` or `->columns(2)` for responsive multi-column layouts.
- **Live Updates**: Use `->live()` for fields that trigger dynamic changes (e.g., toggling visibility of other fields).
- **Feedback**: Use `->helperText()`, `->hintIcon()`, and `->placeholder()` to guide users.
- **Empty States**: Configure custom empty states for tables (`->emptyStateHeading()`, `->emptyStateActions()`).
- **Field Descriptions**: Add `->description()` to sections for context and guidance.
- **Icons**: Use `Heroicon::*` for section icons, tab icons, and visual consistency throughout forms.

### Testing
- **Comprehensive Coverage**: Every resource, page, and widget must have a corresponding Pest test.
- **Assertions**: Use v5 assertions like `assertSchemaExists` instead of `assertFormExists`.
- **Authentication**: Always use `actingAs($user)` in tests before interacting with panels.
- **Job Interactions**: Use `withFakeQueueInteractions()` to test queued jobs dispatched by actions without running them.

## File Structure (v5)

Filament v5 generates a **modular, separated structure** for better organization:

```
app/Filament/
├── Resources/
│   └── PostResource/
│       ├── PostResource.php (Main resource class)
│       ├── Schemas/
│       │   └── PostForm.php (Form configuration - SEPARATED)
│       ├── Tables/
│       │   └── PostsTable.php (Table configuration - SEPARATED)
│       ├── Pages/
│       │   ├── ListPosts.php
│       │   ├── CreatePost.php
│       │   └── EditPost.php
│       └── RelationManagers/ (if any relations)
│           └── CommentsRelationManager.php
├── Pages/
│   └── Dashboard.php
└── Widgets/
    ├── StatsWidget.php (extends StatWidget)
    ├── ChartWidget.php (extends ChartWidget)
    └── TableWidget.php (extends TableWidget)
```

## Examples

### Resource Structure (v5)

**`app/Filament/Resources/Posts/PostResource.php`** (Main Resource)
```php
namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
```

**`app/Filament/Resources/Posts/Schemas/PostForm.php`** (Form - SEPARATED with Advanced Polish UI)
```php
namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Post Management')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Section::make('Post Information')
                                    ->description('Basic post details')
                                    ->icon(Heroicon::OutlinedInformation)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Title')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->unique(ignoreRecord: true)
                                            ->required()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Content')
                            ->icon(Heroicon::OutlinedPencil)
                            ->schema([
                                Section::make('Post Body')
                                    ->description('Main content')
                                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                                    ->schema([
                                        Textarea::make('content')
                                            ->label('Content')
                                            ->required()
                                            ->rows(10)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Settings')
                            ->icon(Heroicon::OutlinedCog6Tooth)
                            ->schema([
                                Section::make('Post Configuration')
                                    ->description('Advanced settings')
                                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('status')
                                            ->label('Status')
                                            ->default('draft'),
                                        TextInput::make('author')
                                            ->label('Author'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
```

**`app/Filament/Resources/Posts/Tables/PostsTable.php`** (Table - SEPARATED)
```php
namespace App\Filament\Resources\Posts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }
}
```

**`app/Filament/Resources/Users/Tables/UsersTable.php`** (Table - Advanced with TextColumn badge(), ActionGroup, Filters, Icons)
```php
namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // TextColumn with icon before text
                TextColumn::make('name')
                    ->label(__('validation.attributes.name'))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::OutlinedUser)
                    ->iconPosition('before')
                    ->toggleable(),

                // Copyable column for easy clipboard access
                TextColumn::make('email')
                    ->label(__('validation.attributes.email'))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconPosition('before')
                    ->copyable()
                    ->copyableState(fn (string $state): string => $state),

                // TextColumn with badge() for status visualization
                TextColumn::make('status')
                    ->label(__('messages.status'))
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->icons([
                        'success' => Heroicon::OutlinedCheckCircle,
                        'danger' => Heroicon::OutlinedXCircle,
                    ])
                    ->formatStateUsing(fn (string $state): string => __("messages.{$state}"))
                    ->sortable()
                    ->toggleable(),

                // TextColumn with badge() and match expression for formatting
                TextColumn::make('role')
                    ->label(__('messages.role'))
                    ->badge()
                    ->colors([
                        'danger' => 'admin',
                        'warning' => 'editor',
                        'info' => 'user',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => __('messages.administrator'),
                        'editor' => __('messages.editor'),
                        'user' => __('messages.regular_user'),
                        default => $state,
                    })
                    ->sortable()
                    ->toggleable(),

                // TextColumn with badge() for boolean-like states
                TextColumn::make('email_verified_at')
                    ->label(__('messages.verified'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('messages.verified') : __('messages.unverified'))
                    ->colors([
                        'success' => fn ($state) => $state !== null,
                        'danger' => fn ($state) => $state === null,
                    ])
                    ->icons([
                        'success' => Heroicon::OutlinedCheckCircle,
                        'danger' => Heroicon::OutlinedXCircle,
                    ])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Badge for 2FA status
                TextColumn::make('two_factor_confirmed_at')
                    ->label(__('messages.two_factor'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('messages.enabled') : __('messages.disabled'))
                    ->colors([
                        'success' => fn ($state) => $state !== null,
                        'warning' => fn ($state) => $state === null,
                    ])
                    ->icons([
                        'success' => Heroicon::OutlinedShieldCheck,
                        'warning' => Heroicon::OutlinedExclamationTriangle,
                    ])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // DateTime column with custom format - always visible
                TextColumn::make('created_at')
                    ->label(__('messages.joined'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                // Toggleable column - hidden by default for cleaner view
                TextColumn::make('updated_at')
                    ->label(__('messages.last_updated'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Advanced filters with custom query closures
            ->filters([
                SelectFilter::make('status')
                    ->label(__('messages.status'))
                    ->options([
                        'active' => __('messages.active'),
                        'inactive' => __('messages.inactive'),
                    ])
                    ->placeholder(__('messages.all_statuses')),

                // Custom filter with query closure
                SelectFilter::make('email_verified_at')
                    ->label(__('messages.verification'))
                    ->options([
                        'verified' => __('messages.verified'),
                        'unverified' => __('messages.unverified'),
                    ])
                    ->query(function ($query, $value) {
                        return match ($value) {
                            'verified' => $query->whereNotNull('email_verified_at'),
                            'unverified' => $query->whereNull('email_verified_at'),
                            default => $query,
                        };
                    })
                    ->placeholder(__('messages.all_verifications')),

                // Custom filter for 2FA status
                SelectFilter::make('two_factor_confirmed_at')
                    ->label(__('messages.two_factor'))
                    ->options([
                        'enabled' => __('messages.enabled'),
                        'disabled' => __('messages.disabled'),
                    ])
                    ->query(function ($query, $value) {
                        return match ($value) {
                            'enabled' => $query->whereNotNull('two_factor_confirmed_at'),
                            'disabled' => $query->whereNull('two_factor_confirmed_at'),
                            default => $query,
                        };
                    })
                    ->placeholder(__('messages.all_settings')),
            ], layout: FiltersLayout::AboveContent)
            // Record actions wrapped in ActionGroup for better organization
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->icon(Heroicon::OutlinedPencil),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->label('Actions')
                    ->tooltip('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading(__('messages.bulk_delete_confirmation_title'))
                        ->modalDescription(__('messages.bulk_delete_confirmation_description'))
                        ->modalSubmitActionLabel(__('messages.delete'))
                        ->modalCancelActionLabel(__('messages.cancel')),
                ]),
            ])
            // Complete empty state configuration
            ->emptyStateHeading(__('messages.no_users'))
            ->emptyStateDescription(__('messages.create_first_user'))
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
```

**Key Table Column Patterns:**
- **`->badge()`**: Use instead of deprecated `BadgeColumn` - converts TextColumn into a badge style
- **`->toggleable()`**: Allow users to show/hide columns via table settings
- **`isToggledHiddenByDefault: true`**: Hide less important columns by default
- **`isToggledHiddenByDefault: false`**: Keep essential columns always visible
- **`ActionGroup::make([...])`**: Wrap record actions in a dropdown group for cleaner UI
- **`->icon(Heroicon::EllipsisVertical)`**: Use vertical ellipsis for action group trigger

### Resource Pages (v5)

**`app/Filament/Resources/Posts/Pages/ListPosts.php`**
```php
namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

**`app/Filament/Resources/Users/Pages/ListUsers.php`** (List Page with Custom Title & Actions)
```php
namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return __('messages.manage_users');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('messages.create_user'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
```

**`app/Filament/Resources/Users/Pages/CreateUser.php`** (Create Page with Redirect)
```php
namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return __('messages.create_new_user');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
```

**`app/Filament/Resources/Users/Pages/EditUser.php`** (Edit Page with Delete/Restore & Redirect)
```php
namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return __('messages.edit_user', ['name' => $this->record->name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('messages.delete'))
                ->icon('heroicon-o-trash-2')
                ->requiresConfirmation()
                ->modalHeading(__('messages.delete_confirmation_title'))
                ->modalDescription(__('messages.delete_confirmation_description'))
                ->modalSubmitActionLabel(__('messages.delete'))
                ->modalCancelActionLabel(__('messages.cancel')),
            RestoreAction::make()
                ->label(__('messages.restore'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $this->record->trashed()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
```

**`app/Filament/Resources/Users/UserResource.php`** (Resource with Navigation & Labels)
```php
namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    // Control sort order in navigation
    protected static ?int $navigationSort = 1;

    // Attribute to use for record titles (used in breadcrumbs, etc.)
    protected static ?string $recordTitleAttribute = 'name';

    // Localized model labels
    public static function getModelLabel(): string
    {
        return __('messages.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.users');
    }

    public static function getNavigationLabel(): string
    {
        return __('messages.users');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
```

### Widgets (v5)

**`app/Filament/Widgets/StatsWidget.php`**
```php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Posts', 12)
                ->description('Increase of 5% this month'),
            Stat::make('Active Users', 456)
                ->color('success'),
        ];
    }
}
```

**`app/Filament/Widgets/ChartWidget.php`**
```php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class PostsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Posts Created';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Posts',
                    'data' => [10, 20, 15, 25],
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

### Dashboard Page (v5)

**`app/Filament/Pages/Dashboard.php`**
```php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';
}
```

**`resources/views/filament/pages/dashboard.blade.php`**
```blade
<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <livewire:filament.widgets.stats-widget />
        <livewire:filament.widgets.posts-chart-widget />
    </div>
</x-filament-panels::page>
```

### V5 Action Patterns

**Table Row Actions vs Toolbar Actions**
```php
// In PostsTable::configure()
return $table
    // ✅ Row actions (inline buttons on each record)
    ->recordActions([
        EditAction::make(),
        DeleteAction::make(),
    ])
    // ✅ Toolbar actions (header buttons for bulk operations)
    ->toolbarActions([
        BulkActionGroup::make([
            DeleteBulkAction::make(),
            ExportBulkAction::make(),
        ]),
    ]);
```

### Dynamic Form Fields (v5)
```php
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

Select::make('country')
    ->options(Country::class)
    ->live()
    ->afterStateUpdated(fn (Set $set) => $set('city', null));

Select::make('city')
    ->options(fn (Get $get) => 
        City::where('country_id', $get('country'))->pluck('name', 'id')
    )
    ->visible(fn (Get $get) => filled($get('country')))
    ->required();
```

### Advanced Form with Tabs & Polish UI (v5)
```php
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Tabs::make('Product Management')
                ->id('product-tabs')
                ->contained(false)
                ->scrollable()
                ->persistTabInQueryString()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->schema([
                            Section::make('Product Information')
                                ->description('Basic product details')
                                ->icon(Heroicon::OutlinedSparkles)
                                ->columns(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Product Name')
                                        ->required()
                                        ->columnSpanFull(),
                                    TextInput::make('sku')
                                        ->label('SKU')
                                        ->required(),
                                    TextInput::make('price')
                                        ->label('Price')
                                        ->numeric()
                                        ->required(),
                                ]),
                        ]),
                    Tabs\Tab::make('Details')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([
                            Section::make('Product Description')
                                ->description('Detailed product information')
                                ->icon(Heroicon::OutlinedPencil)
                                ->schema([
                                    Textarea::make('description')
                                        ->label('Description')
                                        ->rows(5)
                                        ->columnSpanFull(),
                                    RichEditor::make('content')
                                        ->label('Rich Content')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tabs\Tab::make('Settings')
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->schema([
                            Section::make('Configuration')
                                ->description('Advanced settings')
                                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                ->schema([
                                    ToggleButton::make('is_active')
                                        ->label('Active')
                                        ->helperText('Make this product visible to customers')
                                        ->onIcon(Heroicon::SolidEye)
                                        ->offIcon(Heroicon::OutlineEyeSlash)
                                        ->onColor('success')
                                        ->offColor('gray')
                                        ->columnSpanFull(),
                                    ToggleButton::make('is_featured')
                                        ->label('Featured')
                                        ->helperText('Show on homepage')
                                        ->onIcon(Heroicon::SolidStar)
                                        ->offIcon(Heroicon::OutlineStar)
                                        ->onColor('warning')
                                        ->offColor('gray')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ]),
        ]);
}
```

**Advanced Tab Features:**
- `->id('product-tabs')` - Uniquely identifies the tab group for persistence and JS interaction
- `->contained(false)` - Removes container borders when sections are inside tabs
- `->scrollable()` - Enables horizontal scrolling for tabs on narrow screens
- `->persistTabInQueryString()` - Remembers active tab in URL, shareable across links
- Alternative: `->persistTab()` - Uses session storage instead of query string

**When to use each feature:**
- **contained(false)** - Required when using Section inside Tabs (cleaner appearance)
- **scrollable()** - Use for 4+ tabs to prevent wrapping
- **vertical()** - Use for forms with very long tab lists or mobile-first designs
- **persistTabInQueryString()** - Best for complex forms where users need shareable links
- **persistTab()** - Better for privacy-conscious apps (doesn't expose state in URL)

### Advanced User Form with ToggleButton, DateTimePicker & Localization (v5)

**`app/Filament/Resources/Users/Schemas/UserForm.php`** (Complete User Form with All Field Types)
```php
namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButton;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('User Management')
                    ->id('user-management-tabs')
                    ->contained(false)
                    ->scrollable()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tabs\Tab::make('Personal')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make(__('messages.personal_information'))
                                    ->description(__('messages.update_user_details'))
                                    ->icon(Heroicon::OutlinedInformationCircle)
                                    ->columns([
                                        'sm' => 2,
                                        'lg' => 3,
                                        'xl' => 4,
                                    ])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('validation.attributes.name'))
                                            ->placeholder(__('messages.enter_full_name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText(__('messages.first_and_last_name'))
                                            ->columnSpan([
                                                'default' => 1,
                                                'sm' => 2,
                                                'xl' => 3,
                                            ])
                                            ->columnOrder([
                                                'default' => 2,
                                                'xl' => 1,
                                            ]),
                                        TextInput::make('email')
                                            ->label(__('validation.attributes.email'))
                                            ->placeholder(__('messages.enter_email_address'))
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText(__('messages.unique_email_required'))
                                            ->columnSpan([
                                                'default' => 1,
                                                'sm' => 2,
                                                'xl' => 3,
                                            ])
                                            ->columnOrder([
                                                'default' => 1,
                                                'xl' => 2,
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Security')
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->schema([
                                Section::make(__('messages.security'))
                                    ->description(__('messages.manage_security_settings'))
                                    ->icon(Heroicon::OutlinedShieldCheck)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('password')
                                            ->label(__('validation.attributes.password'))
                                            ->password()
                                            ->placeholder(__('messages.enter_new_password'))
                                            ->helperText(__('messages.leave_blank_to_keep_current'))
                                            ->columnSpanFull(),
                                        Select::make('status')
                                            ->label(__('messages.account_status'))
                                            ->options([
                                                'active' => __('messages.active'),
                                                'inactive' => __('messages.inactive'),
                                            ])
                                            ->default('active')
                                            ->required()
                                            ->helperText(__('messages.control_user_access')),
                                        Select::make('role')
                                            ->label(__('messages.user_role'))
                                            ->options([
                                                'admin' => __('messages.administrator'),
                                                'editor' => __('messages.editor'),
                                                'user' => __('messages.regular_user'),
                                            ])
                                            ->default('user')
                                            ->required()
                                            ->helperText(__('messages.assign_user_permissions')),
                                    ]),
                            ]),

                        Tabs\Tab::make('Verification')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->schema([
                                Section::make(__('messages.email_verification'))
                                    ->description(__('messages.manage_email_verification'))
                                    ->icon(Heroicon::OutlinedCheckCircle)
                                    ->schema([
                                        DateTimePicker::make('email_verified_at')
                                            ->label(__('messages.email_verified_at'))
                                            ->helperText(__('messages.auto_verify_on_first_login'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Two-Factor')
                            ->icon(Heroicon::OutlinedShieldExclamation)
                            ->schema([
                                Section::make(__('messages.two_factor_authentication'))
                                    ->description(__('messages.enhance_account_security'))
                                    ->icon(Heroicon::OutlinedKey)
                                    ->schema([
                                        ToggleButton::make('two_factor_confirmed_at')
                                            ->label(__('messages.two_factor_enabled'))
                                            ->helperText(__('messages.requires_backup_codes'))
                                            ->onIcon(Heroicon::SolidShieldCheck)
                                            ->offIcon(Heroicon::OutlineShieldCheck)
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->columnSpanFull(),
                                        Textarea::make('two_factor_secret')
                                            ->label(__('messages.two_factor_secret'))
                                            ->helperText(__('messages.store_securely'))
                                            ->columnSpanFull()
                                            ->rows(3),
                                        DateTimePicker::make('two_factor_confirmed_at')
                                            ->label(__('messages.confirmed_at'))
                                            ->helperText(__('messages.when_2fa_was_enabled'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
```

**Key Form Components:**
- **DateTimePicker**: For date/time fields with Laravel casting support
- **ToggleButton**: Advanced boolean input with icons, colors, and polished UX (preferred over basic Toggle)
- **Select**: For dropdown selections with localized options
- **Textarea**: For multi-line text input with configurable rows
- **Grid**: For responsive multi-column layouts
- All labels, placeholders, and helper text use Laravel's `__()` localization

**ToggleButton Features:**
- `->onIcon()` / `->offIcon()`: Visual indicators for on/off states
- `->onColor()` / `->offColor()`: Custom colors for each state
- More visually appealing than basic Toggle with better affordance

### Responsive Design Patterns (v5)

Filament v5 provides powerful responsive design tools using Tailwind CSS breakpoints. Use responsive arrays to adapt layouts to different screen sizes.

**Responsive Section Columns**
```php
Section::make('Personal Information')
    ->columns([
        'default' => 1,  // Mobile: 1 column
        'sm' => 2,       // Small: 2 columns
        'lg' => 3,       // Large: 3 columns
        'xl' => 4,       // Extra large: 4 columns
        '2xl' => 6,      // 2XL: 6 columns
    ])
    ->schema([
        // Fields automatically flow through columns
    ]);
```

**Responsive Column Span**
```php
TextInput::make('name')
    ->columnSpan([
        'default' => 1,  // Mobile: 1 column
        'sm' => 2,       // Small: 2 columns
        'xl' => 3,       // Large: 3 columns
        '2xl' => 4,      // 2XL: 4 columns
    ]);
```

**Responsive Column Order** (reorder fields on different screens)
```php
TextInput::make('name')
    ->columnOrder([
        'default' => 2,  // Mobile: appears second
        'xl' => 1,       // Desktop: appears first
    ]);

TextInput::make('email')
    ->columnOrder([
        'default' => 1,  // Mobile: appears first
        'xl' => 2,       // Desktop: appears second
    ]);
```

**Complete Responsive Form Example**
```php
Section::make('User Details')
    ->columns([
        'sm' => 3,
        'xl' => 6,
        '2xl' => 8,
    ])
    ->schema([
        TextInput::make('name')
            ->columnSpan([
                'default' => 1,
                'sm' => 2,
                'xl' => 3,
                '2xl' => 4,
            ])
            ->columnOrder([
                'default' => 2,
                'xl' => 1,
            ]),
        TextInput::make('email')
            ->columnSpan([
                'default' => 1,
                'xl' => 2,
            ])
            ->columnOrder([
                'default' => 1,
                'xl' => 2,
            ]),
        TextInput::make('phone')
            ->columnSpan([
                'default' => 1,
                'sm' => 1,
                'xl' => 1,
            ]),
        Select::make('country')
            ->columnSpan([
                'default' => 1,
                'sm' => 2,
                'xl' => 2,
            ]),
        Textarea::make('address')
            ->columnSpanFull(), // Always full width
    ]);
```

**Responsive Grid Component**
```php
Grid::make([
    'default' => 1,
    'sm' => 2,
    'lg' => 3,
    'xl' => 4,
])
    ->schema([
        // Components here adapt to grid columns
    ]);
```

**Responsive Tabs**
```php
Tabs::make('User Management')
    ->tabs([
        Tabs\Tab::make('Personal')
            ->schema([
                Section::make()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        // Responsive fields
                    ]),
            ]),
    ])
    ->contained(false)
    ->scrollable(); // Enable horizontal scroll on small screens
```

**Available Breakpoints:**
- `default` - Base styles (mobile-first, < 640px)
- `sm` - Small devices (≥ 640px)
- `md` - Medium devices (≥ 768px)
- `lg` - Large devices (≥ 1024px)
- `xl` - Extra large devices (≥ 1280px)
- `2xl` - 2XL devices (≥ 1536px)

**Best Practices for Responsive Forms:**
1. **Mobile-First**: Start with `default` (simplest layout) then enhance for larger screens
2. **Progressive Complexity**: More columns as screen space increases
3. **Full Width on Mobile**: Use `columnSpanFull()` or `columnSpan(['default' => 'full'])` for important fields
4. **Reorder for UX**: Use `columnOrder()` to prioritize important fields on small screens
5. **Test All Sizes**: Verify form usability on mobile, tablet, and desktop
6. **Scrollable Tabs**: Use `->scrollable()` for tabs on mobile to prevent wrapping

### Relation Manager (v5)

**`app/Filament/Resources/Posts/RelationManagers/CommentsRelationManager.php`**
```php
namespace App\Filament\Resources\Posts\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $recordTitleAttribute = 'content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Comment')
                    ->schema([
                        TextInput::make('content')
                            ->required()
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')
                    ->words(10)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ]);
    }
}
```

Then register it in the main resource:
```php
// In PostResource.php
public static function getRelations(): array
{
    return [
        CommentsRelationManager::class,
    ];
}
```

### Livewire 4 Islands
```blade
{{-- Isolated update region for performance --}}
@island
    <livewire:filament.widgets.expensive-stats />
    <button wire:click="$refresh">Refresh</button>
@endisland
```

### Render Hooks
```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

public function register(): void
{
    FilamentView::registerRenderHook(
        PanelsRenderHook::BODY_START,
        fn (): string => Blade::render('<x-impersonation-banner />'),
    );
}
```

## Generation Commands (v5)

### Creating Resources
```bash
# Generate resource with all pages, form, and table
php artisan filament:resource Post --generate

# Generate resource without pages
php artisan filament:resource Post

# Generate resource in a cluster
php artisan filament:resource Post --cluster=BlogCluster
```

### Creating Pages
```bash
# Dashboard page
php artisan filament:page Dashboard --type=dashboard

# Custom page
php artisan filament:page Settings

# Page with specific type
php artisan filament:page Reports --type=custom
```

### Creating Widgets
```bash
# Stats widget (displays metrics)
php artisan filament:widget StatsOverview

# Chart widget (displays charts)
php artisan filament:widget PostsChart --chart

# Table widget
php artisan filament:widget RecentPosts --table

# Custom widget
php artisan filament:widget CustomWidget
```

### Creating Form Components
```bash
# Custom form field
php artisan filament:field CustomField

# Custom form layout component
php artisan filament:form-layout CustomLayout
```

### Creating Table Components
```bash
# Custom table column
php artisan filament:column CustomColumn

# Bulk action
php artisan filament:bulk-action BulkExport

# Custom filter
php artisan filament:filter StatusFilter
```

### Creating Relation Managers
**Note: Requires a relationship on the model**

```bash
# Generate relation manager for a specific relationship
php artisan filament:relation-manager PostResource comments

# The model must have the relationship defined:
# class Post extends Model {
#     public function comments(): HasMany { ... }
# }
```

## Key v5 Differences from v3

| Feature | v3 | v5 |
|---------|----|----|
| **Form Signature** | `form(Form $form)` | `form(Schema $schema)` |
| **Form Top Level** | `->schema([...])` | `->components([...])` |
| **Form/Table Separation** | Single file | Separate `Schemas/` and `Tables/` classes |
| **Actions Namespace** | `Filament\Tables\Actions\*` | `Filament\Actions\*` |
| **Row Actions** | `->actions([...])` | `->recordActions([...])` |
| **Bulk Actions** | `->bulkActions([...])` | `->toolbarActions([BulkActionGroup::make([...])])` |
| **Widgets** | `extends DashboardComponent` | `extends StatsOverviewWidget`, `ChartWidget` |
| **RelationManager** | Single `schema()` method | Separate `form()` and `table()` |
| **Livewire** | Livewire 2.x | Livewire 4.x with Islands |

## Best Practices for v5

### Code Organization
1. **Separate Configuration Classes**: Always use separate `Schemas/` and `Tables/` classes for maintainability
2. **Use Typed Properties**: Use `BackedEnum` for icons and other properties
3. **Type Safety**: Ensure all methods have return type declarations
4. **Leverage Enums**: Use Laravel Enums for status fields instead of string constants

### UI/UX
5. **Localization**: Never hardcode strings; use `__('key')` for all UI text
6. **File Visibility**: Always explicitly set `->visibility()` on `FileUpload` components
7. **Icon Consistency**: Use `Heroicon::*` for consistent visual language
8. **Badge Styling**: Use `TextColumn::make()->badge()` (not deprecated `BadgeColumn`) for status/role visualization
9. **Empty States**: Always configure custom empty states with helpful messaging and actions
10. **Feedback**: Use `->helperText()`, `->hintIcon()`, and `->placeholder()` to guide users
11. **ToggleButton**: Use `ToggleButton` instead of basic `Toggle` for advanced boolean input with better UX

### Responsive Design
12. **Mobile-First**: Design for mobile first, then enhance for larger screens
13. **Responsive Columns**: Use `->columns(['default' => 1, 'sm' => 2, 'xl' => 3])` for adaptive layouts
14. **Responsive Span**: Use `->columnSpan()` arrays to control field width across breakpoints
15. **Responsive Order**: Use `->columnOrder()` to reorder fields for better mobile UX
16. **Breakpoint Consistency**: Use Tailwind breakpoints: `default`, `sm`, `md`, `lg`, `xl`, `2xl`
17. **Scrollable Tabs**: Use `->scrollable()` for tabs to prevent wrapping on mobile

### Performance
18. **Schema Utilities**: Use `Get` and `Set` from `Filament\Schemas\Components\Utilities` for reactive fields
19. **Performance**: Wrap expensive computations in `@island` directive
20. **Islands Architecture**: Use `@island` for widgets and charts to prevent full page re-renders
21. **Tab Persistence**: Use `->persistTabInQueryString()` for shareable links or `->persistTab()` for session storage

### Filters & Search
22. **Custom Filters**: Use `->query()` closures for complex filter logic
23. **Filter Layout**: Use `FiltersLayout::AboveContent` for prominent filter placement
24. **Searchable Columns**: Enable `->searchable()` and `->sortable()` on appropriate columns
25. **Toggleable Columns**: Use `->toggleable()` to let users customize table views

### Navigation & Pages
26. **Navigation Sort**: Use `protected static ?int $navigationSort` to control menu order
27. **Record Title**: Set `protected static ?string $recordTitleAttribute` for breadcrumbs
28. **Custom Titles**: Override `getTitle()` for localized page titles
29. **Redirect URLs**: Override `getRedirectUrl()` to control post-action navigation

### Testing
30. **Authentication**: Always use `actingAs($user)` before interacting with resources
31. **Comprehensive Coverage**: Test all CRUD operations, filters, and search functionality
32. **Pest Livewire**: Use `livewire()` helper and v5-specific assertions
33. **Form Assertions**: Use `assertHasNoFormErrors()` for form validation
34. **Table Assertions**: Use `assertCanSeeTableRecords()` and `assertCannotSeeTableRecords()`

### Action Safety
35. **Delete Confirmation**: Always use `->requiresConfirmation()` for delete actions
36. **Modal Customization**: Customize modal heading, description, and button labels
37. **Bulk Action Safety**: Require confirmation for all destructive bulk actions
38. **Irreversible Actions**: Add warnings for actions that cannot be undone

### Testing Requirements
39. **Mandatory Tests**: Every resource MUST have corresponding Pest tests
40. **Form Tests**: Test form schema, validation, and submission
41. **Table Tests**: Test columns, filters, search, and actions
42. **Page Tests**: Test page rendering and header actions
43. **Action Tests**: Test all actions with confirmation flows
44. **Authorization Tests**: Test policy-based access control
45. **Test Organization**: Use MARK comments to organize test sections
46. **Run All Tests**: Always verify all tests pass before committing
47. **Test Coverage**: Maintain minimum 80% code coverage

### Testing a Resource (v5)

**⚠️ IMPORTANT: Test Creation Checklist**

For EVERY resource you create, generate comprehensive tests covering:

- [ ] **Page Rendering**: List, Create, Edit pages render successfully
- [ ] **Form Schema**: All form fields exist with correct types
- [ ] **Form Validation**: Required fields, validation rules work
- [ ] **Create Operation**: Can create new records
- [ ] **Update Operation**: Can update existing records
- [ ] **Delete Operation**: Can delete records (with confirmation)
- [ ] **Table Columns**: All columns exist and display correctly
- [ ] **Table Search**: Searchable columns work
- [ ] **Table Filters**: All filters work correctly
- [ ] **Table Actions**: Record actions (edit, delete, view) work
- [ ] **Bulk Actions**: Bulk delete and other bulk actions work
- [ ] **Authorization**: Policies enforce correct permissions
- [ ] **Empty States**: Empty state displays when no records

**Test File Structure:**
```
tests/Feature/Filament/Resources/
├── Users/
│   ├── UserResourceTest.php
│   ├── Schemas/
│   │   └── UserFormTest.php
│   ├── Tables/
│   │   └── UsersTableTest.php
│   └── Pages/
│       ├── ListUsersTest.php
│       ├── CreateUserTest.php
│       └── EditUserTest.php
```

**Basic Resource Tests**
```php
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Models\User;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

it('can create a post', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(CreatePost::class)
        ->fillForm([
            'title' => 'My First Post',
            'slug' => 'my-first-post',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(Post::class, [
        'title' => 'My First Post',
    ]);
});

it('can list posts with filters', function () {
    $admin = User::factory()->admin()->create();
    Post::factory()->count(5)->create();

    actingAs($admin);

    livewire(ListPosts::class)
        ->assertCanSeeTableRecords(Post::all())
        ->callTableAction('edit', Post::first());
});

it('can delete a post', function () {
    $post = Post::factory()->create();
    $admin = User::factory()->admin()->create();

    actingAs($admin);

    livewire(ListPosts::class)
        ->callTableAction('delete', $post)
        ->assertSuccessful();

    $this->assertModelMissing($post);
});
```

**Comprehensive User Resource Tests (Advanced Patterns)**
```php
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

// Page rendering tests
it('can render list users page', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertSuccessful();
});

it('can render create user page', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->assertSuccessful();
});

// Create operation test
it('can create a user', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role' => 'user',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(User::class, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 'active',
        'role' => 'user',
    ]);
});

// Update operation test
it('can update a user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'status' => 'active',
        'role' => 'user',
    ]);
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'name' => 'New Name',
            'status' => 'inactive',
            'role' => 'editor',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(User::class, [
        'id' => $user->id,
        'name' => 'New Name',
        'status' => 'inactive',
        'role' => 'editor',
    ]);
});

// Delete operation test
it('can delete a user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete')
        ->assertHasNoErrors();

    $this->assertModelMissing($user);
});

// Table filter tests
it('filters users by status', function () {
    $admin = User::factory()->create();
    User::factory()->create(['status' => 'active']);
    User::factory()->create(['status' => 'inactive']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords(User::where('status', 'active')->get())
        ->assertCannotSeeTableRecords(User::where('status', 'inactive')->get());
});

it('filters users by role', function () {
    $admin = User::factory()->create();
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'user']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->filterTable('role', 'admin')
        ->assertCanSeeTableRecords(User::where('role', 'admin')->get())
        ->assertCannotSeeTableRecords(User::where('role', 'user')->get());
});

// Table search tests
it('searches users by name', function () {
    $admin = User::factory()->create();
    $john = User::factory()->create(['name' => 'John Doe']);
    $jane = User::factory()->create(['name' => 'Jane Smith']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->searchTable('John')
        ->assertCanSeeTableRecords([$john])
        ->assertCannotSeeTableRecords([$jane]);
});

it('searches users by email', function () {
    $admin = User::factory()->create();
    $user1 = User::factory()->create(['email' => 'john@example.com']);
    $user2 = User::factory()->create(['email' => 'jane@example.com']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->searchTable('john@')
        ->assertCanSeeTableRecords([$user1])
        ->assertCannotSeeTableRecords([$user2]);
});
```

### Testing Form Schema (v5)

Test that form fields exist with correct configuration:

```php
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButton;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

it('has correct form schema structure', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $form = Livewire::test(UserForm::class)
        ->getComponent();

    // Assert tabs exist
    expect($form->getComponents())
        ->toHaveCount(4) // Personal, Security, Verification, Two-Factor tabs
        ->and($form->getComponent(Tabs::class))->not->toBeNull();
});

it('has required fields in Personal tab', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->assertFormSchemaHas([
            'name' => TextInput::class,
            'email' => TextInput::class,
        ])
        ->assertFieldExists('name')
        ->assertFieldExists('email')
        ->fillForm(['name' => 'Test User'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required']);
});

it('validates email format', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->fillForm(['email' => 'invalid-email'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);
});

it('has status and role select fields', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => User::factory()->create()])
        ->assertFormSchemaHas([
            'status' => Select::class,
            'role' => Select::class,
        ])
        ->assertFieldExists('status')
        ->assertFieldExists('role');
});

it('has toggle button for two factor', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => User::factory()->create()])
        ->assertFormSchemaHas([
            'two_factor_confirmed_at' => ToggleButton::class,
        ]);
});

it('has datetime picker for email verification', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => User::factory()->create()])
        ->assertFormSchemaHas([
            'email_verified_at' => DateTimePicker::class,
        ]);
});
```

### Testing Table Configuration (v5)

Test table columns, filters, and actions:

```php
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

it('has correct table columns', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    User::factory()->count(3)->create();

    livewire(ListUsers::class)
        ->assertTableColumnExists('name', TextColumn::class)
        ->assertTableColumnExists('email', TextColumn::class)
        ->assertTableColumnExists('status', TextColumn::class)
        ->assertTableColumnExists('role', TextColumn::class)
        ->assertTableColumnExists('created_at', TextColumn::class);
});

it('has badge columns for status and role', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->loadTable()
        ->assertTableColumnStateSet('status', 'active', fn ($column) => $column->isBadge())
        ->assertTableColumnStateSet('role', 'user', fn ($column) => $column->isBadge());
});

it('has searchable columns', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create(['name' => 'Searchable User']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$user]);
});

it('has sortable columns', function () {
    $admin = User::factory()->create();
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Zoe']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->sortTable('name')
        ->assertCanSeeTableRecords(User::all(), inOrder: true);
});

it('has status filter', function () {
    $admin = User::factory()->create();
    User::factory()->create(['status' => 'active']);
    User::factory()->create(['status' => 'inactive']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertTableFilterExists('status', SelectFilter::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords(User::where('status', 'active')->get())
        ->assertCannotSeeTableRecords(User::where('status', 'inactive')->get());
});

it('has toggleable columns', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertTableColumnToggleable('updated_at')
        ->assertTableColumnHiddenByDefault('updated_at')
        ->assertTableColumnVisible('name');
});

it('displays empty state when no users', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertTableEmptyStateVisible()
        ->assertTableEmptyStateHeading('No Users');
});
```

### Testing Actions with Confirmation (v5)

Test that delete actions require and handle confirmation:

```php
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

it('requires confirmation for delete action', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete')
        ->assertActionRequiresConfirmation('delete');
});

it('deletes user after confirmation', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete', data: [])
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertModelMissing($user);
});

it('shows confirmation modal for bulk delete', function () {
    $admin = User::factory()->create();
    User::factory()->count(3)->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->callTableBulkAction('delete')
        ->assertActionRequiresConfirmation('delete');
});

it('bulk deletes users after confirmation', function () {
    $admin = User::factory()->create();
    $users = User::factory()->count(3)->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->callTableBulkAction('delete', data: [])
        ->assertHasNoErrors();

    foreach ($users as $user) {
        $this->assertModelMissing($user);
    }
});
```

### Testing Authorization (v5)

Test that policies control access:

```php
use App\Models\User;
use App\Policies\UserPolicy;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

it('allows admin to view users', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertSuccessful();
});

it('denies access to non-admin users', function () {
    $user = User::factory()->create();
    actingAs($user);

    livewire(ListUsers::class)
        ->assertStatus(403);
});

it('users can only edit their own profile', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    actingAs($user);

    livewire(EditUser::class, ['record' => $otherUser->getKey()])
        ->assertStatus(403);
});
```

### Running Tests - Verification Checklist

Before committing any changes, ALWAYS run:

```bash
# Run all Filament tests
php artisan test --filter=Filament

# Run specific resource tests
php artisan test tests/Feature/Filament/Resources/Users

# Run with coverage
php artisan test --coverage

# Run all tests to ensure nothing broke
php artisan test
```

**✅ Pre-commit Checklist:**
- [ ] All new resources have complete test coverage
- [ ] All tests pass: `php artisan test`
- [ ] No test failures or warnings
- [ ] Code coverage meets project requirements (e.g., 80%+)
- [ ] Form validation tests included
- [ ] Table filter/search tests included
- [ ] Action confirmation tests included
- [ ] Authorization tests included

**Key Testing Patterns:**
- Always use `actingAs($user)` before interacting with Filament resources
- Use `fillForm()` to set form values
- Use `call('create')` or `call('save')` to trigger form submission
- Use `callAction('delete')` for page header actions
- Use `callTableAction('edit', $record)` for table row actions
- Use `filterTable('filter_name', 'value')` for testing filters
- Use `searchTable('query')` for testing search functionality
- Use `assertCanSeeTableRecords()` and `assertCannotSeeTableRecords()` for visibility assertions
- Use `assertHasNoFormErrors()` to validate form submission
- Use `assertHasNoErrors()` for action assertions
- Use `assertActionRequiresConfirmation()` for delete actions
- Use `assertFormSchemaHas()` to verify field types
- Use `assertFieldExists()` to check field presence
- Use `assertTableColumnExists()` to verify table columns
- Use `assertTableFilterExists()` to verify filters
- Use `assertTableColumnToggleable()` to verify toggleable columns
- Use `assertTableEmptyStateVisible()` for empty state tests

### Common Test Assertions Reference

**Form Assertions:**
```php
->assertFormSchemaHas(['field' => TextInput::class])
->assertFieldExists('field_name')
->assertHasFormErrors(['field' => 'required'])
->assertHasNoFormErrors()
```

**Table Assertions:**
```php
->assertTableColumnExists('name', TextColumn::class)
->assertTableFilterExists('status', SelectFilter::class)
->assertCanSeeTableRecords($records)
->assertCannotSeeTableRecords($records)
->assertTableColumnToggleable('column_name')
->assertTableColumnHiddenByDefault('column_name')
->assertTableColumnVisible('column_name')
->assertTableEmptyStateVisible()
->assertTableEmptyStateHeading('No Records')
```

**Action Assertions:**
```php
->assertActionExists('delete')
->assertActionRequiresConfirmation('delete')
->assertHasNoErrors()
->assertNotified('Record deleted successfully')
->assertRedirect()
```

**Page Assertions:**
```php
->assertSuccessful()
->assertStatus(403) // For authorization failures
->assertViewHas('record')
```

### Testing Widgets (v5)

### Generating Tests (v5)

**Create Test File Structure:**
```bash
# Generate resource test
php artisan pest:test Feature/Filament/Resources/Users/UserResourceTest

# Generate form schema test
php artisan pest:test Feature/Filament/Resources/Users/Schemas/UserFormTest

# Generate table test  
php artisan pest:test Feature/Filament/Resources/Users/Tables/UsersTableTest

# Generate page tests
php artisan pest:test Feature/Filament/Resources/Users/Pages/ListUsersTest
php artisan pest:test Feature/Filament/Resources/Users/Pages/CreateUserTest
php artisan pest:test Feature/Filament/Resources/Users/Pages/EditUserTest
```

**Complete Test Example Template:**

Create `tests/Feature/Filament/Resources/Users/UserResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use function Pest\Livewire\livewire;
use function Pest\Laravel\actingAs;

// MARK: - Page Rendering Tests
it('can render list users page', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertSuccessful();
});

it('can render create user page', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->assertSuccessful();
});

it('can render edit user page', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->assertSuccessful();
});

// MARK: - Create Operation Tests
it('can create a user', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role' => 'user',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(User::class, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

it('validates required fields on create', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->fillForm([])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
        ]);
});

it('validates email format', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(CreateUser::class)
        ->fillForm(['email' => 'invalid-email'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);
});

// MARK: - Update Operation Tests
it('can update a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'status' => 'active',
        'role' => 'user',
    ]);
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->fillForm([
            'name' => 'New Name',
            'status' => 'inactive',
            'role' => 'editor',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(User::class, [
        'id' => $user->id,
        'name' => 'New Name',
    ]);
});

// MARK: - Delete Operation Tests
it('can delete a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete')
        ->assertHasNoErrors();

    $this->assertModelMissing($user);
});

it('requires confirmation for delete', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete')
        ->assertActionRequiresConfirmation('delete');
});

// MARK: - Table Filter Tests
it('filters users by status', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['status' => 'active']);
    User::factory()->create(['status' => 'inactive']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords(User::where('status', 'active')->get())
        ->assertCannotSeeTableRecords(User::where('status', 'inactive')->get());
});

it('filters users by role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'user']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->filterTable('role', 'admin')
        ->assertCanSeeTableRecords(User::where('role', 'admin')->get())
        ->assertCannotSeeTableRecords(User::where('role', 'user')->get());
});

// MARK: - Table Search Tests
it('searches users by name', function () {
    $admin = User::factory()->admin()->create();
    $john = User::factory()->create(['name' => 'John Doe']);
    $jane = User::factory()->create(['name' => 'Jane Smith']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->searchTable('John')
        ->assertCanSeeTableRecords([$john])
        ->assertCannotSeeTableRecords([$jane]);
});

it('searches users by email', function () {
    $admin = User::factory()->admin()->create();
    $user1 = User::factory()->create(['email' => 'john@example.com']);
    $user2 = User::factory()->create(['email' => 'jane@example.com']);
    actingAs($admin);

    livewire(ListUsers::class)
        ->searchTable('john@')
        ->assertCanSeeTableRecords([$user1])
        ->assertCannotSeeTableRecords([$user2]);
});

// MARK: - Bulk Action Tests
it('can bulk delete users', function () {
    $admin = User::factory()->admin()->create();
    $users = User::factory()->count(3)->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->callTableBulkAction('delete')
        ->assertHasNoErrors();

    foreach ($users as $user) {
        $this->assertModelMissing($user);
    }
});

// MARK: - Authorization Tests
it('denies access to non-admin users', function () {
    $user = User::factory()->create();
    actingAs($user);

    livewire(ListUsers::class)
        ->assertStatus(403);
});
```

### Action Safety Patterns (v5)

Always require confirmation for destructive actions to prevent accidental data loss.

**Delete Action with Confirmation**
```php
use Filament\Actions\DeleteAction;

DeleteAction::make()
    ->requiresConfirmation()
    ->modalHeading('Delete User')
    ->modalDescription('Are you sure you want to delete this user? This action cannot be undone.')
    ->modalSubmitActionLabel('Delete')
    ->modalCancelActionLabel('Cancel')
    ->color('danger');
```

**Bulk Delete with Confirmation**
```php
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

$table->toolbarActions([
    BulkActionGroup::make([
        DeleteBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading('Delete Selected Users')
            ->modalDescription('Are you sure you want to delete the selected users? All associated data will be permanently removed.')
            ->modalSubmitActionLabel('Delete All')
            ->modalCancelActionLabel('Cancel')
            ->color('danger'),
    ]),
]);
```

**Action Group with Mixed Safety**
```php
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

ActionGroup::make([
    ViewAction::make(),
    EditAction::make(),
    DeleteAction::make()
        ->requiresConfirmation()
        ->modalHeading('Delete Record')
        ->modalDescription('This will permanently delete the record.')
        ->color('danger'),
])
    ->icon(Heroicon::EllipsisVertical)
    ->tooltip('Actions');
```

**Custom Confirmation with Additional Fields**
```php
DeleteAction::make()
    ->requiresConfirmation()
    ->modalHeading('Delete Project')
    ->modalDescription('This will delete the project and all associated tasks.')
    ->modalForm([
        TextInput::make('confirmation')
            ->label('Type "DELETE" to confirm')
            ->required()
            ->rule('in:DELETE'),
    ])
    ->modalSubmitActionLabel('Delete Project')
    ->modalCancelActionLabel('Cancel');
```

**Best Practices:**
1. **Always require confirmation** for delete actions
2. **Clear messaging**: Explain what will be deleted
3. **Warn about permanence**: Mention if action is irreversible
4. **Distinct buttons**: Use "Delete" not "Submit" for submit label
5. **Color coding**: Use `color('danger')` for destructive actions
6. **Bulk actions**: Extra important for operations affecting multiple records
7. **Additional validation**: Consider requiring text confirmation for critical deletions

### Testing Widgets (v5)
```php
use App\Filament\Widgets\StatsOverview;
use function Pest\Livewire\livewire;

it('displays stats widget', function () {
    livewire(StatsOverview::class)
        ->assertViewHas('stats')
        ->assertSuccessful();
});
```

## Important v5 Concepts

### Schema vs Form
- **Schema**: The new v5 way - returns `Schema` type, uses `->components([...])` at top level
- **Form**: The old v3 way - returns `Form` type, uses `->schema([...])` at top level
- Filament v5 generates resources using `Schema`, not `Form`

### Actions and Their Locations
- `recordActions([...])` - Inline actions per row in tables
- `toolbarActions([...])` - Header/footer actions for bulk operations
- `headerActions()` - Actions in page headers (Create, Edit pages)
- Always use `Filament\Actions\*` namespace

### Component Locations
- **Form fields** (TextInput, Select, DatePicker, etc.): `Filament\Forms\Components\*`
- **Table columns**: `Filament\Tables\Columns\*`
- **Layout components** (Section, Grid, Tabs, Fieldset, Group, etc.): `Filament\Schemas\Components\*`
- **Infolists**: `Filament\Infolists\Components\*`

### Relationship Configuration
- Define the relationship method on the model: `public function comments() { return $this->hasMany(...); }`
- Register in resource: `getRelations()` returns array of RelationManager classes
- Each RelationManager has separate `form()` and `table()` methods (using `Schema` and `Table`)

## Anti-patterns to Avoid

### ❌ Wrong: Using v3 Form Signature in v5
```php
// ❌ WRONG - v3 pattern
public static function form(Form $form): Form
{
    return $form->schema([...]);
}
```

**✅ CORRECT - v5 pattern**
```php
// ✅ CORRECT - v5 pattern
public static function form(Schema $schema): Schema
{
    return $schema->components([...]);
}
```

---

### ❌ Wrong: Mixing Action Namespaces
```php
// ❌ WRONG - Mixing namespaces
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Actions\DeleteAction;

$table->actions([
    EditAction::make(),    // Wrong namespace
    DeleteAction::make(),  // Wrong namespace
]);
```

**✅ CORRECT: Unified Action Namespace**
```php
// ✅ CORRECT - All from Filament\Actions\*
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

$table->recordActions([
    EditAction::make(),
    DeleteAction::make(),
]);
```

---

### ❌ Wrong: Using Deprecated BadgeColumn
```php
// ❌ WRONG - BadgeColumn is deprecated in Filament v5
use Filament\Tables\Columns\BadgeColumn;

BadgeColumn::make('status')
    ->colors([
        'success' => 'active',
        'danger' => 'inactive',
    ]),
```

**✅ CORRECT: Use TextColumn with badge() Method**
```php
// ✅ CORRECT - TextColumn with badge() is the v5 way
use Filament\Tables\Columns\TextColumn;

TextColumn::make('status')
    ->badge()
    ->colors([
        'success' => 'active',
        'danger' => 'inactive',
    ])
    ->icons([
        'success' => Heroicon::OutlinedCheckCircle,
        'danger' => Heroicon::OutlinedXCircle,
    ]),
```

**Why badge() is better:**
- `BadgeColumn` is deprecated and will be removed
- `TextColumn::make()->badge()` provides the same visual style
- Full compatibility with all TextColumn features (icons, copyable, etc.)
- Consistent API across all column types

---

### ❌ Wrong: Using Actions Without ActionGroup for Record Actions
```php
// ❌ WRONG - Actions directly in recordActions without grouping
// ❌ WRONG - Delete without confirmation
$table->recordActions([
    EditAction::make(),
    DeleteAction::make(),
    ViewAction::make(),
]);
```

**✅ CORRECT: Use ActionGroup for Record Actions**
```php
// ✅ CORRECT - Actions wrapped in ActionGroup for better UX
use Filament\Actions\ActionGroup;

$table->recordActions([
    ActionGroup::make([
        ViewAction::make(),
        EditAction::make(),
        DeleteAction::make()
            ->requiresConfirmation()
            ->modalHeading('Delete Record')
            ->modalDescription('Are you sure you want to delete this record?')
            ->modalSubmitActionLabel('Delete')
            ->modalCancelActionLabel('Cancel'),
    ])
        ->icon(Heroicon::EllipsisVertical)
        ->label('Actions')
        ->tooltip('Actions')
        ->button() // Optional: use button style
        ->color('primary')
        ->size('sm'),
]);
```

**Benefits of ActionGroup:**
- Cleaner table UI with dropdown menu instead of multiple buttons
- Better mobile responsiveness
- Consistent with Filament v5 design patterns
- Customizable icon, color, size, and style

---

### ❌ Wrong: Using Old Bulk Action Syntax
```php
// ❌ WRONG - v3 syntax
// ❌ WRONG - Delete without confirmation
$table->bulkActions([
    DeleteBulkAction::make(),
    ExportBulkAction::make(),
]);
```

**✅ CORRECT: v5 Bulk Action Grouping**
```php
// ✅ CORRECT - v5 syntax with BulkActionGroup
// ✅ CORRECT - Delete requires confirmation
$table->toolbarActions([
    BulkActionGroup::make([
        DeleteBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading('Delete Selected Records')
            ->modalDescription('Are you sure you want to delete the selected records? This action cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->modalCancelActionLabel('Cancel'),
        ExportBulkAction::make(),
    ]),
]);
```

---

### ❌ Wrong: Hardcoding UI Text
```php
// ❌ WRONG - No localization
TextInput::make('name')
    ->label('User Name')
    ->helperText('Enter the full name'),

Button::make('Save')
    ->label('Click to save'),
```

**✅ CORRECT: Using Localization**
```php
// ✅ CORRECT - All text localized
TextInput::make('name')
    ->label(__('validation.attributes.name'))
    ->helperText(__('messages.name_helper')),

Button::make('Save')
    ->label(__('actions.save')),
```

---

### ❌ Wrong: Combining Form/Table Logic in Single Class
```php
// ❌ WRONG - Too much responsibility
class PostResource extends Resource
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ... 50 more form fields ...
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
            // ... 30 more columns ...
        ]);
    }
}
```

**✅ CORRECT: Separated Configuration Classes**
```php
// ✅ CORRECT - Separated concerns
class PostResource extends Resource
{
    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }
}

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Form fields only
        ]);
    }
}

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            // Table columns only
        ]);
    }
}
```

---

### ❌ Wrong: Missing File Upload Visibility
```php
// ❌ WRONG - No explicit visibility
FileUpload::make('avatar')
    ->directory('avatars')
    ->required(),
```

**✅ CORRECT: Explicit Visibility**
```php
// ✅ CORRECT - Explicit visibility set
FileUpload::make('avatar')
    ->directory('avatars')
    ->visibility('public')  // or 'private'
    ->required(),
```

---

### ❌ Wrong: Unclosed Livewire Component Tags
```blade
{{-- ❌ WRONG - Self-closing tag --}}
<livewire:filament.widgets.stats-widget />

{{-- ❌ WRONG - Unclosed tag --}}
<livewire:filament.widgets.stats-widget>
```

**✅ CORRECT: Properly Closed Tags**
```blade
{{-- ✅ CORRECT - Properly closed --}}
<livewire:filament.widgets.stats-widget></livewire:filament.widgets.stats-widget>
```

---

### ❌ Wrong: Full Page Re-renders for Expensive Operations
```blade
{{-- ❌ WRONG - Expensive chart re-renders entire page --}}
<div class="grid grid-cols-3 gap-6">
    <x-filament::stats :stats="$this->veryExpensiveStats" />
    <livewire:chart-widget />
    <!-- Other expensive components -->
</div>
```

**✅ CORRECT: Using Islands for Performance**
```blade
{{-- ✅ CORRECT - Isolated regions --}}
<div class="grid grid-cols-3 gap-6">
    @island
        <x-filament::stats :stats="$this->veryExpensiveStats" />
        <livewire:chart-widget />
    @endisland
</div>
```

---

### ❌ Wrong: Storing Unvalidated User Input
```php
// ❌ WRONG - No validation
$post = Post::create($data);
```

**✅ CORRECT: Validation in Resource/Page**
```php
// ✅ CORRECT - Validation via form schema
class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;
    
    // Validation is inherited from PostForm schema
    // via Resource's form() method
}
```

---

### ❌ Wrong: Testing Without Authentication
```php
// ❌ WRONG - No authentication
it('can create a post', function () {
    livewire(CreatePost::class)
        ->fillForm(['title' => 'Test'])
        ->call('create');
});
```

**✅ CORRECT: Always Authenticate**
```php
// ✅ CORRECT - Authenticated context
it('can create a post', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    livewire(CreatePost::class)
        ->fillForm(['title' => 'Test'])
        ->call('create')
        ->assertSuccessful();
});
```

## References & Official Documentation

### Official Filament v5 Documentation
- **Main Docs**: https://filamentphp.com/docs
- **Resources**: https://filamentphp.com/docs/resources/resources
- **Forms & Schemas**: https://filamentphp.com/docs/forms/schemas
- **Tables**: https://filamentphp.com/docs/tables/tables
- **Actions**: https://filamentphp.com/docs/actions/overview
- **Widgets**: https://filamentphp.com/docs/widgets/overview
- **Pages**: https://filamentphp.com/docs/pages/pages
- **Relations**: https://filamentphp.com/docs/resources/relations
- **Authorization**: https://filamentphp.com/docs/authorization

### Related Laravel Ecosystem
- **Laravel**: https://laravel.com/docs
- **Livewire 4**: https://livewire.laravel.com/docs
- **Laravel Policies**: https://laravel.com/docs/authorization#creating-policies
- **Laravel Validation**: https://laravel.com/docs/validation
- **Tailwind CSS v4**: https://tailwindcss.com/docs
- **Alpine.js**: https://alpinejs.dev

### Pest Testing
- **Pest Documentation**: https://pestphp.com/docs
- **Pest Livewire Plugin**: https://pestphp.com/docs/plugins/livewire
- **Laravel Testing**: https://laravel.com/docs/testing

### Version Requirements
- **PHP**: 8.3 or higher
- **Laravel**: 12.x
- **Filament**: 5.x
- **Livewire**: 4.x
- **Pest**: 4.x
