# ADR-0006 : Les cartes saboteur sont rares, secrètes, et ne coûtent jamais une manche

## Statut

Accepté (2026-10-26)

S'ajoute à l'[ADR-0005](0005-defis-de-lan-donnees-lues-sur-les-postes.md) : les
cartes saboteur sont jugées sur les mêmes relevés que les autres.

## Contexte

Les défis de LAN proposent avant chaque match une offre de 3 cartes défis
(argent, or, prismatique). Demande : des cartes **saboteur**, rares, qui font
« perdre » ou « mourir le plus », et d'autres qui gênent directement un autre
joueur. Une LAN réunit 3 à 5 amis, souvent dans la même équipe.

Trois risques à éviter :

- une carte qui vise un joueur et fait perdre son match au saboteur : il ne
  « jouerait » plus, et s'ennuierait ;
- un malus qui puisse tourner au bonus (une offre « 100 % prismatique » infligée
  à la cible lui ouvre en fait les plus gros gains) ;
- une information qui fuite : savoir qui sabote, ou savoir qu'on est maudit,
  tue l'effet.

## Décision

### 1. Une rareté à part, la plus rare

`saboteur` : 3 % par emplacement d'offre (pris sur l'argent, 65 → 62 %), soit
environ une offre sur onze. Tirée comme les autres, relançable comme les autres.

### 2. Deux familles

| Carte | Famille | Effet |
|---|---|---|
| Mauvais perdant | contre la partie | 40 pts si le joueur perd |
| Martyr | contre la partie | 40 pts s'il finit avec le plus de morts de la partie |
| Pickpocket | contre un joueur | vole une relance à la cible |
| Malédiction | contre un joueur | prochain défi réussi de la cible à 50 % |

- **Contre la partie** : une carte de match ordinaire (choisie, verrouillée,
  jugée en fin de match). « Martyr » attend une donnée calculée par le serveur
  sur les 10 joueurs (`mostDeathsInGame`) ; tant qu'elle manque, verdict inconnu.
- **Contre un joueur** : effet **immédiat**, cible choisie parmi les participants
  inscrits aux défis (jamais soi-même). Le saboteur ne marque pas de points et
  reçoit **aussitôt une offre neuve** : saboter est un bonus en plus de sa
  manche, jamais à sa place.

### 3. La Malédiction est un vrai malus, et ne se voit pas

- −50 % sur le **prochain défi réussi**, appliqué après le bonus de manche,
  arrondi à l'entier inférieur. Un échec ne la lève pas : elle attend la
  prochaine réussite. Elle ne peut pas devenir un avantage.
- Invisible pour la cible et pour les autres : absente du parcours, du
  classement et du fil. Elle se révèle au règlement (`curseApplied`).
- Deux malédictions ne se cumulent pas, **sans le dire** : refuser la seconde
  apprendrait au saboteur que la cible est déjà maudite.

### 4. Le secret

- Une carte saboteur choisie est affichée « secrète » aux autres ; son
  propriétaire la voit.
- Le saboteur n'est jamais nommé dans l'API. Il est conservé en base
  (`lan_sabotage`) pour l'administration.
- Le fil annonce un Pickpocket (la cible voit bien qu'elle a perdu une relance),
  jamais une Malédiction.

### 5. Le choix de la cible passe par l'API

`GET /me` envoie la liste des cibles possibles (`sabotageTargets`) quand l'offre
contient une carte qui vise un joueur ; `POST /me/pick` reçoit la cible
(`target`). Le serveur revérifie tout : participant, inscrit aux défis, pas
soi-même, relance disponible pour un Pickpocket.

## Alternatives écartées

- **Malédiction = offre 100 % prismatique** : peut rapporter plus qu'une offre
  normale, ce n'est pas un malus.
- **Cible imposée (le leader)** : plus simple, mais le joueur voulait choisir
  parmi tous les participants.
- **Carte qui vise un joueur à la place de la manche** : le saboteur ne jouerait
  pas son match.
- **Malédictions cumulables** : imprévisible (−75 %, −87 %…) et inutile à 3-5 joueurs.

## Conséquences

### Positives

- Du chaos sans frustration : le saboteur joue toujours son match, la cible
  aussi.
- Le secret est garanti par le serveur, pas par la discipline du front.

### Négatives / limites assumées

- ⚠️ **Mauvais perdant et Martyr** incitent à perdre ou mourir exprès : c'est du
  *feeding* / *throwing* au sens des règles de Riot, sanctionnable (signalements,
  détection automatique). Retenues à la demande des joueurs, qui en assument le
  risque sur leurs comptes.
- Martyr reste en verdict inconnu tant que la réception des relevés de l'agent
  (lot 4) ne calcule pas `mostDeathsInGame`.
- Ordre bonus / malédiction indifférent sur le barème actuel (10, 25, 40, 60,
  100) : il ne compterait que pour un barème aux arrondis différents.

### Déclencheurs de révision

- Une sanction Riot liée aux cartes « contre la partie » : les retirer du tirage.
- Une LAN plus grande (10+ joueurs) : revoir le non-cumul des malédictions et la
  liste complète des cibles envoyée au front.
