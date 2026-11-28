# Pointeuse – intérimaires restaurants

Application de pointage pour intérimaires travaillant dans plusieurs restaurants.
Laravel 13 (API JSON, SQLite) + Svelte 5 (SPA, Tailwind 4) servis par le même conteneur Docker.

## Commandes

- `composer install && npm install` puis `php artisan migrate --seed` (SQLite `database/database.sqlite`).
- Dev : `php artisan serve` + `npm run dev` (Vite HMR). Build prod : `npm run build`.
- Tests : `php artisan test`.
- Docker : `docker compose up -d --build` (port `PORT`, défaut 8080 ; données dans le volume `pointeuse-data`). Seed de démo au premier démarrage seulement (ou `SEED_DEMO=1`). `APP_URL` doit être l'adresse publique : c'est elle qu'encodent les QR codes.
- Sauvegarde SQLite : `php artisan pointeuse:backup` (copie `VACUUM INTO` dans `backups/` à côté de la base, 30 conservées) ; lancée à chaque démarrage du conteneur et planifiée chaque jour (`routes/console.php`, nécessite `schedule:work`/cron hors Docker).

## Structure

- `routes/api.php` → contrôleurs dans `app/Http/Controllers/Api/`. `routes/web.php` est un catch-all qui sert `resources/views/app.blade.php` (la SPA).
- Modèles : `Restaurant`, `Worker` (intérimaire : nom, prénom uniquement, pas de taux horaire ni de société), `Assignment` (un intérimaire sur un shift dans un restaurant + son pointage : arrivée, départ, signature PNG en data URL, absent, note admin).
- Une affectation se planifie par `start` / `end` en `HH:MM` (`Assignment::applyTimes`) ; le shift est **déduit** (`Shifts::detect`, recouvrement maximal), jamais saisi. Heures < `day_start_hour` = lendemain matin, fin ≤ début = lendemain. Chevauchement interdit pour un même intérimaire (`Assignment::overlapping`). Le walk-in de la tablette ne demande rien non plus : `planned_start` = maintenant, **`planned_end` null** (pas d'heure de fin par défaut, le départ pointé fait foi), shift = `Shifts::current`, ou pointage du créneau déjà planifié. Pour les chevauchements une plage ouverte finit au départ pointé, sinon à la fin de son shift (`Assignment::effectiveEnd`). Shifts (open 9h–17h30, swing 17h30–23h30, close 23h30–6h) et heure de bascule de la « journée de service » (7h) dans `config/pointeuse.php`, helpers dans `app/Support/Shifts.php`.
- Statut d'un pointage dérivé (`Assignment::getStatusAttribute`) : planned → present → to_sign → validated, ou absent. Côté front `displayStatus` ajoute `late` (début dépassé de 10 min) et `missed` (créneau terminé sans pointage).
- Pointage (`ClockController`) : routes scopées `pointeuse/{slug}/assignments/{id}/clock-in|clock-out|sign` (le créneau doit appartenir au restaurant), arrivée acceptée de 3 h avant le début à 2 h après la fin du créneau, mises à jour conditionnelles (un second tap est refusé), signature = vrai PNG, une seule fois (correction admin pour re-signer). Les départs oubliés sont clôturés à la fin du créneau après 4 h de grâce (`Assignment::closeForgotten`, appelé par `day()`, les listes admin et `pointeuse:close-forgotten`), avec une note admin, statut `to_sign`.
- Correction admin (`AssignmentController::update`) : heures comparées à la minute (le formulaire n'envoie arrivée/départ que s'ils ont été modifiés), `edited_at` posé seulement si pointage/planning changent, départ sans arrivée ou avant l'arrivée refusé.
- Front : `resources/js/pointeuse/` (vue tablette : calendrier du jour, pointage, signature), `resources/js/mobile/` (vue téléphone `/p/{slug}` ouverte en scannant le QR code du restaurant : choix du prénom → un bouton arrivée/départ, signature proposée après le départ sans bloquer ; le téléphone mémorise l'intérimaire dans `localStorage` `pointeuse.me`, sinon il affiche la liste des prénoms) et `resources/js/admin/` (planning hebdo, pointages, récap mensuel en heures + export CSV, intérimaires, restaurants). Routeur maison dans `lib/router.svelte.js`.

## Conventions

- Pas de compte ni de mot de passe : la tablette mémorise son restaurant dans `localStorage` (`/?restaurant=slug` pour la configurer), le QR code imprimable d'un restaurant (admin → Restaurants, lib `qrcode` côté client) pointe sur `/p/slug`, l'admin est sur `/admin`.
- Les dates sont sérialisées en ISO 8601 avec l'offset Europe/Paris (`SerializesLocalDates`). Ne pas réintroduire le `Z` UTC. Le formulaire admin affiche et envoie des heures murales Europe/Paris (`timeZone` explicite), quel que soit le fuseau du navigateur.
- Recherche de noms insensible aux accents via `matchesWorker` (`lib/format.js`). Les appels API ont un timeout de 15 s (`lib/api.js`) et les écrans qui pollent ignorent les réponses périmées (compteur `loadSeq`).
- Corriger un pointage côté admin efface la signature (l'intérimaire doit re-signer).
- Svelte 5 en runes (`$state`, `$derived`, `$props`), pas de store legacy. Textes UI en français.
- Style de l'admin calqué sur le repo `~/dev/coffre` (Laravel Breeze) : nav blanche en haut avec soulignement émeraude, bandeau de titre via `admin/Page.svelte`, tables `card` + en-têtes `.th`, boutons `btn btn-primary` (émeraude) / `btn-secondary`, badges arrondis, police Figtree, plugin `@tailwindcss/forms`. Palette gray/emerald, pas de slate.
