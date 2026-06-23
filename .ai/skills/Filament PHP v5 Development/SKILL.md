---
name: filament-v5-laravel-13
description: >
  Expert guidance for building admin panels with Filament PHP v5 on Laravel 13.
  Covers resources, schemas, tables, actions, widgets, relation managers, and
  PHPUnit testing patterns. Use this skill whenever working with Filament
  resources, forms, tables, actions, widgets, pages, or any related Filament
  component. Also activate for queue jobs, Livewire components, or any Laravel
  feature in this stack.
compatible_agents:
  - Junie
  - Claude Code
  - Cursor
  - Gemini CLI
  - OpenCode
  - Codex
  - Copilot
  - Cline
  - Roo Code
  - Windsurf
tags:
  - filament
  - filament-v5
  - laravel
  - laravel-13
  - php-8.5
  - livewire-4
  - tailwind-4
  - phpunit
  - admin-panel
  - tall-stack
---

# Filament v5 + Laravel 13 Expert

## Stack Versions (EXACT — never assume otherwise)

| Package | Version |
|---|---|
| PHP | 8.5 |
| Laravel | 13.x |
| Filament | v5 |
| Livewire | v4 |
| Tailwind CSS | v4 |
| PHPUnit | v12 |
| Laravel Pint | v1 |

## Core Rules

1. **Search docs first.** Use `search-docs` before any implementation. Pass relevant `packages` to scope results. Never skip this step.
2. **Follow existing conventions.** Check sibling files for structure, naming, and approach before creating new files.
3. **Use Artisan make commands** for all new files (`php artisan make:`, `php artisan filament:`). Pass `--no-interaction`.
4. **Run Pint after every PHP change:** `vendor/bin/pint --dirty --format agent`
5. **PHPUnit only** — never write Pest tests. Use `php artisan make:test --phpunit {Name}`.
6. **Strict typing** — all methods must have return type declarations and parameter type hints.
7. **Localize all strings** — never hardcode UI text; always use `__('key')`.

---

## File Structure (v5 — Separated Architecture)

```
app/Filament/
├── Resources/
│   └── PostResource/
│       ├── PostResource.php          ← Main resource class
│       ├── Schemas/
│       │   └── PostForm.php          ← Form configuration (SEPARATE CLASS)
│       ├── Tables/
│       │   └── PostsTable.php        ← Table configuration (SEPARATE CLASS)
│       ├── Pages/
│       │   ├── ListPosts.php
│       │   ├── CreatePost.php
│       │   └── EditPost.php
│       └── RelationManagers/
│           └── CommentsRelationManager.php
├── Pages/
│   └── Dashboard.php
└── Widgets/
    ├── StatsOverviewWidget.php
    └── PostsChartWidget.php
```

Always split form and table logic into separate classes under `Schemas/` and `Tables/`.

---

## Namespace Reference (CRITICAL — wrong namespace = runtime error)

| Component type | Correct namespace |
|---|---|
| Form fields (`TextInput`, `Select`, `FileUpload`, `Repeater`, …) | `Filament\Forms\Components\` |
| Layout components (`Section`, `Grid`, `Tabs`, `Fieldset`, `Group`, …) | `Filament\Schemas\Components\` |
| Schema utilities (`Get`, `Set`) | `Filament\Schemas\Components\Utilities\` |
| Table columns (`TextColumn`, `IconColumn`, …) | `Filament\Tables\Columns\` |
| Table filters (`SelectFilter`, `Filter`, …) | `Filament\Tables\Filters\` |
| **All actions** (`EditAction`, `DeleteAction`, `CreateAction`, `BulkActionGroup`, …) | `Filament\Actions\` ← **never** use sub-namespaces |
| Infolist entries (`TextEntry`, `IconEntry`, …) | `Filament\Infolists\Components\` |
| Icons | `Filament\Support\Icons\Heroicon` (enum, e.g. `Heroicon::OutlinedPencil`) |

---

## v5 API Changes vs v3/v4

| Feature | ❌ Old (v3/v4) | ✅ v5 |
|---|---|---|
| Form method signature | `form(Form $form): Form` | `form(Schema $schema): Schema` |
| Form top-level | `->schema([...])` | `->components([...])` |
| Row actions | `->actions([...])` | `->recordActions([...])` |
| Bulk actions | `->bulkActions([...])` | `->toolbarActions([BulkActionGroup::make([...])])` |
| Actions namespace | `Filament\Tables\Actions\*` | `Filament\Actions\*` |
| Badge column | `BadgeColumn::make()` | `TextColumn::make()->badge()` |
| Toolbar actions method | `->toolbarActions([...])` on table | same, but wraps `CreateAction` etc. |

---

## Resource — Full Example

### `PostResource.php`

```php
namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\{CreatePost, EditPost, ListPosts};
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
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('messages.post');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.posts');
    }

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
            'index'  => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit'   => EditPost::route('/{record}/edit'),
        ];
    }
}
```

### `Schemas/PostForm.php`

```php
namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\{Select, Textarea, TextInput, Toggle};
use Filament\Schemas\Components\{Grid, Section, Tabs};
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('post-tabs')
                ->id('post-tabs')
                ->contained(false)
                ->scrollable()
                ->persistTabInQueryString()
                ->tabs([
                    Tabs\Tab::make(__('messages.general'))
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([
                            Section::make(__('messages.post_information'))
                                ->description(__('messages.post_information_description'))
                                ->icon(Heroicon::OutlinedInformationCircle)
                                ->columns(2)
                                ->schema([
                                    TextInput::make('title')
                                        ->label(__('validation.attributes.title'))
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                    TextInput::make('slug')
                                        ->label(__('messages.slug'))
                                        ->unique(ignoreRecord: true)
                                        ->required()
                                        ->columnSpanFull(),
                                    Select::make('status')
                                        ->label(__('messages.status'))
                                        ->options([
                                            'draft'     => __('messages.draft'),
                                            'published' => __('messages.published'),
                                        ])
                                        ->required()
                                        ->default('draft'),
                                ]),
                        ]),
                    Tabs\Tab::make(__('messages.content'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->schema([
                            Section::make(__('messages.post_body'))
                                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                                ->schema([
                                    Textarea::make('content')
                                        ->label(__('validation.attributes.content'))
                                        ->rows(10)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ]),
        ]);
    }
}
```

### `Tables/PostsTable.php`

```php
namespace App\Filament\Resources\Posts\Tables;

use Filament\Actions\{ActionGroup, BulkActionGroup, DeleteAction, DeleteBulkAction, EditAction};
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('validation.attributes.title'))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->iconPosition('before'),

                TextColumn::make('status')
                    ->label(__('messages.status'))
                    ->badge()
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'published',
                    ])
                    ->formatStateUsing(fn (string $state): string => __("messages.{$state}"))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('messages.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('messages.status'))
                    ->options([
                        'draft'     => __('messages.draft'),
                        'published' => __('messages.published'),
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->icon(Heroicon::OutlinedPencil),
                    DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading(__('messages.delete_post'))
                        ->modalDescription(__('messages.delete_post_description'))
                        ->modalSubmitActionLabel(__('messages.delete'))
                        ->modalCancelActionLabel(__('messages.cancel')),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->tooltip(__('messages.actions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading(__('messages.bulk_delete_posts'))
                        ->modalDescription(__('messages.bulk_delete_description'))
                        ->modalSubmitActionLabel(__('messages.delete'))
                        ->modalCancelActionLabel(__('messages.cancel')),
                ]),
            ])
            ->emptyStateHeading(__('messages.no_posts'))
            ->emptyStateDescription(__('messages.create_first_post'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }
}
```

### Pages

```php
// ListPosts.php
class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

// CreatePost.php
class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

// EditPost.php
class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalSubmitActionLabel(__('messages.delete'))
                ->modalCancelActionLabel(__('messages.cancel')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
```

---

## Form Component Patterns

### Reactive fields (Get / Set)

```php
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\{Get, Set};

Select::make('country')
    ->options(Country::class)
    ->live()
    ->afterStateUpdated(fn (Set $set) => $set('city', null)),

Select::make('city')
    ->options(fn (Get $get) => City::where('country_id', $get('country'))->pluck('name', 'id'))
    ->visible(fn (Get $get): bool => filled($get('country')))
    ->required(),
```

### Repeater for HasMany

```php
use Filament\Forms\Components\Repeater;

Repeater::make('items')
    ->relationship()          // binds to relationship matching field name
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('qty')->numeric()->required(),
    ])
    ->columns(2),
```

### File upload (always set visibility)

```php
use Filament\Forms\Components\FileUpload;

FileUpload::make('avatar')
    ->directory('avatars')
    ->visibility('public')    // REQUIRED — never omit
    ->image()
    ->required(),
```

### ToggleButton (preferred over Toggle)

```php
use Filament\Forms\Components\ToggleButton;

ToggleButton::make('is_active')
    ->label(__('messages.active'))
    ->onIcon(Heroicon::SolidEye)
    ->offIcon(Heroicon::OutlineEyeSlash)
    ->onColor('success')
    ->offColor('gray')
    ->columnSpanFull(),
```

---

## Table Column Patterns

```php
// Badge status column
TextColumn::make('status')->badge()->colors(['success' => 'active', 'danger' => 'inactive']),

// Copyable email
TextColumn::make('email')->copyable()->icon(Heroicon::OutlinedEnvelope)->iconPosition('before'),

// Toggleable + hidden by default
TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->toggleable(isToggledHiddenByDefault: true),

// Computed state
TextColumn::make('full_name')->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
```

---

## Responsive Layout

```php
Section::make()
    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
    ->schema([
        TextInput::make('name')
            ->columnSpan(['default' => 1, 'sm' => 2, 'xl' => 3])
            ->columnOrder(['default' => 2, 'xl' => 1]),

        TextInput::make('email')
            ->columnSpan(['default' => 1, 'xl' => 2])
            ->columnOrder(['default' => 1, 'xl' => 2]),

        Textarea::make('bio')->columnSpanFull(),
    ]),
```

Available breakpoints: `default` (< 640px), `sm` (≥ 640), `md` (≥ 768), `lg` (≥ 1024), `xl` (≥ 1280), `2xl` (≥ 1536).

---

## Relation Manager

```php
namespace App\Filament\Resources\Posts\RelationManagers;

use Filament\Actions\{CreateAction, DeleteAction, EditAction};
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';
    protected static ?string $recordTitleAttribute = 'content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('content')->required()->maxLength(1000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')->words(10)->searchable(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ]);
    }
}
```

Register in resource: `public static function getRelations(): array { return [CommentsRelationManager::class]; }`

---

## Queue Jobs — Safe Pattern for Bluehost / Shared Hosting

**Never use `SerializesModels` with Arabic data or shared hosting queue tables.**

```php
// ✅ CORRECT — pass only integer IDs, no SerializesModels
class ProcessMatterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    // NO SerializesModels trait

    public function __construct(
        public readonly int $matterId,
    ) {}

    public function handle(): void
    {
        $matter = Matter::withTrashed()->find($this->matterId);

        if (! $matter) {
            Log::error("ProcessMatterJob: matter {$this->matterId} not found");
            return;
        }

        // ... business logic
    }
}

// Dispatch after transaction commits
dispatch(new ProcessMatterJob($matter->id))->afterCommit();
```

**Bluehost cron (non-overlapping):**
```
*/16 * * * * php /path/to/artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

---

## Notifications & Polling (WebSocket alternative for shared hosting)

```php
// Send a database notification
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

Notification::make()
    ->title(__('messages.export_complete'))
    ->success()
    ->actions([
        Action::make('download')->url($url)->openUrlInNewTab(),
    ])
    ->sendToDatabase($user);
```

```blade
{{-- Livewire polling in Blade component --}}
<div wire:poll.16000ms="checkNotifications">
    {{-- notification list --}}
</div>
```

---

## PHPUnit Tests

### Setup

```php
<?php

namespace Tests\Feature\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\{CreatePost, EditPost, ListPosts};
use App\Models\{Post, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }
}
```

### Page rendering

```php
public function test_can_render_list_page(): void
{
    $this->get(PostResource::getUrl('index'))->assertSuccessful();
}

public function test_can_render_create_page(): void
{
    $this->get(PostResource::getUrl('create'))->assertSuccessful();
}

public function test_can_render_edit_page(): void
{
    $post = Post::factory()->create();
    $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertSuccessful();
}
```

### Livewire form tests

```php
use Livewire\Livewire;

public function test_can_create_post(): void
{
    Livewire::test(CreatePost::class)
        ->fillForm([
            'title'  => 'Hello World',
            'slug'   => 'hello-world',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(Post::class, ['title' => 'Hello World']);
}

public function test_validates_required_fields(): void
{
    Livewire::test(CreatePost::class)
        ->fillForm([])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required', 'slug' => 'required']);
}

public function test_can_update_post(): void
{
    $post = Post::factory()->create();

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(Post::class, ['id' => $post->id, 'title' => 'Updated']);
}
```

### Table tests

```php
use function Livewire\Livewire;

public function test_can_list_posts(): void
{
    $posts = Post::factory()->count(5)->create();

    Livewire::test(ListPosts::class)
        ->assertCanSeeTableRecords($posts);
}

public function test_can_search_by_title(): void
{
    $match  = Post::factory()->create(['title' => 'Unique Title']);
    $other  = Post::factory()->create(['title' => 'Other Post']);

    Livewire::test(ListPosts::class)
        ->searchTable('Unique')
        ->assertCanSeeTableRecords([$match])
        ->assertCannotSeeTableRecords([$other]);
}

public function test_can_filter_by_status(): void
{
    Post::factory()->create(['status' => 'published']);
    Post::factory()->create(['status' => 'draft']);

    Livewire::test(ListPosts::class)
        ->filterTable('status', 'published')
        ->assertCanSeeTableRecords(Post::where('status', 'published')->get())
        ->assertCannotSeeTableRecords(Post::where('status', 'draft')->get());
}

public function test_can_delete_post(): void
{
    $post = Post::factory()->create();

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->callAction('delete')
        ->assertHasNoErrors();

    $this->assertModelMissing($post);
}

public function test_can_bulk_delete_posts(): void
{
    $posts = Post::factory()->count(3)->create();

    Livewire::test(ListPosts::class)
        ->callTableBulkAction('delete', $posts)
        ->assertHasNoErrors();

    foreach ($posts as $post) {
        $this->assertModelMissing($post);
    }
}
```

### Running tests

```bash
# Single test file
php artisan test --compact tests/Feature/Filament/Resources/Posts/PostResourceTest.php

# Filter by method name
php artisan test --compact --filter=test_can_create_post

# All tests
php artisan test --compact
```

---

## Artisan Generation Commands

```bash
# Resource with all pages, form, table
php artisan filament:resource Post --generate --no-interaction

# Relation manager
php artisan filament:relation-manager PostResource comments --no-interaction

# Widget types
php artisan filament:widget StatsOverview --no-interaction
php artisan filament:widget PostsChart --chart --no-interaction
php artisan filament:widget RecentPosts --table --no-interaction

# Custom page
php artisan filament:page Settings --no-interaction
```

---

## Common Mistakes (NEVER DO THESE)

### ❌ Wrong form signature

```php
// WRONG
public static function form(Form $form): Form { return $form->schema([...]); }

// CORRECT
public static function form(Schema $schema): Schema { return $schema->components([...]); }
```

### ❌ Wrong action namespace

```php
use Filament\Tables\Actions\EditAction;   // WRONG
use Filament\Actions\EditAction;          // CORRECT
```

### ❌ BadgeColumn (deprecated)

```php
BadgeColumn::make('status')               // WRONG
TextColumn::make('status')->badge()       // CORRECT
```

### ❌ Old bulk/row action methods

```php
->actions([...])        // WRONG  → use ->recordActions([...])
->bulkActions([...])    // WRONG  → use ->toolbarActions([BulkActionGroup::make([...])])
```

### ❌ Missing file upload visibility

```php
FileUpload::make('file')                          // WRONG — visibility unset
FileUpload::make('file')->visibility('public')    // CORRECT
```

### ❌ Delete without confirmation

```php
DeleteAction::make()                              // WRONG
DeleteAction::make()->requiresConfirmation()      // CORRECT
```

### ❌ SerializesModels with Arabic payloads or shared hosting

```php
class MyJob { use SerializesModels; }  // WRONG on Bluehost / Arabic data
// CORRECT: pass only int IDs, no SerializesModels, use Model::withTrashed()->find($id)
```

### ❌ Hardcoded UI strings

```php
->label('User Name')                    // WRONG
->label(__('validation.attributes.name'))  // CORRECT
```

### ❌ Self-closing Livewire tags

```blade
<livewire:my-component />                               {{-- WRONG --}}
<livewire:my-component></livewire:my-component>         {{-- CORRECT --}}
```

---

## Property Type Reference (override correctly)

```php
// These have union types — MUST match exactly
protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
protected static string|UnitEnum|null $navigationGroup = null;
protected string $view = 'filament.pages.my-page';  // NOT static on Page/Widget
```

---

## Render Hooks & Islands

```php
// ServiceProvider — inject content without overriding views
FilamentView::registerRenderHook(
    PanelsRenderHook::BODY_START,
    fn (): string => Blade::render('<x-my-banner />'),
);
```

```blade
{{-- Islands — isolate expensive widgets from full-page re-renders --}}
@island
    <livewire:expensive-chart-widget></livewire:expensive-chart-widget>
@endisland
```

---

## Checklist Before Committing

- [ ] `vendor/bin/pint --dirty --format agent` — no formatting errors
- [ ] `php artisan test --compact` — all tests green
- [ ] All new resources have PHPUnit tests covering create, update, delete, filters, search
- [ ] All delete actions have `->requiresConfirmation()`
- [ ] All `FileUpload` fields have `->visibility()`
- [ ] No hardcoded strings — all use `__()`
- [ ] No `SerializesModels` on jobs that handle Arabic data or run on shared hosting
- [ ] All Livewire tags are properly closed
