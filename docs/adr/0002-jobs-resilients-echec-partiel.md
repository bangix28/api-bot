# ADR-0002 : Les jobs de refresh continuent sur échec partiel et rendent compte

## Statut

Accepté (2026-07-30)

## Contexte

Les jobs de refresh (comptes, historiques de matchs, snapshots d'elo) itèrent sur
N éléments indépendants, chacun dépendant d'un appel à l'API Riot et d'écritures
en base. Avant ce chantier :

- La boucle de `RefreshRiotAccountDataHandler` n'avait aucune protection : une
  exception sur le compte n°2 (rate-limit 429, compte supprimé, donnée
  inattendue) abandonnait les comptes 3 à N. Un seul compte malade privait tous
  les autres de refresh.
- Les commandes console (`refreshSummoners`, `daily-elo`) laissaient remonter les
  exceptions brutes, ou pire : affichaient `[OK] Commande effectuée avec succès !`
  alors que **tous** les éléments avaient échoué (constaté en dev le 2026-07-30 :
  10 comptes sur 10 en échec, sortie `[OK]`, exit code 0).
- Aucun compte-rendu : impossible de distinguer un run parfait d'un run à moitié
  dégradé sans inspecter la base à la main.
- Les crons prod n'avaient donc aucun moyen de signaler un échec (exit code
  toujours 0, pas de log).

Contrainte : ces traitements tournent en cron sans humain devant ; le succès
« silencieux » et l'échec « silencieux » y sont indistinguables par définition —
seuls les exit codes et les logs permettent à l'extérieur de juger un run.

## Décision

Trois règles, appliquées à tous les jobs itératifs :

1. **Try/catch par élément** : l'échec d'un élément est logué en `warning`
   (channel `refresh`, avec identifiant et exception) et la boucle continue.
   Un élément malade ne coûte que lui-même, pas le reste du lot.

2. **Compteurs + résumé** : chaque handler compte succès et échecs et termine par
   un `info` de synthèse (`Refresh des comptes terminé {"ok":10,"failed":0}`).
   Le taux d'échec d'un run se lit en une ligne de log.

3. **Les commandes traduisent l'issue en exit code** : try/catch global,
   `info` début/fin avec durée, `error` + **`Command::FAILURE`** en cas
   d'exception non rattrapée. Cron, un healthcheck ou un simple `echo $?`
   voient l'échec sans lire les logs.

Les erreurs ne sont jamais avalées : rattrapées → loguées avec contexte ;
non rattrapables → exit code non nul. Dans tous les cas, une trace existe.

### Alternatives écartées

- **Fail-fast (tout arrêter au premier échec)** : inacceptable pour des éléments
  indépendants — un compte supprimé chez Riot bloquerait définitivement le
  refresh de toute la guilde à chaque run.
- **Retry automatique par élément** : complexité prématurée ; le cron suivant
  retentera naturellement. À reconsidérer si les échecs transitoires (429)
  deviennent fréquents.
- **File de messages (Messenger) avec un message par élément** : l'isolation
  d'échec serait meilleure (ack/retry/dead-letter par message), mais
  l'infrastructure (worker, supervision) est surdimensionnée pour deux crons
  sur un VPS.

## Conséquences

### Positives

- Un élément en échec ne coûte que lui-même ; les N-1 autres sont traités.
- Chaque run est auditable : résumé `ok/failed`, durée, détail par échec dans
  `app-YYYY-MM-DD.log` (channel `refresh`).
- Les exit codes sont fiables : un futur healthcheck (type healthchecks.io)
  devient possible sans rien changer au code.

### Négatives / limites assumées

- **Le pattern ne protège pas des échecs Doctrine** (limite vécue le
  2026-07-30) : une exception pendant un `flush()` **ferme l'EntityManager**,
  et tous les `save()` suivants du même process échouent en cascade
  (`EntityManagerClosed`) — le try/catch par élément logue alors N warnings mais
  ne sauve rien. Le pattern isole les échecs *de lecture* (API Riot) ; un échec
  *d'écriture* reste de facto fatal au run. Mitigation possible si le besoin
  se présente : `EntityManager` réinitialisable ou transaction par élément.
- Un échec partiel récurrent peut passer inaperçu : `failed: 1` chaque nuit
  n'alerte personne tant qu'on ne lit pas les logs (pas d'alerting — voir
  ADR-0001, déclencheurs de révision).
- Le résumé agrège : il dit « 3 échecs », pas « toujours le même compte depuis
  3 semaines ». Le diagnostic fin reste manuel (`grep` sur le puuid).

### Déclencheurs de révision

Cet ADR devra être remplacé si :
- les échecs d'écriture en base deviennent courants (→ transaction ou EM par
  élément, voire passage à Messenger) ;
- un système d'alerte est introduit (→ les seuils `failed` devraient déclencher
  une notification, pas seulement une ligne de log) ;
- le volume de comptes rend les runs longs au point de vouloir paralléliser
  (→ la question de l'isolation d'échec se repose entièrement).
