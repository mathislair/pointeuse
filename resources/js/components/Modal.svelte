<script>
    let { title = '', onclose, size = 'md', children, footer } = $props();

    const widths = { sm: 'sm:max-w-md', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' };

    function onkeydown(e) {
        if (e.key === 'Escape') onclose?.();
    }
</script>

<svelte:window {onkeydown} />

<div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" role="presentation" onclick={(e) => e.target === e.currentTarget && onclose?.()}>
    <div class="fixed inset-0 bg-gray-500 opacity-75" role="presentation" onclick={() => onclose?.()}></div>
    <div class="relative z-10 mb-6 flex max-h-[90dvh] w-full flex-col overflow-hidden rounded-lg bg-white shadow-xl sm:mx-auto {widths[size]}" role="dialog" aria-modal="true" aria-label={title}>
        <div class="flex items-start justify-between gap-4 px-6 pt-6">
            <h2 class="text-lg font-medium text-gray-900">{title}</h2>
            {#if onclose}
                <button type="button" class="-mt-1 -mr-2 rounded-md p-1 text-gray-400 hover:text-gray-600" onclick={onclose} aria-label="Fermer">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                </button>
            {/if}
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-5">
            {@render children?.()}
        </div>
        {#if footer}
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                {@render footer()}
            </div>
        {/if}
    </div>
</div>
