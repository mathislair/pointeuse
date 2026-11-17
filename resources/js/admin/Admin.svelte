<script>
    import { router } from '../lib/router.svelte.js';
    import Planning from './Planning.svelte';
    import Pointages from './Pointages.svelte';
    import Recap from './Recap.svelte';
    import Workers from './Workers.svelte';
    import Restaurants from './Restaurants.svelte';

    const nav = [
        { href: '/admin', label: 'Planning', component: Planning },
        { href: '/admin/pointages', label: 'Pointages', component: Pointages },
        { href: '/admin/recap', label: 'Récap du mois', component: Recap },
        { href: '/admin/interimaires', label: 'Intérimaires', component: Workers },
        { href: '/admin/restaurants', label: 'Restaurants', component: Restaurants },
    ];

    const current = $derived(nav.find((n) => n.href === router.path) ?? nav[0]);
    let open = $state(false);

    $effect(() => {
        router.path;
        open = false;
    });
</script>

<div class="min-h-screen bg-gray-50">
    <nav class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <div class="flex">
                    <a href="/admin" class="flex shrink-0 items-center gap-2 font-semibold text-gray-900">
                        <svg class="h-8 w-8 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Pointeuse
                    </a>
                    <div class="hidden space-x-5 whitespace-nowrap sm:-my-px sm:ms-8 sm:flex">
                        {#each nav as n}
                            <a href={n.href} class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition {current === n ? 'border-emerald-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'}">{n.label}</a>
                        {/each}
                    </div>
                </div>

                <div class="hidden items-center gap-2 sm:flex">
                    <a href="/" class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">Ouvrir la pointeuse →</a>
                </div>

                <div class="-me-2 flex items-center sm:hidden">
                    <button type="button" onclick={() => (open = !open)} class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500" aria-label="Menu">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            {#if open}
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            {:else}
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            {/if}
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {#if open}
            <div class="sm:hidden">
                <div class="space-y-1 pt-2 pb-3">
                    {#each nav as n}
                        <a href={n.href} class="block border-l-4 py-2 pe-4 ps-3 text-base font-medium {current === n ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800'}">{n.label}</a>
                    {/each}
                </div>
                <div class="border-t border-gray-200 pt-1 pb-1">
                    <a href="/" class="block py-2 pe-4 ps-3 text-base font-medium text-gray-600 hover:bg-gray-50">Ouvrir la pointeuse →</a>
                </div>
            </div>
        {/if}
    </nav>

    <current.component />
</div>
