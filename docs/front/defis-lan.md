# Défis de LAN — guide d'intégration front

Comment afficher les défis de LAN façon ARAM du chaos : l'écran de la LAN
(classement, fil d'exploits) et le parcours de chaque joueur (offre de cartes,
choix, relances).

> Le front est une application cliente **externe à ce dépôt**. Ce document décrit
> le contrat ; il n'y a pas de code front ici. Décisions : [ADR-0005](../adr/0005-defis-de-lan-donnees-lues-sur-les-postes.md).

---

## 1. Le jeu en une minute

- **Une manche = un match.** Avant chaque partie, le joueur reçoit une **offre de
  3 cartes défis** et en **choisit une**. Le choix est définitif.
- **Relances** : 3 par joueur et par LAN ; une relance remplace **une** carte de
  l'offre par une carte absente de l'offre.
- Au **lancement** de la partie, la carte est **verrouillée** sur ce match
  (`cardLocked: true`). Un joueur qui lance une partie sans avoir choisi la joue
  sans carte et garde son offre pour la suivante.
- À la **fin** de la partie, la carte est jugée sur les statistiques du match :
  - `success` : points de la carte ;
  - `failure` : rien — mais échouer une carte **prismatique** rend une relance ;
  - `unknown` : la donnée manquait (agent absent ou coupé) — rien, mais une
    relance rendue.
- **Bonus de manche** : quand au moins deux joueurs ont engagé une carte sur le
  même match et que **tous** la réussissent, chacun gagne **+50 %**.
- Puis une nouvelle offre arrive pour le match suivant.

Le verrouillage et le règlement sont automatiques (détection des parties et
relevés de l'agent) : le front ne les déclenche jamais, il les **constate** en
relisant le parcours ou le classement.

---

## 2. Le contrat en un coup d'œil

```
GET  /api/lan-challenges/cards             catalogue des cartes et règles        compte API ou joueur
GET  /api/lan-events/{lanId}/standings     classement de la LAN                  compte API ou joueur
GET  /api/lan-events/{lanId}/feed?limit=   fil d'exploits                        compte API ou joueur
GET  /api/lan-events/{lanId}/me            mon parcours                          joueur
POST /api/lan-events/{lanId}/me/join       rejoindre les défis                   joueur
POST /api/lan-events/{lanId}/me/pick       choisir une carte     { "slot": 0 }   joueur
POST /api/lan-events/{lanId}/me/reroll     relancer une carte    { "slot": 0 }   joueur
```

- **Compte API** : le compte e-mail du front (`ROLE_API`), pour l'écran de la LAN.
- **Joueur** : jeton obtenu par la connexion Discord ([connexion-discord.md](connexion-discord.md)).
- Les routes `/me` désignent le **compte Riot du joueur inscrit à la LAN** : le
  front n'envoie jamais de Riot ID.
- Erreurs : corps `{ "code": "…", "message": "…" }`, `message` en français et
  affichable tel quel. Dates : ISO 8601 avec fuseau.

---

## 3. Une carte

Même forme partout (offre, carte active, résultats, fil) :

```json
{
  "code": "penta_kill",
  "label": "Penta",
  "description": "Réalise un penta.",
  "rarity": "prismatic",
  "points": 100
}
```

`rarity` : `silver` | `gold` | `prismatic` — à styliser comme les augments
(argent, or, prismatique). `code` est stable : c'est lui qu'il faut utiliser
pour une icône ou une animation.

---

## 4. `GET /api/lan-challenges/cards`

```json
{
  "cards": [ { "code": "double_kill", "label": "Doublé", "…": "…" } ],
  "rarities": [
    { "rarity": "silver", "points": 10, "drawWeight": 65 },
    { "rarity": "gold", "points": 25, "drawWeight": 28 },
    { "rarity": "prismatic", "points": 60, "drawWeight": 7 }
  ],
  "rules": { "initialRerolls": 3, "roundBonusPercent": 50 }
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
      "activeCard": { "code": "quadra_kill", "…": "…" },
      "cardLocked": true
    }
  ]
}
```

| Champ | Usage |
|---|---|
| `rank` | Ordre : points, puis défis réussis (à points égaux, la régularité l'emporte), puis Riot ID |
| `joined` | `false` : participant qui n'a pas encore rejoint les défis (« pas encore commencé ») |
| `activeCard` | Carte choisie par le joueur, `null` sinon. Visible de tous : c'est le sel du jeu |
| `cardLocked` | `true` : la carte est en jeu sur un match en cours (« en partie ») |

Rafraîchissement conseillé : toutes les **15 secondes** pendant la LAN.

## 6. `GET /api/lan-events/{lanId}/feed?limit=20`

Les manches réglées, **la plus récente en premier**. `limit` : 20 par défaut,
de 1 à 50.

```json
{
  "entries": [
    {
      "riotId": "Pseudo#EUW",
      "gameId": "EUW1_7…",
      "card": { "code": "penta_kill", "label": "Penta", "…": "…" },
      "verdict": "success",
      "points": 150,
      "roundBonus": true,
      "settledAt": "2026-11-07T21:34:52+01:00"
    }
  ]
}
```

Pour animer les nouveaux exploits, comparer avec le fil précédent : une entrée
inconnue (même `riotId`, `gameId` et `card`) est nouvelle.

---

## 7. Le parcours du joueur

### `GET /api/lan-events/{lanId}/me`

```json
{
  "lan": { "id": 3, "name": "LAN d'automne", "…": "…" },
  "riotId": "Pseudo#EUW",
  "joined": true,
  "run": {
    "score": 37,
    "rerollsLeft": 2,
    "offer": [ { "code": "double_kill", "…": "…" }, { "…": "…" }, { "…": "…" } ],
    "activeCard": null,
    "cardLocked": false,
    "results": [
      { "gameId": "EUW1_7…", "card": { "…": "…" }, "verdict": "success", "points": 37, "roundBonus": true }
    ]
  }
}
```

`joined: false` et `run: null` tant que le joueur n'a pas rejoint (§ `join`).

L'état du parcours se lit ainsi :

| `offer` | `activeCard` | `cardLocked` | À afficher |
|---|---|---|---|
| 3 cartes | `null` | `false` | L'offre : choisir (`pick`) ou relancer (`reroll`) |
| `null` | une carte | `false` | « Carte choisie, lance ta partie » |
| `null` | une carte | `true` | « En partie » — rien à faire |
| 3 cartes | `null` | `false` (après un match) | Nouvelle offre ; le dernier résultat est en fin de `results` |

### `POST /api/lan-events/{lanId}/me/join`

Inscrit le joueur aux défis et distribue sa première offre. Rejouable sans
risque : rejoindre une seconde fois ne redistribue pas. Réponse : comme `GET /me`.

### `POST /api/lan-events/{lanId}/me/pick` — `{ "slot": 1 }`

Choisit la carte à l'emplacement `slot` (0, 1 ou 2) de l'offre. Réponse : comme `GET /me`.

### `POST /api/lan-events/{lanId}/me/reroll` — `{ "slot": 0 }`

Remplace la carte de l'emplacement `slot` et consomme une relance. Réponse : comme `GET /me`.

### Erreurs

| Code | `code` | Cas |
|---|---|---|
| `400` | `invalid_slot` | `slot` absent, pas un entier, ou hors de 0..2 |
| `401` | — | Jeton absent, invalide ou expiré |
| `403` | — | Route `/me` appelée avec le compte API |
| `403` | `not_a_participant` | Aucun compte du joueur n'est inscrit à cette LAN |
| `404` | `lan_not_found` | LAN inconnue |
| `409` | `not_joined` | `pick` / `reroll` avant d'avoir rejoint |
| `409` | `no_offer` | Pas d'offre en cours (carte déjà choisie) |
| `409` | `no_reroll_left` | Plus de relance |
| `409` | `lan_finished` | La LAN est terminée : on consulte encore, on n'agit plus |
| `409` | `several_accounts_in_lan` | Plusieurs comptes du joueur sont inscrits à la LAN : un administrateur doit n'en garder qu'un |
