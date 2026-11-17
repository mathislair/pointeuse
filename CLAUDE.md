# Pointeuse – intérimaires restaurants

Application de pointage pour intérimaires travaillant dans plusieurs restaurants.
Laravel 13 (API JSON, SQLite) + Svelte 5 (SPA, Tailwind 4) servis par le même conteneur Docker.

## Commandes

- `composer install && npm install` puis `php artisan migrate --seed` (SQLite `database/database.sqlite`).
- Dev : `php artisan serve` + `npm run dev` (Vite HMR). Build prod : `npm run build`.
- Tests : `php artisan test`.
- Docker : `docker compose up -d --build` (port `PORT`, défaut 8080 ; données dans le volume `pointeuse-data`).

## Structure

- `routes/api.php` → contrôleurs dans `app/Http/Controllers/Api/`. `routes/web.php` est un catch-all qui sert `resources/views/app.blade.php` (la SPA).
- Modèles : `Restaurant`, `Worker` (intérimaire : nom, prénom uniquement, pas de taux horaire ni de société), `Assignment` (un intérimaire sur un shift dans un restaurant + son pointage : arrivée, départ, signature PNG en data URL, absent, note admin).
- Une affectation se planifie par `start` / `end` en `HH:MM` (`Assignment::applyTimes`) ; le shift est **déduit** (`Shifts::detect`, recouvrement maximal), jamais saisi. Heures < `day_start_hour` = lendemain matin, fin ≤ début = lendemain. Chevauchement interdit pour un même intérimaire (`Assignment::overlapping`). Le walk-in de la tablette ne demande rien non plus : `planned_start` = maintenant, **`planned_end` null** (pas d'heure de fin par défaut, le départ pointé fait foi), shift = `Shifts::current`, ou pointage du créneau déjà planifié. Pour les chevauchements une plage ouverte finit au départ pointé, sinon à la fin de son shift (`Assignment::effectiveEnd`). Shifts (open 9h–17h30, swing 17h30–23h30, close 23h30–6h) et heure de bascule de la « journée de service » (7h) dans `config/pointeuse.php`, helpers dans `app/Support/Shifts.php`.
- Statut d'un pointage dérivé (`Assignment::getStatusAttribute`) : planned → present → to_sign → validated, ou absent.
- Front : `resources/js/pointeuse/` (vue tablette/mobile : calendrier du jour, pointage, signature) et `resources/js/admin/` (planning hebdo, pointages, récap mensuel en heures + export CSV, intérimaires, restaurants). Routeur maison dans `lib/router.svelte.js`.

## Conventions

- Pas de compte ni de mot de passe : la tablette mémorise son restaurant dans `localStorage` (`/?restaurant=slug` pour la configurer), l'admin est sur `/admin`.
- Les dates sont sérialisées en ISO 8601 avec l'offset Europe/Paris (`SerializesLocalDates`). Ne pas réintroduire le `Z` UTC.
- Corriger un pointage côté admin efface la signature (l'intérimaire doit re-signer).
- Svelte 5 en runes (`$state`, `$derived`, `$props`), pas de store legacy. Textes UI en français.
- Style de l'admin calqué sur le repo `~/dev/coffre` (Laravel Breeze) : nav blanche en haut avec soulignement émeraude, bandeau de titre via `admin/Page.svelte`, tables `card` + en-têtes `.th`, boutons `btn btn-primary` (émeraude) / `btn-secondary`, badges arrondis, police Figtree, plugin `@tailwindcss/forms`. Palette gray/emerald, pas de slate.
