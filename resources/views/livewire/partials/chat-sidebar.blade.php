<div class="border-b border-gray-100 p-3 dark:border-white/10">
    <div class="relative">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-gray-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input
            type="text"
            wire:model.live.debounce.300ms="userSearch"
            placeholder="{{ __('Start a chat with...') }}"
            class="fi-input block w-full rounded-xl border-none bg-gray-100 py-2 ps-9 pe-3 text-sm text-gray-950 shadow-sm ring-1 ring-transparent transition focus:bg-white focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:focus:bg-white/10"
        />
    </div>

    @if ($userSearch !== '')
        <div class="mt-2 max-h-48 space-y-0.5 overflow-y-auto">
            @forelse ($this->otherUsers as $user)
                <button
                    type="button"
                    wire:click="startConversationWith({{ $user->id }})"
                    class="flex w-full items-center gap-2.5 rounded-xl px-2 py-2 text-start text-sm transition hover:bg-gray-50 dark:hover:bg-white/5"
                >
                    @include('livewire.partials.chat-avatar', ['user' => $user, 'size' => 28])
                    <span class="min-w-0 flex-1 truncate font-medium text-gray-950 dark:text-white">{{ $user->display_name ?: $user->name }}</span>
                </button>
            @empty
                <p class="px-2 py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No matching users.') }}</p>
            @endforelse
        </div>
    @endif
</div>

<div class="flex-1 overflow-y-auto">
    @forelse ($this->conversations as $conversation)
        @php($other = $this->otherParticipant($conversation))
        @continue(! $other)
        @php($isUnread = $this->isConversationUnread($conversation))
        <button
            type="button"
            wire:click="selectConversation({{ $conversation->id }})"
            @class([
                'flex w-full items-center gap-3 px-4 py-3 text-start transition hover:bg-gray-50 dark:hover:bg-white/5',
                'bg-primary-50/70 dark:bg-primary-500/10' => $activeConversationId === $conversation->id,
            ])
        >
            <span class="relative flex-shrink-0">
                @include('livewire.partials.chat-avatar', ['user' => $other, 'size' => 40])
                @if ($isUnread)
                    <span class="absolute -start-0.5 -top-0.5 h-3 w-3 rounded-full bg-primary-600 ring-2 ring-white dark:ring-gray-900"></span>
                @endif
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                    <span @class([
                        'truncate text-sm text-gray-950 dark:text-white',
                        'font-semibold' => $isUnread,
                        'font-medium' => ! $isUnread,
                    ])>{{ $other->display_name ?: $other->name }}</span>
                    @if ($conversation->last_message_at)
                        <span class="flex-shrink-0 text-[11px] text-gray-400">{{ $conversation->last_message_at->diffForHumans(null, true) }}</span>
                    @endif
                </span>
            </span>
        </button>
    @empty
        <div class="flex h-full flex-col items-center justify-center gap-2 p-8 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" class="h-10 w-10 text-gray-300 dark:text-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No conversations yet — search a colleague above to start one.') }}</p>
        </div>
    @endforelse
</div>
