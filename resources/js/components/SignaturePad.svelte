<script>
    /**
     * Finger / stylus signature on a canvas. `value` is a PNG data URL (bindable), or null when empty.
     */
    let { value = $bindable(null), height = 180 } = $props();

    /** Cumulative stroke length (CSS px) below which a touch is a tap, not a signature. */
    const MIN_INK = 60;

    let canvas = $state();
    let drawing = false;
    let last = null;
    let hasInk = $state(false);
    let ink = 0;

    function setup() {
        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';
    }

    $effect(() => {
        if (!canvas) return;
        setup();
        const ro = new ResizeObserver(() => {
            if (!hasInk) setup();
        });
        ro.observe(canvas);
        return () => ro.disconnect();
    });

    function pos(e) {
        const r = canvas.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function down(e) {
        e.preventDefault();
        canvas.setPointerCapture(e.pointerId);
        drawing = true;
        last = pos(e);
        const ctx = canvas.getContext('2d');
        ctx.beginPath();
        ctx.arc(last.x, last.y, 1.2, 0, Math.PI * 2);
        ctx.fillStyle = '#0f172a';
        ctx.fill();
        hasInk = true;
    }

    function move(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = pos(e);
        const ctx = canvas.getContext('2d');
        ctx.beginPath();
        ctx.moveTo(last.x, last.y);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        ink += Math.hypot(p.x - last.x, p.y - last.y);
        last = p;
    }

    function up(e) {
        if (!drawing) return;
        drawing = false;
        // A tap or a tiny scribble is not a signature: keep the button disabled.
        value = ink >= MIN_INK ? canvas.toDataURL('image/png') : null;
    }

    export function clear() {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasInk = false;
        ink = 0;
        value = null;
    }
</script>

<div class="relative">
    <canvas
        bind:this={canvas}
        class="block w-full touch-none rounded-xl border-2 border-dashed border-gray-300 bg-white"
        style="height: {height}px"
        onpointerdown={down}
        onpointermove={move}
        onpointerup={up}
        onpointercancel={up}
        onpointerleave={up}
    ></canvas>
    {#if !hasInk}
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-gray-400">Signez ici avec le doigt</div>
    {/if}
    <div class="pointer-events-none absolute right-3 bottom-6 left-3 border-b border-gray-200"></div>
    <button type="button" class="btn btn-ghost absolute top-1 right-1 px-2 py-1 text-xs" onclick={clear} disabled={!hasInk}>Effacer</button>
</div>
