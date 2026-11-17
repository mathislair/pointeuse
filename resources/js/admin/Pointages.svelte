<script>
    import { get } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { ymd, addDays, fmtDate, fmtTime, fmtDuration, displayStatus, STATUS, fmtPlanned } from '../lib/format.js';
    import StatusBadge from '../components/StatusBadge.svelte';
    import Page from './Page.svelte';
    import AssignmentForm from './AssignmentForm.svelte';

    let from = $state(ymd(addDays(new Date(), -7)));
    let to = $state(ymd(new Date()));
    let restaurantId = $state('');
    let workerId = $state('');
    let status = $state('');
    let restaurants = $state([]);
    let workers = $state([]);
    let rows = $state([]);
    let loading = $state(false);
    let editing = $state(null);
    const now = new Date();

    Promise.all([get('/restaurants'), get('/workers')]).then(([r, w]) => {
        restaurants = r;
        workers = w;
    });

    async function load() {
        if (!from || !to || from > to) return;
        loading = true;
        try {
            rows = await get('/assignments', { from, to, restaurant_id: restaurantId, worker_id: workerId });
        } finally {
            loading = false;
        }
    }
    $effect(() => {
        from; to; restaurantId; workerId;
        load();
    });

    const filtered = $derived(status ? rows.filter((a) => displayStatus(a, now) === status) : rows);
    const totals = $derived({
        minutes: filtered.reduce((s, a) => s + (a.worked_minutes ?? 0), 0),
        unsigned: filtered.filter((a) => a.status === 'to_sign').length,
    });

    function quick(daysBack) {
        from = ymd(addDays(new Date(), -daysBack));
        to = ymd(new Date());
    }
</script>

<Page title="Pointages" subtitle="{filtered.length} pointage{filtered.length > 1 ? 's' : ''} · {fmtDuration(totals.minutes)} travaillées{totals.unsigned ? ` · ${totals.unsigned} à signer` : ''}{loading ? ' · chargement…' : ''}">
    {#snippet actions()}
        <button class="btn btn-secondary" onclick={() => quick(0)}>Aujourd'hui</button>
        <button class="btn btn-secondary" onclick={() => quick(7)}>7 jours</button>
        <button class="btn btn-secondary" onclick={() => quick(30)}>30 jours</button>
    {/snippet}

    <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div><label for="from">Du</label><input id="from" type="date" class="mt-1 block w-full" bind:value={from} /></div>
        <div><label for="to">Au</label><input id="to" type="date" class="mt-1 block w-full" bind:value={to} /></div>
        <div>
            <label for="r">Restaurant</label>
            <select id="r" class="mt-1 block w-full" bind:value={restaurantId}><option value="">Tous</option>{#each restaurants as r}<option value={r.id}>{r.name}</option>{/each}</select>
        </div>
        <div>
            <label for="w">Intérimaire</label>
            <select id="w" class="mt-1 block w-full" bind:value={workerId}><option value="">Tous</option>{#each workers as w}<option value={w.id}>{w.last_name} {w.first_name}</option>{/each}</select>
        </div>
        <div>
            <label for="s">Statut</label>
            <select id="s" class="mt-1 block w-full" bind:value={status}><option value="">Tous</option>{#each Object.entries(STATUS) as [k, s]}<option value={k}>{s.label}</option>{/each}</select>
        </div>
    </div>

    <!-- table (desktop) -->
    <div class="card hidden overflow-x-auto md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">Date</th><th class="th">Restaurant</th><th class="th">Intérimaire</th><th class="th">Shift</th>
                    <th class="th">Prévu</th><th class="th">Arrivée</th><th class="th">Départ</th><th class="th text-right">Durée</th><th class="th">Statut</th><th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {#each filtered as a (a.id)}
                    {@const s = appConfig.shift(a.shift)}
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 whitespace-nowrap text-gray-900">{fmtDate(a.date)}</td>
                        <td class="px-4 py-2.5 text-gray-600"><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {a.restaurant.color}"></span>{a.restaurant.name}</span></td>
                        <td class="px-4 py-2.5 font-medium text-gray-900">{a.worker.first_name} {a.worker.last_name}</td>
                        <td class="px-4 py-2.5 text-gray-600">{s.label}</td>
                        <td class="px-4 py-2.5 text-gray-500 tabular-nums">{fmtPlanned(a, '–')}</td>
                        <td class="px-4 py-2.5 tabular-nums {a.clock_in_at ? 'text-gray-900' : 'text-gray-300'}">{a.clock_in_at ? fmtTime(a.clock_in_at) : '–'}</td>
                        <td class="px-4 py-2.5 tabular-nums {a.clock_out_at ? 'text-gray-900' : 'text-gray-300'}">{a.clock_out_at ? fmtTime(a.clock_out_at) : '–'}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums {a.worked_minutes !== null ? 'text-gray-900' : 'text-gray-300'}">{fmtDuration(a.worked_minutes).replace('—', '–')}</td>
                        <td class="px-4 py-2.5"><StatusBadge status={displayStatus(a, now)} />{#if a.edited_at}<span class="ms-1 rounded bg-amber-100 px-1.5 text-xs font-medium text-amber-800" title="Corrigé par l'administrateur">✎</span>{/if}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap"><button class="text-emerald-700 hover:underline" onclick={() => (editing = a)}>Modifier</button></td>
                    </tr>
                {:else}
                    <tr><td colspan="10" class="px-6 py-12 text-center"><p class="text-sm font-medium text-gray-900">Aucun pointage</p><p class="mt-1 text-sm text-gray-500">Aucune affectation sur cette période avec ces filtres.</p></td></tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- cards (mobile) -->
    <div class="space-y-2 md:hidden">
        {#each filtered as a (a.id)}
            {@const s = appConfig.shift(a.shift)}
            <button class="card w-full p-4 text-left" onclick={() => (editing = a)}>
                <div class="flex items-center justify-between gap-2">
                    <p class="font-medium text-gray-900">{a.worker.first_name} {a.worker.last_name}</p>
                    <StatusBadge status={displayStatus(a, now)} />
                </div>
                <p class="mt-1 text-sm text-gray-500">{fmtDate(a.date)} · {a.restaurant.name} · {s.label}</p>
                <p class="mt-1 text-sm text-gray-700 tabular-nums">{a.clock_in_at ? fmtTime(a.clock_in_at) : '–'} → {a.clock_out_at ? fmtTime(a.clock_out_at) : '–'} · {fmtDuration(a.worked_minutes)}</p>
            </button>
        {:else}
            <p class="card px-6 py-12 text-center text-sm text-gray-500">Aucun pointage sur cette période.</p>
        {/each}
    </div>
</Page>

{#if editing}
    <AssignmentForm assignment={editing} {restaurants} {workers} onclose={() => (editing = null)} onsaved={() => { editing = null; load(); }} />
{/if}
