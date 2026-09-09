@php
    $other = $showingThread ? $this->otherParticipant($this->activeConversation) : null;
@endphp

<div class="flex items-center gap-3 bg-gradient-to-r from-primary-600 to-primary-500 px-4 py-3 text-white">
    @if ($showingThread && $other)
        <button type="button" wire:click="backToList" class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-white/90 transition hover:bg-white/15" aria-label="{{ __('Back') }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>
        @include('livewire.partials.chat-avatar', ['user' => $other, 'size' => 36])
        <span class="min-w-0 flex-1 truncate font-semibold">{{ $other->display_name ?: $other->name }}</span>
    @else
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-5 w-5 flex-shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
        </svg>
        <span class="min-w-0 flex-1 truncate font-semibold">{{ __('Messages') }}</span>
    @endif

    <button type="button" wire:click="toggleOpen" class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-white/90 transition hover:bg-white/15" aria-label="{{ __('Close') }}">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    </button>
</div>

@if ($showingThread)
    @include('livewire.partials.chat-thread', ['isPopup' => true])
@else
    @include('livewire.partials.chat-sidebar')
@endif
