<script>
    import { get, post, put, del } from '../lib/api.js';
    import Modal from '../components/Modal.svelte';
    import Page from './Page.svelte';

    let workers = $state([]);
    let search = $state('');
    let showInactive = $state(false);
    let editing = $state(null); // {} for new
    let deleting = $state(null);
    let form = $state({});
    let error = $state('');
    let busy = $state(false);

    const load = async () => (workers = await get('/workers'));
    load();

    const filtered = $derived.by(() => {
        const q = search.trim().toLowerCase();
        return workers.filter((w) => (showInactive || w.active) && (!q || `${w.first_name} ${w.last_name}`.toLowerCase().includes(q)));
    });

    function openForm(w = null) {
        editing = w ?? {};
        form = { first_name: w?.first_name ?? '', last_name: w?.last_name ?? '', active: w?.active ?? true };
        error = '';
    }

    async function save() {
        busy = true;
        error = '';
        try {
            editing.id ? await put(`/workers/${editing.id}`, form) : await post('/workers', form);
            editing = null;
            await load();
        } catch (e) {
            error = e.firstError ?? e.message;
        } finally {
            busy = false;
        }
    }

    async function confirmDelete() {
        await del(`/workers/${deleting.id}`);
        deleting = null;
        editing = null;
        await load();
    }
</script>

<Page title="Intérimaires" subtitle="La liste est commune à tous les restaurants : nom et prénom suffisent.">
    {#snippet actions()}
        <input type="search" placeholder="Rechercher…" bind:value={search} />
        <label class="flex items-center gap-2 text-sm font-normal text-gray-600"><input type="checkbox" bind:checked={showInactive} /> Inactifs</label>
        <button class="btn btn-primary" onclick={() => openForm()}>Ajouter un intérimaire</button>
    {/snippet}

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr><th class="th">Nom</th><th class="th">Prénom</th><th class="th">Statut</th><th class="th"></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {#each filtered as w (w.id)}
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{w.last_name}</td>
                        <td class="px-4 py-3 text-gray-600">{w.first_name}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {w.active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'}">{w.active ? 'Actif' : 'Inactif'}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button class="text-emerald-700 hover:underline" onclick={() => openForm(w)}>Modifier</button>
                            <button class="ms-3 text-red-600 hover:underline" onclick={() => (deleting = w)}>Supprimer</button>
                        </td>
                    </tr>
                {:else}
                    <tr><td colspan="4" class="px-6 py-12 text-center"><p class="text-sm font-medium text-gray-900">Aucun intérimaire</p><p class="mt-1 text-sm text-gray-500">Ajoutez les personnes qui pointeront sur les tablettes.</p></td></tr>
                {/each}
            </tbody>
        </table>
    </div>
</Page>

{#if editing}
    <Modal title={editing.id ? "Modifier l'intérimaire" : 'Nouvel intérimaire'} onclose={() => (editing = null)} size="sm">
        {#snippet footer()}
            <button class="btn btn-secondary" onclick={() => (editing = null)}>Annuler</button>
            <button class="btn btn-primary" onclick={save} disabled={busy || !form.first_name || !form.last_name}>Enregistrer</button>
        {/snippet}
        <form class="space-y-4" onsubmit={(e) => { e.preventDefault(); save(); }}>
            <div><label for="fn">Prénom</label><input id="fn" type="text" class="mt-1 block w-full" bind:value={form.first_name} required /></div>
            <div><label for="ln">Nom</label><input id="ln" type="text" class="mt-1 block w-full" bind:value={form.last_name} required /></div>
            <label class="flex items-center gap-2 text-sm font-normal text-gray-700"><input type="checkbox" bind:checked={form.active} /> Actif (visible dans la pointeuse et le planning)</label>
            {#if error}<p class="text-sm text-red-600">{error}</p>{/if}
        </form>
    </Modal>
{/if}

{#if deleting}
    <Modal title="Supprimer {deleting.first_name} {deleting.last_name} ?" onclose={() => (deleting = null)} size="sm">
        {#snippet footer()}
            <button class="btn btn-secondary" onclick={() => (deleting = null)}>Annuler</button>
            <button class="btn btn-danger" onclick={confirmDelete}>Supprimer</button>
        {/snippet}
        <p class="text-sm text-gray-600">Tous ses pointages seront supprimés avec lui. Pour garder l'historique, désactivez-le plutôt.</p>
    </Modal>
{/if}
