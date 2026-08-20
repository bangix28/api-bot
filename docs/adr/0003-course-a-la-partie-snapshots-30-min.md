# ADR-0003 : La course se mesure à la partie, sur des relevés toutes les 30 minutes

## Statut

Accepté (2026-08-15)

## Contexte

La Ranked Race classe les membres du club sur les LP gagnés pendant une période.
Elle était calculée à la volée depuis `summoner_elo_daily` — un relevé par joueur,
par jour et par file, pris à 3h du matin par le cron `daily-elo`. Deux limites :

- **Granularité quotidienne.** Une partie jouée lundi 22h n'était visible que le
  mardi matin. Pire, la fenêtre « lundi→dimanche » mesurait en réalité lundi 3h →
  dimanche 3h : **les parties du dimanche n'étaient comptées dans aucune semaine**.
- **Départ à date fixe.** Le rang de départ d'un joueur était son rang au lundi 3h,
  qu'il joue ou non. Un joueur Master subissant le decay d'inactivité (−75 LP) puis
  gagnant 2 parties sur 2 (+40 LP) ressortait à **−35 bruts / −77 pondérés** : classé
  derrière un Bronze ayant gagné une partie. Le classement mesurait « qui s'est le
  moins fait décay », pas « qui a le mieux joué ».

Le besoin exprimé était double : rafraîchir toutes les 30 minutes, et faire démarrer
la course à la **première partie classée** plutôt qu'à une date arbitraire.

Contrainte structurante : le cron `refreshSummoners` (*/30) récupérait **déjà**
`rankedSolo` et `rankedFlex` pour tous les comptes, puis jetait la donnée.

## Décision

### 1. Le segment est l'unité de calcul

**Un delta n'est compté que si `wins + losses` a augmenté entre deux relevés.**
Un « segment » est une transition portant au moins une partie ; la progression est
la somme des segments.

Cette règle unique absorbe trois phénomènes qui polluaient le classement — le decay
Master+, les lectures transitoires de l'API Riot, et le reset de saison (le compteur
de parties repart à zéro). Aucun n'a de partie jouée, donc aucun ne produit de segment.

### 2. Le rang de départ découle de la règle, il n'est pas stocké

Si les transitions sans partie valent zéro, alors le premier point qui compte est
mécaniquement **le relevé qui précède la première partie du joueur**. C'est
exactement le « départ à la première game » demandé — obtenu **sans table
d'activation, sans date de départ persistée, sans déclencheur**.

Le départ est **par joueur** : chacun démarre à sa propre première partie. La fin,
elle, reste **fixe et commune** (dimanche 23:59:59 / dernier jour du mois).

### 3. Trois faits distincts, jamais une équation

Exclure le hors-jeu a un prix : `rang de départ + progression ≠ rang d'arrivée` dès
qu'un decay tombe entre deux segments. C'est assumé et rendu lisible — les LP gagnés
ou perdus hors jeu sont exposés à part (`offRaceDelta`). Le front affiche trois faits,
il ne fait jamais l'addition.

### 4. Une table dédiée, alimentée par le cron existant

`summoner_elo_snapshot` (`captured_at DATETIME`, colonnes NOT NULL) est la source
unique de la course. L'écriture est greffée sur `RefreshRiotAccountDataHandler`, où
les rangs sont déjà en mémoire : **coût Riot nul**, garanti par un test qui compte
les appels du client.

`summoner_elo_daily` n'est pas modifiée et continue d'alimenter `/elo-daily`.

### 5. On n'écrit que ce qui apporte une information

`EloChangeDetector` : premier relevé, rang modifié, ou franchissement de journée
(un battement quotidien). Plus une **garde de monotonie** refusant tout relevé
antérieur ou simultané au dernier connu — run concurrent, horloge revenue en arrière,
ou heure d'hiver où 02:30 existe deux fois.

Volumétrie pour 15 comptes : **~60 lignes/jour** au lieu de 1 440.

### 6. Le report est borné à la cadence de collecte

`findForWindow()` renvoie aussi, pour chaque joueur, son dernier relevé **avant** la
fenêtre : sans lui, la première partie de la période serait invisible, le premier
relevé de la fenêtre lui étant postérieur.

Ce report est borné à **deux heures**, calé sur la cadence de collecte et non sur des
jours. Le battement quotidien garantit déjà une ligne à l'intérieur de chaque fenêtre :
le report n'a donc pas à faire apparaître un joueur, seulement à affiner son rang de
départ.

> **Première borne essayée : deux jours. C'était faux, et les données réelles l'ont
> montré.** Sur un événement de juillet, un joueur passait de 149 à **764** de
> progression brute : le segment reliant un relevé de l'avant-veille au premier relevé
> de la fenêtre créditait à celle-ci deux jours de parties jouées avant son début.

### 7. Le fuseau est explicite, et PHP est aligné dessus

Les fenêtres de course sont des bornes **civiles** (lundi 00:00 à Paris). `SystemClock`
reçoit son fuseau par configuration (`app.timezone`) au lieu de suivre celui du serveur,
et **le fuseau de PHP est aligné dessus au démarrage du Kernel**.

> Sans ce second point, Doctrine réhydratait les `DATETIME` sans fuseau en UTC : l'API
> annonçait `15:08+00:00` pour un relevé pris à 15h08 à Paris, et l'âge des données
> tombait à zéro en permanence. Bug latent invisible tant que rien ne comparait un
> instant relu avec l'heure courante.

### 8. La validation conditionnelle s'appuie sur l'instant du dernier relevé

L'ETag dérive de `MAX(captured_at)`, pas d'un hachage de la réponse : un `MAX` indexé
suffit à répondre `304` avant le contrôleur. Un hachage de contenu serait de toute
façon inutilisable — la réponse porte `generatedAt` et `snapshotAgeSeconds`, qui
changent à chaque seconde.

### 9. Cron système, verrouillé

Aucune nouvelle exécution n'est créée : on greffe une écriture sur une boucle
existante. `symfony/lock` (store `flock`) protège `refreshSummoners` et `daily-elo`
du chevauchement — `RiotApiGateway` peut dormir 125 s par appel rate-limité, un run
peut déborder sur le suivant. Verrou non pris → `warning` et **`Command::SUCCESS`** :
un chevauchement légitime ne doit pas faire alerter le cron.

### 10. Les matchs des deux files sont collectés

La collecte ne connaissait que la queue 420 : aucune ligne flex n'entrait en base.
Les deux files classées sont désormais interrogées, ce qui permet de dater le départ
d'un joueur à la seconde (`date_game_end − game_duration`) et d'écarter les remakes
de moins de cinq minutes. **Cet affinage ne touche pas au score** : un match non
collecté fait simplement retomber sur l'instant d'observation.

## Alternatives écartées

- **Un troisième cron dédié aux relevés** : `N × 48` appels `league-entries` par jour
  purement redondants (720/jour pour 15 comptes) pour aucune information de plus, la
  donnée étant déjà récupérée par `refreshSummoners`.
- **Réutiliser `summoner_elo_daily`** : son opération `/elo-daily` désactive la
  pagination, justifiée par « 1 point/jour max (~365/an) ». 48 points/jour feraient
  17 520 points/an renvoyés sans pagination. Sa clé unique `(compte, jour, file)`
  l'interdit de toute façon.
- **Stocker la date de départ (table `ranked_race_activation`, colonne sur
  `ranked_race_event`)** : dénormalisation à invalider (backfill, correction de relevé,
  changement de règle) sans rien économiser — la requête qui la calculerait est celle
  qu'on exécute déjà.
- **Détecter la première partie via `history_account_lol` seul** : la queue 440 n'était
  pas collectée, la course flex n'aurait donc jamais démarré. Le delta `wins + losses`
  est queue-agnostique par construction.
- **Déclencheur global (la première partie d'un membre lance la course pour tous)** :
  écarté au profit du départ par joueur, pour qu'un joueur qui commence vendredi
  n'hérite pas du decay subi par les autres depuis lundi.
- **Messenger ou Symfony Scheduler** : cohérent avec l'ADR-0002 — l'infrastructure
  (worker supervisé) reste surdimensionnée pour deux crons sur un VPS.
- **Politique de rétention / compaction** : ingénierie spéculative à ~22 000 lignes/an.

## Conséquences

### Positives

- Un joueur qui gagne ses parties monte au classement, quel que soit son decay.
- La première partie d'une période est comptée, et son heure de lancement est exposée.
- Les parties du dimanche ne sont plus perdues : la fuite passe de ~24 h à ≤ 30 min.
- Coût Riot inchangé pour les points de course ; seul le lot « collecte flex » ajoute
  un appel par compte et par run.
- Le classement ne dépend plus de la collation MySQL : à égalité stricte, l'ordre est
  départagé par `riotId` (mis au jour par la bascule de source).

### Négatives / limites assumées

- **`rang de départ + progression ≠ rang d'arrivée`** dès qu'un decay tombe entre deux
  segments. C'est le prix d'un classement qui récompense le jeu ; `offRaceDelta` le
  rend lisible, mais le front doit être écrit en conséquence.
- ~~**Biais du yo-yo à la frontière de tier** : une montée Gold→Platinum est payée au
  tarif Gold (1.25), la redescente au tarif Platinum (1.4). Un aller-retour coûte donc
  plus qu'il ne rapporte. Le corriger imposerait de découper un delta à la frontière,
  ce que `TierCoefficient` refuse explicitement. Un test fige la valeur attendue pour
  que le biais ne dérive pas en silence.~~
  **Remplacé par l'[ADR-0004](0004-echelle-de-course-continue-et-ponderation-par-palier.md)
  (2026-08-20)** : la pondération est devenue une conversion de position, le découpage à
  la frontière en découle, et un aller-retour vaut exactement zéro.
- **Fuite de fin de période** : une partie terminée à 23h50 le dimanche est observée
  par le relevé de 00h00 lundi et attribuée à la semaine suivante. Bornée à la cadence
  du cron.
- **Stockage en heure civile** dans une colonne sans fuseau : l'ambiguïté du passage à
  l'heure d'hiver (02:30 deux fois) est neutralisée par la garde de monotonie, pas
  éliminée.
- **Les remakes ne sont écartés que du déclencheur de départ, pas du comptage.**
  Question ouverte : *un remake incrémente-t-il `wins`/`losses` dans les league
  entries ?* Si oui, il crée un segment à +0 LP et gonfle le dénominateur du winrate.
  À constater en observation puis à trancher ici.

### Déclencheurs de révision

Cet ADR devra être révisé si :

- la table dépasse **2 millions de lignes** ou **500 Mo**, ou si un `findForWindow`
  mensuel dépasse **200 ms** → écrire `ranked-race:compact --older-than=90days`
  (garder le dernier relevé de chaque heure au-delà de 90 jours) ;
- le métier exige une date de départ **gelée et opposable** même après correction de
  données, ou si la course devient notifiée (Discord) et qu'il faut un état « déjà
  notifié » → la décision n°2 tombe, il faudra persister l'ignition ;
- les crons tournent depuis **plusieurs hôtes** → le store `flock` ne suffit plus ;
- la cadence du cron change → `RaceFreshness::SNAPSHOT_INTERVAL_SECONDS`, la borne de
  report et la marge de clôture (45 min) sont toutes calées dessus ;
- le nombre de comptes approche **30** → le quota Riot devient contraignant ; repli
  documenté : passer `getSummonerAcountsDetails()` (niveau et icône, quasi statique)
  d'un rafraîchissement toutes les 30 minutes à une fois par jour.
