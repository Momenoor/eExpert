@php
    $isPopup = $mode === 'popup';
    $showingThread = (bool) $this->activeConversation;
@endphp

<div
    x-data="{
        scrollToBottom() {
            $nextTick(() => {
                const el = this.$refs.messageList;
                if (el) { el.scrollTop = el.scrollHeight; }
            });
        },
        fillViewport() {
            const el = this.$refs.pageShell;
            if (! el) { return; }
            el.style.height = Math.max(320, window.innerHeight - el.getBoundingClientRect().top - 24) + 'px';
        },
    }"
    x-init="
        scrollToBottom();
        if ($refs.pageShell) {
            fillViewport();
            window.addEventListener('resize', fillViewport);
        }
    "
    x-on:message-sent.window="scrollToBottom()"
    wire:poll.20s="$refresh"
    @if ($isPopup) wire:key="chat-widget-popup" @endif
    class="fi-chat-widget {{ $isPopup ? 'fixed bottom-6 end-6 z-50 flex flex-col items-end gap-3' : '' }}"
>
    @if ($isPopup)
        {{-- Floating launcher bubble --}}
        <button
            type="button"
            wire:click="toggleOpen"
            @class([
                'group relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg shadow-primary-600/30 ring-1 ring-white/10 transition-transform hover:scale-105 active:scale-95',
            ])
            aria-label="{{ __('Chat') }}"
        >
            <svg wire:loading.remove wire:target="toggleOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-6 w-6 transition-transform duration-200" :class="$wire.isOpen ? 'rotate-90 scale-90 opacity-0 absolute' : ''">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" :class="$wire.isOpen ? '' : 'hidden'">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>

            @if ($this->unreadCount > 0)
                <span
                    x-show="! $wire.isOpen"
                    class="absolute -end-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger-500 px-1 text-[11px] font-semibold leading-none text-white ring-2 ring-white dark:ring-gray-950"
                >{{ $this->unreadCount }}</span>
            @endif
        </button>

        <div
            x-show="$wire.isOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            style="display: none;"
            class="flex h-[32rem] w-[23rem] max-w-[calc(100vw-3rem)] origin-bottom-end flex-col overflow-hidden rounded-3xl border border-gray-950/5 bg-white shadow-2xl shadow-gray-950/20 dark:border-white/10 dark:bg-gray-900"
        >
            @include('livewire.partials.chat-body', ['isPopup' => true, 'showingThread' => $showingThread])
        </div>
    @else
        <div
            x-ref="pageShell"
            class="fi-chat-page grid grid-cols-1 gap-4 overflow-hidden lg:grid-cols-[20rem_1fr]"
        >
            <div class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-gray-950/5 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                @include('livewire.partials.chat-sidebar')
            </div>

            <div class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-gray-950/5 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                @include('livewire.partials.chat-thread', ['isPopup' => false])
            </div>
        </div>
    @endif
</div>
