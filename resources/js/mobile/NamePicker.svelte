<script>
    /** "Qui es-tu ?" – big tiles for the people planned today, a search for everyone else. */
    import { fmtTime, displayStatus, rankSlot, matchesWorker } from '../lib/format.js';

    let { todays, workers, now, onpick, oncancel = null } = $props();

    let search = $state('');

    /** One tile per worker planned today, with their most relevant slot. */
    const planned = $derived.by(() => {
        const byWorker = new Map();
        for (const a of [...todays].sort((x, y) => new Date(x.planned_start) - new Date(y.planned_start))) {
            const cur = byWorker.get(a.worker.id);
            if (!cur || rankSlot(a, now) > rankSlot(cur, now)) byWorker.set(a.worker.id, a);
        }
        return [...byWorker.values()].sort((x, y) => x.worker.first_name.localeCompare(y.worker.first_name, 'fr'));
    });

    function hint(a) {
        switch (displayStatus(a, now)) {
            case 'present':
                return { text: `En poste depuis ${fmtTime(a.clock_in_at)}`, cls: 'text-emerald-700' };
            case 'late':
                return { text: `Prévu à ${fmtTime(a.planned_start)}`, cls: 'text-amber-700' };
            case 'planned':
                return { text: `Prévu à ${fmtTime(a.planned_start)}`, cls: 'text-gray-500' };
            case 'missed':
                return { text: 'Créneau passé sans pointage', cls: 'text-gray-500' };
            case 'to_sign':
                return { text: 'Heures à signer', cls: 'text-orange-700' };
            case 'validated':
                return { text: 'Journée validée ✓', cls: 'text-blue-700' };
            case 'absent':
                return { text: 'Marqué absent', cls: 'text-red-700' };
            default:
                return { text: '', cls: '' };
        }
    }

    const plannedIds = $derived(new Set(planned.map((a) => a.worker.id)));

    const others = $derived.by(() => {
        const list = workers.filter((w) => !plannedIds.has(w.id)).sort((x, y) => x.first_name.localeCompare(y.first_name, 'fr'));
        if (!search.trim()) return planned.length ? [] : list;
        return list.filter((w) => matchesWorker(w, search));
    });

    const matchingPlanned = $derived(search.trim() ? planned.filter((a) => matchesWorker(a.worker, search)) : planned);
</script>

<div class="flex flex-1 flex-col gap-5">
    <div>
        <h2 class="text-2xl font-bold">Qui es-tu ?</h2>
        <p class="text-gray-500">Appuie sur ton prénom pour pointer.</p>
    </div>

    {#if planned.length}
        <div>
            <h3 class="mb-2 text-xs font-semibold tracking-wide text-gray-400 uppercase">Prévu{planned.length > 1 ? 's' : ''} aujourd'hui</h3>
            <div class="grid grid-cols-2 gap-3">
                {#each matchingPlanned as a (a.worker.id)}
                    {@const h = hint(a)}
                    <button type="button" class="card flex min-h-24 flex-col items-start justify-between p-4 text-left transition active:scale-[0.97]" onclick={() => onpick(a.worker)}>
                        <span class="min-w-0">
                            <span class="block truncate text-xl font-bold leading-tight">{a.worker.first_name}</span>
                            <span class="block truncate text-sm text-gray-500">{a.worker.last_name}</span>
                        </span>
                        <span class="mt-2 text-xs font-medium {h.cls}">{h.text}</span>
                    </button>
                {:else}
                    <p class="col-span-2 text-sm text-gray-400">Aucun prénom prévu ne correspond.</p>
                {/each}
            </div>
        </div>
    {/if}

    <div class="flex flex-1 flex-col">
        <h3 class="mb-2 text-xs font-semibold tracking-wide text-gray-400 uppercase">{planned.length ? 'Pas dans la liste ?' : 'Tous les intérimaires'}</h3>
        <input type="search" class="w-full rounded-xl px-4 py-3 text-base" placeholder="Cherche ton prénom…" bind:value={search} autocomplete="off" autocapitalize="off" />
        {#if others.length}
            <div class="card mt-3 divide-y divide-gray-100 overflow-hidden">
                {#each others.slice(0, 40) as w (w.id)}
                    <button type="button" class="flex w-full items-center justify-between px-4 py-3.5 text-left active:bg-gray-50" onclick={() => onpick(w)}>
                        <span><span class="text-lg font-semibold">{w.first_name}</span> <span class="text-gray-500">{w.last_name}</span></span>
                        <span class="text-gray-300">›</span>
                    </button>
                {/each}
                {#if others.length > 40}
                    <p class="px-4 py-2 text-center text-xs text-gray-400">… et {others.length - 40} autres : affine ta recherche.</p>
                {/if}
            </div>
        {:else if search.trim() && !matchingPlanned.length}
            <p class="mt-3 text-sm text-gray-400">Personne ne correspond. Demande à ton responsable de t'ajouter.</p>
        {/if}
    </div>

    {#if oncancel}
        <button type="button" class="btn btn-ghost mt-auto" onclick={oncancel}>Annuler</button>
    {/if}
</div>
