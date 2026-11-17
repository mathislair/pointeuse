<script>
    import { get } from '../lib/api.js';
    let { onpick } = $props();
</script>

<div class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6 py-10">
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-900 text-3xl text-amber-400">◷</div>
        <h1 class="text-2xl font-bold">Pointeuse</h1>
        <p class="mt-1 text-gray-500">Dans quel restaurant est cette tablette ?</p>
    </div>
    {#await get('/restaurants')}
        <p class="text-center text-gray-400">Chargement…</p>
    {:then restaurants}
        {#if restaurants.length === 0}
            <p class="card p-4 text-center text-gray-500">Aucun restaurant. <a class="underline" href="/admin/restaurants">Créer un restaurant</a></p>
        {:else}
            <div class="grid gap-3">
                {#each restaurants as r (r.id)}
                    <button class="card flex items-center gap-4 px-5 py-4 text-left text-lg font-semibold transition hover:ring-gray-400 active:scale-[0.99]" onclick={() => onpick(r)}>
                        <span class="h-4 w-4 rounded-full" style="background: {r.color}"></span>
                        {r.name}
                    </button>
                {/each}
            </div>
        {/if}
    {:catch e}
        <p class="rounded-xl bg-red-50 p-4 text-red-800">{e.message}</p>
    {/await}
    <p class="mt-8 text-center text-xs text-gray-400"><a href="/admin" class="underline">Administration</a></p>
</div>
