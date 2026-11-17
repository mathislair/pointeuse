<script>
    import { get, post, put, del } from '../lib/api.js';
    import Modal from '../components/Modal.svelte';
    import Page from './Page.svelte';

    let restaurants = $state([]);
    let editing = $state(null);
    let deleting = $state(null);
    let form = $state({});
    let error = $state('');
    let busy = $state(false);
    let copied = $state(null);

    const load = async () => (restaurants = await get('/restaurants'));
    load();

    function openForm(r = null) {
        editing = r ?? {};
        form = { name: r?.name ?? '', color: r?.color ?? '#059669' };
        error = '';
    }

    async function save() {
        busy = true;
        error = '';
        try {
            editing.id ? await put(`/restaurants/${editing.id}`, form) : await post('/restaurants', form);
            editing = null;
            await load();
        } catch (e) {
            error = e.firstError ?? e.message;
        } finally {
            busy = false;
        }
    }

    async function confirmDelete() {
        await del(`/restaurants/${deleting.id}`);
        deleting = null;
        await load();
    }

    const link = (r) => `${window.location.origin}/?restaurant=${r.slug}`;
    async function copy(r) {
        try {
            await navigator.clipboard.writeText(link(r));
            copied = r.id;
            setTimeout(() => (copied = null), 1500);
        } catch {}
    }
</script>

<Page title="Restaurants" subtitle="Chaque tablette de pointeuse est reliée à un restaurant : ouvrez son lien sur l'appareil pour la configurer.">
    {#snippet actions()}
        <button class="btn btn-primary" onclick={() => openForm()}>Ajouter un restaurant</button>
    {/snippet}

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr><th class="th">Nom</th><th class="th">Lien de la pointeuse</th><th class="th"></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {#each restaurants as r (r.id)}
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><p class="flex items-center gap-2 font-medium text-gray-900"><span class="h-2.5 w-2.5 rounded-full" style="background: {r.color}"></span>{r.name}</p><p class="text-xs text-gray-500">{r.slug}</p></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <code class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">{link(r)}</code>
                                <button class="text-xs text-emerald-700 hover:underline" onclick={() => copy(r)}>{copied === r.id ? 'copié ✓' : 'copier'}</button>
                                <a class="text-xs text-emerald-700 hover:underline" href="/?restaurant={r.slug}" target="_blank">ouvrir</a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button class="text-emerald-700 hover:underline" onclick={() => openForm(r)}>Modifier</button>
                            <button class="ms-3 text-red-600 hover:underline" onclick={() => (deleting = r)}>Supprimer</button>
                        </td>
                    </tr>
                {:else}
                    <tr><td colspan="3" class="px-6 py-12 text-center"><p class="text-sm font-medium text-gray-900">Aucun restaurant</p><p class="mt-1 text-sm text-gray-500">Créez un restaurant pour commencer à planifier.</p></td></tr>
                {/each}
            </tbody>
        </table>
    </div>
</Page>

{#if editing}
    <Modal title={editing.id ? 'Modifier le restaurant' : 'Nouveau restaurant'} onclose={() => (editing = null)} size="sm">
        {#snippet footer()}
            <button class="btn btn-secondary" onclick={() => (editing = null)}>Annuler</button>
            <button class="btn btn-primary" onclick={save} disabled={busy || !form.name}>Enregistrer</button>
        {/snippet}
        <form class="space-y-4" onsubmit={(e) => { e.preventDefault(); save(); }}>
            <div><label for="name">Nom du restaurant</label><input id="name" type="text" class="mt-1 block w-full" bind:value={form.name} required /></div>
            <div>
                <label for="color">Couleur</label>
                <div class="mt-1 flex items-center gap-3">
                    <input id="color" type="color" class="h-9 w-12 cursor-pointer rounded-md border border-gray-300 p-0.5" bind:value={form.color} />
                    <input type="text" class="w-32 font-mono text-xs" bind:value={form.color} />
                </div>
            </div>
            {#if error}<p class="text-sm text-red-600">{error}</p>{/if}
        </form>
    </Modal>
{/if}

{#if deleting}
    <Modal title="Supprimer {deleting.name} ?" onclose={() => (deleting = null)} size="sm">
        {#snippet footer()}
            <button class="btn btn-secondary" onclick={() => (deleting = null)}>Annuler</button>
            <button class="btn btn-danger" onclick={confirmDelete}>Supprimer</button>
        {/snippet}
        <p class="text-sm text-gray-600">Tout son planning et ses pointages seront supprimés. Cette action est définitive.</p>
    </Modal>
{/if}
