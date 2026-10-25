# ADR-0005 : Les défis de LAN se valident sur des données lues sur les postes des joueurs, jamais déclarées

## Statut

Accepté (2026-10-25)

Deux points restent à vérifier sur une vraie partie (cf. « Points ouverts ») ;
ils peuvent réduire le catalogue de cartes, pas changer l'architecture.

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

### 3. Les statistiques viennent d'un agent sur chaque poste

Un agent lancé sur le PC du joueur lit la Live Client Data API pendant la partie
et transmet ses relevés à api-bot, authentifié par le compte du joueur.
La LCU n'est utilisée que si une carte indispensable en dépend, et seulement
après vérification sur une vraie partie.

### 4. Un relevé n'est cru que s'il est recoupé

- Il doit porter sur une partie que le serveur a **vue démarrer** (décision 2),
  avec les mêmes participants.
- Chaque agent voit les scores de tous les joueurs : quand plusieurs participants
  jouent la même partie, leurs relevés doivent **concorder**. Un relevé isolé et
  contradictoire est écarté.

### 5. Une carte n'existe que si sa condition est mesurable

Le catalogue est filtré par les données réellement disponibles : multi-kills,
morts, KDA, participation aux kills, premier sang, solo kills et Ace sont
calculables depuis la Live Client Data API. Les cartes de dégâts, soins,
boucliers et CC sont retirées tant qu'aucune source fiable ne les fournit.

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

- Un composant de plus à installer sur chaque PC, sous Windows (LoL ne tourne
  pas sous Linux) ; il se développe et se teste sous Linux contre un faux serveur
  Live Client qui rejoue les exemples officiels de Riot.
- Un joueur seul dans sa partie n'a personne pour recouper son relevé : seule la
  cohérence avec Spectator le protège.
- Catalogue réduit tant que dégâts, soins et CC ne sont pas disponibles.

### Points ouverts

- La Live Client Data API expose-t-elle, en ARAM du chaos, le résultat de la
  partie (`GameEnd`), le premier sang et les augments ? Les exemples officiels
  sont anciens et ne les montrent pas.
- Spectator-V5 voit-il les parties de la file 2400 ?

### Déclencheurs de révision

- Riot ouvre la file 2400 dans Match-V5 : la validation peut repasser côté
  serveur, sans agent.
- La Live Client Data API ne fournit pas le résultat de la partie : les cartes
  « gagner… » dépendront de la LCU.
