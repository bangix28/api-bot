# Contrat d'API : agent de LAN → api-bot

Version du contrat : **v1** (2026-10-25, connexion Discord depuis l'agent) · Décisions : [ADR-0005](../adr/0005-defis-de-lan-donnees-lues-sur-les-postes.md)

L'agent tourne sur le PC Windows de chaque joueur pendant une LAN. Il lit la
Live Client Data API du jeu (`https://127.0.0.1:2999`) et transmet ce qu'il lit
à api-bot, qui valide les cartes défis. L'agent est un projet séparé : ce
document est **la seule chose que les deux projets partagent**.

Principe directeur : **l'agent transporte, le serveur juge.** L'agent n'interprète
rien (pas de calcul de série, de vainqueur, de solo kill) : il relaie les données
brutes du jeu. Toute règle métier vit côté serveur, où elle se corrige sans
redistribuer l'agent.

---

## 1. Connexion : « Se connecter avec Discord » depuis l'agent

Chaque relevé doit prouver **qui l'envoie** : sans cela, n'importe qui pourrait
déclarer un penta avec un simple `curl`. Cette preuve est un **jeton d'agent**,
mais le joueur ne le manipule jamais : il l'obtient en se connectant avec
Discord depuis l'agent, comme on se connecte à une application de bureau.

### Ce que voit le joueur

1. Il lance l'agent et clique sur **Se connecter avec Discord**.
2. Son navigateur s'ouvre sur Discord ; il autorise l'application.
3. L'onglet affiche « Connecté, tu peux fermer cet onglet » et l'agent :
   « Connecté : Pseudo#EUW — LAN d'automne (sam. 14:00 → dim. 04:00) ».

S'il n'est inscrit à aucune LAN, l'agent le dit **dès la connexion** :
« Tu n'es inscrit à aucune LAN en cours ou à venir ».

### Le jeton obtenu

- Rattaché au joueur **et à une LAN** : celle en cours à laquelle il participe,
  sinon la prochaine. Il ne vaut que pour cette LAN.
- **Expire à la fin de la LAN** (`endsAt`). Un jeton qui fuite ne sert à rien
  après le week-end ; pour la LAN suivante, le joueur se reconnecte.
- **Une heure de grâce, pour le seul relevé final** : une partie lancée à
  03:50 se termine après la fin de la LAN, son relevé final est accepté
  jusqu'à `endsAt` + 1 h. Elle compte si elle a commencé avant `endsAt`.
- Se reconnecter révoque le jeton précédent du joueur pour cette LAN.
- L'agent le stocke dans le dossier de l'utilisateur Windows (`%APPDATA%`),
  jamais à côté de l'exécutable. Préfixe `agt_`, pour le repérer s'il fuite
  dans un log.

Chaque requête de l'agent porte l'en-tête :

```
Authorization: Bearer agt_3f9c…
```

### Le déroulé technique

C'est **api-bot** qui parle à Discord : le secret de l'application Discord ne
quitte jamais le serveur, et l'agent ne connaît ni Discord ni son secret.
L'échange suit le schéma standard des applications de bureau (OAuth 2.0 pour
applications natives, RFC 8252, avec PKCE, RFC 7636).

```
Agent                        Navigateur                         api-bot                         Discord
  │ 1. state + code_verifier   │                                   │                               │
  │    écoute 127.0.0.1:<port> │                                   │                               │
  │──── ouvre ────────────────▶│ GET /agent/v1/login?… ───────────▶│ 2. ─── redirige ─────────────▶│
  │                            │                                   │                               │ autorise
  │                            │                                   │◀── 3. code Discord ───────────│
  │                            │                                   │ joueur + LAN, code à usage unique
  │◀─ 4. http://127.0.0.1:<port>/callback?code=…&state=… ──────────│                               │
  │ 5. POST /api/agent/v1/token (code + code_verifier) ───────────▶│                               │
  │◀──────────────────────────────── jeton agt_…, joueur, LAN ─────│                               │
```

**1. L'agent prépare la connexion.**
- Tire un `state` et un `code_verifier` aléatoires (32 octets, base64url) ;
  calcule `code_challenge = BASE64URL(SHA256(code_verifier))`.
- Écoute sur `http://127.0.0.1:<port>/callback`, `<port>` étant un port libre
  choisi par le système.
- Ouvre le navigateur sur :

```
GET {api-bot}/agent/v1/login?port=<port>&state=<state>&code_challenge=<code_challenge>
```

**2. api-bot envoie le joueur sur Discord** (scope `identify`), avec son propre
URL de retour (`{api-bot}/agent/v1/login/callback`, déclaré dans l'application
Discord).

**3. api-bot identifie le joueur et sa LAN.** Il échange le code Discord,
retrouve le compte par son identifiant Discord, puis la LAN en cours ou à venir
à laquelle l'un de ses comptes Riot participe.

**4. api-bot rend la main à l'agent** en redirigeant le navigateur vers
`http://127.0.0.1:<port>/callback`, avec :
- en cas de succès : `?code=<code>&state=<state>` — un code **à usage unique,
  valable 60 secondes**, et non le jeton lui-même : une URL finit dans
  l'historique du navigateur ;
- en cas d'échec : `?error=<erreur>&state=<state>`, avec `error` parmi
  `access_denied` (le joueur a refusé sur Discord), `unknown_player` (compte
  Discord lié à aucun joueur), `not_registered` (inscrit à aucune LAN en cours
  ou à venir).

api-bot ne redirige **que** vers `http://127.0.0.1:<port>/callback` : jamais
vers une autre adresse, ce qui interdit de détourner la connexion vers un site
tiers. L'agent **ignore tout retour dont le `state` diffère** de celui qu'il a
tiré, puis répond au navigateur par une page « Tu peux fermer cet onglet ».

**5. L'agent échange le code contre le jeton :**

```
POST /api/agent/v1/token
Content-Type: application/json

{ "code": "<code>", "codeVerifier": "<code_verifier>" }
```

Réponse `200` :

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

Erreur `400 invalid_grant` : code inconnu, expiré, déjà utilisé, ou
`codeVerifier` qui ne correspond pas au `code_challenge` de l'étape 1. Le
`code_verifier` garantit que seul l'agent qui a lancé la connexion peut
récupérer le jeton, même si le code est intercepté.

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
  "acceptedGameModes": ["KIWI"],
  "reportIntervalSeconds": 15
}
```

| Champ | Sens |
|---|---|
| `player.riotIds` | Comptes Riot liés au joueur. L'agent n'envoie un relevé que si le joueur du poste (`activePlayer.riotId`) en fait partie. |
| `lan` | La LAN du jeton (toujours présente). |
| `lan.status` | `upcoming` : la LAN n'a pas commencé, **l'agent n'envoie rien** et attend `startsAt`. `in_progress` : l'agent envoie. |
| `acceptedGameModes` | Valeurs de `gameData.gameMode` qui comptent. `KIWI` = ARAM du chaos. Toute autre partie est ignorée par l'agent, sans rien envoyer. |
| `reportIntervalSeconds` | Cadence d'envoi pendant une partie. Fixée par le serveur pour pouvoir l'ajuster sans redistribuer l'agent. |

### Erreurs

| Code | `code` | Cas | Réaction de l'agent |
|---|---|---|---|
| `401` | `invalid_token` | Jeton absent, inconnu ou révoqué (reconnexion ailleurs, révocation par un admin) | Effacer le jeton, proposer **Se connecter avec Discord** |
| `401` | `token_expired` | La LAN du jeton est terminée | Effacer le jeton, afficher « LAN terminée », proposer de se reconnecter pour la suivante |

---

## 3. `POST /api/agent/v1/reports`

Envoie un relevé : l'état de la partie à un instant donné.

### Quand envoyer

| Moment | `final` | Remarque |
|---|---|---|
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
{ "status": "ignored", "reason": "game_mode_not_accepted" }
```

| `reason` | Cas |
|---|---|
| `lan_not_started` | La LAN du jeton n'a pas encore commencé (horloge du poste en avance, par ex.). |
| `game_mode_not_accepted` | `gameData.gameMode` n'est pas dans `acceptedGameModes`. |
| `no_participant_in_game` | Aucun participant de la LAN parmi les `allPlayers`. |
| `stale_sequence` | `sequence` déjà reçue ou dépassée : sans effet, rien à corriger. |

### Erreurs

Corps : `{ "code": "…", "message": "…" }` (message en français, pour les logs de l'agent).

| Code HTTP | `code` | Cas | Réaction de l'agent |
|---|---|---|---|
| `400` | `invalid_report` | Champ manquant, `liveClient` sans `allPlayers` / `events` / `gameData` | Journaliser, ne pas rejouer |
| `401` | `invalid_token` | Jeton absent, inconnu ou révoqué | Effacer le jeton, proposer **Se connecter avec Discord** |
| `401` | `token_expired` | Relevé intermédiaire après `endsAt`, ou relevé final plus d'1 h après `endsAt` (cf. § 1) | Effacer le jeton, afficher « LAN terminée » |
| `403` | `not_your_account` | `activePlayer.riotId` n'est pas un compte du joueur authentifié | Ne pas rejouer : on n'envoie que **sa propre** partie |
| `413` | `report_too_large` | Corps décompressé > 1 Mo | Ne pas rejouer |
| `426` | `agent_outdated` | `agentVersion` refusée | Avertir le joueur de mettre l'agent à jour |
| `429` | `too_many_reports` | Plus d'un relevé toutes les 5 s pour ce jeton | Attendre `Retry-After` (secondes) |
| `5xx` | — | Panne serveur | Relevé final : rejouer (§ 5) ; relevé intermédiaire : abandonner |

---

## 4. Ce que fait le serveur d'un relevé (informatif)

Pour que l'agent sache ce qui compte, pas pour qu'il le reproduise.

1. **Identité** : le joueur du poste est `activePlayer.riotId`, rapproché des
   comptes du joueur authentifié (sinon `403`).
2. **Rattachement à une partie** : la Live Client Data API ne donne **pas**
   d'identifiant de partie. Le serveur rattache le relevé à la partie que
   Spectator-V5 a vue démarrer pour ce joueur ; à défaut, il regroupe les
   relevés qui partagent les mêmes 10 `riotId`, le même mode et une heure de
   début cohérente (`réception − gameTime`).
3. **Pseudos des événements** : `KillerName`, `VictimName`, `Assisters`, `Acer`,
   `Recipient` ne contiennent que le pseudo (`riotIdGameName`), sans le tag. Le
   serveur les résout en Riot ID complet grâce à `allPlayers` de la même partie.
4. **Calculs** : série la plus longue (`Multikill.KillStreak`), solo kills
   (`ChampionKill` sans `Assisters`), kills d'équipe (somme des `scores.kills`
   par `team`), Aces (`Ace.Acer`), premier sang (`FirstBlood.Recipient`),
   vainqueur (`GameEnd.Result`, vu du joueur du poste, étendu à son équipe).
5. **Recoupement** : quand plusieurs agents rapportent la même partie, leurs
   relevés finaux doivent concorder (scores, journal d'événements). Un relevé
   isolé et contradictoire est écarté.

---

## 5. Comportement attendu de l'agent

### Lecture de la Live Client Data API

- Interroger `GET https://127.0.0.1:2999/liveclientdata/allgamedata` **toutes les
  2 secondes**. Pas de réponse = pas de partie en cours.
- Vérifier le certificat avec le **certificat racine de Riot**
  ([riotgames.pem](https://static.developer.riotgames.com/docs/lol/riotgames.pem)),
  embarqué dans l'agent. Ne jamais désactiver la vérification.
- **Partie détectée** (l'API répond après une période sans réponse) : rappeler
  `session`, tirer un nouveau `sessionId`, remettre `sequence` à 1.
- **Ne rien envoyer** si : `session.lan.status` vaut `upcoming` ; `gameData.gameMode`
  n'est pas accepté ; `activePlayer.riotId` n'est pas dans `player.riotIds`
  (le joueur regarde une partie en spectateur, ou joue sur un compte non lié).
- Le journal `events` est cumulatif depuis `GameStart` : un agent lancé en
  cours de partie ne perd rien, il n'a pas à reconstituer le passé.

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

- L'agent ne lit **que** la Live Client Data API : aucune lecture mémoire,
  aucune interaction avec le jeu, aucune autre donnée du PC.
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
  en cas de défaite (seul `"Win"` a été observé) ; la visibilité de la file
  2400 dans Spectator-V5, qui conditionne le rattachement des relevés (§ 4).
