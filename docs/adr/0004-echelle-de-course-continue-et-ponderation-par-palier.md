# ADR-0004 : L'échelle de course compte des LP, et chaque LP est payé au tarif du palier où il est gagné

## Statut

Accepté (2026-08-20)

Remplace la limite « Biais du yo-yo à la frontière de tier » de l'[ADR-0003](0003-course-a-la-partie-snapshots-30-min.md),
qui l'assumait comme dette. Les décisions n°1 à 10 de l'ADR-0003 restent en vigueur :
le segment reste l'unité de calcul, le rang de départ découle toujours de la règle,
et rien n'est persisté.

## Contexte

Constaté en prod le **2026-08-20** : un joueur promu en Diamond affichait
**+1 448,8** de progression pondérée et **+876** de progression brute pour
**276 LP réellement gagnés**. Rapport apparent ×5,25, alors que le coefficient
maximum de la table valait 2,2.

`RaceScore` additionnait trois échelles qui ne s'emboîtaient pas :
`RankedTier::getScore()` avançait par pas de **1000**, alors qu'un palier ne
contient que quatre divisions de 100 LP, soit **400 points de distance réelle**.

```
Emerald I 99 LP = 6000 + 400 + 99 = 6499
Diamond IV 0 LP = 7000 + 100 +  0 = 7100   -> +601 pour +1 LP réel
```

Chaque franchissement de palier **fabriquait 600 points qu'aucune partie n'avait
gagnés**. La frontière apex en fabriquait 500 de plus, son plancher sautant 900 au
lieu de 400 (`Diamond I 99 = 7499` → `Master 0 = 8000`). À l'intérieur d'un palier
l'échelle était juste : les divisions étaient déjà continues.

Trois constats ont orienté la décision.

- **Les deux classements étaient faux.** `rawDelta`, présenté au joueur comme ses
  « LP gagnés », était la somme des mêmes deltas pollués. `rankRaw` changeait autant
  que `rankWeighted`.
- **La pondération n'était pas la cause.** `1 448,8 / 876 = 1,654`, dans la plage
  `[1,0 ; 2,2]` : le rapport ne dénonçait rien. C'est la comparaison au LP *réel*
  qui donnait le ×5,25. La pondération amplifiait les 600 fantômes de 1,6 à 2,2.
- **Le yo-yo ne pouvait pas produire ce chiffre.** Dans l'ancienne règle, toute
  excursion fermée biaise le pondéré *vers le bas* (montée au tarif bas, descente au
  tarif haut) : un cycle Emerald↔Diamond valait −163. Défaut réel, mais de signe
  opposé.

Le diagnostic a été établi avec `ranked-race:audit`, qui décompose un score jusqu'au
segment. Le segment de promotion du joueur pesait **961,6 des 1 448,8 points** — 66 %
de son score de la semaine, pour **une seule partie**.

## Décision

### 1. L'échelle de course compte les LP cumulés depuis Iron IV 0 LP

`RaceScore::of()` renvoie `tierIndex × 400 + divisionIndex × 100 + LP`.
**Un point vaut un LP partout**, y compris au franchissement d'un palier : un palier
occupe exactement les 400 LP de ses quatre divisions.

La progression brute redevient donc ce que le front en dit depuis le début : un
nombre de LP.

### 2. Les trois libellés apex partagent l'indice 7

Master, Grandmaster et Challenger ne sont pas trois paliers empilés mais un seul, sans
divisions ni plafond. Leur plancher commun vaut `7 × 400 = 2800`, si bien que
`Diamond I 99 = 2799` et `Master 0 = 2800` : **la frontière apex est continue sans cas
particulier**. Les deux discontinuités tombent par la même construction, là où
l'ancien code avait besoin d'une branche dédiée pour rattraper un plancher arbitraire.

Le libellé GM/Challenger reste cosmétique, conformément à la règle déjà posée : le
compter à part fabriquerait de nouveaux deltas fantômes au moment de la promotion.

### 3. La pondération est une conversion de position, pas un facteur appliqué à un delta

`WeightedProgressionScale::at()` convertit une position de l'échelle en points de
course, chaque LP payé au tarif du palier **où il a été gagné**. Le delta pondéré d'un
segment est la différence de deux conversions.

Trois propriétés en découlent, qu'un facteur appliqué au delta entier n'offrait pas :

| Propriété | Ancienne règle | Nouvelle règle |
|---|---|---|
| Aller-retour Gold I 90 → Plat IV 20 → Gold I 90 | −94,5 | **0,0 exactement** |
| Le même finissant 15 LP plus bas | −115,5 | **les 15 LP perdus, et rien d'autre** |
| Dépendance au chemin et à l'ordre des segments | oui | **aucune** |
| Segment traversant une frontière | payé au tarif du départ | **découpé au pro-rata** |

C'est la levée explicite du refus posé par l'ADR-0003 (« le corriger imposerait de
découper un delta à la frontière, ce que `TierCoefficient` refuse »). Le découpage
n'est plus un cas particulier à écrire : il tombe de la conversion.

`TierCoefficient` devient la **pente** de cette conversion sur la bande du palier.

### 4. Le calcul passe par des centièmes entiers

Les tarifs n'ont jamais plus de deux décimales. La conversion accumule et soustrait en
entiers, puis divise par 100 une seule fois.

Ce n'est pas cosmétique : en flottants, un LP apex valait `2,2000000000003`, et un
aller-retour ne rendait zéro qu'à 10⁻¹³ près. La propriété n°3 n'aurait alors été
testable qu'avec une tolérance — donc un test qui n'aurait pas prouvé ce qu'il annonce.

### 5. Les tarifs sont calibrés sur une friction réelle, pas sur la difficulté supposée du palier

| Palier | Avant | Après |
|---|---|---|
| Iron → Platinum | 1,0 → 1,4 | **1,0** |
| Emerald | 1,6 | **1,05** |
| Diamond | 1,8 | **1,15** |
| Master / GM / Challenger | 2,2 | **1,35** |

Le postulat « gagner du LP est plus dur en haut » est vrai du *skill*, faux du *LP par
partie* — et c'est le LP par partie que la course mesure. Sous Master, le montant gagné
dépend de l'écart entre le MMR et le rang affiché, pas du palier : un joueur à son
niveau d'équilibre nette zéro en Bronze comme en Diamond. Ce qui produit du LP, c'est
d'être **sous-classé**, et le convertir est plus facile en bas, où les gains gonflés et
les winrates à 70 % sont accessibles.

L'ancienne grille rendait un Diamond assidu **structurellement imbattable** : +200 LP en
Diamond valaient 360 points contre 300 pour +300 LP en Iron. Le podium était fermé au
bas de l'échelle, à l'inverse de l'objectif de la course.

La correction reste volontairement **modeste et tardive**, pour les frictions propres au
haut de l'échelle : décompression des gains, retour rapide à 50 % de winrate, decay apex
qui taxe l'activité, files longues aux heures creuses, impossibilité d'être sous-classé
au sommet. Le seuil de bascule passe de « un Diamond bat un Iron s'il fait au moins
55 % de ses LP » à « au moins 87 % ».

Planchers de la conversion après recalibration :

| Palier | Position `s` | Points |
|---|---|---|
| Iron IV → Platinum I | 0 → 2000 | **identiques à `s`** |
| Emerald IV | 2000 | 2000 |
| Diamond IV | 2400 | 2420 |
| Master 0 LP | 2800 | 2880 |

Au-delà de Master 0 LP, pente 1,35 sans plafond.

### 6. `RankedTier::getScore()` n'est pas touché

Il alimente `RankedQueueEntity::getScore()`, **persisté** dans `riot_account.score`
et `summoner_elo_daily.score`, et sert le classement du rang absolu ainsi que
`/elo-daily`. Les deux échelles coexistent désormais explicitement : `getScore()` pour
le rang absolu, `index()` pour la position dont dérive l'échelle de course.

Un test de non-régression fige `GM 250 LP` à **9250** en rang absolu et **3050** en
course.

### 7. Un compte non classé n'a pas de place dans l'échelle

`RankedTier::UNRANKED->index()` lève, là où `getScore()` retombait sur 0. Avec
`Iron IV 0 LP = 0`, un non-classé mappé à 0 serait *à égalité avec Iron IV* — un faux
voisinage, alors qu'il tombait auparavant dans une zone inoccupée sous 1000. Et
`TierCoefficient::for(UNRANKED)` levait déjà : on ne fait qu'échouer plus tôt et
visiblement. En pratique aucun snapshot n'est écrit pour un compte non classé.

### 8. Le garde-fou est dérivé de la table, jamais recopié

`TierCoefficient::lowest()` / `highest()` énumèrent la table. Le rapport pondéré/brut
d'un segment ne peut pas sortir de cette plage : c'est l'invariant qui aurait attrapé
le +1 448,8, et il est vérifié par `ranked-race:audit`.

La recalibration de la décision n°5 a fait passer la plage de `[1,0 ; 2,2]` à
`[1,0 ; 1,35]` **sans une ligne de plus**. Une plage écrite en dur serait devenue
silencieusement fausse le jour même.

### 9. Un changement de règle incrémente `ALGO_VERSION`

L'ETag dérive de `MAX(captured_at)`, qui ne bouge pas au déploiement : sans ce bump, les
clients resteraient jusqu'à **30 minutes** sur les anciennes valeurs, en `304`. Les
trois lots de ce chantier ont porté le jeton de `v1` à `v4`.

Ce jeton n'est **pas** une version sémantique. Toute modification des règles invalide
100 % des réponses en cache : il n'existe pas de changement « mineur » ici, et
numéroter `v3.1` suggérerait une gradation qui n'existe pas.

### 10. Le correctif est rétroactif, et la règle n'est pas versionnée par période

Le score n'est jamais persisté : il est recalculé à la lecture depuis `tier`,
`division`, `league_points`, `wins`, `losses`. Aucune migration, aucun backfill — mais
**tout l'historique change**, événements terminés compris.

Versionner la règle pour préserver les classements passés supposerait de persister les
palmarès, donc de reprendre l'alternative « stocker l'ignition » déjà écartée par
l'ADR-0003. On assume un changement rétroactif unique, annoncé.

## Alternatives écartées

- **Corriger `RankedTier::getScore()` pour rendre l'échelle continue à la source** :
  casse deux colonnes persistées, le classement du rang absolu et `/elo-daily`. Le
  périmètre du correctif devait rester confiné au namespace `RankedRace`.
- **Plafonner le rapport pondéré/brut en sortie à 2,2** : masque le symptôme et laisse
  `rawDelta` faux. Le brut était pollué autant que le pondéré.
- **Découper le delta à la frontière sans rendre l'échelle continue** : corrige
  l'asymétrie du yo-yo mais laisse les 600 fantômes dans le brut *et* dans le pondéré.
  On pondérerait toujours 601 points au lieu de 1.
- **Figer un tarif unique au palier de départ de la course** : rend la symétrie triviale
  et le score calculable de tête par le joueur, mais paie un LP gagné en Diamond au
  tarif Emerald. Écarté au profit de l'exactitude — l'explicabilité sera traitée par
  l'affichage, pas en faussant le calcul.
- **Assumer les 600 points comme bonus de promotion implicite** : un bonus doit être une
  ligne visible et chiffrée, jamais un artefact de la représentation interne. Un bonus
  de montée explicite est prévu, à un montant de l'ordre d'une victoire et demie.
- **Accumuler la conversion en flottants** : voir décision n°4.
- **Plafonner le delta d'un segment par partie jouée** : referme un trou réel — un relevé
  aberrant *suivi d'une partie* crée un segment, jusqu'à −3 251 pondérés — mais demande
  d'arbitrer un plafond de LP par partie, très variable en apex. Reporté hors de ce
  chantier, pas abandonné.

## Conséquences

### Positives

- La progression brute est enfin un nombre de LP : le front peut l'afficher comme tel.
- Franchir un palier ne rapporte plus rien en soi, et une démotion ne retire que les LP
  réellement perdus.
- Un aller-retour à la frontière est neutre : le classement n'incite plus à *ne pas*
  tenter la montée en fin de période.
- Le bas de l'échelle peut redevenir gagnant quand il fait mieux.
- Les deux frontières fautives sont réparées par une seule construction, sans cas
  particulier pour l'apex.
- L'invariant de plage suit automatiquement toute recalibration future.

### Négatives / limites assumées

- **Sous Emerald, `rawDelta == weightedDelta` exactement.** Pour une bonne partie du
  club, les deux classements affichent le même chiffre. Voulu, mais la page de règles
  devra le dire : sinon les joueurs concernés chercheront le bug. Le bonus de montée à
  venir est ce qui redifférenciera les deux colonnes.
- **Le classement Points perd de son pouvoir de différenciation.** Si la distribution
  réelle du club se concentre sous Emerald, la question de le remplacer par des
  catégories par palier de départ se posera — ségréguer est plus lisible que pondérer.
- **Arrondi à une décimale.** `weightedProgression()` arrondit, or la conversion produit
  des centièmes. Un aller-retour finissant 15 LP plus bas vaut exactement −18,75 avec
  l'ancienne table et l'API renvoyait −18,8. Perte plafonnée à 0,05 point, invisible à
  l'affichage, mais une décomposition affichée au joueur ne se recomposera pas
  exactement au centième.
- **Rétroactivité totale et irréversible en lecture.** Les palmarès passés changent, et
  les anciens chiffres sont irrécupérables sans capture préalable du JSON.
- **`start.raceScore` et `end.raceScore` changent d'échelle sans changer de nom**
  (~1100–10000 → ~0–3000). Le guide front doit être mis à jour ; un client qui affiche
  ce champ brut se met à mentir en silence.
- **Le trou du relevé aberrant traversé par une partie reste ouvert** (cf. alternatives
  écartées). La protection décrite dans l'ADR-0003 est partielle : elle n'absorbe une
  lecture transitoire que si *aucune* partie n'est jouée de part et d'autre.
- **La course mensuelle d'août est tronquée au 15**, date de création de
  `summoner_elo_snapshot`. Sa baseline est en réalité mi-août, et rien ne le signale
  dans le JSON. Sans lien avec cette décision, mais visible en même temps qu'elle.

### Déclencheurs de révision

Cet ADR devra être révisé si :

- la distribution réelle des paliers du club se révèle concentrée sous Emerald sur
  plusieurs mois → le classement pondéré ne différencie plus rien, passer aux catégories
  par palier de départ ;
- Riot **ajoute ou retire un palier** (comme Emerald en 2023) → l'échelle se décale de
  400 partout, `RankedTier::index()` et les planchers de la conversion changent, et
  l'historique n'est plus comparable d'une saison à l'autre ;
- Riot **change le nombre de divisions par palier** → la constante `LP_PER_TIER = 400`
  n'est plus dérivable de `4 × 100` ;
- le métier veut des **palmarès gelés et opposables** → la décision n°10 tombe, il
  faudra persister les classements, et avec eux la version de règle qui les a produits ;
- un tarif doit gagner une **troisième décimale** → l'accumulation en centièmes de la
  décision n°4 ne suffit plus ;
- le bonus de montée est livré → l'invariant de plage de la décision n°8 ne s'applique
  plus au total, seulement à la composante de conversion.
