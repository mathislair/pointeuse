<script>
    import { get } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { fmtMonth, fmtDate, fmtTime, fmtDuration } from '../lib/format.js';
    import StatusBadge from '../components/StatusBadge.svelte';
    import Page from './Page.svelte';

    const d = new Date();
    let month = $state(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    let restaurantId = $state('');
    let restaurants = $state([]);
    let recap = $state(null);
    let loading = $state(false);
    let open = $state(new Set());

    get('/restaurants').then((r) => (restaurants = r));

    async function load() {
        loading = true;
        try {
            recap = await get('/recap', { month, restaurant_id: restaurantId });
        } finally {
            loading = false;
        }
    }
    $effect(() => {
        month; restaurantId;
        load();
    });

    function shiftMonth(n) {
        const [y, m] = month.split('-').map(Number);
        const x = new Date(y, m - 1 + n, 1);
        month = `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}`;
    }

    function toggle(id) {
        const s = new Set(open);
        s.has(id) ? s.delete(id) : s.add(id);
        open = s;
    }

    const exportUrl = $derived(`/api/recap/export?month=${month}${restaurantId ? `&restaurant_id=${restaurantId}` : ''}`);
</script>

<Page>
    {#snippet heading()}
        <div class="flex items-center gap-3">
            <button class="btn btn-secondary px-3 py-1.5" onclick={() => shiftMonth(-1)} title="Mois précédent">‹</button>
            <div>
                <h2 class="text-xl leading-tight font-semibold text-gray-800">{fmtMonth(month)}</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    {#if recap}{recap.totals.shifts} shift{recap.totals.shifts > 1 ? 's' : ''} travaillé{recap.totals.shifts > 1 ? 's' : ''} · {fmtDuration(recap.totals.minutes)} · {recap.totals.unsigned} non signé{recap.totals.unsigned > 1 ? 's' : ''} · {recap.totals.absent} absence{recap.totals.absent > 1 ? 's' : ''}{/if}{loading ? ' · chargement…' : ''}
                </p>
            </div>
            <button class="btn btn-secondary px-3 py-1.5" onclick={() => shiftMonth(1)} title="Mois suivant">›</button>
        </div>
    {/snippet}
    {#snippet actions()}
        <input type="month" bind:value={month} />
        <select bind:value={restaurantId}>
            <option value="">Tous les restaurants</option>
            {#each restaurants as r}<option value={r.id}>{r.name}</option>{/each}
        </select>
        <a class="btn btn-secondary" href={exportUrl} download>Exporter en CSV</a>
    {/snippet}

    {#if recap}
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card p-4"><p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Shifts travaillés</p><p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">{recap.totals.shifts}</p></div>
            <div class="card p-4"><p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Heures</p><p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">{fmtDuration(recap.totals.minutes)}</p></div>
            <div class="card p-4 {recap.totals.unsigned ? 'border-amber-200' : ''}"><p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Non signés</p><p class="mt-1 text-2xl font-semibold tabular-nums {recap.totals.unsigned ? 'text-amber-700' : 'text-gray-900'}">{recap.totals.unsigned}</p></div>
            <div class="card p-4"><p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Absences</p><p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">{recap.totals.absent}</p></div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="min-w-0 lg:col-span-2">
                <div class="card overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr><th class="th">Intérimaire</th><th class="th text-right">Shifts</th><th class="th text-right">Heures</th><th class="th text-right">Alertes</th><th class="th"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            {#each recap.workers as w (w.worker_id)}
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{w.worker.last_name} {w.worker.first_name}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-900">{w.worked_shifts}<span class="text-gray-400"> / {w.planned_shifts}</span></td>
                                    <td class="px-4 py-2.5 text-right font-medium tabular-nums text-gray-900">{fmtDuration(w.minutes)}</td>
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        {#if w.unsigned}<span class="ms-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{w.unsigned} non signé{w.unsigned > 1 ? 's' : ''}</span>{/if}
                                        {#if w.incomplete}<span class="ms-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{w.incomplete} en cours</span>{/if}
                                        {#if w.absent}<span class="ms-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{w.absent} abs.</span>{/if}
                                        {#if !w.unsigned && !w.incomplete && !w.absent}<span class="text-gray-300">–</span>{/if}
                                    </td>
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap"><button class="text-emerald-700 hover:underline" onclick={() => toggle(w.worker_id)}>{open.has(w.worker_id) ? 'masquer' : 'détail'}</button></td>
                                </tr>
                                {#if open.has(w.worker_id)}
                                    <tr class="bg-emerald-50/40">
                                        <td colspan="5" class="px-4 py-3">
                                            <div class="mb-2 flex flex-wrap gap-2 text-xs">
                                                {#each w.by_restaurant as br}
                                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-gray-700"><span class="h-2 w-2 rounded-full" style="background: {br.restaurant.color}"></span>{br.restaurant.name} · {br.shifts} shift{br.shifts > 1 ? 's' : ''} · <strong class="text-gray-900">{fmtDuration(br.minutes)}</strong></span>
                                                {/each}
                                            </div>
                                            <table class="ms-0 w-full text-xs">
                                                <tbody class="divide-y divide-emerald-100">
                                                    {#each w.lines as a (a.id)}
                                                        <tr>
                                                            <td class="py-1 text-gray-900">{fmtDate(a.date)}</td>
                                                            <td class="py-1 text-gray-600">{a.restaurant.name}</td>
                                                            <td class="py-1 text-gray-600">{appConfig.shift(a.shift).label}</td>
                                                            <td class="py-1 text-gray-600 tabular-nums">{fmtTime(a.clock_in_at)} → {fmtTime(a.clock_out_at)}</td>
                                                            <td class="py-1 text-right font-medium text-gray-900 tabular-nums">{fmtDuration(a.worked_minutes)}</td>
                                                            <td class="py-1 text-right"><StatusBadge status={a.status} /></td>
                                                        </tr>
                                                    {/each}
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                {/if}
                            {:else}
                                <tr><td colspan="5" class="px-6 py-12 text-center"><p class="text-sm font-medium text-gray-900">Aucun pointage ce mois-ci</p><p class="mt-1 text-sm text-gray-500">Les heures apparaîtront ici dès qu'un intérimaire aura pointé.</p></td></tr>
                            {/each}
                        </tbody>
                        {#if recap.workers.length}
                            <tfoot class="border-t-2 border-gray-300">
                                <tr><td class="px-4 py-2 font-semibold text-gray-900">Total</td><td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900">{recap.totals.shifts}</td><td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900">{fmtDuration(recap.totals.minutes)}</td><td colspan="2"></td></tr>
                            </tfoot>
                        {/if}
                    </table>
                </div>
            </section>

            <section class="min-w-0">
                <div class="card">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs font-medium tracking-wide text-gray-500 uppercase">Par restaurant</div>
                    <div class="divide-y divide-gray-100">
                        {#each recap.restaurants as r (r.restaurant.id)}
                            <div class="flex items-center gap-3 px-4 py-3">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {r.restaurant.color}"></span>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900">{r.restaurant.name}</p>
                                    <p class="text-xs text-gray-500">{r.shifts} shift{r.shifts > 1 ? 's' : ''}</p>
                                </div>
                                <p class="font-medium tabular-nums text-gray-900">{fmtDuration(r.minutes)}</p>
                            </div>
                        {:else}
                            <p class="px-4 py-8 text-center text-sm text-gray-500">Rien ce mois-ci.</p>
                        {/each}
                    </div>
                </div>
            </section>
        </div>
    {:else}
        <p class="text-sm text-gray-500">Chargement…</p>
    {/if}
</Page>
