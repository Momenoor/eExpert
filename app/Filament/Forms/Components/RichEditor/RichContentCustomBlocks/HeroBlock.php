<?php

namespace App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;

class HeroBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'hero';
    }

    public static function getLabel(): string
    {
        return 'Hero';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Configure the hero block')
            ->schema([
                TextInput::make('title'),
            ]);
    }

    /**
     * @throws \Throwable
     */
    public static function toPreviewHtml(array $config): string
    {
        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.hero.preview', [
            'heading' => 'Hero',
            'title' => $config['title'] ?? '',
        ])->render();
    }

    public static function toHtml(array $config, array $data): string
    {
        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.hero.preview', [
            'heading' => 'Hero',
            'title' => $config['title'] ?? '',
        ])->render();
    }
}
