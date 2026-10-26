# Connexion Discord des joueurs — guide d'intégration front

Comment un joueur se connecte avec Discord, consulte son profil et lie ses
comptes Riot.

> Le front est une application cliente **externe à ce dépôt**. Ce document décrit
> le contrat ; il n'y a pas de code front ici.

Un **joueur** n'est pas un compte du site : c'est une identité Discord et les
comptes Riot qui la portent. Il n'a ni e-mail ni mot de passe, et n'a pas accès
à l'administration.

---

## 1. Le contrat en un coup d'œil

```
POST /api/auth/discord          code Discord → jeton du joueur        (public)
GET  /api/me                    profil du joueur connecté             (joueur)
POST /api/me/riot-accounts      lier un compte Riot suivi             (joueur)
POST /api/me/agent-devices/approve   connecter l'agent de LAN du joueur  (joueur)
```

Erreurs : corps `{ "code": "…", "message": "…" }`, `message` en français et
affichable tel quel.

---

## 2. Connexion

### 2.1 Envoyer le joueur sur Discord

```
https://discord.com/oauth2/authorize
  ?client_id=<DISCORD_CLIENT_ID>
  &response_type=code
  &scope=identify
  &redirect_uri=<URL de retour du front, encodée>
  &state=<valeur aléatoire>
```

- `client_id` : public, à mettre dans la configuration du front.
- `redirect_uri` : **identique au caractère près** à celle déclarée dans
  l'application Discord et à `DISCORD_REDIRECT_URI` côté api-bot (en dev :
  `http://localhost:3000/auth/discord/callback`). Une différence, même un `/`
  final, fait échouer la connexion en `401 discord_login_failed`.
- `state` : tirer une valeur aléatoire, la garder (`sessionStorage`) et
  **vérifier au retour** qu'elle revient identique, sinon abandonner. C'est la
  protection contre un retour forgé (CSRF).
- `scope=identify` suffit : le site n'a besoin ni de l'e-mail ni des serveurs
  du joueur.

### 2.2 Échanger le code

Discord renvoie le navigateur sur `redirect_uri?code=…&state=…` (ou
`?error=access_denied` si le joueur refuse). Après vérification du `state` :

```
POST /api/auth/discord
Content-Type: application/json

{ "code": "<code reçu de Discord>" }
```

Le code est à **usage unique** et expire en quelques minutes : l'envoyer tout de
suite, une seule fois.

**`200`**

```json
{
  "token": "eyJ0eXAiOiJKV1Qi…",
  "player": {
    "discordId": "80351110224678912",
    "discordUsername": "pseudo",
    "riotIds": []
  }
}
```

| Code | `code` | Cas |
|---|---|---|
| `400` | `missing_code` | Corps sans `code` |
| `401` | `discord_login_failed` | Code refusé, expiré ou déjà utilisé ; `redirect_uri` différente ; Discord injoignable ; connexion Discord non configurée côté serveur |

### 2.3 Utiliser le jeton

```
Authorization: Bearer <token>
```

- Durée de vie : **1 heure**. Passé ce délai, l'API répond `401` : relancer la
  connexion Discord (§ 2.1). Ajouter `&prompt=none` à l'URL pour qu'un joueur
  qui a déjà autorisé l'application ne revoie pas l'écran d'autorisation.
- Le jeton d'un joueur **ne donne pas accès** aux classements
  (`/api/riot-account/*`, `/api/ranked-race*` : rôle `ROLE_API`). Le front
  continue d'utiliser son propre compte API pour les afficher.

---

## 3. `GET /api/me` — profil du joueur

**`200`**

```json
{
  "discordId": "80351110224678912",
  "discordUsername": "pseudo",
  "riotIds": ["Pseudo#EUW", "Smurf#1234"]
}
```

`riotIds` vide : le joueur n'a encore lié aucun compte — l'inviter à le faire (§ 4).

| Code | Cas |
|---|---|
| `401` | Jeton absent, invalide ou expiré |
| `403` | Jeton valide mais pas celui d'un joueur (compte API ou administrateur) |

---

## 4. `POST /api/me/riot-accounts` — lier un compte Riot

```
POST /api/me/riot-accounts
Authorization: Bearer <token>
Content-Type: application/json

{ "riotId": "Pseudo#EUW" }
```

Seuls les comptes **déjà suivis par le site** (ajoutés par un administrateur)
peuvent être liés : un joueur ne fait pas entrer un compte dans les classements
par lui-même. La casse est ignorée (`pseudo#euw` = `Pseudo#EUW`).

**`200`** : le profil à jour (même format que § 3). Lier un compte déjà lié au
même joueur renvoie aussi `200` : le front peut rejouer la demande sans risque.

| Code | `code` | Cas | Message à afficher |
|---|---|---|---|
| `400` | `invalid_riot_id` | Absent ou mal formé (attendu `Pseudo#TAG`) | Le `message` reçu |
| `404` | `riot_account_not_tracked` | Aucun compte suivi ne porte ce Riot ID | Inviter à vérifier la saisie, ou à demander l'ajout du compte à un administrateur |
| `409` | `riot_account_already_linked` | Compte déjà lié à un autre joueur | Seul un administrateur peut corriger une liaison |
| `401` / `403` | — | Comme § 3 | — |

Il n'y a pas de route pour délier un compte : une liaison erronée se corrige en
administration.

---

## 5. Page « Connecter mon agent »

L'agent de LAN (programme lancé sur le PC du joueur pendant une LAN) ne se
connecte pas lui-même : il affiche un **code** que le joueur saisit sur cette
page, où il est déjà connecté. L'URL de la page est celle que l'agent affiche
(`verificationUrl`, par ex. `https://site.example/agent`). Déroulé complet côté
agent : [contrat de l'agent](../agent/contrat-releve.md), § 1.

```
POST /api/me/agent-devices/approve
Authorization: Bearer <token>
Content-Type: application/json

{ "userCode": "K7QF-92PZ" }
```

- Accepter la saisie telle quelle : casse, espaces et tiret sont indifférents.
- Le code vaut **10 minutes** et ne sert qu'une fois.

**`200`** : afficher « Agent connecté pour la LAN *LAN d'automne* ».

```json
{
  "lan": { "id": 3, "name": "LAN d'automne", "startsAt": "2026-11-07T14:00:00+01:00", "endsAt": "2026-11-08T04:00:00+01:00" },
  "player": { "riotIds": ["Pseudo#EUW"] }
}
```

| Code | `code` | Message à afficher |
|---|---|---|
| `400` | `invalid_user_code` | « Code inconnu ou expiré : relance la connexion depuis l'agent » |
| `409` | `not_registered` | « Tu n'es inscrit à aucune LAN en cours ou à venir » |
| `401` / `403` | — | Reconnexion Discord nécessaire (§ 2) |
