<script>
    /**
     * Horizontal "calendar of the day": one row per planned worker, from 8h to 6h30 the next day.
     * Light bar = planned shift, solid bar = actual presence (arrival → departure or now).
     */
    import { appConfig } from '../lib/config.svelte.js';
    import { parseYmd, fmtTime, displayStatus, fmtPlanned } from '../lib/format.js';

    let { date, assignments = [], now, onselect, compact = false } = $props();

    const START_HOUR = 8;
    const HOURS = 22.5; // 8:00 → 6:30
    const start = $derived.by(() => {
        const d = parseYmd(date);
        d.setHours(START_HOUR, 0, 0, 0);
        return d;
    });
    const end = $derived(new Date(start.getTime() + HOURS * 3600_000));

    const pct = (t) => Math.min(100, Math.max(0, ((new Date(t) - start) / (end - start)) * 100));
    const hours = Array.from({ length: Math.floor(HOURS) + 1 }, (_, i) => (START_HOUR + i) % 24);

    const nowPct = $derived(now >= start && now <= end ? pct(now) : null);

    const rows = $derived([...assignments].sort((a, b) => new Date(a.planned_start) - new Date(b.planned_start) || a.worker.last_name.localeCompare(b.worker.last_name)));

    const actualColor = { present: '#10b981', to_sign: '#f97316', validated: '#0ea5e9', absent: '#ef4444' };
</script>

<div class="overflow-x-auto">
    <div class="min-w-[760px] px-4 pb-4">
        <!-- hour ruler -->
        <div class="relative ml-28 h-5 text-[10px] text-gray-400 sm:ml-36">
            {#each hours as h, i}
                <span class="absolute -trangray-x-1/2" style="left: {(i / HOURS) * 100}%">{String(h).padStart(2, '0')}h</span>
            {/each}
        </div>

        <div class="relative">
            {#if rows.length === 0}
                <p class="py-6 text-center text-sm text-gray-400">Aucun intérimaire planifié aujourd'hui.</p>
            {/if}
            {#each rows as a (a.id)}
                {@const st = displayStatus(a, now)}
                {@const shift = appConfig.shift(a.shift)}
                {@const actualEnd = a.clock_out_at ?? (a.clock_in_at ? now : null)}
                {@const plannedEnd = a.planned_end ?? actualEnd ?? a.planned_start}
                <button type="button" class="group flex w-full items-center gap-2 rounded-lg py-1 text-left hover:bg-gray-50" onclick={() => onselect?.(a)}>
                    <div class="w-26 shrink-0 truncate pl-1 text-sm font-medium sm:w-34">
                        {a.worker.first_name} <span class="text-gray-500">{a.worker.last_name}</span>
                    </div>
                    <div class="relative h-8 flex-1 rounded-md bg-gray-50 ring-1 ring-gray-100 ring-inset">
                        {#each hours as _, i}
                            <span class="absolute inset-y-0 border-l border-gray-200/70" style="left: {(i / HOURS) * 100}%"></span>
                        {/each}
                        <!-- planned -->
                        <div class="absolute inset-y-1 rounded opacity-30" style="left: {pct(a.planned_start)}%; width: {pct(plannedEnd) - pct(a.planned_start)}%; background: {shift.color}" title="{a.planned_end ? 'Prévu' : 'Pointage manuel'} {fmtPlanned(a)}"></div>
                        <!-- actual -->
                        {#if a.clock_in_at && actualEnd}
                            <div class="absolute inset-y-2 flex items-center overflow-hidden rounded px-1.5 text-[11px] font-semibold text-white" style="left: {pct(a.clock_in_at)}%; width: {Math.max(0.6, pct(actualEnd) - pct(a.clock_in_at))}%; background: {actualColor[st] ?? '#10b981'}">
                                <span class="truncate">{fmtTime(a.clock_in_at)}{a.clock_out_at ? ` – ${fmtTime(a.clock_out_at)}` : ' →'}</span>
                            </div>
                        {:else if st === 'absent'}
                            <div class="absolute inset-y-2 flex items-center rounded bg-red-500/80 px-1.5 text-[11px] font-semibold text-white" style="left: {pct(a.planned_start)}%; width: {Math.max(3, pct(plannedEnd) - pct(a.planned_start))}%">Absent</div>
                        {:else}
                            <div class="absolute inset-y-0 flex items-center px-1.5 text-[11px] text-gray-500" style="left: {pct(a.planned_start)}%">{shift.label}</div>
                        {/if}
                    </div>
                </button>
            {/each}

            {#if nowPct !== null && rows.length}
                <div class="pointer-events-none absolute top-0 bottom-0 ml-28 sm:ml-36" style="left: 0; right: 0">
                    <div class="absolute inset-y-0 w-0.5 bg-red-500" style="left: calc({nowPct}% + 0.5rem)">
                        <span class="absolute -top-1 -left-1 h-2.5 w-2.5 rounded-full bg-red-500"></span>
                    </div>
                </div>
            {/if}
        </div>
    </div>
</div>
