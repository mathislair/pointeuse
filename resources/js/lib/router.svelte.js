// Minimal history-based router shared by the whole SPA.
let path = $state(window.location.pathname);
let search = $state(window.location.search);

function sync() {
    path = window.location.pathname;
    search = window.location.search;
}
window.addEventListener('popstate', sync);

export const router = {
    get path() {
        return path;
    },
    get search() {
        return search;
    },
    get query() {
        return new URLSearchParams(search);
    },
    navigate(to, { replace = false } = {}) {
        if (to === path + search) return;
        window.history[replace ? 'replaceState' : 'pushState']({}, '', to);
        sync();
    },
    /** Intercept same-origin <a> clicks so the SPA handles navigation. */
    handleClick(event) {
        const a = event.target.closest('a[href]');
        if (!a || a.target || a.hasAttribute('download') || event.metaKey || event.ctrlKey || event.button !== 0) return;
        const url = new URL(a.href, window.location.origin);
        if (url.origin !== window.location.origin || url.pathname.startsWith('/api')) return;
        event.preventDefault();
        router.navigate(url.pathname + url.search);
    },
};
