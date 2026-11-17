<script>
    import { get } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { router } from '../lib/router.svelte.js';
    import { fmtDateLong, fmtTime } from '../lib/format.js';
    import RestaurantPicker from './RestaurantPicker.svelte';
    import DayTimeline from './DayTimeline.svelte';
    import WorkerCard from './WorkerCard.svelte';
    import ClockDialog from './ClockDialog.svelte';
    import WalkInDialog from './WalkInDialog.svelte';

    const STORAGE_KEY = 'pointeuse.restaurant';

    let slug = $state(router.query.get('restaurant') || localStorage.getItem(STORAGE_KEY) || '');
    let day = $state(null);
    let error = $state('');
    let now = $state(new Date());
    let selected = $state(null);
    let walkIn = $state(false);

    $effect(() => {
        if (slug) localStorage.setItem(STORAGE_KEY, slug);
        if (router.query.get('restaurant')) router.navigate('/', { replace: true });
    });

    async function load() {
        if (!slug) return;
        try {
            day = await get(`/pointeuse/${slug}/day`);
            error = '';
        } catch (e) {
            if (e.status === 404) {
                localStorage.removeItem(STORAGE_KEY);
                slug = '';
                day = null;
                return;
            }
            error = e.message;
        }
    }

    $effect(() => {
        slug;
        day = null;
        load();
        const tick = setInterval(() => (now = new Date()), 1000);
        const poll = setInterval(load, 30_000);
        const onVisible = () => document.visibilityState === 'visible' && load();
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            clearInterval(tick);
            clearInterval(poll);
            document.removeEventListener('visibilitychange', onVisible);
        };
    });

    const groups = $derived.by(() => {
        if (!day) return [];
        const out = appConfig.shiftKeys.map((key) => ({
            key,
            shift: appConfig.shift(key),
            items: day.assignments.filter((a) => a.shift === key),
        }));
        if (day.hanging?.length) {
            out.unshift({ key: 'hanging', shift: { label: 'Close de la veille', color: '#8b5cf6' }, items: day.hanging, hanging: true });
        }
        return out;
    });

    function refreshSelected(updated) {
        if (updated) selected = updated;
        load();
    }

    function changeRestaurant() {
        localStorage.removeItem(STORAGE_KEY);
        slug = '';
        day = null;
    }
</script>

{#if !slug}
    <RestaurantPicker onpick={(r) => (slug = r.slug)} />
{:else if !day && !error}
    <div class="flex min-h-dvh items-center justify-center text-gray-500">Chargement…</div>
{:else if error && !day}
    <div class="m-6 rounded-xl bg-red-50 p-4 text-red-800">
        {error}
        <button class="btn btn-secondary ml-3" onclick={load}>Réessayer</button>
    </div>
{:else}
    <div class="min-h-dvh pb-28">
        <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:gap-6">
                <span class="h-3.5 w-3.5 shrink-0 rounded-full" style="background: {day.restaurant.color}"></span>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg leading-tight font-bold sm:text-xl">{day.restaurant.name}</h1>
                    <p class="truncate text-sm text-gray-500">{fmtDateLong(day.date)}</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold tabular-nums tracking-tight sm:text-4xl">{fmtTime(now)}</div>
                </div>
                <button class="btn btn-ghost hidden h-9 w-9 rounded-full p-0 sm:inline-flex" title="Changer de restaurant" onclick={changeRestaurant} aria-label="Changer de restaurant">⚙</button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-6 px-4 py-5">
            {#if error}
                <div class="rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-800">Connexion perdue : {error}</div>
            {/if}

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between px-4 pt-4 pb-2">
                    <h2 class="font-bold">Calendrier de la journée</h2>
                    <span class="text-xs text-gray-500">{day.assignments.length} planifié{day.assignments.length > 1 ? 's' : ''}</span>
                </div>
                <DayTimeline date={day.date} assignments={[...day.hanging, ...day.assignments]} {now} onselect={(a) => (selected = a)} />
            </section>

            {#each groups as group (group.key)}
                <section>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: {group.shift.color}"></span>
                        <h2 class="font-bold">{group.shift.label}</h2>
                        {#if !group.hanging}
                            <span class="text-sm text-gray-500">{group.shift.start} – {group.shift.end}</span>
                        {/if}
                    </div>
                    {#if group.items.length === 0}
                        <p class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-400">Personne de planifié.</p>
                    {:else}
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {#each group.items as a (a.id)}
                                <WorkerCard {a} {now} onclick={() => (selected = a)} />
                            {/each}
                        </div>
                    {/if}
                </section>
            {/each}
        </main>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white/90 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
                <a href="/admin" class="btn btn-ghost text-xs text-gray-400">Admin</a>
                <button class="btn btn-ghost text-xs text-gray-400 sm:hidden" onclick={changeRestaurant}>Changer de restaurant</button>
                <button class="btn btn-secondary" onclick={() => (walkIn = true)}>+ Non planifié</button>
            </div>
        </div>
    </div>

    {#if selected}
        <ClockDialog assignment={selected} restaurant={day.restaurant} onclose={() => (selected = null)} onchanged={refreshSelected} />
    {/if}
    {#if walkIn}
        <WalkInDialog restaurant={day.restaurant} onclose={() => (walkIn = false)} oncreated={(a) => { walkIn = false; load(); selected = a; }} />
    {/if}
{/if}
