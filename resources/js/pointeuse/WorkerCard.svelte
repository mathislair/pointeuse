<script>
    import { appConfig } from '../lib/config.svelte.js';
    import { fmtTime, fmtDuration, displayStatus, fmtPlanned } from '../lib/format.js';
    import StatusBadge from '../components/StatusBadge.svelte';

    let { a, now, onclick } = $props();
    const st = $derived(displayStatus(a, now));
    const shift = $derived(appConfig.shift(a.shift));

    const action = $derived.by(() => {
        switch (st) {
            case 'planned':
            case 'late':
                return { label: "Pointer l'arrivée", cls: 'btn-primary' };
            case 'missed':
                return { label: 'Créneau passé', cls: 'btn-secondary' };
            case 'present':
                return { label: 'Pointer le départ', cls: 'btn-dark' };
            case 'to_sign':
                return { label: 'Valider et signer', cls: 'bg-orange-500 text-white hover:bg-orange-400' };
            case 'validated':
                return { label: 'Voir le récapitulatif', cls: 'btn-secondary' };
            default:
                return { label: 'Détails', cls: 'btn-secondary' };
        }
    });

    const liveMinutes = $derived(a.clock_in_at && !a.clock_out_at ? Math.max(0, Math.floor((now - new Date(a.clock_in_at)) / 60000)) : null);
</script>

<div class="card flex flex-col gap-3 p-4 {st === 'present' ? 'ring-emerald-300' : ''}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="truncate text-lg leading-tight font-bold">{a.worker.first_name} {a.worker.last_name}</div>
            <div class="mt-0.5 text-sm text-gray-500">
                <span class="font-medium" style="color: {shift.color}">{shift.label}</span>
                · {a.planned_end ? 'prévu' : 'en poste'} {fmtPlanned(a)}
            </div>
        </div>
        <StatusBadge status={st} />
    </div>

    {#if a.clock_in_at}
        <div class="grid grid-cols-3 gap-2 text-center text-sm">
            <div class="rounded-lg bg-gray-50 py-1.5">
                <div class="text-[10px] text-gray-400 uppercase">Arrivée</div>
                <div class="font-semibold tabular-nums">{fmtTime(a.clock_in_at)}</div>
            </div>
            <div class="rounded-lg bg-gray-50 py-1.5">
                <div class="text-[10px] text-gray-400 uppercase">Départ</div>
                <div class="font-semibold tabular-nums">{a.clock_out_at ? fmtTime(a.clock_out_at) : '—'}</div>
            </div>
            <div class="rounded-lg bg-gray-50 py-1.5">
                <div class="text-[10px] text-gray-400 uppercase">Durée</div>
                <div class="font-semibold tabular-nums">{fmtDuration(a.worked_minutes ?? liveMinutes)}</div>
            </div>
        </div>
    {/if}

    <button class="btn w-full py-3 text-base {action.cls}" {onclick}>{action.label}</button>
</div>
