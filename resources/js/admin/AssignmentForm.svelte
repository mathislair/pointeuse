<script>
    /** Create or edit a planned shift / clocking. Used by Planning and Pointages. */
    import { get, post, put, del } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { fmtDuration, fmtTime, fmtDateLong, matchesWorker } from '../lib/format.js';
    import Modal from '../components/Modal.svelte';
    import StatusBadge from '../components/StatusBadge.svelte';

    let { assignment = null, defaults = {}, restaurants = [], workers = [], onclose, onsaved } = $props();

    const editing = $derived(!!assignment);
    /** "HH:MM" in Europe/Paris whatever the browser's timezone: the form talks wall-clock Paris time to the API. */
    const hm = (iso) => (iso ? new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: appConfig.value?.timezone ?? 'Europe/Paris' }) : '');

    // svelte-ignore state_referenced_locally
    let form = $state({
        restaurant_id: assignment?.restaurant_id ?? defaults.restaurant_id ?? restaurants[0]?.id ?? '',
        worker_id: assignment?.worker_id ?? defaults.worker_id ?? '',
        date: assignment?.date ?? defaults.date ?? '',
        start: assignment ? hm(assignment.planned_start) : (defaults.start ?? appConfig.shift(appConfig.shiftKeys[0]).start),
        end: assignment ? hm(assignment.planned_end) : (defaults.end ?? appConfig.shift(appConfig.shiftKeys[0]).end),
        clock_in: hm(assignment?.clock_in_at),
        clock_out: hm(assignment?.clock_out_at),
        absent: assignment?.absent ?? false,
        admin_note: assignment?.admin_note ?? '',
    });
    let search = $state('');
    let busy = $state(false);
    let error = $state('');
    let signature = $state(null);
    let confirmDelete = $state(false);

    $effect(() => {
        if (assignment?.has_signature) get(`/assignments/${assignment.id}/signature`).then((r) => (signature = r.signature));
    });

    const filteredWorkers = $derived.by(() => {
        return workers.filter((w) => w.active || w.id === form.worker_id).filter((w) => matchesWorker(w, search));
    });

    /** The shift is deduced from the range (server-side too); shown here as feedback. */
    const shiftKey = $derived(form.end ? appConfig.detectShift(form.start, form.end) : appConfig.shiftAt(form.start));
    const shift = $derived(shiftKey ? appConfig.shift(shiftKey) : null);
    const spansNextDay = $derived(form.start && form.end && (appConfig.minutes(form.end) <= appConfig.minutes(form.start) || appConfig.minutes(form.end) >= 24 * 60));

    const clockingChanged = $derived(editing && (form.clock_in !== hm(assignment.clock_in_at) || form.clock_out !== hm(assignment.clock_out_at)));

    /** Planned but never clocked: offer to copy the planned range into arrival/departure. */
    const canFillFromPlanned = $derived(editing && !form.absent && !!form.start && !!form.end && (form.clock_in !== form.start || form.clock_out !== form.end));
    function fillFromPlanned() {
        form.clock_in = form.start;
        form.clock_out = form.end;
    }

    /**
     * "HH:MM" on the service day → "YYYY-MM-DD HH:MM" (Paris time, parsed as such by the API).
     * Before day_start_hour it is the next morning; a departure before the arrival is the next day too.
     */
    function onServiceDay(hmValue, after = null) {
        if (!form.date || !hmValue) return null;
        const d = new Date(`${form.date}T${hmValue}:00`);
        const dayStart = appConfig.value?.day_start_hour ?? 7;
        if (d.getHours() < dayStart) d.setDate(d.getDate() + 1);
        if (after && d <= after) d.setDate(d.getDate() + 1);
        return d;
    }
    const localStamp = (d) => (d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}` : null);
    const clockIn = $derived(onServiceDay(form.clock_in));
    const clockOut = $derived(onServiceDay(form.clock_out, clockIn));

    const previewMinutes = $derived(clockIn && clockOut ? Math.max(0, Math.round((clockOut - clockIn) / 60000)) : null);

    async function save() {
        busy = true;
        error = '';
        try {
            const payload = {
                restaurant_id: Number(form.restaurant_id),
                worker_id: Number(form.worker_id),
                date: form.date,
                start: form.start,
                end: form.end || null,
                admin_note: form.admin_note || null,
            };
            let saved;
            if (editing) {
                // Arrival / departure are only sent when edited: an untouched clocking keeps its
                // seconds and its signature (the server also compares to the minute).
                saved = await put(`/assignments/${assignment.id}`, {
                    ...payload,
                    ...(clockingChanged ? { clock_in_at: localStamp(clockIn), clock_out_at: localStamp(clockOut) } : {}),
                    absent: form.absent,
                });
            } else {
                saved = await post('/assignments', payload);
            }
            onsaved?.(saved);
        } catch (e) {
            error = e.firstError ?? e.message;
        } finally {
            busy = false;
        }
    }

    async function remove() {
        busy = true;
        try {
            await del(`/assignments/${assignment.id}`);
            onsaved?.(null);
        } catch (e) {
            error = e.message;
        } finally {
            busy = false;
        }
    }
</script>

<Modal title={editing ? 'Modifier le pointage' : 'Planifier un shift'} {onclose} size="lg">
    {#snippet footer()}
        {#if editing}
            {#if confirmDelete}
                <span class="me-auto text-sm text-red-700">Supprimer définitivement cette affectation ?</span>
                <button class="btn btn-secondary" onclick={() => (confirmDelete = false)}>Non</button>
                <button class="btn btn-danger" onclick={remove} disabled={busy}>Oui, supprimer</button>
            {:else}
                <button class="me-auto text-sm text-red-600 hover:underline" onclick={() => (confirmDelete = true)}>Supprimer</button>
                <button class="btn btn-secondary" onclick={onclose}>Annuler</button>
                <button class="btn btn-primary" onclick={save} disabled={busy}>Enregistrer</button>
            {/if}
        {:else}
            <button class="btn btn-secondary" onclick={onclose}>Annuler</button>
            <button class="btn btn-primary" onclick={save} disabled={busy || !form.worker_id || !form.date || !form.start || !form.end}>Planifier</button>
        {/if}
    {/snippet}

    <div class="grid gap-4 sm:grid-cols-2">
        {#if editing}
            <div class="flex items-center gap-2 text-sm text-gray-600 sm:col-span-2">
                <StatusBadge status={assignment.status} />
                {#if assignment.edited_at}<span class="text-xs text-gray-500">· corrigé le {fmtDateLong(assignment.edited_at)} à {fmtTime(assignment.edited_at)}</span>{/if}
            </div>
        {/if}

        <div>
            <label for="restaurant">Restaurant</label>
            <select id="restaurant" class="mt-1 block w-full" bind:value={form.restaurant_id}>
                {#each restaurants as r}<option value={r.id}>{r.name}</option>{/each}
            </select>
        </div>
        <div>
            <label for="date">Date</label>
            <input id="date" type="date" class="mt-1 block w-full" bind:value={form.date} />
        </div>
        <div>
            <label for="start">Planifié de</label>
            <input id="start" type="time" class="mt-1 block w-full" bind:value={form.start} required />
        </div>
        <div>
            <label for="end">à</label>
            <input id="end" type="time" class="mt-1 block w-full" bind:value={form.end} required={!editing} />
        </div>
        <div class="-mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 sm:col-span-2">
            {#if shift}
                <span>Shift <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium" style="background: {shift.color}1a; color: {shift.color}">{shift.label}</span> <span class="text-gray-400">({shift.start} – {shift.end}, déduit des horaires)</span></span>
            {/if}
            {#if spansNextDay}<span class="text-amber-700">Fin le lendemain.</span>{/if}
            {#if editing && !form.end}<span>Pas d'heure de fin (pointage manuel) : le départ pointé fait foi.</span>{/if}
        </div>

        <div class="sm:col-span-2">
            <label for="worker-search">Intérimaire</label>
            <input id="worker-search" type="search" class="mt-1 block w-full" placeholder="Rechercher…" bind:value={search} />
            <div class="mt-2 max-h-40 divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-300">
                {#each filteredWorkers as w (w.id)}
                    <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50 {Number(form.worker_id) === w.id ? 'bg-emerald-50 text-emerald-900' : 'text-gray-800'}" onclick={() => (form.worker_id = w.id)}>
                        <span><span class="font-medium">{w.last_name}</span> {w.first_name}</span>
                        {#if Number(form.worker_id) === w.id}<span class="text-emerald-600">✓</span>{/if}
                    </button>
                {:else}
                    <p class="px-3 py-3 text-center text-sm text-gray-500">Aucun intérimaire</p>
                {/each}
            </div>
        </div>

        {#if editing}
            <div class="-mb-2 flex items-center justify-between gap-2 sm:col-span-2">
                <span class="text-sm font-medium text-gray-700">Pointage</span>
                {#if canFillFromPlanned}
                    <button type="button" class="text-xs text-emerald-700 hover:underline" onclick={fillFromPlanned}>
                        {!form.clock_in && !form.clock_out ? 'Pré-remplir avec les heures planifiées' : 'Reprendre les heures planifiées'} ({form.start} – {form.end})
                    </button>
                {/if}
            </div>
            <div>
                <label for="in">Arrivée</label>
                <input id="in" type="time" class="mt-1 block w-full" bind:value={form.clock_in} disabled={form.absent} />
            </div>
            <div>
                <label for="out">Départ</label>
                <input id="out" type="time" class="mt-1 block w-full" bind:value={form.clock_out} disabled={form.absent} />
            </div>
            <label class="flex items-center gap-2 text-sm font-normal text-gray-700 sm:col-span-2"><input type="checkbox" bind:checked={form.absent} /> Absent (efface le pointage)</label>
            {#if previewMinutes !== null && !form.absent}
                <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700 sm:col-span-2">Durée travaillée : <strong class="text-gray-900">{fmtDuration(previewMinutes)}</strong>{#if clockOut && clockIn && clockOut.getDate() !== clockIn.getDate()} <span class="text-gray-500">(départ le lendemain)</span>{/if}</p>
            {/if}
            {#if clockingChanged && assignment.has_signature}
                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 sm:col-span-2">Modifier les heures supprime la signature : l'intérimaire devra re-signer sur la pointeuse.</p>
            {/if}
        {/if}

        <div class="sm:col-span-2">
            <label for="note">Note</label>
            <input id="note" type="text" class="mt-1 block w-full" bind:value={form.admin_note} placeholder="Facultatif" />
        </div>

        {#if editing && signature}
            <div class="sm:col-span-2">
                <label for="sig">Signature de l'intérimaire · {fmtDateLong(assignment.signed_at)} {fmtTime(assignment.signed_at)}</label>
                <img src={signature} alt="Signature" class="mt-1 h-28 w-full rounded-md border border-gray-200 bg-white object-contain" />
            </div>
        {/if}

        {#if error}
            <p class="text-sm text-red-600 sm:col-span-2">{error}</p>
        {/if}
    </div>
</Modal>
