import { get } from './api.js';

let config = $state(null);
let promise = null;

export const appConfig = {
    get value() {
        return config;
    },
    get shifts() {
        return config?.shifts ?? {};
    },
    get shiftKeys() {
        return Object.keys(config?.shifts ?? {});
    },
    shift(key) {
        return config?.shifts?.[key] ?? { label: key, start: '', end: '', color: '#64748b' };
    },
    /** Minutes since the start of the service day for a "HH:MM" time. */
    minutes(hm) {
        if (!hm) return null;
        const [h, m] = hm.split(':').map(Number);
        return (h < (config?.day_start_hour ?? 7) ? h + 24 : h) * 60 + m;
    },
    /** The shift a planned "HH:MM" range belongs to: the one it overlaps the most. */
    detectShift(start, end) {
        const from = this.minutes(start);
        let to = this.minutes(end);
        if (from === null || to === null) return null;
        if (to <= from) to += 24 * 60;
        let best = null;
        let bestOverlap = -Infinity;
        for (const key of this.shiftKeys) {
            const s = this.minutes(this.shift(key).start);
            let e = this.minutes(this.shift(key).end);
            if (e <= s) e += 24 * 60;
            const overlap = Math.min(to, e) - Math.max(from, s);
            if (overlap > bestOverlap) {
                bestOverlap = overlap;
                best = key;
            }
        }
        return best;
    },
    /** The shift in progress at a "HH:MM" time (else the next one to start). */
    shiftAt(hm) {
        const at = this.minutes(hm);
        if (at === null) return null;
        let next = null;
        for (const key of this.shiftKeys) {
            const s = this.minutes(this.shift(key).start);
            let e = this.minutes(this.shift(key).end);
            if (e <= s) e += 24 * 60;
            if (at >= s && at < e) return key;
            if (at < s && next === null) next = key;
        }
        return next ?? this.shiftKeys.at(-1) ?? null;
    },
    load() {
        promise ??= get('/config').then((c) => (config = c));
        return promise;
    },
};
