<script>
    /** QR code to stick on the wall: scanning it opens the mobile time clock of this restaurant. */
    import QRCode from 'qrcode';
    import { appConfig } from '../lib/config.svelte.js';
    import Modal from '../components/Modal.svelte';

    let { restaurant, onclose } = $props();

    const { origin, warning } = appConfig.publicOrigin;
    const url = $derived(`${origin}/p/${restaurant.slug}`);
    let dataUrl = $state('');
    let copied = $state(false);

    $effect(() => {
        QRCode.toDataURL(url, { width: 800, margin: 2, errorCorrectionLevel: 'M', color: { dark: '#111827', light: '#ffffff' } }).then((d) => (dataUrl = d));
    });

    async function copy() {
        try {
            await navigator.clipboard.writeText(url);
            copied = true;
            setTimeout(() => (copied = false), 1500);
        } catch {}
    }

    function download() {
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = `qr-pointeuse-${restaurant.slug}.png`;
        a.click();
    }

    function print() {
        const w = window.open('', '_blank');
        if (!w) return;
        const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
        w.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>QR code – ${esc(restaurant.name)}</title>
<style>
  @page { size: A4 portrait; margin: 15mm; }
  body { margin: 0; font-family: Figtree, system-ui, sans-serif; color: #111827; display: flex; min-height: 100vh; align-items: center; justify-content: center; text-align: center; }
  .sheet { display: flex; flex-direction: column; align-items: center; gap: 18px; }
  .dot { width: 18px; height: 18px; border-radius: 999px; display: inline-block; vertical-align: middle; margin-right: 10px; background: ${esc(restaurant.color)}; }
  h1 { font-size: 34px; margin: 0; }
  h2 { font-size: 26px; font-weight: 600; margin: 0; color: #047857; }
  img { width: 130mm; height: 130mm; }
  ol { text-align: left; font-size: 20px; line-height: 1.5; margin: 0; padding-left: 28px; }
  .url { font-family: ui-monospace, monospace; font-size: 14px; color: #6b7280; }
</style></head><body><div class="sheet">
  <h1><span class="dot"></span>${esc(restaurant.name)}</h1>
  <h2>Pointeuse intérimaires</h2>
  <img src="${dataUrl}" alt="QR code">
  <ol><li>Scanne le QR code avec ton téléphone</li><li>Choisis ton prénom</li><li>Appuie sur « Je pointe mon arrivée »</li></ol>
  <p class="url">${esc(url)}</p>
</div><script>window.onload = () => { const img = document.querySelector('img'); (img.decode ? img.decode() : Promise.resolve()).catch(() => {}).then(() => window.print()); };<\/script></body></html>`);
        w.document.close();
    }
</script>

<Modal title="QR code – {restaurant.name}" {onclose} size="sm">
    {#snippet footer()}
        <button class="btn btn-secondary" onclick={download} disabled={!dataUrl}>Télécharger PNG</button>
        <button class="btn btn-primary" onclick={print} disabled={!dataUrl}>Imprimer l'affiche</button>
    {/snippet}

    <div class="space-y-4 text-center">
        <p class="text-sm text-gray-600">À afficher dans le restaurant. L'intérimaire le scanne avec son téléphone, choisit son prénom et pointe son arrivée ou son départ.</p>
        {#if warning}
            <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-left text-sm text-amber-800">{warning}</p>
        {/if}
        {#if dataUrl}
            <img src={dataUrl} alt="QR code de la pointeuse {restaurant.name}" class="mx-auto h-64 w-64 rounded-xl border border-gray-200" />
        {:else}
            <div class="mx-auto h-64 w-64 animate-pulse rounded-xl bg-gray-100"></div>
        {/if}
        <div class="flex items-center justify-center gap-2">
            <code class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">{url}</code>
            <button class="text-xs text-emerald-700 hover:underline" onclick={copy}>{copied ? 'copié ✓' : 'copier'}</button>
            <a class="text-xs text-emerald-700 hover:underline" href={url} target="_blank">ouvrir</a>
        </div>
    </div>
</Modal>
