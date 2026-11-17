<script>
    import { STATUS } from '../lib/format.js';
    let { status, size = 'sm' } = $props();
    const s = $derived(STATUS[status] ?? { label: status, cls: 'bg-gray-100 text-gray-700' });
</script>

<span class="inline-flex items-center gap-1.5 rounded-full font-medium {s.cls} {size === 'lg' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs'}">
    {#if status === 'present'}<span class="pulse-dot h-2 w-2 rounded-full bg-emerald-500"></span>{/if}
    {s.label}
</span>
