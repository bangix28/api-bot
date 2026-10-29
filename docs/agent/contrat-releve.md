# Contrat d'API : agent de LAN → api-bot

Version du contrat : **v1** (2026-10-26 : appairage de l'agent depuis le front ; cycle des parties piloté par les relevés ; 2026-10-28 : files choisies par LAN, `acceptedQueues` et `queueId` remplacent `acceptedGameModes` — aucun agent n'était encore distribué, la v1 est amendée sans passer par une v2) · Décisions : [ADR-0005](../adr/0005-defis-de-lan-donnees-lues-sur-les-postes.md)

L'agent tourne sur le PC Windows d'un ou plusieurs joueurs pendant une LAN. Il
lit la Live Client Data API du jeu (`https://127.0.0.1:2999`) et transmet ce
qu'il lit à api-bot, qui valide les cartes défis. Il lit aussi, une fois par
partie, la file de la partie dans le client LoL (§ 5).

**Un agent par partie suffit.** La Live Client Data API donne les scores et le
journal d'événements des **10 joueurs** de la partie : le relevé d'un seul agent
permet de régler les cartes de tous les participants de la LAN qui y jouent. Les
autres n'installent rien ; ils se connectent seulement avec Discord sur le front
pour choisir leurs cartes. Nul besoin de désigner à l'avance qui porte l'agent :
le serveur l'apprend du jeton de chaque relevé (§ 1).

| Situation | Conséquence |
|---|---|
| Un agent dans la partie | Toutes les cartes de la partie sont réglées sur son relevé. |
| Plusieurs agents dans la même partie | Leurs relevés sont **recoupés** : ils doivent concorder (§ 4). |
| Aucun agent dans la partie | Rien n'est rapporté : les cartes engagées finissent en verdict inconnu. | L'agent est un projet séparé : ce
document est **la seule chose que les deux projets partagent**.

Principe directeur : **l'agent transporte, le serveur juge.** L'agent n'interprète
rien (pas de calcul de série, de vainqueur, de solo kill) : il relaie les données
brutes du jeu. Toute règle métier vit côté serveur, où elle se corrige sans
redistribuer l'agent.

---

## 1. Connexion : appairer l'agent depuis le front

Chaque relevé doit prouver **qui l'envoie** : sans cela, n'importe qui pourrait
déclarer un penta avec un simple `curl`. Cette preuve est un **jeton d'agent**.

L'agent est un programme séparé : il ne peut pas lire la session du joueur dans
son navigateur. Il ne se connecte donc pas lui-même : le joueur, **déjà connecté
avec Discord sur le front**, y **approuve un code** affiché par l'agent — comme
on connecte une télévision à un compte (RFC 8628). L'agent ne parle jamais à
Discord.

### Ce que voit le joueur

1. Il lance l'agent, qui affiche : « Sur le site, ouvre *Connecter mon agent* et
   saisis le code **K7QF-92PZ** ».
2. Sur le front, où il est déjà connecté, il saisit le code.
3. L'agent affiche : « Connecté : Pseudo#EUW — LAN d'automne (sam. 14:00 → dim. 04:00) ».

S'il n'est inscrit à aucune LAN en cours ou à venir, c'est **le front** qui le
lui dit à la saisie du code (§ 1.2).

### Le jeton obtenu

- Rattaché au joueur **et à une LAN** : celle en cours à laquelle l'un de ses
  comptes participe, sinon la prochaine. Il ne vaut que pour cette LAN.
- **Expire à la fin de la LAN** (`endsAt`). Un jeton qui fuite ne sert à rien
  après le week-end ; pour la LAN suivante, le joueur appaire de nouveau l'agent.
- **Une heure de grâce, pour le seul relevé final** : une partie lancée à
  03:50 se termine après la fin de la LAN, son relevé final est accepté
  jusqu'à `endsAt` + 1 h. Elle compte si elle a commencé avant `endsAt`.
- Un nouvel appairage révoque le jeton précédent du joueur pour cette LAN.
- L'agent le stocke dans le dossier de l'utilisateur Windows (`%APPDATA%`),
  jamais à côté de l'exécutable. Préfixe `agt_`, pour le repérer s'il fuite
  dans un log.

Chaque requête de l'agent porte l'en-tête :

```
Authorization: Bearer agt_3f9c…
```

### Le déroulé

```
Agent                                   api-bot                                  Front (joueur connecté)
  │ 1. POST /api/agent/v1/device ─────────▶│                                          │
  │◀── userCode K7QF-92PZ, deviceCode ─────│                                          │
  │ affiche le code                        │                                          │
  │                                        │◀── 2. POST /api/me/agent-devices/approve │
  │                                        │       { userCode }  (JWT du joueur)      │
  │ 3. POST /api/agent/v1/token ──────────▶│                                          │
  │    toutes les `interval` secondes      │                                          │
  │◀── authorization_pending … puis jeton ─│                                          │
```

#### 1.1 `POST /api/agent/v1/device` — l'agent demande un code

Sans authentification, sans corps.

Réponse `200` :

```json
{
  "userCode": "K7QF-92PZ",
  "deviceCode": "dev_Hn3V…",
  "verificationUrl": "https://site.example/agent",
  "expiresIn": 600,
  "interval": 5
}
```

| Champ | Sens |
|---|---|
| `userCode` | À **afficher** au joueur, qui le saisit sur le front. 8 caractères sans ambiguïté (ni `0`/`O`, ni `1`/`I`), casse et tiret indifférents à la saisie. |
| `deviceCode` | **Secret de l'agent** : ne jamais l'afficher. Il sert à récupérer le jeton (§ 1.3). |
| `verificationUrl` | Page du front où saisir le code : à afficher, ou à ouvrir dans le navigateur. |
| `expiresIn` | Durée de validité du code, en secondes (10 min). Passé ce délai, en redemander un. |
| `interval` | Délai minimal, en secondes, entre deux interrogations de `token`. |

#### 1.2 `POST /api/me/agent-devices/approve` — le front approuve le code

Appelé par le **front**, avec le JWT du joueur connecté (cf.
[connexion-discord.md](../front/connexion-discord.md)) :

```
POST /api/me/agent-devices/approve
Authorization: Bearer <JWT du joueur>
Content-Type: application/json

{ "userCode": "k7qf92pz" }
```

Réponse `200` : la LAN pour laquelle l'agent est appairé, à afficher au joueur.

```json
{
  "lan": { "id": 3, "name": "LAN d'automne", "startsAt": "2026-11-07T14:00:00+01:00", "endsAt": "2026-11-08T04:00:00+01:00" },
  "player": { "riotIds": ["Pseudo#EUW", "Smurf#1234"] }
}
```

| Code | `code` | Cas |
|---|---|---|
| `400` | `invalid_user_code` | Code inconnu, expiré, ou déjà approuvé |
| `409` | `not_registered` | Aucun compte du joueur ne participe à une LAN en cours ou à venir |
| `401` / `403` | — | Pas de JWT de joueur |

#### 1.3 `POST /api/agent/v1/token` — l'agent récupère son jeton

L'agent interroge toutes les `interval` secondes jusqu'à l'approbation :

```
POST /api/agent/v1/token
Content-Type: application/json

{ "deviceCode": "dev_Hn3V…" }
```

Réponse `200`, **une seule fois** : le code est ensuite consommé.

```json
{
  "token": "agt_3f9c…",
  "expiresAt": "2026-11-08T04:00:00+01:00",
  "player": { "riotIds": ["Pseudo#EUW", "Smurf#1234"] },
  "lan": {
    "id": 3,
    "name": "LAN d'automne",
    "startsAt": "2026-11-07T14:00:00+01:00",
    "endsAt": "2026-11-08T04:00:00+01:00"
  }
}
```

| Code | `code` | Cas | Réaction de l'agent |
|---|---|---|---|
| `400` | `authorization_pending` | Le joueur n'a pas encore saisi le code | Réessayer après `interval` secondes |
| `400` | `slow_down` | Interrogé plus vite que `interval` | Ajouter 5 s à l'intervalle |
| `400` | `expired_token` | Le code a expiré sans être approuvé | Redemander un code (§ 1.1) |
| `400` | `invalid_grant` | `deviceCode` inconnu ou déjà utilisé | Redemander un code (§ 1.1) |

### Sécurité

- Le `deviceCode` est le seul secret de l'agent avant le jeton : le serveur n'en
  garde qu'une empreinte, et il ne sert qu'une fois.
- Le `userCode` est court pour être saisi, mais il ne donne rien seul : il doit
  être approuvé par un joueur **connecté**, expire en 10 minutes et ne sert
  qu'une fois. Le jeton émis appartient au joueur qui l'a approuvé.
- Approuver le code d'un autre lui donnerait un agent… lié à **son propre**
  compte : on ne peut pas se faire passer pour quelqu'un d'autre ainsi.

---

## 2. `GET /api/agent/v1/session`

À appeler **au démarrage** (si un jeton est déjà enregistré), puis **toutes les
5 minutes** et **avant chaque nouvelle partie**. Confirme que le jeton est
valable et donne les réglages du moment.

### Réponse `200`

```json
{
  "player": {
    "riotIds": ["Pseudo#EUW", "Smurf#1234"]
  },
  "lan": {
    "id": 3,
    "name": "LAN d'automne",
    "startsAt": "2026-11-07T14:00:00+01:00",
    "endsAt": "2026-11-08T04:00:00+01:00",
    "status": "in_progress"
  },
  "acceptedQueues": [
    { "queueId": 2400, "gameMode": "KIWI" },
    { "queueId": 420, "gameMode": "CLASSIC" }
  ],
  "reportIntervalSeconds": 15
}
```

| Champ | Sens |
|---|---|
| `player.riotIds` | Comptes Riot liés au joueur. L'agent n'envoie un relevé que si le joueur du poste (`activePlayer.riotId`) en fait partie. |
| `lan` | La LAN du jeton (toujours présente). |
| `lan.status` | `upcoming` : la LAN n'a pas commencé, **l'agent n'envoie rien** et attend `startsAt`. `in_progress` : l'agent envoie. |
| `acceptedQueues` | Files où les parties comptent, choisies pour la LAN en administration (au moins une). `queueId` : `2400` ARAM du chaos, `420` classée solo/duo, `440` classée flexible. `gameMode` : la valeur de `gameData.gameMode` correspondante, pour écarter une partie sans interroger le client LoL. Toute autre partie est ignorée par l'agent, sans rien envoyer (§ 5). |
| `reportIntervalSeconds` | Cadence d'envoi pendant une partie. Fixée par le serveur pour pouvoir l'ajuster sans redistribuer l'agent. |

### Erreurs

| Code | `code` | Cas | Réaction de l'agent |
|---|---|---|---|
| `401` | `invalid_token` | Jeton absent, inconnu ou révoqué (nouvel appairage, révocation par un admin) | Effacer le jeton, recommencer l'appairage (§ 1) |
| `401` | `token_expired` | La LAN du jeton est terminée | Effacer le jeton, afficher « LAN terminée », proposer un nouvel appairage pour la suivante |

---

## 3. `POST /api/agent/v1/reports`

Envoie un relevé : l'état de la partie à un instant donné.

### Quand envoyer

| Moment | `final` | Remarque |
|---|---|---|
| **Dès que la partie est détectée** | `false` | Relevé d'ouverture (`sequence: 1`), **sans attendre** l'intervalle : c'est lui qui déclare la partie commencée et verrouille les cartes (§ 4). Rejoué une fois en cas d'échec réseau. |
| Toutes les `reportIntervalSeconds` pendant la partie | `false` | Alimente les annonces en direct. Un envoi raté n'est **pas** rejoué : le suivant le remplace. |
| Dès qu'un événement `GameEnd` apparaît | `true` | Le relevé qui compte pour la validation. **À rejouer** jusqu'à succès (cf. § 5). |
| L'API locale disparaît sans `GameEnd` | `true` | Dernier état connu, avec `endReason: "client_closed"` (crash, quitte la partie). |

### Requête

```
POST /api/agent/v1/reports
Authorization: Bearer agt_…
Content-Type: application/json
Content-Encoding: gzip          (facultatif, recommandé : ~60 Ko → ~6 Ko)
```

```json
{
  "agentVersion": "1.0.0",
  "sessionId": "8d0e5a7c-2f1b-4c3e-9a6d-1b2c3d4e5f60",
  "sequence": 17,
  "final": false,
  "endReason": null,
  "observedAt": "2026-11-07T21:14:03+01:00",
  "queueId": 2400,
  "liveClient": {
    "activePlayer": { "riotId": "Pseudo#EUW", "…": "…" },
    "allPlayers": [ "…" ],
    "events": { "Events": [ "…" ] },
    "gameData": { "gameMode": "KIWI", "gameTime": 412.6, "mapNumber": 12, "…": "…" }
  }
}
```

| Champ | Type | Sens |
|---|---|---|
| `agentVersion` | string, semver | Version de l'agent : permet au serveur de refuser une version connue pour être fausse. |
| `sessionId` | UUID | Tiré par l'agent **au début de chaque partie** détectée. Regroupe les relevés d'une même partie vue par ce poste. |
| `sequence` | entier ≥ 1 | Rang du relevé dans la partie, croissant. Un relevé de rang inférieur ou égal au dernier reçu est ignoré (rejeu, désordre réseau). |
| `final` | bool | `true` pour le relevé de fin (§ « Quand envoyer »). Un seul par `sessionId`. |
| `endReason` | `"game_end"` \| `"client_closed"` \| `null` | Obligatoire si `final`, `null` sinon. |
| `observedAt` | date ISO 8601 avec fuseau | Heure de la lecture locale. **Indicative** : le serveur se fie à son heure de réception. |
| `queueId` | entier \| `null` | File de la partie, lue dans le client LoL (§ 5). `null` (ou absent) si l'agent n'a pas pu la lire : le serveur la déduit alors du mode quand il n'en désigne qu'une (`KIWI` → `2400`) ; une partie `CLASSIC` sans `queueId` est ignorée, car ce peut être une normale ou une partie personnalisée. |
| `liveClient` | objet | La réponse de `GET /liveclientdata/allgamedata`, **telle quelle**, sans rien retirer ni renommer. |

Le serveur ne lit dans `liveClient` que ce dont il a besoin (`activePlayer.riotId`,
`allPlayers`, `events`, `gameData`) : relayer l'objet entier évite que l'agent
ait à suivre ses évolutions.

### Réponses

`202 Accepted` : relevé enregistré.

```json
{ "status": "accepted" }
```

`200 OK` : relevé valide mais **sans objet** ; l'agent cesse d'envoyer pour cette
partie (`sessionId`) et attend la suivante.

```json
{ "status": "ignored", "reason": "queue_not_accepted" }
```

| `reason` | Cas |
|---|---|
| `lan_not_started` | La LAN du jeton n'a pas encore commencé (horloge du poste en avance, par ex.). |
| `queue_not_accepted` | La file de la partie n'est pas dans `acceptedQueues`, ou le serveur ne peut pas la connaître (partie `CLASSIC` sans `queueId`, `queueId` incohérent avec `gameData.gameMode`). |
| `no_participant_in_game` | Aucun participant de la LAN parmi les `allPlayers`. |
| `joined_too_late` | Le premier relevé de cette partie est arrivé après 90 s de jeu : elle n'engage aucune carte (§ 4). |
| `stale_sequence` | `sequence` déjà reçue ou dépassée : sans effet, rien à corriger. |
| `lan_finished` | La partie a commencé après la fin de la LAN (`endsAt`) : elle ne compte pas. |

### Erreurs

Corps : `{ "code": "…", "message": "…" }` (message en français, pour les logs de l'agent).

| Code HTTP | `code` | Cas | Réaction de l'agent |
|---|---|---|---|
| `400` | `invalid_report` | Champ manquant, `liveClient` sans `allPlayers` / `events` / `gameData` | Journaliser, ne pas rejouer |
| `401` | `invalid_token` | Jeton absent, inconnu ou révoqué | Effacer le jeton, recommencer l'appairage (§ 1) |
| `401` | `token_expired` | Relevé intermédiaire après `endsAt`, ou relevé final plus d'1 h après `endsAt` (cf. § 1) | Effacer le jeton, afficher « LAN terminée » |
| `403` | `not_your_account` | `activePlayer.riotId` n'est pas un compte du joueur authentifié | Ne pas rejouer : on n'envoie que **sa propre** partie |
| `413` | `report_too_large` | Corps décompressé > 1 Mo. Un corps brut > 1 Mo est refusé plus tôt par le serveur web, avec un corps HTML : se fier au code HTTP | Ne pas rejouer |
| `426` | `agent_outdated` | `agentVersion` refusée (réservé : aucune version n'est refusée en v1) | Avertir le joueur de mettre l'agent à jour |
| `429` | `too_many_reports` | Plus d'un relevé toutes les 5 s pour ce jeton | Attendre `Retry-After` (secondes) |
| `5xx` | — | Panne serveur | Relevé final : rejouer (§ 5) ; relevé intermédiaire : abandonner |

---

## 4. Ce que fait le serveur d'un relevé (informatif)

Pour que l'agent sache ce qui compte, pas pour qu'il le reproduise.

1. **Identité** : le joueur du poste est `activePlayer.riotId`, rapproché des
   comptes du joueur authentifié (sinon `403`).
2. **Rattachement à une partie** : la Live Client Data API ne donne **pas**
   d'identifiant de partie. Le serveur en construit un à partir du mode, des
   10 `riotId` et de l'heure de début estimée (`réception − gameTime`) ; les
   relevés de plusieurs agents d'une même partie retombent sur la même clé, à
   une minute près.
3. **Cycle de la partie** (les relevés le pilotent seuls, sans interroger Riot) :
   - **premier relevé** : la partie commence ; la carte de chaque participant de
     la LAN présent, choisie avant ce moment, est verrouillée sur elle ;
   - premier relevé **après 90 s de jeu** : la partie n'engage aucune carte
     (`joined_too_late`) — sinon une carte pourrait être choisie en connaissant
     déjà le début de la partie ;
   - **relevé final** : la manche est réglée, les offres suivantes distribuées ;
   - **plus aucun relevé pendant une heure** : la partie est réglée en verdict
     inconnu, pour qu'aucune carte ne reste bloquée.
4. **Pseudos des événements** : `KillerName`, `VictimName`, `Assisters`, `Acer`,
   `Recipient` ne contiennent que le pseudo (`riotIdGameName`), sans le tag. Le
   serveur les résout en Riot ID complet grâce à `allPlayers` de la même partie.
5. **Calculs** : série la plus longue (`Multikill.KillStreak`), solo kills
   (`ChampionKill` sans `Assisters`), kills d'équipe (somme des `scores.kills`
   par `team`), Aces (`Ace.Acer`), premier sang (`FirstBlood.Recipient`),
   vainqueur (`GameEnd.Result`, vu du joueur du poste, étendu à son équipe),
   plus de morts de la partie (`scores.deaths` des 10 joueurs, pour la carte Gameplay Shaco).
6. **Portée** : le relevé règle les cartes de **tous** les participants de la
   LAN présents dans `allPlayers`, pas seulement celle du joueur du poste.
7. **Recoupement** : une partie rapportée par un seul agent est acceptée sur
   son relevé. Rapportée par plusieurs, leurs relevés finaux doivent concorder
   (scores, journal d'événements) ; un relevé contradictoire est écarté.
   Sans recoupement, la confiance repose sur le joueur qui porte l'agent : la
   règle du `403` (le relevé porte sur sa propre partie) et la cohérence d'un
   relevé à l'autre (même clé, mêmes joueurs) en sont les garde-fous.

---

## 5. Comportement attendu de l'agent

### Lecture de la Live Client Data API

- Interroger `GET https://127.0.0.1:2999/liveclientdata/allgamedata` **toutes les
  2 secondes**. Pas de réponse = pas de partie en cours.
- Vérifier le certificat avec le **certificat racine de Riot**
  ([riotgames.pem](https://static.developer.riotgames.com/docs/lol/riotgames.pem)),
  embarqué dans l'agent. Ne jamais désactiver la vérification.
- **Partie détectée** (l'API répond après une période sans réponse) : rappeler
  `session`, tirer un nouveau `sessionId`, remettre `sequence` à 1, et envoyer
  **aussitôt** le relevé d'ouverture (§ 3).
- **Lancer l'agent avant de jouer** : il doit tourner dès l'écran de chargement.
  Un agent démarré après 90 s de jeu ne fait pas compter la partie (§ 4).
- **Ne rien envoyer** si : `session.lan.status` vaut `upcoming` ; `gameData.gameMode`
  n'est celui d'aucune des `acceptedQueues` ; la file lue dans le client LoL n'est
  pas acceptée ; `activePlayer.riotId` n'est pas dans `player.riotIds`
  (le joueur regarde une partie en spectateur, ou joue sur un compte non lié).

### Lecture de la file dans le client LoL

La Live Client Data API ne donne pas la file : `CLASSIC` vaut pour la SoloQ, la
Flex, une normale ou une partie personnalisée. L'agent la lit dans l'API du
client LoL (LCU), non documentée par Riot (ADR-0005, révision du 2026-10-28) :

- port et mot de passe dans le `lockfile` du dossier d'installation de League
  (`LeagueClient:<pid>:<port>:<mot de passe>:<protocole>`) ;
- `GET https://127.0.0.1:<port>/lol-gameflow/v1/session`, Basic `riot:<mot de passe>`,
  file dans `gameData.queue.id` ; certificat vérifié avec le même `riotgames.pem` ;
- une seule lecture réussie par partie, retentée à chaque relevé tant qu'elle échoue ;
- client illisible : envoyer quand même avec `queueId: null` (une ARAM du chaos
  compte encore) et avertir le joueur une fois par partie.
- Le journal `events` est cumulatif depuis `GameStart` : l'agent n'a jamais à
  reconstituer le passé d'une partie.

### Fin de partie et rejeu

- Le relevé `final` est le seul qui compte pour les cartes : en cas d'échec
  réseau ou `5xx`, le rejouer avec **le même `sessionId` et la même `sequence`**,
  avec attente croissante (5 s, 15 s, 60 s, puis toutes les 5 min) pendant
  **1 heure**, y compris après un redémarrage de l'agent (le garder sur disque).
- Tant qu'un relevé final attend d'être envoyé, **ne pas effacer le jeton**,
  même si `session` répond `token_expired` : la grâce d'une heure (§ 1) est
  faite pour lui. S'il reçoit lui-même `token_expired`, il est abandonné.
- Un relevé intermédiaire raté est abandonné : le suivant le remplace.

### Respect du joueur

- L'agent ne lit **que** la Live Client Data API et, dans le client LoL, la file
  de la partie : aucune lecture mémoire, aucune interaction avec le jeu, aucune
  autre donnée du PC.
- Il n'envoie rien hors d'une LAN en cours à laquelle le joueur participe.
- Il journalise localement ce qu'il envoie, pour que le joueur puisse le vérifier.

---

## 6. Exemple de relevé final (anonymisé)

Construit sur une vraie partie d'ARAM du chaos : structure, scores et événements réels ;
pseudos, tags et champions remplacés.

```json
{
  "agentVersion": "1.0.0",
  "sessionId": "8d0e5a7c-2f1b-4c3e-9a6d-1b2c3d4e5f60",
  "sequence": 66,
  "final": true,
  "endReason": "game_end",
  "observedAt": "2026-11-07T21:30:21+01:00",
  "liveClient": {
    "activePlayer": { "riotId": "Joueur2#EUW", "riotIdGameName": "Joueur2", "riotIdTagLine": "EUW", "level": 18 },
    "allPlayers": [
      {
        "riotId": "Joueur1#777", "riotIdGameName": "Joueur1", "riotIdTagLine": "777",
        "team": "ORDER", "championName": "Jinx", "level": 18, "isDead": false,
        "scores": { "kills": 26, "deaths": 9, "assists": 17, "creepScore": 40, "wardScore": 0.0 }
      },
      {
        "riotId": "Joueur2#EUW", "riotIdGameName": "Joueur2", "riotIdTagLine": "EUW",
        "team": "ORDER", "championName": "Lulu", "level": 18, "isDead": false,
        "scores": { "kills": 3, "deaths": 6, "assists": 28, "creepScore": 12, "wardScore": 0.0 }
      }
    ],
    "events": {
      "Events": [
        { "EventID": 0, "EventName": "GameStart", "EventTime": 0.02 },
        { "EventID": 2, "EventName": "ChampionKill", "EventTime": 94.35, "KillerName": "Joueur3", "VictimName": "Joueur9", "Assisters": ["Joueur1", "Joueur2"] },
        { "EventID": 3, "EventName": "FirstBlood", "EventTime": 94.35, "Recipient": "Joueur3" },
        { "EventID": 7, "EventName": "Multikill", "EventTime": 103.69, "KillerName": "Joueur1", "KillStreak": 2 },
        { "EventID": 34, "EventName": "Ace", "EventTime": 328.52, "Acer": "Joueur1", "AcingTeam": "ORDER" },
        { "EventID": 127, "EventName": "GameEnd", "EventTime": 980.09, "Result": "Win" }
      ]
    },
    "gameData": { "gameMode": "KIWI", "gameTime": 980.76, "mapName": "Map12", "mapNumber": 12, "mapTerrain": "Default" }
  }
}
```

(`allPlayers` contient les 10 joueurs et chaque joueur bien plus de champs —
objets, runes, sorts : l'agent les transmet tous, l'extrait est abrégé.)

---

## 7. Évolution du contrat

- Ajouter un champ ou une `reason` est **compatible** : l'agent ignore ce qu'il
  ne connaît pas, le serveur accepte les champs inconnus.
- Retirer ou changer le sens d'un champ impose une **v2** (`/api/agent/v2/…`),
  servie en parallèle de la v1 le temps que les agents soient mis à jour.
- Points encore ouverts (cf. ADR-0005) : la valeur exacte de `GameEnd.Result`
  en cas de défaite (seul `"Win"` a été observé) ; les seuils (90 s, une minute
  de tolérance sur la clé, une heure avant abandon), à confirmer en LAN.
