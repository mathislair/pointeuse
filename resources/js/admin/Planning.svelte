<script>
    import { get } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { ymd, addDays, startOfWeek, fmtDate, fmtTime, displayStatus, STATUS, fmtPlanned } from '../lib/format.js';
    import Page from './Page.svelte';
    import AssignmentForm from './AssignmentForm.svelte';

    let weekStart = $state(startOfWeek(new Date()));
    let restaurantId = $state('');
    let restaurants = $state([]);
    let workers = $state([]);
    let assignments = $state([]);
    let loading = $state(false);
    let modal = $state(null); // { assignment } | { defaults }

    const days = $derived(Array.from({ length: 7 }, (_, i) => ymd(addDays(weekStart, i))));
    let now = $state(new Date());
    const today = $derived(ymd(now));
    $effect(() => {
        const tick = setInterval(() => (now = new Date()), 60_000);
        return () => clearInterval(tick);
    });

    Promise.all([get('/restaurants'), get('/workers')]).then(([r, w]) => {
        restaurants = r;
        workers = w;
    });

    let loadSeq = 0;
    let error = $state('');
    async function load() {
        const seq = ++loadSeq;
        loading = true;
        try {
            const rows = await get('/assignments', { from: days[0], to: days[6], restaurant_id: restaurantId });
            if (seq !== loadSeq) return;
            assignments = rows;
            error = '';
        } catch (e) {
            if (seq === loadSeq) error = e.message;
        } finally {
            if (seq === loadSeq) loading = false;
        }
    }
    $effect(() => {
        days;
        restaurantId;
        load();
    });

    const visibleRestaurants = $derived(restaurantId ? restaurants.filter((r) => r.id === Number(restaurantId)) : restaurants);
    const cell = (rid, date, shift) => assignments.filter((a) => a.restaurant_id === rid && a.date === date && a.shift === shift);
    const dot = { planned: 'bg-gray-300', late: 'bg-amber-400', missed: 'bg-gray-500', present: 'bg-emerald-500', to_sign: 'bg-orange-400', validated: 'bg-blue-500', absent: 'bg-red-500' };

    function saved() {
        modal = null;
        load();
    }
</script>

<Page>
    {#if error}
        <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p>
    {/if}
    {#snippet heading()}
        <div class="flex items-center gap-3">
            <button class="btn btn-secondary px-3 py-1.5" onclick={() => (weekStart = addDays(weekStart, -7))} title="Semaine précédente">‹</button>
            <div>
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Semaine du {fmtDate(days[0])} au {fmtDate(days[6])}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{assignments.length} affectation{assignments.length > 1 ? 's' : ''} · {assignments.filter((a) => a.absent).length} absence{assignments.filter((a) => a.absent).length > 1 ? 's' : ''}{loading ? ' · chargement…' : ''}</p>
            </div>
            <button class="btn btn-secondary px-3 py-1.5" onclick={() => (weekStart = addDays(weekStart, 7))} title="Semaine suivante">›</button>
        </div>
    {/snippet}
    {#snippet actions()}
        <button class="btn btn-secondary" onclick={() => (weekStart = startOfWeek(new Date()))}>Cette semaine</button>
        <select bind:value={restaurantId}>
            <option value="">Tous les restaurants</option>
            {#each restaurants as r}<option value={r.id}>{r.name}</option>{/each}
        </select>
        <button class="btn btn-primary" onclick={() => (modal = { defaults: { date: today, restaurant_id: restaurantId || undefined } })}>Planifier</button>
    {/snippet}

    <!-- Desktop grid: rows = restaurant × shift, columns = days -->
    <div class="card hidden overflow-x-auto md:block">
        <table class="min-w-full table-fixed text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th w-44">Restaurant / shift</th>
                    {#each days as d}
                        <th class="th {d === today ? 'text-emerald-700' : ''}">
                            {fmtDate(d)}
                            {#if d === today}<span class="ms-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium normal-case tracking-normal text-emerald-800">aujourd'hui</span>{/if}
                        </th>
                    {/each}
                </tr>
            </thead>
            <tbody>
                {#each visibleRestaurants as r (r.id)}
                    {#each appConfig.shiftKeys as shift, si}
                        {@const s = appConfig.shift(shift)}
                        <tr class="border-t {si === 0 ? 'border-gray-200' : 'border-gray-100'}">
                            <td class="px-4 py-2 align-top">
                                {#if si === 0}
                                    <p class="flex items-center gap-2 font-medium text-gray-900"><span class="h-2.5 w-2.5 rounded-full" style="background: {r.color}"></span>{r.name}</p>
                                {/if}
                                <p class="text-xs text-gray-500">{s.label} <span class="text-gray-400">{s.start}–{s.end}</span></p>
                            </td>
                            {#each days as d}
                                {@const items = cell(r.id, d, shift)}
                                <td class="group px-2 py-1.5 align-top {d === today ? 'bg-emerald-50/40' : ''}">
                                    <div class="flex flex-col gap-1">
                                        {#each items as a (a.id)}
                                            {@const st = displayStatus(a, now)}
                                            <button class="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-left text-xs text-gray-800 hover:border-emerald-300 hover:bg-emerald-50 {st === 'absent' ? 'line-through opacity-60' : ''}" onclick={() => (modal = { assignment: a })} title={STATUS[st].label}>
                                                <span class="h-2 w-2 shrink-0 rounded-full {dot[st]}"></span>
                                                <span class="truncate">{a.worker.first_name} {a.worker.last_name.charAt(0)}.</span>
                                                <span class="ms-auto text-[10px] whitespace-nowrap text-gray-400 tabular-nums">{fmtPlanned(a, '–')}</span>
                                            </button>
                                        {/each}
                                        <button class="rounded-md border border-dashed border-gray-200 px-2 py-0.5 text-xs text-gray-400 opacity-0 transition group-hover:opacity-100 hover:border-emerald-400 hover:text-emerald-700" onclick={() => (modal = { defaults: { date: d, start: s.start, end: s.end, restaurant_id: r.id } })}>+ ajouter</button>
                                    </div>
                                </td>
                            {/each}
                        </tr>
                    {/each}
                {:else}
                    <tr><td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">Aucun restaurant. <a class="text-emerald-700 hover:underline" href="/admin/restaurants">Créer un restaurant</a></td></tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- Mobile: one block per day -->
    <div class="space-y-3 md:hidden">
        {#each days as d}
            <section class="card p-4 {d === today ? 'border-emerald-200' : ''}">
                <h3 class="mb-2 font-semibold text-gray-900">{fmtDate(d)} {#if d === today}<span class="ms-1 text-xs font-normal text-emerald-700">aujourd'hui</span>{/if}</h3>
                {#each visibleRestaurants as r (r.id)}
                    <div class="mb-2">
                        <p class="flex items-center gap-2 text-sm font-medium text-gray-800"><span class="h-2 w-2 rounded-full" style="background: {r.color}"></span>{r.name}</p>
                        {#each appConfig.shiftKeys as shift}
                            {@const items = cell(r.id, d, shift)}
                            {@const s = appConfig.shift(shift)}
                            <div class="ms-4 flex flex-wrap items-center gap-1.5 py-1">
                                <span class="w-12 text-xs text-gray-500">{s.label}</span>
                                {#each items as a (a.id)}
                                    {@const st = displayStatus(a, now)}
                                    <button class="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs" onclick={() => (modal = { assignment: a })}>
                                        <span class="h-2 w-2 rounded-full {dot[st]}"></span>{a.worker.first_name} {a.worker.last_name.charAt(0)}. <span class="text-[10px] text-gray-400 tabular-nums">{fmtPlanned(a, '–')}</span>
                                    </button>
                                {/each}
                                <button class="rounded-md border border-dashed border-gray-300 px-2 py-0.5 text-xs text-gray-400" onclick={() => (modal = { defaults: { date: d, start: s.start, end: s.end, restaurant_id: r.id } })}>+</button>
                            </div>
                        {/each}
                    </div>
                {/each}
            </section>
        {/each}
    </div>

    <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-500">
        {#each Object.entries(STATUS) as [k, s]}
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {dot[k]}"></span>{s.label}</span>
        {/each}
    </div>
</Page>

{#if modal}
    <AssignmentForm assignment={modal.assignment} defaults={modal.defaults ?? {}} {restaurants} {workers} onclose={() => (modal = null)} onsaved={saved} />
{/if}
