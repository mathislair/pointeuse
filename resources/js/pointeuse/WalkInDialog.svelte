<script>
    /** A worker who is not on today's planning shows up: pick them in the shared list and clock in. */
    import { get, post } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import Modal from '../components/Modal.svelte';

    let { restaurant, onclose, oncreated } = $props();

    let workers = $state([]);
    let search = $state('');
    let workerId = $state(null);
    let busy = $state(false);
    let error = $state('');
    const shift = $derived(appConfig.shift(currentShift()));

    get('/workers', { active_only: 1 }).then((w) => (workers = w));

    const filtered = $derived.by(() => {
        const q = search.trim().toLowerCase();
        return workers.filter((w) => !q || `${w.first_name} ${w.last_name}`.toLowerCase().includes(q)).slice(0, 30);
    });

    /** The shift whose window contains now, else the next one to start. */
    function currentShift() {
        const now = new Date();
        const minutes = now.getHours() * 60 + now.getMinutes();
        const toMin = (hm) => hm.split(':').reduce((h, m) => Number(h) * 60 + Number(m));
        for (const [key, s] of Object.entries(appConfig.shifts)) {
            const start = toMin(s.start);
            let end = toMin(s.end);
            const m = end <= start && minutes < start ? minutes + 1440 : minutes;
            if (end <= start) end += 1440;
            if (m >= start - 60 && m < end) return key;
        }
        return appConfig.shiftKeys[0];
    }

    async function submit() {
        if (!workerId) return (error = 'Choisis un intérimaire.');
        busy = true;
        error = '';
        try {
            const a = await post(`/pointeuse/${restaurant.slug}/walk-in`, { worker_id: workerId });
            oncreated?.(a);
        } catch (e) {
            error = e.firstError ?? e.message;
        } finally {
            busy = false;
        }
    }
</script>

<Modal title="Intérimaire non planifié" {onclose}>
    {#snippet footer()}
        <button class="btn btn-secondary" onclick={onclose}>Annuler</button>
        <button class="btn btn-primary" onclick={submit} disabled={busy || !workerId}>Pointer l'arrivée</button>
    {/snippet}

    <div class="space-y-4">
        <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">L'arrivée sera pointée maintenant, sur le shift en cours <span class="font-medium" style="color: {shift.color}">{shift.label}</span>, sans heure de fin : le départ pointé fera foi. Si l'intérimaire était déjà planifié ici aujourd'hui, c'est ce créneau qui sera pointé.</p>
        <div>
            <label for="search">Intérimaire</label>
            <input id="search" class="mt-1 w-full" placeholder="Rechercher un nom…" bind:value={search} autocomplete="off" />
            <div class="mt-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-100">
                {#each filtered as w (w.id)}
                    <button type="button" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-gray-50 {workerId === w.id ? 'bg-gray-900 text-white hover:bg-gray-900' : ''}" onclick={() => (workerId = w.id)}>
                        <span><strong>{w.first_name}</strong> {w.last_name}</span>
                        {#if workerId === w.id}<span>✓</span>{/if}
                    </button>
                {:else}
                    <p class="px-3 py-4 text-center text-sm text-gray-400">Aucun résultat. <a href="/admin/interimaires" class="underline">Ajouter un intérimaire</a></p>
                {/each}
            </div>
        </div>
        {#if error}
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p>
        {/if}
    </div>
</Modal>
