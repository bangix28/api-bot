# Déploiement en production

Procédure de mise en production pour **api-bot**.

> **Environnement cible** : VPS Ubuntu, **nginx + PHP-FPM 8.4 + MySQL**, **sans Docker**.
> (Le dev local, lui, tourne sous Docker — cette procédure ne concerne QUE la prod native.)

Adapte les valeurs entre `< >` à ta prod :
- `<PROJECT_DIR>` : dossier du projet, ex. `/var/www/api-bot`
- `<DB_USER>` / `<DB_NAME>` : identifiants MySQL de prod (ex. base `apibot`)
- `<PHP_FPM>` : service PHP-FPM, ex. `php8.4-fpm`

---

## ⚠️ Spécificités de la release « Ranked Race live » (30 min + départ à la partie)

Décisions et justifications complètes : **`docs/adr/0003-course-a-la-partie-snapshots-30-min.md`**.
Contrat pour l'équipe front : **`docs/front/ranked-race-live.md`**.

1. **Aucune nouvelle variable d'environnement.** Le fuseau (`app.timezone`) et le
   store de verrou (`flock`) sont figés en configuration. Rien à ajouter dans
   `.env.local`.
2. **Nouvelle dépendance `symfony/lock`** → le `composer install` de l'étape 4 est
   obligatoire avant de relancer les crons. Le verrou s'écrit dans le répertoire
   temporaire système ; aucun droit particulier à poser.
3. **Migrations** : création de `summoner_elo_snapshot` puis reprise de
   l'historique quotidien exploitable. `summoner_elo_daily` **n'est pas modifiée**
   — la reprise est donc réversible sans perte, et `/elo-daily` est intact.
4. **Le fuseau de PHP est aligné sur `Europe/Paris` au démarrage du Kernel.** Les
   instants sont stockés en heure civile, il faut donc les relire dans le même
   fuseau — sinon Doctrine les réhydrate en UTC et l'API annonce deux heures
   d'avance. L'écart entre les deux commandes ci-dessous est normal et attendu :
   ```bash
   php -r 'echo date_default_timezone_get(), PHP_EOL;'   # PHP brut → souvent UTC
   php bin/console about --env=prod | grep -i timezone   # avec Kernel → Europe/Paris
   ```
   Si la seconde ne dit pas `Europe/Paris`, le déploiement est incomplet.
5. **Ordre de déploiement en deux temps.** Idéalement, déployer d'abord la version
   qui *écrit* les relevés sans les lire, **laisser tourner 24 à 48 h**, puis
   déployer la bascule de lecture. Si tout part d'un coup, la course n'aura que
   l'historique repris tant que le cron n'a pas tourné plusieurs fois — les
   fenêtres en cours seront pauvres pendant une journée.
6. **`rankStart` change de sens sans changer de forme** : c'est désormais le rang
   *juste avant la première partie* du joueur, plus le rang du lundi 3h. À
   annoncer au front, sinon ça se lit comme un bug.

### Contrôles spécifiques après déploiement

```bash
# Les relevés arrivent-ils ?
mysql -u <DB_USER> -p <DB_NAME> -e "
  SELECT DATE(captured_at) j, COUNT(*) n FROM summoner_elo_snapshot GROUP BY j ORDER BY j DESC LIMIT 3;"
# ~60 lignes/jour pour 15 comptes (dédoublonnage actif). Beaucoup plus = dédup HS.

# Les deux files sont-elles couvertes ?
mysql -u <DB_USER> -p <DB_NAME> -e "
  SELECT queue_type, COUNT(*) FROM summoner_elo_snapshot GROUP BY queue_type;"

# Aucun compte oublié ?
mysql -u <DB_USER> -p <DB_NAME> -e "
  SELECT COUNT(DISTINCT riot_account_id) FROM summoner_elo_snapshot;"

# Le verrou se déclenche-t-il anormalement souvent ?
grep 'déjà en cours' var/log/app-$(date +%F).log

# Des points de course perdus ?
grep 'Point de course non enregistré' var/log/app-$(date +%F).log

# Quota Riot (surtout après l'ajout de la collecte flex : +1 appel/compte/run)
grep rate_limited var/log/app-$(date +%F).log
```

Puis vérifier les quatre combinaisons de `/api/ranked-race` et un événement passé,
et qu'une seconde requête avec `If-None-Match` renvoie bien **304**.

**Rollback** : `doctrine:migrations:migrate prev --env=prod` ne supprime que les
points repris (03:00 pile) et laisse ceux écrits par le cron. `summoner_elo_daily`
étant intacte, la course redevient calculable dès que le code repasse en arrière.

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

### 4. Purger le cache compilé de la release précédente
```bash
rm -rf var/cache/prod
```
> `composer install` déclenche un `cache:clear` qui **démarre d'abord le conteneur
> déjà présent** — celui de la release précédente. En prod (`debug=false`) il n'est
> pas revalidé : du code neuf s'exécute alors contre une configuration périmée.
> C'est ce qui a fait échouer le déploiement du 2026-08-15 :
> `You have requested a non-existent parameter "app.timezone"`.
> Purger d'abord rend ce cas impossible, quel que soit l'écart entre les releases.

### 5. Dépendances (sans dev, autoload optimisé)
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

### 6. Vérifier l'état des migrations AVANT exécution
```bash
php bin/console doctrine:migrations:status --env=prod
php bin/console doctrine:migrations:list --env=prod
```
On doit voir **2 migrations à appliquer**, jouées dans cet ordre :
1. `Version20260628111804` — normalise les comptes non classés (`tier NULL` → `UNRANKED`)
2. `Version20260719111548` — supprime la colonne `data`

> Un avertissement « previously executed migrations not registered » sur d'anciennes versions est **pré-existant** et sans danger.

### 7. Vider et réchauffer le cache prod
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```
> ⚠️ Le `warmup` **compile le conteneur** : s'il échoue sur `app.riot.api.token`, c'est que le paramètre manque en prod (cf. Pré-requis).

### 8. Appliquer les migrations (normalisation puis DROP)
```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### 9. Recharger PHP-FPM (purge de l'OPcache)
```bash
sudo systemctl reload <PHP_FPM>
# si opcache.validate_timestamps=0 : préférer un restart
# sudo systemctl restart <PHP_FPM>
```

### 10. Désactiver la maintenance
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
