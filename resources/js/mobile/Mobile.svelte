<script>
    /**
     * Mobile time clock reached by scanning the restaurant's QR code (/p/{slug}).
     * One screen to pick your first name, one tap to clock in or out. The phone
     * remembers who you are: the next scan lands straight on the clock button,
     * otherwise it starts with the list of names.
     */
    import { get } from '../lib/api.js';
    import { router } from '../lib/router.svelte.js';
    import { fmtDateLong, fmtTime } from '../lib/format.js';
    import NamePicker from './NamePicker.svelte';
    import Punch from './Punch.svelte';

    const ME_KEY = 'pointeuse.me';

    const slug = $derived(decodeURIComponent(router.path.split('/')[2] ?? ''));
    let day = $state(null);
    let workers = $state([]);
    let error = $state('');
    let notFound = $state(false);
    let now = $state(new Date());
    let workerId = $state(Number(localStorage.getItem(ME_KEY)) || null);
    let choosing = $state(false);

    // Polling and refreshes after a tap overlap: only the latest response may update the screen.
    let loadSeq = 0;

    async function load() {
        if (!slug) return;
        const seq = ++loadSeq;
        try {
            const [d, w] = await Promise.all([get(`/pointeuse/${slug}/day`), get('/workers', { active_only: 1 })]);
            if (seq !== loadSeq) return;
            day = d;
            workers = w;
            error = '';
            notFound = false;
        } catch (e) {
            if (seq !== loadSeq) return;
            if (e.status === 404) notFound = true;
            else error = e.message;
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

    /** Today's slots in this restaurant, yesterday's unfinished close shift included. */
    const todays = $derived(day ? [...day.hanging, ...day.assignments] : []);

    const me = $derived.by(() => {
        if (!workerId) return null;
        return workers.find((w) => w.id === workerId) ?? todays.find((a) => a.worker.id === workerId)?.worker ?? null;
    });
    const mine = $derived(todays.filter((a) => a.worker.id === workerId));

    // A remembered worker who no longer exists (deleted, deactivated): forget them.
    $effect(() => {
        if (workerId && workers.length && !me) {
            localStorage.removeItem(ME_KEY);
            workerId = null;
        }
    });

    function pick(w) {
        workerId = w.id;
        localStorage.setItem(ME_KEY, String(w.id));
        choosing = false;
    }

    const greeting = $derived.by(() => {
        const h = now.getHours();
        if (h >= 5 && h < 18) return 'Bonjour';
        return 'Bonsoir';
    });
</script>

<div class="flex min-h-dvh flex-col bg-gray-50">
    {#if notFound}
        <main class="flex flex-1 flex-col items-center justify-center px-6 text-center">
            <div class="mb-4 text-5xl">🤔</div>
            <h1 class="text-xl font-bold">Restaurant introuvable</h1>
            <p class="mt-2 text-gray-500">Ce QR code ne correspond à aucun restaurant. Demande à ton responsable de le réimprimer.</p>
        </main>
    {:else if !day && !error}
        <main class="flex flex-1 items-center justify-center text-gray-500">Chargement…</main>
    {:else if !day}
        <main class="flex flex-1 flex-col items-center justify-center px-6 text-center">
            <p class="rounded-xl bg-red-50 p-4 text-red-800">{error}</p>
            <button class="btn btn-secondary mt-4" onclick={load}>Réessayer</button>
        </main>
    {:else}
        <header class="border-b border-gray-200 bg-white" style="border-top: 6px solid {day.restaurant.color}">
            <div class="mx-auto flex max-w-md items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                    <h1 class="truncate text-base font-bold leading-tight">{day.restaurant.name}</h1>
                    <p class="truncate text-xs text-gray-500">{fmtDateLong(day.date)}</p>
                </div>
                <div class="text-2xl font-bold tabular-nums tracking-tight">{fmtTime(now)}</div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-md flex-1 flex-col px-5 pt-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            {#if error}
                <div class="mb-4 rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-800">Connexion perdue : {error}</div>
            {/if}

            {#if me && !choosing}
                <Punch worker={me} assignments={mine} restaurant={day.restaurant} {now} {greeting} onchanged={load} onswitch={() => (choosing = true)} />
            {:else}
                <NamePicker {todays} {workers} {now} onpick={pick} oncancel={me ? () => (choosing = false) : null} />
            {/if}
        </main>
    {/if}
</div>
