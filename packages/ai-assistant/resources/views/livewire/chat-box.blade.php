<div class="flex flex-col h-[600px] bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $this->conversation?->title ?? 'AI Assistant' }}
        </h3>
        <button
            wire:click="newConversation"
            class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
        >
            New Chat
        </button>
    </div>

    {{-- Messages --}}
    <div class="flex-1 overflow-y-auto p-4 space-y-4" id="ai-chat-messages">
        @forelse ($this->messages as $message)
            <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] rounded-lg px-4 py-2 {{ $message->role === 'user' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' }}">
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! \Illuminate\Support\Str::markdown($message->content) !!}
                    </div>
                </div>
            </div>
        @empty
            <div class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
                <p>Start a conversation by typing a message below.</p>
            </div>
        @endforelse

        @if ($isLoading)
            <div class="flex justify-start">
                <div class="bg-gray-100 dark:bg-gray-800 rounded-lg px-4 py-2">
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Input --}}
    <div class="border-t border-gray-200 dark:border-gray-700 p-4">
        <form wire:submit="send" class="flex gap-2">
            <input
                type="text"
                wire:model="input"
                placeholder="Type your message..."
                class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                @disabled($isLoading)
            />
            <button
                type="submit"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="send">Send</span>
                <span wire:loading wire:target="send">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
            </button>
        </form>
    </div>

    @script
    <script>
        Livewire.hook('morph.updated', () => {
            const container = document.getElementById('ai-chat-messages');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    </script>
    @endscript
</div>
