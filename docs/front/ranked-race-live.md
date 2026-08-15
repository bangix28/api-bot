# Ranked Race — guide d'intégration front

Comment consommer `GET /api/ranked-race` pour afficher un classement qui se
rafraîchit tout seul, sans marteler l'API ni mentir à l'utilisateur.

> Le front est une application cliente **externe à ce dépôt**. Ce document décrit
> le contrat et les patterns recommandés ; il n'y a pas de code front ici.

---

## 1. Le contrat en un coup d'œil

```
GET /api/ranked-race?queue=solo|flex&period=week|month
GET /api/ranked-race-events
GET /api/ranked-race-events/{id}
```

Authentification : JWT `ROLE_API` (`Authorization: Bearer …`).

### Champs de course

| Champ | Type | Usage |
|---|---|---|
| `raceStatus` | enum | `upcoming` \| `awaiting_first_game` \| `running` \| `settling` \| `finished` \| `empty` |
| `lastSnapshotAt` | ISO-8601 | **Le champ à afficher** : « mis à jour il y a 4 min » |
| `nextRefreshAt` | ISO-8601 | Pilote le timer de rafraîchissement |
| `generatedAt` | ISO-8601 | Heure de calcul — utile pour détecter un cache |
| `staleness.snapshotAgeSeconds` | int \| null | Âge **calculé serveur** (cf. §3) |
| `staleness.snapshotIntervalSeconds` | int | Cadence de collecte — ne rien coder en dur |
| `window.start` / `.end` | `YYYY-MM-DD` | Dernier jour **inclus** |
| `windowIso.start` / `.endExclusive` | ISO-8601 | Fin **exclue** — pour les comparaisons |
| `progressionSuspended` | bool | Placements en cours : la Progression est masquée |

### Champs par joueur (`progression[]`)

| Champ | Usage |
|---|---|
| `start` / `end` | Rang de départ (avant la 1re partie) et rang actuel |
| `rawDelta` / `weightedDelta` | Progression brute et pondérée par le tier |
| `rankRaw` / `rankWeighted` | Deux classements côte à côte |
| `gamesPlayed` / `winrate` | `winrate` **absent** du JSON quand nul |
| **`offRaceDelta`** | LP gagnés ou perdus **hors jeu** (decay, corrections) |
| **`startedAt`** | Heure de lancement de sa première partie, `null` s'il n'a pas joué |
| **`lastActivityAt`** | Dernier relevé ayant constaté une partie — badge « en série » |

> **Les champs nuls sont omis** du JSON. Ne pas confondre « absent » et « faux ».

---

## 2. ⚠️ Départ + progression ≠ rang actuel

C'est le point qui surprend, et il est **volontaire**.

La progression ne compte que ce qui a été gagné **en jouant**. Le decay
d'inactivité et les corrections de MMR de Riot sont exclus du score — sinon un
joueur Master qui gagne ses deux parties ressort classé négatif. Ces LP hors jeu
sont exposés séparément dans `offRaceDelta`.

**Le front ne doit jamais faire l'addition.** Il affiche trois faits distincts :

```
Kenolane — Master

  Départ    Master 45 LP        ← rang juste avant sa 1re partie
  Actuel    Master 30 LP

  +60 LP gagnés en 5 parties (3V-2D)
  −75 LP hors jeu (decay)

  Score de course : +132 pts
```

`45 + 60 ≠ 30` : l'écart de 75, c'est exactement `offRaceDelta`. Affiche-le, ne
le calcule pas.

---

## 3. Fraîcheur : utiliser `snapshotAgeSeconds`, pas une soustraction de dates

L'horloge d'un poste client dérive souvent de plusieurs minutes. Un
`Date.now() - lastSnapshotAt` affiche alors « mis à jour il y a **−3 min** ».

L'âge est donc calculé **côté serveur**. Le client le corrige avec une **durée**
mesurée localement — jamais avec une date :

```js
const ageAffiche = reponse.staleness.snapshotAgeSeconds
                 + (Date.now() - tReceptionLocale) / 1000;
```

Affichage recommandé, via `Intl.RelativeTimeFormat('fr')` :

| Âge | Rendu |
|---|---|
| < 5 min | badge plein, point qui pulse 2 s puis statique |
| 5 – 40 min | badge en contour, statique |
| **≥ 40 min** | **avertissement ambre « données retardées »** |

Le troisième cas est un outil de diagnostic gratuit : au-delà de 40 minutes, le
cron a sauté un tour.

Un seul `setInterval` de 30 s au niveau de l'écran recalcule les libellés
relatifs — surtout pas un timer par ligne.

---

## 4. Rafraîchissement : polling calé sur `nextRefreshAt`

**Pas de SSE, pas de WebSocket.** En PHP-FPM, chaque connexion SSE monopolise un
worker : une quinzaine d'onglets suffit à saturer le pool. Il n'y a ni Mercure ni
Messenger dans le projet, et les introduire pour une donnée qui change 48 fois
par jour serait disproportionné.

```js
const delai = Math.max(30_000, Date.parse(r.nextRefreshAt) - Date.now())
            + Math.random() * 20_000;   // jitter
```

Le **jitter est indispensable** : sans lui, tous les clients tapent l'API à la
même seconde, juste après le cron.

Résultat : **~2 requêtes par heure et par onglet** au lieu de 120 avec un polling
à 30 s — et une meilleure fraîcheur.

Règles complémentaires :

- **`nextRefreshAt` absent ou aberrant** → repli sur un intervalle fixe de 5 min.
- **Onglet caché** (`visibilitychange`) → annuler le timer (`clearTimeout` +
  `AbortController.abort()`). Au retour, refetch immédiat si > 60 s. Ne jamais
  laisser tourner un polling en arrière-plan : les navigateurs bridant les timers
  à 1/min, on brûle de la batterie pour rien.
- **`finished` / `empty`** → arrêt complet du polling, un seul fetch.
- **`awaiting_first_game`** → 5 min : on n'attend qu'un booléen.
- **Hors ligne** (`online`/`offline`) → suspendre, backoff 5 s → 2 min plafonné,
  reprise automatique.
- **Anti-chevauchement** : un seul `AbortController` en vol.
- **Multi-onglets (bonus)** : `BroadcastChannel('ranked-race')` — le premier
  onglet qui fetch diffuse la réponse aux autres.
- **JWT** : un polling long fait **toujours** expirer le token. Prévoir refresh +
  retry unique avant redirection login. Ce cas se produira.

### Requêtes conditionnelles

L'API renvoie un `ETag` dérivé de l'instant du dernier relevé. Renvoyer
`If-None-Match` économise l'intégralité de la charge utile (~13 Ko) :

```
1er appel → 200, ETag: "v1-1786799308-367e85717104"
2e appel  → 304 Not Modified, 0 octet
```

`Cache-Control: private, max-age=60` absorbe les rafales (changement d'onglet,
remontage de composant, double-fetch de React StrictMode).

> Sur un 304, le client garde sa copie — donc un `snapshotAgeSeconds` figé. C'est
> précisément pourquoi la correction locale du §3 n'est pas optionnelle.

---

## 5. Les six états, et ce qu'on affiche

| État | Condition | Rendu |
|---|---|---|
| `upcoming` | Fenêtre pas encore ouverte (événements) | Compte à rebours avant l'ouverture |
| `awaiting_first_game` | Ouverte, personne n'a joué | **Appel à l'action**, pas un tableau vide : « La course de la semaine n'a pas encore démarré. Lance une ranked pour donner le départ. » |
| `running` | Au moins une partie jouée | Badge fraîcheur, podium, ma carte, tableau, temps restant |
| `settling` | Fenêtre close depuis < 45 min | Bandeau « Clôture en cours », tableau verrouillé, **animations désactivées** |
| `finished` | Définitif | Podium final, plus de badge live, **polling arrêté** |
| `empty` | Fenêtre close, personne n'a joué | « Personne n'a couru cette semaine » |

`settling` n'est pas du zèle : une partie terminée à 23h58 le dimanche n'est
observée qu'au relevé suivant. Annoncer « terminé » à minuit serait faux.

> `progressionSuspended` est **orthogonal** au statut : une course peut être
> `running` avec la Progression masquée pendant les placements. Ne pas fusionner
> les deux notions — le Winrate, lui, continue.

---

## 6. Rendu et animations

- **Jamais de skeleton sur refetch.** Il n'existe qu'au premier chargement. Un
  skeleton toutes les 30 minutes fait clignoter l'écran — c'est le défaut n°1 des
  tableaux de bord en polling. Pendant un refetch : `aria-busy="true"` et un
  micro-spinner dans l'indicateur de fraîcheur.
- **Jamais une erreur qui écrase des données valides.** Réseau en échec avec
  données déjà affichées → opacité réduite, bandeau discret « impossible de
  rafraîchir — dernière MAJ il y a 34 min », bouton Réessayer. L'état d'erreur
  plein écran est réservé au premier chargement.
- **Mouvement de ligne en FLIP** : mesurer avant re-render, appliquer un
  `transform: translateY(delta)` sans transition, puis le retirer avec une
  transition. **Animer `transform` uniquement** — jamais `top`, jamais `order`,
  sinon on relayoute toute la liste à chaque frame.
- **Clés stables `riotId`**, jamais l'index : avec un tri qui change, des clés par
  index annulent le FLIP et provoquent des mismatches de contenu.
- **Purger le diff au changement de `queue` ou `period`**, sinon on affiche
  « ↑7 places » en passant de solo à flex. Clé du cache de diff : `${queue}:${period}`.
- **Trois signaux distincts**, jamais confondus : le mouvement de ligne, la flèche
  ↑2/↓1 (persistée ~2 refresh), et la pastille `+18 LP` qui apparaît 4 s. C'est
  cette dernière qui fait revenir le joueur.

---

## 7. Accessibilité et performance

- **Une seule région `aria-live="polite"`**, visuellement masquée, au niveau de
  l'écran, annonçant un **résumé** : « Classement mis à jour. Kenolane reste 1er
  avec +18 LP. Vous êtes 7e. » Mettre `aria-live` sur la liste ferait annoncer
  vingt lignes à chaque refresh — inutilisable au lecteur d'écran.
- **Jamais `aria-live="assertive"`** : ce n'est pas une alerte, ça interromprait
  la lecture en cours.
- **`prefers-reduced-motion: reduce`** : court-circuiter le FLIP **en JS aussi**,
  pas seulement la transition CSS. Réordonnancement instantané, pastilles
  statiques, pas de pulsation ni de confettis.
- **Ne jamais déplacer le focus** lors d'un refresh automatique.
- **Contraste** : les couleurs de tier LoL (Gold, Bronze) passent mal sur fond
  sombre. Toujours doubler par le libellé texte ; une flèche + un chiffre + un
  texte `sr-only`, jamais la couleur seule.
- **Lignes memoïsées** sur `(rankWeighted, raceScore, gamesPlayed, isMe, movement)` :
  sur un refresh sans changement — cas majoritaire — zéro ligne re-render.
- **Nettoyer systématiquement** dans les effets : `clearTimeout`, `abort()`,
  retrait des listeners `visibilitychange` / `online` / `offline`. Un polling qui
  fuit après démontage est le bug le plus fréquent de ce pattern.

---

## 8. Payloads d'exemple

### `running`

```json
{
  "queue": "solo",
  "period": "week",
  "window": { "start": "2026-08-10", "end": "2026-08-16" },
  "windowIso": {
    "start": "2026-08-10T00:00:00+02:00",
    "endExclusive": "2026-08-17T00:00:00+02:00"
  },
  "raceStatus": "running",
  "lastSnapshotAt": "2026-08-15T15:08:28+02:00",
  "nextRefreshAt": "2026-08-15T15:38:28+02:00",
  "generatedAt": "2026-08-15T16:21:48+02:00",
  "staleness": { "snapshotAgeSeconds": 4400, "snapshotIntervalSeconds": 1800 },
  "progressionSuspended": false,
  "progression": [
    {
      "riotId": "Kenolane#EUW",
      "summonerName": "Kenolane",
      "logoId": "4568",
      "start": { "tier": "MASTER", "division": "I", "leaguePoints": 45, "raceScore": 8045 },
      "end":   { "tier": "MASTER", "division": "I", "leaguePoints": 30, "raceScore": 8030 },
      "rawDelta": 60,
      "weightedDelta": 132,
      "offRaceDelta": -75,
      "rankRaw": 1,
      "rankWeighted": 1,
      "gamesPlayed": 5,
      "winrate": 60,
      "startedAt": "2026-08-10T21:22:40+02:00",
      "lastActivityAt": "2026-08-15T14:38:00+02:00"
    }
  ],
  "winrate": {
    "gamesRequired": 5,
    "qualified": [
      { "riotId": "Kenolane#EUW", "wins": 3, "losses": 2, "gamesPlayed": 5, "winrate": 60 }
    ],
    "notQualified": []
  }
}
```

### `awaiting_first_game`

```json
{
  "queue": "solo",
  "period": "week",
  "window": { "start": "2026-08-10", "end": "2026-08-16" },
  "raceStatus": "awaiting_first_game",
  "lastSnapshotAt": "2026-08-10T00:04:12+02:00",
  "nextRefreshAt": "2026-08-10T00:34:12+02:00",
  "staleness": { "snapshotAgeSeconds": 620, "snapshotIntervalSeconds": 1800 },
  "progressionSuspended": false,
  "progression": [
    {
      "riotId": "Kenolane#EUW",
      "summonerName": "Kenolane",
      "logoId": "4568",
      "start": { "tier": "MASTER", "division": "I", "leaguePoints": 45, "raceScore": 8045 },
      "end":   { "tier": "MASTER", "division": "I", "leaguePoints": 45, "raceScore": 8045 },
      "rawDelta": 0,
      "weightedDelta": 0,
      "offRaceDelta": 0,
      "rankRaw": 1,
      "rankWeighted": 1,
      "gamesPlayed": 0
    }
  ],
  "winrate": { "gamesRequired": 5, "qualified": [], "notQualified": [] }
}
```

Noter : `winrate`, `startedAt` et `lastActivityAt` sont **absents** — ils sont nuls.
Les joueurs sont présents avec 0 partie ; c'est `raceStatus` qui dit que la course
n'a pas démarré, **jamais la taille du tableau**.

### `empty`

```json
{
  "queue": "flex",
  "period": "week",
  "window": { "start": "2026-08-03", "end": "2026-08-09" },
  "raceStatus": "empty",
  "lastSnapshotAt": "2026-08-09T23:38:02+02:00",
  "staleness": { "snapshotAgeSeconds": 145000, "snapshotIntervalSeconds": 1800 },
  "progressionSuspended": false,
  "progression": [],
  "winrate": { "gamesRequired": 5, "qualified": [], "notQualified": [] }
}
```

---

## 9. CORS

Le serveur autorise `If-None-Match` et expose `ETag`. Si le front tourne sur une
autre origine, vérifier que celle-ci est bien dans `CORS_ALLOW_ORIGIN` côté API —
sinon `response.headers.get('ETag')` renvoie `null` et le préflight rejette la
requête conditionnelle.
