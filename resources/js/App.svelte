<script>
    import { router } from './lib/router.svelte.js';
    import { appConfig } from './lib/config.svelte.js';
    import Pointeuse from './pointeuse/Pointeuse.svelte';
    import Admin from './admin/Admin.svelte';

    const isAdmin = $derived(router.path === '/admin' || router.path.startsWith('/admin/'));
</script>

<svelte:document onclick={router.handleClick} />

{#await appConfig.load()}
    <div class="flex min-h-dvh items-center justify-center text-gray-500">Chargement…</div>
{:then}
    {#if isAdmin}
        <Admin />
    {:else}
        <Pointeuse />
    {/if}
{:catch e}
    <div class="m-6 rounded-xl bg-red-50 p-4 text-red-800">Impossible de charger la configuration : {e.message}</div>
{/await}
