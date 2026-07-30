# Déploiement en production

Procédure de mise en production pour **api-bot**.

> **Environnement cible** : VPS Ubuntu, **nginx + PHP-FPM 8.4 + MySQL**, **sans Docker**.
> (Le dev local, lui, tourne sous Docker — cette procédure ne concerne QUE la prod native.)

Adapte les valeurs entre `< >` à ta prod :
- `<PROJECT_DIR>` : dossier du projet, ex. `/var/www/api-bot`
- `<DB_USER>` / `<DB_NAME>` : identifiants MySQL de prod (ex. base `apibot`)
- `<PHP_FPM>` : service PHP-FPM, ex. `php8.4-fpm`

---

## ⚠️ Spécificités de la release « refonte hexagonale /refresh »

Cette release embarque **2 migrations** dont une **destructive**. Trois points de vigilance :

1. **`DROP` de la colonne `data`** (`history_account_lol`) — **irréversible**. Le `down()` recrée une colonne *vide* : il ne restaure PAS les données. → **la sauvegarde BDD (étape 2) est obligatoire**.
2. **Le nouveau code est incompatible avec les données non normalisées.** Il lit les comptes via `RankedTier/RankedRank::fromString()` ; sur des lignes non classées non encore migrées (`tier NULL` / rang `'non classée'`), il **lève une exception**. → **les migrations doivent être appliquées AVANT que le nouveau code serve du trafic** (d'où la fenêtre de maintenance).
3. **API** : l'endpoint `GET /history-account-lol/{id}` ne renvoie plus le champ `data`. Vérifier qu'aucun consommateur prod ne s'en sert.

---

## Pré-requis

- Accès SSH au VPS avec droits `sudo`.
- Le paramètre **`app.riot.api.token`** doit être défini côté prod (via `.env.local` / variable d'env). Il est désormais injecté à la compilation du conteneur (`#[Autowire]`) → un token manquant fait **échouer le `cache:warmup`**.
- `APP_ENV=prod` (dans `.env.local`).
- La PR a été **mergée sur `master`** (on déploie `master`, pas la branche de feature).

---

## Procédure

```bash
cd <PROJECT_DIR>
```

### 1. Activer la maintenance
Couper le trafic applicatif le temps du déploiement (voir l'annexe nginx en bas).

### 2. 🔒 Sauvegarde de la base (NON négociable)
```bash
mysqldump -u <DB_USER> -p <DB_NAME> > ~/backup_<DB_NAME>_$(date +%F_%H%M).sql
ls -lh ~/backup_<DB_NAME>_*.sql   # vérifier que le dump n'est pas vide
```

### 3. Récupérer le code
```bash
git fetch origin
git checkout master
git pull --ff-only origin master
```

### 4. Dépendances (sans dev, autoload optimisé)
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

### 5. Vérifier l'état des migrations AVANT exécution
```bash
php bin/console doctrine:migrations:status --env=prod
php bin/console doctrine:migrations:list --env=prod
```
On doit voir **2 migrations à appliquer**, jouées dans cet ordre :
1. `Version20260628111804` — normalise les comptes non classés (`tier NULL` → `UNRANKED`)
2. `Version20260719111548` — supprime la colonne `data`

> Un avertissement « previously executed migrations not registered » sur d'anciennes versions est **pré-existant** et sans danger.

### 6. Vider et réchauffer le cache prod
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```
> ⚠️ Le `warmup` **compile le conteneur** : s'il échoue sur `app.riot.api.token`, c'est que le paramètre manque en prod (cf. Pré-requis).

### 7. Appliquer les migrations (normalisation puis DROP)
```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### 8. Recharger PHP-FPM (purge de l'OPcache)
```bash
sudo systemctl reload <PHP_FPM>
# si opcache.validate_timestamps=0 : préférer un restart
# sudo systemctl restart <PHP_FPM>
```

### 9. Désactiver la maintenance
Rouvrir le trafic (annexe nginx).

---

## Vérifications post-déploiement

```bash
# La colonne data a bien disparu
mysql -u <DB_USER> -p <DB_NAME> -e "SHOW COLUMNS FROM history_account_lol;"

# Plus aucune ligne 'non classée' / NULL
mysql -u <DB_USER> -p <DB_NAME> -e "SELECT DISTINCT summoner_ranked_solo_tier FROM riot_account;"
```

- Ouvrir la route **`/refresh`** : la page répond, colonnes **tier / rank / LP** affichées correctement.
- Commande témoin : `php bin/console refreshSummoners --env=prod` (tape l'API Riot réelle).
- Surveiller les logs quelques minutes : `tail -f var/log/app-$(date +%F).log` (voir section « Logs & monitoring »).

---

## Logs & monitoring

Les logs applicatifs sont écrits dans `<PROJECT_DIR>/var/log/` via des handlers Monolog
`rotating_file` (fichiers datés, rotation automatique, aucun logrotate nécessaire pour eux).

| Fichier | Contenu | Rétention |
|---|---|---|
| `prod-YYYY-MM-DD.log` | Erreurs HTTP avec contexte complet (handler `fingers_crossed` : n'écrit que si une requête produit une `error`) | 14 jours |
| `app-YYYY-MM-DD.log` | Signaux métier dès `info` : appels API Riot (channel `riot`), jobs de refresh (channel `refresh`) | 14 jours |
| `deprecation-YYYY-MM-DD.log` | Dépréciations PHP/Symfony | 7 jours |
| `cron-refresh.log`, `cron-daily-elo.log` | Sortie console des crons (résumés, erreurs fatales) | 8 semaines (logrotate) |

### Commandes utiles

```bash
cd <PROJECT_DIR>

# Suivre l'activité métier du jour (refresh, appels Riot)
tail -f var/log/app-$(date +%F).log

# Suivre les erreurs HTTP du jour
tail -f var/log/prod-$(date +%F).log

# L'API Riot nous rate-limite-t-elle ?
grep rate_limited var/log/app-$(date +%F).log

# Combien de comptes ont été ignorés lors des refresh du jour ?
grep -c 'Refresh du compte ignoré' var/log/app-$(date +%F).log

# Résumés des derniers runs de refresh
grep 'Refresh des comptes terminé' var/log/app-$(date +%F).log
```

### Installation initiale (one-shot sur le VPS)

```bash
cd <PROJECT_DIR>

# 1. Droits : tout ce qui écrit dans var/log tourne en www-data (FPM + crons).
#    setgid (2775) pour que les nouveaux fichiers héritent du groupe.
sudo chown -R www-data:www-data var/log
sudo chmod 2775 var/log

# 2. Rotation des logs cron (les logs Monolog s'auto-rotent via max_files)
sudo cp deploy/logrotate/api-bot /etc/logrotate.d/api-bot
sudo logrotate -d /etc/logrotate.d/api-bot   # dry-run : vérifier qu'il ne râle pas

# 3. Crons dans le crontab de www-data (même utilisateur que FPM → pas de conflit de droits)
sudo crontab -u www-data -e   # copier le contenu de deploy/crontab.example
sudo crontab -u www-data -l   # vérifier
```

> Les commandes cron retournent un exit code ≠ 0 en cas d'échec : les erreurs
> fatales apparaissent dans `cron-*.log`, le détail dans `app-YYYY-MM-DD.log`.

---

## Rollback

Un rollback doit remettre **code ET schéma cohérents** (l'ancien code cherche la colonne `data`).

```bash
# 1. Code
cd <PROJECT_DIR>
git checkout <commit_ou_tag_precedent>
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console cache:clear --env=prod && php bin/console cache:warmup --env=prod
sudo systemctl reload <PHP_FPM>

# 2. Schéma : restaurer le backup (la colonne data et ses données reviennent)
mysql -u <DB_USER> -p <DB_NAME> < ~/backup_<DB_NAME>_<...>.sql
```

> ⚠️ `doctrine:migrations:migrate prev` recrée la colonne `data` **vide** : ça ne suffit pas si tu as besoin des anciennes données → **restaure le backup** (étape 2 ci-dessus).

---

## Annexe — bascule maintenance nginx (exemple)

Dans le `server { }` du site, servir un 503 quand un fichier drapeau existe :

```nginx
set $maintenance 0;
if (-f <PROJECT_DIR>/maintenance.on) { set $maintenance 1; }
if ($maintenance = 1) {
    return 503;
}
error_page 503 /maintenance.html;
location = /maintenance.html { root <PROJECT_DIR>/public; internal; }
```

Activer / désactiver :
```bash
touch <PROJECT_DIR>/maintenance.on     # maintenance ON
rm <PROJECT_DIR>/maintenance.on        # maintenance OFF
```
