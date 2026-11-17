const fr = 'fr-FR';

const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

export const toDate = (v) => (v instanceof Date ? v : v ? new Date(v) : null);

/** "17:32" */
export function fmtTime(v) {
    const d = toDate(v);
    return d ? d.toLocaleTimeString(fr, { hour: '2-digit', minute: '2-digit' }) : '—';
}

/** "sam. 15 nov." */
export function fmtDate(v) {
    const d = parseYmd(v);
    return d ? cap(d.toLocaleDateString(fr, { weekday: 'short', day: 'numeric', month: 'short' })) : '—';
}

/** "samedi 15 novembre 2026" */
export function fmtDateLong(v) {
    const d = parseYmd(v);
    return d ? cap(d.toLocaleDateString(fr, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })) : '—';
}

/** "15/11/2026" */
export function fmtDateShort(v) {
    const d = parseYmd(v);
    return d ? d.toLocaleDateString(fr, { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
}

/** "novembre 2026" */
export function fmtMonth(ym) {
    const [y, m] = ym.split('-').map(Number);
    return cap(new Date(y, m - 1, 1).toLocaleDateString(fr, { month: 'long', year: 'numeric' }));
}

/** 369 → "6h09" */
/** Planned slot: "18:00 – 23:30", or "dès 18:00" for an open-ended manual clocking. */
export function fmtPlanned(a, sep = ' – ') {
    return a.planned_end ? `${fmtTime(a.planned_start)}${sep}${fmtTime(a.planned_end)}` : `dès ${fmtTime(a.planned_start)}`;
}

export function fmtDuration(minutes) {
    if (minutes === null || minutes === undefined) return '—';
    const h = Math.floor(minutes / 60);
    const m = Math.round(minutes % 60);
    return `${h}h${String(m).padStart(2, '0')}`;
}

/** Parse "YYYY-MM-DD" as a local date (not UTC). Other inputs go through Date. */
export function parseYmd(v) {
    if (!v) return null;
    if (v instanceof Date) return v;
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
        const [y, m, d] = v.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    return new Date(v);
}

/** Date → "YYYY-MM-DD" (local). */
export function ymd(d) {
    const x = toDate(d);
    return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
}

export function addDays(d, n) {
    const x = new Date(parseYmd(d));
    x.setDate(x.getDate() + n);
    return x;
}

/** Monday of the week containing d. */
export function startOfWeek(d) {
    const x = new Date(parseYmd(d));
    const day = (x.getDay() + 6) % 7;
    x.setDate(x.getDate() - day);
    x.setHours(0, 0, 0, 0);
    return x;
}

export const STATUS = {
    planned: { label: 'Prévu', cls: 'bg-gray-100 text-gray-700' },
    late: { label: 'En retard', cls: 'bg-amber-100 text-amber-800' },
    present: { label: 'Présent', cls: 'bg-emerald-100 text-emerald-800' },
    to_sign: { label: 'À signer', cls: 'bg-orange-100 text-orange-800' },
    validated: { label: 'Validé', cls: 'bg-blue-100 text-blue-800' },
    absent: { label: 'Absent', cls: 'bg-red-100 text-red-800' },
};

/** Derived status with "late" when an unclocked worker is past their start time. */
export function displayStatus(a, now = new Date()) {
    if (a.status === 'planned' && now - new Date(a.planned_start) > 10 * 60 * 1000) return 'late';
    return a.status;
}
