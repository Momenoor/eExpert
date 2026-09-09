<x-filament-panels::page>
    <div
        x-data="{
            scrollToBottom() {
                $nextTick(() => {
                    const el = this.$refs.messageList;
                    if (el) { el.scrollTop = el.scrollHeight; }
                });
            },
        }"
        x-init="scrollToBottom()"
        x-on:message-sent.window="scrollToBottom()"
        class="fi-chat-page grid grid-cols-1 gap-4 lg:grid-cols-[20rem_1fr]"
        style="height: calc(100vh - 12rem);"
    >
        {{-- Sidebar: existing conversations + start a new one --}}
        <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-3 dark:border-white/10">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="userSearch"
                    placeholder="{{ __('Start a chat with...') }}"
                    class="fi-input block w-full rounded-lg border-none bg-gray-50 px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                />

                @if ($userSearch !== '')
                    <div class="mt-2 max-h-48 space-y-1 overflow-y-auto">
                        @forelse ($this->otherUsers as $user)
                            <button
                                type="button"
                                wire:click="startConversationWith({{ $user->id }})"
                                class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <img src="{{ $user->getFilamentAvatarUrl() ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->display_name ?: $user->name) }}"
                                     class="h-7 w-7 flex-shrink-0 rounded-full object-cover" alt="" />
                                <span class="truncate text-gray-950 dark:text-white">{{ $user->display_name ?: $user->name }}</span>
                            </button>
                        @empty
                            <p class="px-2 py-1.5 text-sm text-gray-500 dark:text-gray-400">{{ __('No matching users.') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto">
                @forelse ($this->conversations as $conversation)
                    @php($other = $this->otherParticipant($conversation))
                    @continue(! $other)
                    <button
                        type="button"
                        wire:click="selectConversation({{ $conversation->id }})"
                        @class([
                            'flex w-full items-center gap-3 border-b border-gray-100 px-3 py-3 text-start hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5',
                            'bg-primary-50 dark:bg-primary-500/10' => $activeConversationId === $conversation->id,
                        ])
                    >
                        <img src="{{ $other->getFilamentAvatarUrl() ?? 'https://ui-avatars.com/api/?name=' . urlencode($other->display_name ?: $other->name) }}"
                             class="h-9 w-9 flex-shrink-0 rounded-full object-cover" alt="" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $other->display_name ?: $other->name }}</span>
                                @if ($this->isConversationUnread($conversation))
                                    <span class="h-2 w-2 flex-shrink-0 rounded-full bg-primary-600"></span>
                                @endif
                            </span>
                            @if ($conversation->last_message_at)
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $conversation->last_message_at->diffForHumans() }}</span>
                            @endif
                        </span>
                    </button>
                @empty
                    <p class="p-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No conversations yet — search a colleague above to start one.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Thread --}}
        <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            @if ($this->activeConversation && ($other = $this->otherParticipant($this->activeConversation)))
                <div class="flex items-center gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                    <img src="{{ $other->getFilamentAvatarUrl() ?? 'https://ui-avatars.com/api/?name=' . urlencode($other->display_name ?: $other->name) }}"
                         class="h-8 w-8 rounded-full object-cover" alt="" />
                    <span class="font-medium text-gray-950 dark:text-white">{{ $other->display_name ?: $other->name }}</span>
                </div>

                <div x-ref="messageList" class="flex-1 space-y-3 overflow-y-auto p-4">
                    @foreach ($this->messages as $message)
                        @php($isMine = $message->user_id === auth()->id())
                        <div @class(['flex', 'justify-end' => $isMine])>
                            <div @class([
                                'max-w-md rounded-2xl px-4 py-2 text-sm',
                                'bg-primary-600 text-white' => $isMine,
                                'bg-gray-100 text-gray-950 dark:bg-white/10 dark:text-white' => ! $isMine,
                            ])>
                                <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                <p @class([
                                    'mt-1 text-[11px] opacity-70',
                                ])>{{ $message->created_at->format('H:i') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="sendMessage" x-on:submit="window.dispatchEvent(new CustomEvent('message-sent'))" class="flex items-center gap-2 border-t border-gray-200 p-3 dark:border-white/10">
                    <input
                        type="text"
                        wire:model="body"
                        autocomplete="off"
                        placeholder="{{ __('Type a message...') }}"
                        class="fi-input block w-full rounded-lg border-none bg-gray-50 px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    />
                    <button type="submit" class="fi-btn fi-btn-color-primary flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        {{ __('Send') }}
                    </button>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Pick a conversation, or search a colleague to start one.') }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
