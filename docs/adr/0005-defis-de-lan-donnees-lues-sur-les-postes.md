# ADR-0005 : Les défis de LAN se valident sur des données lues sur les postes des joueurs, jamais déclarées

## Statut

Accepté (2026-10-25)

Complété le même jour après une capture de la Live Client Data API sur une vraie
partie d'ARAM du chaos (cf. « Vérifié sur une vraie partie ») : le résultat et le
premier sang sont disponibles, pas les augments. Reste ouvert : la visibilité de
la file 2400 dans Spectator-V5.

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

### 2. Le serveur observe les parties avec Spectator-V5

Pendant une LAN en cours, api-bot interroge Spectator-V5 pour chaque participant.
Il en déduit le **début** d'une partie (cartes verrouillées, participants qui
jouent ensemble) et sa **fin** (l'endpoint repasse en 404 : la manche se clôt,
une nouvelle offre est distribuée). Coût : 5 joueurs × 2 appels/min, négligeable.

### 3. Les statistiques viennent d'un agent sur le poste d'un joueur

Un agent lancé sur le PC d'un joueur lit la Live Client Data API pendant la
partie et transmet ses relevés à api-bot, authentifié par le compte du joueur.

- **Un agent par partie suffit** : l'API donne les scores et le journal des
  10 joueurs, donc un seul relevé règle les cartes de tous les participants de
  la partie. Les autres n'installent rien. Personne n'est désigné à l'avance :
  chaque relevé dit de qui il vient, par son jeton.
La LCU n'est utilisée que si une carte indispensable en dépend, et seulement
après vérification sur une vraie partie.

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

- Il doit porter sur une partie que le serveur a **vue démarrer** (décision 2),
  avec les mêmes participants, et sur la propre partie de celui qui l'envoie
  (le joueur du poste est l'un de ses comptes).
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
  repose sur le joueur qui le porte, encadrée par la cohérence avec Spectator.
- Une partie sans aucun agent n'est pas vue : ses cartes finissent en verdict
  inconnu.
- Catalogue réduit tant que dégâts, soins, CC et augments ne sont pas disponibles.
- Le résultat n'est connu que par les agents présents jusqu'à `GameEnd`.

### Points ouverts

- Spectator-V5 voit-il les parties de la file 2400 ?
- La Live Client Data API ne donne pas d'identifiant de partie : le relevé est
  rattaché à la partie que Spectator a vue démarrer pour ce joueur. Si Spectator
  ne voit pas la file 2400, il faudra un autre moyen de grouper les relevés
  (mêmes 10 Riot ID, même mode, horaires cohérents).

### Déclencheurs de révision

- Riot ouvre la file 2400 dans Match-V5 : la validation peut repasser côté
  serveur, sans agent.
- La Live Client Data API se met à exposer dégâts, soins ou augments : le
  catalogue peut s'élargir sans changer l'architecture.
