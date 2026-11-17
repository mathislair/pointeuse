# Pointeuse intérimaires

Application de pointage pour les intérimaires de plusieurs restaurants : chaque intérimaire pointe son arrivée et son départ sur la tablette du restaurant, valide ses heures et signe ; l'administrateur voit qui a travaillé, corrige les pointages et obtient le récap du mois (shifts et heures par intérimaire, export CSV).

- **Backend** : Laravel 13, API JSON, base SQLite.
- **Frontend** : Svelte 5 + Tailwind 4 (SPA, vue mobile incluse).
- **Déploiement** : un seul conteneur Docker (Apache + PHP 8.4), données persistées dans un volume.

## Notions

- **3 shifts par jour** : Open 9h–17h30, Swing 17h30–23h30, Close 23h30–6h (configurables dans `config/pointeuse.php`). Un intérimaire est planifié d'une heure à une autre (la plage peut déborder d'un shift, ou finir le lendemain) ; le shift est déduit automatiquement de la plage (celui qu'elle recouvre le plus). Un intérimaire ne peut pas être planifié deux fois sur le même créneau, quel que soit le restaurant.
- **Intérimaire** : nom + prénom, rien d'autre. La liste est commune à tous les restaurants.
- **Restaurant** : chaque tablette est reliée à un restaurant ; un intérimaire est planifié pour un shift dans un restaurant.
- Pas de compte ni de mot de passe.

## Lancer avec Docker

```bash
docker compose up -d --build
```

L'application est disponible sur http://localhost:8080 (changer le port avec `PORT=8090 docker compose up -d`).
Au premier démarrage, la base est créée et quelques restaurants / intérimaires de démonstration sont ajoutés. La clé d'application et la base SQLite vivent dans le volume `pointeuse-data`.

Variables utiles (dans `docker-compose.yml` ou l'environnement) : `PORT`, `APP_URL`, `APP_KEY` (sinon générée automatiquement).

## Utilisation

- **Pointeuse** : `/` — choisir le restaurant une fois (ou ouvrir `/?restaurant=<slug>`, le lien est affiché dans Admin → Restaurants). La vue montre le calendrier de la journée (qui est planifié, de quelle heure à quelle heure, présence réelle en temps réel) et une carte par intérimaire : *Pointer l'arrivée* → *Pointer le départ* → *Valider et signer* (récapitulatif des heures + signature au doigt). Le bouton *Non planifié* permet de faire pointer un intérimaire absent du planning : il est pointé à partir de maintenant sur le shift en cours, sans heure de fin (c'est le départ pointé qui fait foi) et sans rien saisir d'autre que son nom.
- **Administration** : `/admin`
  - *Planning* : grille semaine × restaurants × shifts, ajout / modification / suppression d'affectations.
  - *Pointages* : tableau filtrable (période, restaurant, intérimaire, statut), modification des heures, absence, note, visualisation de la signature. Modifier les heures efface la signature.
  - *Récap du mois* : shifts et heures par intérimaire (détail par restaurant et par shift), par restaurant, alertes (non signés, en cours, absences), export CSV.
  - *Intérimaires* et *Restaurants* : gestion des listes.

## Développement local

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve        # http://localhost:8000
npm run dev              # Vite avec rechargement à chaud
```

Tests : `php artisan test`.

## API (résumé)

| Méthode | Route | Rôle |
| --- | --- | --- |
| GET | `/api/config` | shifts, date de service courante |
| CRUD | `/api/restaurants`, `/api/workers`, `/api/assignments` | référentiels et planning (`assignments?from=&to=` ; création avec `date`, `start`, `end` en HH:MM, le shift est calculé) |
| GET | `/api/pointeuse/{slug}/day` | journée courante d'un restaurant |
| POST | `/api/pointeuse/{slug}/walk-in` | intérimaire non planifié : crée l'affectation et pointe l'arrivée |
| POST | `/api/assignments/{id}/clock-in` / `clock-out` / `sign` | pointage et signature |
| GET | `/api/recap?month=YYYY-MM` · `/api/recap/export` | récap mensuel JSON / CSV |
