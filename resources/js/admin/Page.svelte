<script>
    /** Page header band (title + subtitle + actions) and content container, like coffre's AuthenticatedLayout. */
    let { title = '', subtitle = '', heading, actions, children } = $props();
</script>

<header class="bg-white shadow-sm">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
        {#if heading}
            {@render heading()}
        {:else}
            <div>
                <h2 class="text-xl leading-tight font-semibold text-gray-800">{title}</h2>
                {#if subtitle}<p class="mt-0.5 text-sm text-gray-500">{subtitle}</p>{/if}
            </div>
        {/if}
        {#if actions}
            <div class="flex flex-wrap items-center gap-2">{@render actions()}</div>
        {/if}
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    {@render children?.()}
</main>
