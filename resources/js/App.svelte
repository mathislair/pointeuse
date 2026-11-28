<script>
    import { router } from './lib/router.svelte.js';
    import { appConfig } from './lib/config.svelte.js';
    import Pointeuse from './pointeuse/Pointeuse.svelte';
    import Mobile from './mobile/Mobile.svelte';
    import Admin from './admin/Admin.svelte';

    const isAdmin = $derived(router.path === '/admin' || router.path.startsWith('/admin/'));
    // /p/{slug}: the mobile time clock opened by scanning a restaurant's QR code.
    const isMobile = $derived(/^\/p\/[^/]+/.test(router.path));
</script>

<svelte:document onclick={router.handleClick} />

{#await appConfig.load()}
    <div class="flex min-h-dvh items-center justify-center text-gray-500">Chargement…</div>
{:then}
    {#if isAdmin}
        <Admin />
    {:else if isMobile}
        <Mobile />
    {:else}
        <Pointeuse />
    {/if}
{:catch e}
    <div class="m-6 rounded-xl bg-red-50 p-4 text-red-800">Impossible de charger la configuration : {e.message}</div>
{/await}
