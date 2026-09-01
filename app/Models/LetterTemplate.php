<?php

namespace App\Models;

use App\Enums\LetterTemplateCategories;
use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\HeroBlock;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable('name', 'slug', 'subject', 'body', 'placeholders', 'locale', 'is_active', 'is_default', 'category')]
class LetterTemplate extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    public function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'placeholders' => 'array',
            'category' => LetterTemplateCategories::class,
        ];
    }

    public function matterLetters(): HasMany
    {
        return $this->hasMany(MatterLetter::class);
    }

    public function getFilamentRichContentField(): string
    {
        return 'body';
    }

    public function setUpRichContent()
    {
        $this->registerRichContent($this->getFilamentRichContentField())
            ->mentions([
                MentionProvider::make('@')
                    ->getSearchResultsUsing(fn ($query) => User::where('name', 'like', "%{$query}%")->pluck('name', 'id'))
                    ->getLabelsUsing(fn ($ids) => User::whereIn('id', $ids)->pluck('display_name', 'id'))
                    ->url(fn ($record) => route('filament.admin.resources.users.view', $record)),
            ])
            ->customBlocks([
                HeroBlock::class,
            ])
            ->toHtml();
    }
}
