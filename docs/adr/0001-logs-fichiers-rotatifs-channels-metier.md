# ADR-0001 : Logs applicatifs en fichiers rotatifs avec channels métier dédiés

## Statut

Accepté (2026-07-30)

## Contexte

Jusqu'ici l'application utilisait la configuration Monolog par défaut de Symfony Flex,
pensée pour un déploiement conteneurisé :

- En prod, tous les logs partaient sur `php://stderr`. Or la prod est un **VPS
  nginx + PHP-FPM sans Docker** : le stderr des workers FPM y est perdu (ou noyé
  dans le log global de FPM), et celui des commandes cron part dans le mail cron.
  Concrètement, **aucun log applicatif n'était consultable en prod**.
- L'unique handler `fingers_crossed` (déclenché par une `error`) jetait les
  `warning` métier (compte ignoré pendant un refresh, rate-limit Riot…) dès lors
  qu'aucune erreur ne survenait dans la même requête. Les signaux les plus utiles
  étaient donc filtrés par construction.
- Aucune distinction entre le bruit HTTP et les signaux métier : impossible de
  suivre un job de refresh sans grep-er au milieu des logs de requêtes.
- L'application n'a **pas d'agrégateur de logs** (pas d'ELK/Loki/Sentry) : le seul
  consommateur des logs est un humain connecté en SSH.

## Décision

1. **Destination prod : `rotating_file` vers `var/log/`**, pas `stderr`.
   Les fichiers sont datés (`prod-YYYY-MM-DD.log`) et auto-rotés par Monolog
   (`max_files`), sans dépendre de logrotate ni de droits root. logrotate système
   n'est utilisé que pour les sorties console des crons (`cron-*.log`).

2. **Deux channels métier dédiés**, sortis du pipeline HTTP :
   - `riot` : chaque appel à l'API Riot (endpoint, durée, issue) — émis par
     `RiotApiGateway`, seul point de contact avec l'API ;
   - `refresh` : cycle de vie des jobs de refresh (démarrage, compte ignoré,
     résumé ok/failed, durée) — émis par les handlers Application et les commandes.

   Ils sont écrits **sans condition dès `info`** dans `app-YYYY-MM-DD.log` par un
   handler propre. Le `fingers_crossed` est conservé pour le channel HTTP
   uniquement (c'est son vrai rôle : dump complet avec contexte debug quand une
   requête produit une `error`), avec `channels: ["!riot", "!refresh", ...]`.

3. **Format ligne lisible**, pas JSON : le consommateur est un humain avec
   `tail`/`grep`. Le JSON n'apporte rien sans collecteur pour le parser.

4. **Convention d'injection alignée sur l'architecture hexagonale** :
   - couche **Application** : `Psr\Log\LoggerInterface $refreshLogger = new NullLogger()`
     — PSR-3 est une norme, pas un framework ; le *nom du paramètre* fait le
     routage vers le channel (alias d'autowiring de MonologBundle), et le
     `NullLogger` par défaut garde les tests unitaires sans conteneur ;
   - couche **Infrastructure** : attribut `#[WithMonologChannel('riot')]` — plus
     explicite, et importer Monolog y est légitime.

### Alternatives écartées

- **Garder `stderr` + configurer FPM (`catch_workers_output`)** : logs mélangés à
  ceux de FPM, pas de séparation par channel, dépendance à une config système
  hors du repo.
- **JSON + agrégateur (Loki/Grafana, Sentry)** : surdimensionné pour un projet
  mono-serveur consulté en SSH ; reporté à plus tard (voir Conséquences).
- **Un seul channel applicatif** : mélangerait la télémétrie des appels Riot
  (volumineuse, technique) avec les événements de jobs (rares, métier).

## Conséquences

### Positives

- Les logs prod existent enfin, lisibles en SSH : `tail -f var/log/app-$(date +%F).log`
  (procédure complète dans DEPLOY.md, section « Logs & monitoring »).
- Les warnings métier ne sont plus jamais filtrés ; les échecs partiels des
  refresh sont visibles et comptés (`{"ok":10,"failed":0}`).
- La latence et le taux d'erreur de l'API Riot sont mesurés en continu
  (baseline actuelle : ~75-155 ms/appel).
- Rotation sans intervention : rétention 14 jours (7 pour les dépréciations),
  bornée en espace disque.

### Négatives / dettes assumées

- Les logs sont locaux au VPS : pas d'alerte automatique, pas de recherche
  transverse, perdus si le disque meurt. Un humain doit se connecter pour les lire.
- Deux mécanismes de rotation coexistent (Monolog `max_files` pour l'app,
  logrotate pour les crons).
- La convention « nom de paramètre = channel » est implicite : un renommage
  malencontreux (`$refreshLogger` → `$logger`) reroute silencieusement vers le
  channel par défaut. Vérifiable via `bin/console debug:container <service>`.

### Déclencheurs de révision

Cet ADR devra être remplacé si :
- la prod passe en conteneur (→ `stderr` redevient le bon choix) ;
- un agrégateur ou un service d'alerte est introduit (→ formatter JSON, handler
  d'expédition) ;
- l'équipe grandit au point que « lire les logs en SSH » ne passe plus à l'échelle.
