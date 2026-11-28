<script>
    /**
     * The one-button screen: "Je pointe mon arrivée" / "Je pointe mon départ".
     * Picks the worker's relevant slot of the day (in post > planned > to sign > done),
     * falls back to a walk-in when nothing is planned. Signing after the departure is
     * offered right away but never blocks.
     */
    import { post } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { fmtTime, fmtDuration, fmtPlanned, displayStatus, rankSlot } from '../lib/format.js';
    import SignaturePad from '../components/SignaturePad.svelte';

    let { worker, assignments, restaurant, now, greeting, onchanged, onswitch } = $props();

    let busy = $state(false);
    let error = $state('');
    let signature = $state(null);
    /** Local result of the last tap, shown until dismissed: { kind: 'in'|'out'|'signed', a } */
    let just = $state(null);
    /** The worker chose to sign later: show a summary instead of nagging with the pad. */
    let skippedSign = $state(false);
    /** A departure a few minutes after the arrival asks for a second tap. */
    let confirmOut = $state(false);
    /** Slot picked from the "hours to sign" banner, instead of the automatic one. */
    let forcedId = $state(null);

    /** The slot the button acts on. */
    const current = $derived.by(() => {
        if (forcedId) {
            const forced = assignments.find((x) => x.id === forcedId);
            if (forced) return forced;
        }
        if (just?.a) {
            const fresh = assignments.find((x) => x.id === just.a.id);
            return fresh ?? just.a;
        }
        let best = null;
        for (const a of assignments) {
            if (!best || rankSlot(a, now) > rankSlot(best, now)) best = a;
        }
        return best;
    });

    /** Another slot of the day still waiting for a signature, mentioned in a banner. */
    const pendingSign = $derived(assignments.find((a) => a.status === 'to_sign' && a.id !== current?.id) ?? null);

    const status = $derived(current ? displayStatus(current, now) : 'none');
    const shift = $derived(current ? appConfig.shift(current.shift) : null);
    const liveMinutes = $derived(current?.clock_in_at && !current.clock_out_at ? Math.max(0, Math.floor((now - new Date(current.clock_in_at)) / 60000)) : null);

    async function run(fn) {
        busy = true;
        error = '';
        try {
            const a = await fn();
            onchanged?.();
            return a;
        } catch (e) {
            error = e.firstError ?? e.message;
            return null;
        } finally {
            busy = false;
        }
    }

    const base = $derived(`/pointeuse/${restaurant.slug}/assignments/${current?.id}`);
    /** Minutes since the arrival under which a departure is probably a mistake. */
    const EARLY_OUT_MINUTES = 5;

    async function clockIn() {
        const a = await run(() => (current && (status === 'planned' || status === 'late') ? post(`${base}/clock-in`) : post(`/pointeuse/${restaurant.slug}/walk-in`, { worker_id: worker.id })));
        if (a) just = { kind: 'in', a };
    }

    async function clockOut() {
        if (liveMinutes !== null && liveMinutes < EARLY_OUT_MINUTES && !confirmOut) {
            confirmOut = true;
            return;
        }
        confirmOut = false;
        const a = await run(() => post(`${base}/clock-out`));
        if (a) just = { kind: 'out', a };
    }

    async function sign() {
        const a = await run(() => post(`${base}/sign`, { signature }));
        if (a) {
            just = { kind: 'signed', a };
            signature = null;
            forcedId = null;
        }
    }

    /** "Plus tard" on the signature: keep the summary on screen. */
    function skip() {
        skippedSign = true;
        forcedId = null;
        error = '';
    }

    /** OK on a confirmation: back to the current state of the day. */
    function dismiss() {
        just = null;
        forcedId = null;
        confirmOut = false;
        error = '';
    }

    /** What the big screen shows. */
    const view = $derived.by(() => {
        if (just?.kind === 'in') return 'just_in';
        if (just?.kind === 'signed') return 'just_signed';
        if (just?.kind === 'out' || status === 'to_sign') return skippedSign ? 'skipped' : 'to_sign';
        if (status === 'present') return 'out';
        if (status === 'planned' || status === 'late') return 'in';
        if (status === 'validated') return 'validated';
        if (status === 'absent') return 'absent';
        // nothing planned, or a planned slot that is already over ("missed"): a fresh arrival
        return 'walk_in';
    });
</script>

<div class="flex flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-gray-500">{greeting}</p>
            <h2 class="truncate text-3xl font-bold leading-tight">{worker.first_name} <span class="text-gray-400">{worker.last_name}</span></h2>
        </div>
        <button type="button" class="shrink-0 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm ring-1 ring-gray-200 active:bg-gray-100" onclick={onswitch}>Pas toi ?</button>
    </div>

    {#if error}
        <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{error}</p>
    {/if}

    {#if pendingSign && view !== 'to_sign' && view !== 'just_signed'}
        <button type="button" class="flex w-full items-center justify-between rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-left text-sm text-orange-900 active:bg-orange-100" onclick={() => { forcedId = pendingSign.id; skippedSign = false; just = null; }}>
            <span>Tu as des heures à signer ({fmtTime(pendingSign.clock_in_at)} → {fmtTime(pendingSign.clock_out_at)}).</span>
            <span class="font-semibold">Signer ›</span>
        </button>
    {/if}

    {#if view === 'in' || view === 'walk_in'}
        <div class="card p-5">
            {#if view === 'in'}
                <p class="text-sm text-gray-500">Ton créneau</p>
                <p class="mt-1 text-xl font-bold"><span style="color: {shift.color}">{shift.label}</span> · {fmtPlanned(current)}</p>
                {#if status === 'late'}
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Le créneau a commencé à {fmtTime(current.planned_start)} : ton heure réelle d'arrivée sera enregistrée.</p>
                {/if}
            {:else if status === 'missed'}
                <p class="text-sm text-gray-500">Ton créneau {fmtPlanned(current)} est passé sans pointage</p>
                <p class="mt-1 text-gray-700">Si tu arrives maintenant, ton arrivée sera pointée à l'heure réelle, sur le créneau en cours.</p>
            {:else}
                <p class="text-sm text-gray-500">Rien de prévu pour toi ici aujourd'hui</p>
                <p class="mt-1 text-gray-700">Pas de souci : ton arrivée sera pointée maintenant sur le créneau en cours, et ton départ fera foi.</p>
            {/if}
        </div>

        <div class="mt-auto">
            <button type="button" class="btn btn-primary w-full rounded-2xl py-5 text-xl font-bold shadow-lg shadow-emerald-600/20 active:scale-[0.98]" onclick={clockIn} disabled={busy}>
                {busy ? 'Un instant…' : `Je pointe mon arrivée · ${fmtTime(now)}`}
            </button>
        </div>
    {:else if view === 'out'}
        <div class="card p-5">
            <div class="flex items-center gap-2 text-sm font-medium text-emerald-700"><span class="pulse-dot h-2.5 w-2.5 rounded-full bg-emerald-500"></span>En poste</div>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-[10px] font-semibold text-gray-400 uppercase">Arrivée</div>
                    <div class="text-2xl font-bold tabular-nums">{fmtTime(current.clock_in_at)}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-[10px] font-semibold text-gray-400 uppercase">Depuis</div>
                    <div class="text-2xl font-bold tabular-nums">{fmtDuration(liveMinutes)}</div>
                </div>
            </div>
            <p class="mt-3 text-sm text-gray-500"><span class="font-medium" style="color: {shift.color}">{shift.label}</span> · {fmtPlanned(current)}</p>
        </div>

        <div class="mt-auto space-y-2">
            {#if confirmOut}
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Tu es arrivé(e) il y a {fmtDuration(liveMinutes)} à peine. Tu pars vraiment ?</p>
                <button type="button" class="btn btn-dark w-full rounded-2xl py-5 text-xl font-bold active:scale-[0.98]" onclick={clockOut} disabled={busy}>{busy ? 'Un instant…' : 'Oui, je pointe mon départ'}</button>
                <button type="button" class="btn btn-ghost w-full py-3 text-gray-500" onclick={() => (confirmOut = false)}>Non, je reste</button>
            {:else}
                <button type="button" class="btn btn-dark w-full rounded-2xl py-5 text-xl font-bold shadow-lg shadow-gray-900/20 active:scale-[0.98]" onclick={clockOut} disabled={busy}>
                    {busy ? 'Un instant…' : `Je pointe mon départ · ${fmtTime(now)}`}
                </button>
            {/if}
        </div>
    {:else if view === 'just_in'}
        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100 text-5xl text-emerald-600">✓</div>
            <p class="text-2xl font-bold">Arrivée pointée à {fmtTime(current.clock_in_at)}</p>
            <p class="text-gray-500">Bon service {worker.first_name} ! Rescanne le QR code en partant pour pointer ton départ.</p>
        </div>
        <button type="button" class="btn btn-secondary w-full rounded-2xl py-4 text-base" onclick={dismiss}>OK</button>
    {:else if view === 'to_sign'}
        <div class="card p-5">
            {#if just?.kind === 'out'}
                <div class="mb-3 flex items-center gap-2 font-semibold text-emerald-700"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100">✓</span>Départ pointé à {fmtTime(current.clock_out_at)}</div>
            {/if}
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-[10px] font-semibold text-gray-400 uppercase">Arrivée</div>
                    <div class="text-lg font-bold tabular-nums">{fmtTime(current.clock_in_at)}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-[10px] font-semibold text-gray-400 uppercase">Départ</div>
                    <div class="text-lg font-bold tabular-nums">{fmtTime(current.clock_out_at)}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-[10px] font-semibold text-gray-400 uppercase">Durée</div>
                    <div class="text-lg font-bold tabular-nums">{fmtDuration(current.worked_minutes)}</div>
                </div>
            </div>
        </div>

        <div>
            <p class="mb-2 text-sm text-gray-700">Signe pour valider tes <strong>{fmtDuration(current.worked_minutes)}</strong> chez <strong>{restaurant.name}</strong>.</p>
            <SignaturePad bind:value={signature} height={170} />
        </div>

        <div class="mt-auto space-y-2">
            <button type="button" class="btn w-full rounded-2xl py-5 text-xl font-bold active:scale-[0.98] {signature ? 'btn-primary shadow-lg shadow-emerald-600/20' : 'bg-gray-300 text-white'}" onclick={sign} disabled={busy || !signature}>Je signe mes heures</button>
            <button type="button" class="btn btn-ghost w-full py-3 text-gray-500" onclick={skip}>Plus tard</button>
        </div>
    {:else if view === 'just_signed' || view === 'validated'}
        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-blue-100 text-5xl text-blue-600">✓</div>
            <p class="text-2xl font-bold">{view === 'just_signed' ? 'Heures signées, merci !' : 'Journée validée'}</p>
            <p class="text-gray-500">{fmtTime(current.clock_in_at)} → {fmtTime(current.clock_out_at)} · {fmtDuration(current.worked_minutes)} chez {restaurant.name}</p>
            <p class="text-gray-500">Bonne soirée {worker.first_name} !</p>
        </div>
        {#if view === 'validated'}
            <button type="button" class="btn btn-secondary w-full rounded-2xl py-4 text-base" onclick={clockIn} disabled={busy}>Je reviens : pointer une nouvelle arrivée</button>
        {:else}
            <button type="button" class="btn btn-secondary w-full rounded-2xl py-4 text-base" onclick={dismiss}>OK</button>
        {/if}
    {:else if view === 'skipped'}
        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100 text-5xl text-emerald-600">✓</div>
            <p class="text-2xl font-bold">Départ pointé à {fmtTime(current.clock_out_at)}</p>
            <p class="text-gray-500">{fmtDuration(current.worked_minutes)} chez {restaurant.name}. Tu pourras signer tes heures plus tard en rescannant le QR code.</p>
        </div>
        <button type="button" class="btn btn-secondary w-full rounded-2xl py-4 text-base" onclick={() => (skippedSign = false)}>Signer maintenant</button>
    {:else if view === 'absent'}
        <div class="card p-5">
            <p class="font-semibold text-red-700">Tu as été marqué(e) absent(e) aujourd'hui.</p>
            <p class="mt-1 text-sm text-gray-600">Si tu es bien là, pointe ton arrivée : ton responsable sera prévenu.</p>
        </div>
        <div class="mt-auto">
            <button type="button" class="btn btn-primary w-full rounded-2xl py-5 text-xl font-bold active:scale-[0.98]" onclick={clockIn} disabled={busy}>Je pointe mon arrivée · {fmtTime(now)}</button>
        </div>
    {/if}
</div>
