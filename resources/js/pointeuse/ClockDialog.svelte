<script>
    import { post, get } from '../lib/api.js';
    import { appConfig } from '../lib/config.svelte.js';
    import { fmtTime, fmtDuration, fmtDateLong, displayStatus, fmtPlanned } from '../lib/format.js';
    import Modal from '../components/Modal.svelte';
    import SignaturePad from '../components/SignaturePad.svelte';
    import StatusBadge from '../components/StatusBadge.svelte';

    let { assignment, restaurant, onclose, onchanged } = $props();

    // svelte-ignore state_referenced_locally
    let a = $state(assignment);
    $effect(() => {
        a = assignment;
    });

    let busy = $state(false);
    let error = $state('');
    let signature = $state(null);
    let done = $state(false);
    let savedSignature = $state(null);

    const shift = $derived(appConfig.shift(a.shift));
    const status = $derived(displayStatus(a));
    const title = $derived(`${a.worker.first_name} ${a.worker.last_name}`);

    $effect(() => {
        if (a.status === 'validated' && a.has_signature) {
            get(`/assignments/${a.id}/signature`).then((r) => (savedSignature = r.signature)).catch(() => {});
        }
    });

    async function run(fn) {
        busy = true;
        error = '';
        try {
            const updated = await fn();
            a = updated;
            onchanged?.(updated);
            return true;
        } catch (e) {
            error = e.firstError ?? e.message;
            return false;
        } finally {
            busy = false;
        }
    }

    const clockIn = () => run(() => post(`/assignments/${a.id}/clock-in`));
    const clockOut = () => run(() => post(`/assignments/${a.id}/clock-out`));
    async function sign() {
        if (!signature) return (error = 'Merci de signer avant de valider.');
        if (await run(() => post(`/assignments/${a.id}/sign`, { signature }))) {
            done = true;
            setTimeout(() => onclose?.(), 1800);
        }
    }
</script>

<Modal {title} {onclose}>
    {#snippet footer()}
        {#if done}
            <span class="text-sm text-emerald-700">Merci, c'est validé !</span>
        {:else if status === 'planned' || status === 'late'}
            <button class="btn btn-secondary" onclick={onclose}>Annuler</button>
            <button class="btn btn-primary px-6 py-3 text-base" onclick={clockIn} disabled={busy}>Pointer l'arrivée · {fmtTime(new Date())}</button>
        {:else if status === 'present'}
            <button class="btn btn-secondary" onclick={onclose}>Annuler</button>
            <button class="btn btn-dark px-6 py-3 text-base" onclick={clockOut} disabled={busy}>Pointer le départ · {fmtTime(new Date())}</button>
        {:else if status === 'to_sign'}
            <button class="btn btn-secondary" onclick={onclose}>Plus tard</button>
            <button class="btn px-6 py-3 text-base {signature ? 'btn-primary' : 'bg-gray-300 text-white'}" onclick={sign} disabled={busy || !signature}>Je valide et je signe</button>
        {:else}
            <button class="btn btn-secondary" onclick={onclose}>Fermer</button>
        {/if}
    {/snippet}

    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
            <span class="inline-flex items-center gap-1.5 font-medium"><span class="h-2.5 w-2.5 rounded-full" style="background: {restaurant.color}"></span>{restaurant.name}</span>
            <span>·</span>
            <span class="font-medium" style="color: {shift.color}">{shift.label}</span>
            <span>·</span>
            <span>{fmtDateLong(a.date)}</span>
            <StatusBadge {status} />
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-gray-50 p-3">
                <div class="text-[10px] font-semibold text-gray-400 uppercase">Prévu</div>
                <div class="font-semibold tabular-nums">{fmtPlanned(a)}</div>
            </div>
            <div class="rounded-xl bg-gray-50 p-3">
                <div class="text-[10px] font-semibold text-gray-400 uppercase">Arrivée</div>
                <div class="text-lg font-bold tabular-nums">{a.clock_in_at ? fmtTime(a.clock_in_at) : '—'}</div>
            </div>
            <div class="rounded-xl bg-gray-50 p-3">
                <div class="text-[10px] font-semibold text-gray-400 uppercase">Départ</div>
                <div class="text-lg font-bold tabular-nums">{a.clock_out_at ? fmtTime(a.clock_out_at) : '—'}</div>
            </div>
            <div class="rounded-xl bg-gray-50 p-3">
                <div class="text-[10px] font-semibold text-gray-400 uppercase">Durée</div>
                <div class="text-lg font-bold tabular-nums">{fmtDuration(a.worked_minutes)}</div>
            </div>
        </div>

        {#if status === 'planned' || status === 'late'}
            <p class="text-gray-700">Bonjour <strong>{a.worker.first_name}</strong> ! Confirme ton arrivée pour le shift <strong>{shift.label}</strong>.</p>
            {#if status === 'late'}
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Le shift a commencé à {fmtTime(a.planned_start)} : l'heure réelle d'arrivée sera enregistrée.</p>
            {/if}
        {:else if status === 'present'}
            <p class="text-gray-700">Tu es arrivé(e) à <strong>{fmtTime(a.clock_in_at)}</strong>. Tu pourras valider tes heures et signer juste après avoir pointé ton départ.</p>
        {:else if status === 'to_sign' && !done}
            <div class="rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">
                Je confirme avoir travaillé de <strong>{fmtTime(a.clock_in_at)}</strong> à <strong>{fmtTime(a.clock_out_at)}</strong>
                (soit <strong>{fmtDuration(a.worked_minutes)}</strong>) au restaurant <strong>{restaurant.name}</strong>.
            </div>
            <div>
                <label for="sig">Signature</label>
                <div class="mt-1"><SignaturePad bind:value={signature} /></div>
            </div>
        {:else if done}
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</div>
                <p class="font-semibold">Heures validées et signées.</p>
                <p class="text-sm text-gray-500">Bonne soirée {a.worker.first_name} !</p>
            </div>
        {:else if status === 'validated'}
            <div>
                <div class="mb-1 text-xs font-semibold text-gray-400 uppercase">Signé le {fmtDateLong(a.signed_at)} à {fmtTime(a.signed_at)}</div>
                {#if savedSignature}
                    <img src={savedSignature} alt="Signature" class="h-32 w-full rounded-xl border border-gray-200 bg-white object-contain" />
                {:else}
                    <div class="h-32 animate-pulse rounded-xl bg-gray-100"></div>
                {/if}
            </div>
        {:else if status === 'absent'}
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">Marqué absent par l'administrateur.</p>
        {/if}

        {#if error}
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p>
        {/if}
    </div>
</Modal>
