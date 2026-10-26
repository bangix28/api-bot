# ADR-0005 : Les défis de LAN se valident sur des données lues sur les postes des joueurs, jamais déclarées

## Statut

Accepté (2026-10-25)

Complété le même jour après une capture de la Live Client Data API sur une vraie
partie d'ARAM du chaos (cf. « Vérifié sur une vraie partie ») : le résultat et le
premier sang sont disponibles, pas les augments.

Révisé le 2026-10-26 : **Spectator-V5 est abandonné**. Le cycle des parties
(début, regroupement, fin) est piloté par les relevés de l'agent (décision 2) ;
la décision initiale est conservée dans les alternatives écartées.

## Contexte

Les défis de LAN reprennent la mécanique de l'ARAM du chaos (« ARAM: Mayhem »,
file `2400`) : avant chaque partie, chaque joueur choisit une carte défi parmi
trois, avec un stock de relances, et le défi doit être validé à partir de ce qui
s'est réellement passé dans la partie. 3 à 5 joueurs, sur une soirée ou un week-end.

Le premier jet (PR #46) collectait la file `2400` via Match-V5, comme la collecte
classée. Testé le 2026-10-25 sur un compte réel, puis sur 35 autres
joueurs : **la file 2400 ne renvoie jamais aucun match**, alors que les
ARAM classiques (`450`) et l'Arena (`1750`) remontent normalement.

Ce n'est pas un défaut de notre collecte :

- **OP.GG** (centre d'aide, août 2026) : « ARAM:Mayhem match data is not provided
  by the Riot API, and match history can only be saved through the OP.GG Desktop app. »
- Le projet communautaire [ARAM-Mayhem-Database](https://github.com/Lanternko/ARAM-Mayhem-Database)
  collecte ses parties via le client LoL sur les postes des joueurs, faute d'API
  publique pour cette file.

Les autres sources possibles ont été vérifiées :

- **Spectator-V5** (`/lol/spectator/v5/active-games/by-summoner/{puuid}`, schéma
  officiel via [riotapi-schema](https://github.com/MingweiSamuel/riotapi-schema)) :
  partie en cours, file, heure de début, joueurs, champions, équipes, runes, sorts.
  **Aucune statistique** (ni KDA, ni dégâts, ni augments) et **pas le résultat**.
- **Live Client Data API** ([doc Riot](https://developer.riotgames.com/docs/lol),
  `https://127.0.0.1:2999/liveclientdata/*`) : officielle, en lecture seule, sans
  clé, « only available locally ». Scores de **tous** les joueurs (kills, morts,
  assistances, CS, vision), objets, niveaux, et un journal d'événements
  (`ChampionKill` avec tueur/victime/assistants, `Multikill`, `Ace`…). Pas de
  dégâts, de soins, de CC ni d'augments dans la documentation.
- **API du client LoL (LCU)** : statistiques de fin de partie complètes, mais
  non documentée par Riot.

### Vérifié sur une vraie partie

Capture du 2026-10-25 : partie d'ARAM du chaos de 16 min 20 s, `allgamedata`
relevé chaque minute et `eventdata` jusqu'à la fin, depuis le poste d'un
joueur. La capture a démarré à 1 min de jeu sans rien perdre : le journal
d'événements est cumulatif depuis `GameStart`.

- **Mode** : `gameMode` vaut `"KIWI"`, sur `Map12` (Abîme hurlant). Le nom
  « ARAM du chaos » n'apparaît nulle part.
- **Résultat** : un événement `GameEnd` porte `"Result": "Win"`, **du point de
  vue du joueur du poste**. Le résultat des autres se déduit de leur équipe
  (`team` : `ORDER` / `CHAOS`). Seule la victoire a été observée : la valeur
  exacte en cas de défaite reste à confirmer.
- **Premier sang** : événement `FirstBlood`, avec le joueur dans `Recipient`.
- **Kills** : un `ChampionKill` par kill (`KillerName`, `VictimName`,
  `Assisters`). Recompter les kills dans le journal donne exactement les
  `scores.kills` des 10 joueurs. Un solo kill est un `ChampionKill` dont
  `Assisters` est vide.
- **Multi-kills et Aces** : `Multikill` (`KillStreak` de 2 à 5) et `Ace`
  (`Acer`, `AcingTeam`).
- **Identité** : `allPlayers` donne le Riot ID complet (`riotId`,
  `riotIdGameName`, `riotIdTagLine`), mais **les événements ne nomment les
  joueurs que par leur pseudo, sans le tag**. Les pseudos sont uniques dans la
  partie : la correspondance pseudo → Riot ID se fait par `allPlayers`.
- **Augments** : **absents**, ni en clé ni en valeur, dans aucun des 15 relevés.
- **Dégâts, soins, CC** : absents, comme le disait la documentation.

## Décision

### 1. Aucune validation déclarative

Un joueur ne déclare jamais lui-même la réussite de son défi, même confirmé par
un autre participant. Une carte n'est validée que sur des données lues dans le
jeu.

### 2. Les relevés de l'agent pilotent le cycle des parties

L'agent voit une partie du début à la fin (l'API locale du jeu n'existe que
pendant la partie) et ses relevés contiennent les 10 joueurs : ils suffisent à
dérouler une manche, sans interroger Riot.

- **Identifier une partie.** La Live Client Data API ne donne pas d'identifiant :
  le serveur en construit un à partir du mode, des 10 Riot ID et de l'heure de
  début estimée (réception du premier relevé − `gameTime`). Deux agents de la
  même partie retombent sur la même clé, à une minute près.
- **Début.** Le **premier relevé** d'une partie la déclare commencée : la carte
  de chaque participant de la LAN présent dans `allPlayers`, choisie avant ce
  moment, est verrouillée sur elle. Une carte choisie après vaut pour la partie
  suivante.
- **Démarrage tardif.** Si ce premier relevé arrive après **90 secondes de jeu**
  (agent lancé en retard), la partie n'engage aucune carte : sinon un joueur
  pourrait choisir sa carte en connaissant déjà le début de la partie.
- **Fin.** Le relevé final (`GameEnd`, ou fermeture du jeu) règle la manche et
  distribue les offres suivantes.
- **Partie abandonnée.** Sans relevé pendant **une heure**, la partie est réglée
  en verdict inconnu (relance rendue) : une carte ne reste jamais bloquée sur
  une partie dont on n'aura plus de nouvelles.

### 3. Les statistiques viennent d'un agent sur le poste d'un joueur

Un agent lancé sur le PC d'un joueur lit la Live Client Data API pendant la
partie et transmet ses relevés à api-bot, authentifié par le compte du joueur.
La LCU n'est utilisée que si une carte indispensable en dépend, et seulement
après vérification sur une vraie partie.

- **Un agent par partie suffit** : l'API donne les scores et le journal des
  10 joueurs, donc un seul relevé règle les cartes de tous les participants de
  la partie. Les autres n'installent rien. Personne n'est désigné à l'avance :
  chaque relevé dit de qui il vient, par son jeton.

- **L'agent est un projet séparé** : autre langage, livré aux joueurs sous forme
  d'exécutable Windows, sans rien de commun avec api-bot qu'un contrat d'API
  ([docs/agent/contrat-releve.md](../agent/contrat-releve.md)).
- **Il envoie les données brutes** (`allPlayers`, `events`, `gameData`), et le
  **serveur fait les calculs** (série la plus longue, solo kills, kills
  d'équipe, vainqueur). Un calcul faux se corrige sans réinstaller l'agent sur
  chaque poste, et le serveur garde la preuve de ce qu'il a reçu.
- **Les joueurs sont identifiés par leur Riot ID**, celui que donne
  `allPlayers`, rapproché du `riotId` des comptes suivis. Les pseudos des
  événements sont d'abord résolus en Riot ID complet dans la partie (cf.
  « Vérifié sur une vraie partie »).

### 4. Un relevé n'est cru que s'il est cohérent

- Il doit porter sur la propre partie de celui qui l'envoie (le joueur du poste
  est l'un de ses comptes) et s'inscrire dans une partie cohérente : même clé
  (décision 2), même mode, mêmes 10 joueurs d'un relevé à l'autre.
- Une partie rapportée par un seul agent est acceptée sur son relevé. Rapportée
  par plusieurs, leurs relevés doivent **concorder** ; un relevé contradictoire
  est écarté.

### 5. Une carte n'existe que si sa condition est mesurable

Le catalogue est filtré par les données réellement disponibles : multi-kills,
morts, KDA, participation aux kills, premier sang, solo kills, Ace, victoire et
durée de partie sont calculables depuis la Live Client Data API (vérifié sur
une vraie partie). Les cartes de dégâts, soins, boucliers, CC et augments sont
retirées tant qu'aucune source fiable ne les fournit.

Le verdict « inconnu » reste nécessaire : un agent coupé avant `GameEnd` ne
rapporte pas le résultat.

## Alternatives écartées

- **Spectator-V5 pour le cycle des parties** (décision initiale, abandonnée le
  2026-10-26). Le serveur interrogeait Riot pour voir les parties démarrer et
  finir. Abandonné : redondant avec l'agent, qui voit tout cela de première main ;
  dépendant d'une clé Riot qui, en développement, expire toutes les 24 h — une
  clé périmée en pleine LAN aurait tout bloqué ; et jamais vérifié sur la file
  2400. Il reste envisageable comme **contrôle indépendant** (la partie a-t-elle
  eu lieu ?) si la confiance dans un agent unique devenait un problème.
- **Match-V5, file 2400** : ne renvoie rien (cf. Contexte).
- **Validation par les joueurs**, avec ou sans confirmation croisée : trop facile
  à contourner, refusée.
- **Basculer sur l'ARAM classique (450)**, validable par Match-V5 : perd le mode
  qui motive la feature.
- **Décoder le flux spectateur** (`observers.encryptionKey`) : rétro-ingénierie
  hors des règles Riot.
- **Récupérer les données d'OP.GG** : pas d'API publique, et dépendance à un tiers.

## Conséquences

### Positives

- La source est officielle (Live Client Data API), sans clé ni quota Riot.
- Les événements arrivent en direct : les annonces « Bob vient de réussir son
  quadra » deviennent possibles.
- Le moteur de cartes reçoit des relevés, pas des matchs Match-V5 : il ne dépend
  pas de la source, et une future ouverture de la file 2400 s'y brancherait.

### Négatives / limites assumées

- Un composant de plus à installer, sur au moins un PC par partie, sous Windows (LoL ne tourne
  pas sous Linux) ; il se développe et se teste sous Linux contre un faux serveur
  Live Client qui rejoue les exemples officiels de Riot.
- Avec un seul agent dans la partie, il n'y a pas de recoupement : la confiance
  repose sur le joueur qui le porte. Aucun contrôle indépendant côté Riot ne
  confirme que la partie a eu lieu ; entre amis, c'est assumé.
- Un agent lancé après 90 s de jeu fait perdre la partie aux défis (décision 2).
- Une partie sans aucun agent n'est pas vue : ses cartes finissent en verdict
  inconnu.
- Catalogue réduit tant que dégâts, soins, CC et augments ne sont pas disponibles.
- Le résultat n'est connu que par les agents présents jusqu'à `GameEnd`.

### Points ouverts

- La valeur exacte de `GameEnd.Result` en cas de défaite (seul `"Win"` a été
  observé).
- Les seuils (90 s de démarrage tardif, une minute de tolérance sur la clé, une
  heure avant abandon) sont des estimations, à confirmer en LAN.

### Déclencheurs de révision

- Riot ouvre la file 2400 dans Match-V5 : la validation peut repasser côté
  serveur, sans agent.
- La Live Client Data API se met à exposer dégâts, soins ou augments : le
  catalogue peut s'élargir sans changer l'architecture.
