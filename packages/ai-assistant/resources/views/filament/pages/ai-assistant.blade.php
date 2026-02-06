<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Sidebar: Conversation History --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Recent Conversations</h3>
                <div class="space-y-2 max-h-[500px] overflow-y-auto">
                    @forelse ($this->getConversations() as $conversation)
                        <button
                            wire:click="selectConversation({{ $conversation->id }})"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm truncate transition-colors {{ $conversationId === $conversation->id ? 'bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                        >
                            {{ $conversation->title ?? 'Untitled' }}
                        </button>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No conversations yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Main Chat Area --}}
        <div class="lg:col-span-3">
            <livewire:ai-assistant::chat-box :conversation-id="$conversationId" :key="$conversationId ?? 'new'" />
        </div>
    </div>
</x-filament-panels::page>
