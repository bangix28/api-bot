# Défis de LAN — guide d'intégration front

Comment afficher les défis de LAN façon ARAM du chaos : l'écran de la LAN
(classement, fil d'exploits) et le parcours de chaque joueur (offre de cartes,
choix, relances, sabotages).

> Le front est une application cliente **externe à ce dépôt**. Ce document décrit
> le contrat ; il n'y a pas de code front ici. Décisions :
> [ADR-0005](../adr/0005-defis-de-lan-donnees-lues-sur-les-postes.md) (données),
> [ADR-0006](../adr/0006-cartes-saboteur.md) (cartes saboteur).

---

## 1. Le jeu en une minute

- **Une manche = un match.** Avant chaque partie, le joueur reçoit une **offre de
  3 cartes défis** et en **choisit une**. Le choix est définitif.
- **Relances** : 3 au départ, et **rechargées à 3** à la fin de chaque manche
  (un stock déjà au-dessus, grâce à un Pickpocket, est conservé). Une relance
  remplace **une** carte de l'offre par une carte absente de l'offre.
- Au **lancement** de la partie, la carte est **verrouillée** sur ce match
  (`cardLocked: true`). Un joueur qui lance une partie sans avoir choisi la joue
  sans carte et garde son offre pour la suivante.
- À la **fin** de la partie, la carte est jugée sur les statistiques du match :
  - `success` : points de la carte ;
  - `failure` : rien ;
  - `unknown` : la donnée manquait (agent absent ou coupé) — rien.
- **Bonus de manche** : quand au moins deux joueurs ont engagé une carte sur le
  même match et que **tous** la réussissent, chacun gagne **+50 %**.
- Puis une nouvelle offre arrive pour le match suivant.

Le verrouillage et le règlement sont automatiques (détection des parties et
relevés de l'agent) : le front ne les déclenche jamais, il les **constate** en
relisant le parcours ou le classement.

### Les cartes saboteur

Rareté à part, la plus rare (`saboteur`, ~3 % par emplacement d'offre), et
**secrètes** : les autres joueurs voient seulement qu'une carte secrète est en jeu.

| Carte | Type | Effet |
|---|---|---|
| Mauvais perdant | contre la partie | 40 pts si le joueur **perd** le match |
| Gameplay Shaco (code `martyr`) | contre la partie | 40 pts s'il finit avec **le plus de morts** de la partie |
| Pickpocket | contre un joueur | Vole une relance à la cible choisie |
| Malédiction | contre un joueur | Le prochain défi réussi de la cible ne lui rapporte que **50 %** |

- Les cartes **contre la partie** se jouent comme les autres : choisies,
  verrouillées, jugées en fin de match.
- Les cartes **contre un joueur** (`needsTarget: true`) ont un **effet
  immédiat** : le joueur choisit sa cible parmi les participants (§ 7), l'effet
  s'applique, et il reçoit **aussitôt une offre neuve** pour jouer son match. Il
  ne marque pas de points : l'effet est sa récompense. L'écran enchaîne donc
  « sabotage effectué » → « choisis ta carte pour ce match ».
- Le saboteur n'est **jamais nommé** à personne.
- La **Malédiction est invisible pour la cible** : rien dans son parcours ni dans
  le fil. Elle la découvre au règlement de son prochain défi réussi
  (`curseApplied: true`). Deux malédictions ne se cumulent pas.

> ⚠️ « Mauvais perdant » et « Gameplay Shaco » poussent à perdre ou mourir exprès, ce que
> les règles de Riot sanctionnent (*feeding*, *throwing*). Les joueurs en
> assument le risque sur leurs comptes.

---

## 2. Le contrat en un coup d'œil

```
GET  /api/me/lan-events/current            ma LAN : en cours, sinon la prochaine joueur
GET  /api/lan-challenges/cards             catalogue des cartes et règles        compte API ou joueur
GET  /api/lan-events/{lanId}/standings     classement de la LAN                  compte API ou joueur
GET  /api/lan-events/{lanId}/feed?limit=   fil d'exploits                        compte API ou joueur
GET  /api/lan-events/{lanId}/me            mon parcours (+ cibles possibles)     joueur
POST /api/lan-events/{lanId}/me/join       rejoindre les défis                   joueur
POST /api/lan-events/{lanId}/me/pick       choisir une carte                     joueur
       { "slot": 0 }                         carte de match
       { "slot": 1, "target": "Pseudo#TAG" } carte qui vise un joueur
POST /api/lan-events/{lanId}/me/reroll     relancer une carte    { "slot": 0 }   joueur
```

- **Compte API** : le compte e-mail du front (`ROLE_API`), pour l'écran de la LAN.
- **Joueur** : jeton obtenu par la connexion Discord ([connexion-discord.md](connexion-discord.md)).
- Les routes `/me` désignent le **compte Riot du joueur inscrit à la LAN** : le
  front n'envoie un Riot ID que pour désigner la cible d'un sabotage.
- Erreurs : corps `{ "code": "…", "message": "…" }`, `message` en français et
  affichable tel quel. Dates : ISO 8601 avec fuseau.

---

## 3. Une carte

Même forme partout (offre, carte active, résultats, fil) :

```json
{
  "code": "curse",
  "label": "Malédiction",
  "description": "Le prochain défi réussi du joueur de ton choix ne lui rapporte que la moitié des points. Il ne le saura qu'au règlement.",
  "rarity": "saboteur",
  "theme": null,
  "points": 0,
  "secret": true,
  "needsTarget": true
}
```

| Champ | Usage |
|---|---|
| `rarity` | `silver` \| `gold` \| `prismatic` \| `saboteur` — à styliser comme les augments |
| `theme` | `bond` (« Lien » : jouer pour ses coéquipiers, à la Yuumi) ou `null`. Indépendant de la rareté : une carte Lien reste argent, or… À marquer d'un badge, en plus du style de rareté (cf. [cartes-lien.md](cartes-lien.md)) |
| `secret` | Carte saboteur : ne jamais l'afficher aux autres joueurs |
| `needsTarget` | Choisir cette carte demande une cible (`target`) |
| `code` | Stable : c'est lui qu'il faut utiliser pour une icône ou une animation |

---

## 4. `GET /api/lan-challenges/cards`

```json
{
  "cards": [ { "code": "double_kill", "label": "Doublé", "…": "…" } ],
  "rarities": [
    { "rarity": "silver", "points": 10, "drawWeight": 62 },
    { "rarity": "gold", "points": 25, "drawWeight": 28 },
    { "rarity": "prismatic", "points": 60, "drawWeight": 7 },
    { "rarity": "saboteur", "points": 40, "drawWeight": 3 }
  ],
  "rules": { "initialRerolls": 3, "roundBonusPercent": 50, "cursePercent": 50 }
}
```

`drawWeight` : chance, sur 100, qu'un emplacement d'offre tire cette rareté. Les
règles viennent du serveur : ne pas les coder en dur dans le front.

---

## 5. `GET /api/lan-events/{lanId}/standings`

Tous les participants de la LAN, y compris ceux qui n'ont pas encore rejoint.

```json
{
  "lan": { "id": 3, "name": "LAN d'automne", "startsAt": "2026-11-07T14:00:00+01:00", "endsAt": "2026-11-08T04:00:00+01:00" },
  "entries": [
    {
      "rank": 1,
      "riotId": "Pseudo#EUW",
      "joined": true,
      "score": 137,
      "successes": 4,
      "roundsPlayed": 6,
      "rerollsLeft": 1,
      "cardChosen": true,
      "cardLocked": true
    }
  ]
}
```

| Champ | Usage |
|---|---|
| `rank` | Ordre : points, puis défis réussis (à points égaux, la régularité l'emporte), puis Riot ID |
| `joined` | `false` : participant qui n'a pas encore rejoint les défis (« pas encore commencé ») |
| `cardChosen` | `true` : le joueur a une carte pour la prochaine partie, ou en jeu. **Laquelle n'est jamais donnée**, saboteur ou non : seul son propriétaire la voit, dans son parcours (`activeCard` de `GET …/me`) |
| `cardLocked` | `true` : la carte est en jeu sur un match en cours (« en partie ») |

Une Malédiction subie n'apparaît **nulle part** dans le classement.

Rafraîchissement conseillé : toutes les **15 secondes** pendant la LAN.

## 6. `GET /api/lan-events/{lanId}/feed?limit=20`

Les événements de la LAN, **le plus récent en premier**. `limit` : 20 par
défaut, de 1 à 50.

```json
{
  "entries": [
    {
      "kind": "round",
      "riotId": "Pseudo#EUW",
      "card": { "code": "triple_kill", "…": "…" },
      "gameId": "EUW1_7…",
      "verdict": "success",
      "points": 12,
      "roundBonus": false,
      "curseApplied": true,
      "occurredAt": "2026-11-07T21:34:52+01:00"
    },
    {
      "kind": "sabotage",
      "riotId": "Autre#EUW",
      "card": { "code": "pickpocket", "…": "…" },
      "gameId": null,
      "verdict": null,
      "points": 0,
      "roundBonus": false,
      "curseApplied": false,
      "occurredAt": "2026-11-07T21:20:03+01:00"
    }
  ]
}
```

| `kind` | Sens de `riotId` | À afficher |
|---|---|---|
| `round` | Le joueur qui a joué la manche | « Pseudo a réussi Triplé : +12 » ; si `curseApplied`, « … (maudit !) » |
| `sabotage` | La **cible** — jamais le saboteur | « Un saboteur a volé une relance à Autre » |

Les cartes saboteur contre la partie apparaissent comme des `round` une fois
réglées : c'est là qu'elles sont révélées. Une Malédiction n'apparaît jamais
comme `sabotage`.

Pour animer les nouveaux événements, comparer avec le fil précédent sur
(`kind`, `riotId`, `card.code`, `occurredAt`).

---

## 7. Le parcours du joueur

### `GET /api/me/lan-events/current` — quelle LAN ouvrir ?

Le point d'entrée du joueur connecté : la LAN en cours à laquelle l'un de ses
comptes est inscrit, sinon la prochaine. Son `id` est le `lanId` des routes
suivantes.

```json
{
  "lan": {
    "id": 3, "name": "LAN d'automne", "startsAt": "2026-11-07T14:00:00+01:00", "endsAt": "2026-11-08T04:00:00+01:00", "status": "in_progress",
    "queues": [{ "id": 2400, "label": "ARAM du chaos" }, { "id": 420, "label": "Classée solo/duo" }]
  },
  "riotIds": ["Pseudo#EUW"]
}
```

| Champ | Usage |
|---|---|
| `lan` | `null` (et `riotIds` vide) : inscrit à aucune LAN en cours ou à venir — « Aucune LAN prévue pour toi » |
| `lan.status` | `upcoming` : afficher la date de début, les défis ouvrent à `startsAt` ; `in_progress` : ouvrir le parcours |
| `lan.queues` | Files où les parties comptent, choisies par l'organisateur (au moins une) : « Défis valables en ARAM du chaos et en classée solo/duo ». Codes : `2400` ARAM du chaos, `420` classée solo/duo, `440` classée flexible |
| `riotIds` | Comptes du joueur inscrits à cette LAN (un seul en temps normal) |

Une LAN terminée n'est plus renvoyée : on passe à la suivante. C'est la même
LAN que celle à laquelle l'agent est appairé (§ 1 du contrat de l'agent).

### `GET /api/lan-events/{lanId}/me`

```json
{
  "lan": { "id": 3, "name": "LAN d'automne", "…": "…" },
  "riotId": "Pseudo#EUW",
  "joined": true,
  "run": {
    "score": 37,
    "rerollsLeft": 2,
    "offer": [ { "code": "pickpocket", "needsTarget": true, "…": "…" }, { "…": "…" }, { "…": "…" } ],
    "activeCard": null,
    "cardLocked": false,
    "results": [
      { "gameId": "EUW1_7…", "card": { "…": "…" }, "verdict": "success", "points": 37, "roundBonus": true, "curseApplied": false }
    ]
  },
  "sabotageTargets": [
    { "riotId": "Ami#EUW", "canBePickpocketed": true },
    { "riotId": "Autre#EUW", "canBePickpocketed": false }
  ]
}
```

- `joined: false` et `run: null` tant que le joueur n'a pas rejoint (§ `join`).
- `sabotageTargets` : les **cibles possibles**, envoyées seulement quand l'offre
  contient une carte qui vise un joueur (vide sinon). Ce sont les participants
  inscrits aux défis, sauf le joueur lui-même. `canBePickpocketed: false` : il
  n'a plus de relance, le Pickpocket ne peut pas le viser (la Malédiction si).
- Le joueur voit **sa propre** carte saboteur (`activeCard`), jamais une
  Malédiction qui le vise.

L'état du parcours se lit ainsi :

| `offer` | `activeCard` | `cardLocked` | À afficher |
|---|---|---|---|
| 3 cartes | `null` | `false` | L'offre : choisir (`pick`) ou relancer (`reroll`) |
| `null` | une carte | `false` | « Carte choisie, lance ta partie » |
| `null` | une carte | `true` | « En partie » — rien à faire |
| 3 cartes | `null` | `false` (après un match ou un sabotage) | Nouvelle offre ; le dernier résultat est en fin de `results` |

### `POST /api/lan-events/{lanId}/me/join`

Inscrit le joueur aux défis et distribue sa première offre. Rejouable sans
risque : rejoindre une seconde fois ne redistribue pas. Réponse : comme `GET /me`.

### `POST /api/lan-events/{lanId}/me/pick`

- Carte de match : `{ "slot": 1 }` — devient `activeCard`.
- Carte qui vise un joueur (`needsTarget: true`) : `{ "slot": 1, "target": "Ami#EUW" }`,
  `target` pris dans `sabotageTargets` (casse indifférente). L'effet est
  immédiat et la réponse contient déjà l'offre neuve du saboteur.

Réponse : comme `GET /me`.

### `POST /api/lan-events/{lanId}/me/reroll` — `{ "slot": 0 }`

Remplace la carte de l'emplacement `slot` et consomme une relance. Réponse : comme `GET /me`.

### Erreurs

| Code | `code` | Cas |
|---|---|---|
| `400` | `invalid_slot` | `slot` absent, pas un entier, ou hors de 0..2 |
| `400` | `target_required` | Carte qui vise un joueur choisie sans `target` |
| `400` | `target_not_allowed` | `target` donné pour une carte qui ne vise personne |
| `400` | `invalid_target` | Cible pas un texte, soi-même, hors de la LAN, ou pas encore inscrite aux défis |
| `401` | — | Jeton absent, invalide ou expiré |
| `403` | — | Route `/me` appelée avec le compte API |
| `403` | `not_a_participant` | Aucun compte du joueur n'est inscrit à cette LAN |
| `404` | `lan_not_found` | LAN inconnue |
| `409` | `not_joined` | `pick` / `reroll` avant d'avoir rejoint |
| `409` | `no_offer` | Pas d'offre en cours (carte déjà choisie) |
| `409` | `no_reroll_left` | Plus de relance |
| `409` | `target_has_no_reroll` | Pickpocket sur une cible sans relance |
| `409` | `lan_finished` | La LAN est terminée : on consulte encore, on n'agit plus |
| `409` | `several_accounts_in_lan` | Plusieurs comptes du joueur sont inscrits à la LAN : un administrateur doit n'en garder qu'un |
