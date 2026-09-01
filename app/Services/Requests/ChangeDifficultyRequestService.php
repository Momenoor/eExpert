<?php

namespace App\Services\Requests;

use App\Enums\MatterDifficulty;
use App\Models\Matter;
use Filament\Forms\Components\Select;

class ChangeDifficultyRequestService extends BaseRequestService
{
    public static function createFormFields(): array
    {
        return [
            Select::make('new_difficulty')
                ->label(__('New Difficulty'))
                ->options(MatterDifficulty::class)
                ->disableOptionWhen(fn (string $value, $record): bool => $value === $record->difficulty->value)
                ->required(),
        ];
    }

    public static function prepareForCreation(array $data, Matter $matter): array
    {
        $comment = $data['comment'] ?? null;
        $extra = null;

        if (! empty($data['new_difficulty'])) {
            $difficulty = $data['new_difficulty'];
            $comment = __('New Difficulty MatterRequest').': '.$difficulty->getLabel().'. '.$comment;
            $extra = ['new_difficulty' => $difficulty->value];
        }

        return ['comment' => $comment, 'extra' => $extra];
    }

    public function approve(array $data = [], $component = null): void
    {
        $this->markApproved($data);

        $this->request->matter->update([
            'difficulty' => $this->request->extra['new_difficulty'],
        ]);

        $this->onApproveNotify();
        $this->refresh($component);
    }

    public function reject(array $data = [], $component = null): void
    {
        $this->markRejected($data);
        $this->onRejectNotify();
        $this->refresh($component);
    }
}
