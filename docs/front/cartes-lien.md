# Cartes Lien (thème Yuumi) — intégration front

Complément de [defis-lan.md](defis-lan.md). Trois nouvelles cartes défis
récompensent le joueur qui joue **pour ses coéquipiers**, à la Yuumi. Elles
forment le thème **Lien**.

## Ce qui change pour le front

Une seule chose : un champ `theme` sur **chaque** carte, partout où une carte
apparaît (offre, carte active, résultats, fil, catalogue).

```json
{
  "code": "where_is_mama",
  "label": "Où est maman ?",
  "description": "Fais une assistance sur au moins 12 kills d'un même coéquipier (6 sur la Faille).",
  "rarity": "silver",
  "theme": "bond",
  "points": 10,
  "secret": false,
  "needsTarget": false
}
```

| `theme` | Signification |
|---|---|
| `"bond"` | Carte Lien |
| `null` | Carte sans thème (toutes les autres) |

Rien d'autre ne bouge : pas de nouvel endpoint, pas de nouvelle rareté. Une carte
Lien se tire, se choisit, se relance et rapporte ses points **exactement comme
les autres cartes de sa rareté**. Le thème ne sert qu'à l'affichage.

## Les trois cartes

| `code` | Nom | Rareté | Points | Condition (ARAM du chaos / Faille) |
|---|---|---|---|---|
| `where_is_mama` | Où est maman ? | `silver` | 10 | Assistance sur 12 / 6 kills d'un même coéquipier |
| `all_for_you` | Tout pour toi | `gold` | 25 | 20 / 12 assistances, avec 3 kills au maximum |
| `avenger` | Ange vengeur | `gold` | 25 | Venger un coéquipier 3 / 2 fois : tuer son tueur dans les 10 secondes |

`label`, `description` et `points` viennent de l'API : ne pas les coder en dur
(les seuils seront ajustés après de vraies parties). Seul `code` est stable, et
c'est lui qu'il faut utiliser pour une icône ou une illustration.

## Affichage proposé : le thème Yuumi

La rareté reste le **cadre** de la carte (argent, or…), comme aujourd'hui. Le
thème s'ajoute **par-dessus**, sans le remplacer : une carte Lien or doit rester
lisible comme une carte or.

- **Badge** « Lien » dans un coin de la carte, avec une icône de patte de chat
  ou de cœur.
- **Accent rose/violet** (couleurs de Yuumi) : liseré intérieur, lueur douce au
  survol, ou fond de l'illustration. Proposition de teintes :
  - rose `#F4A6C8`
  - violet `#9B7BD4`
  - texte sur badge : `#2B1B3D`
- **Illustrations** par `code` :
  - `where_is_mama` : Yuumi accrochée à un allié
  - `all_for_you` : Yuumi qui tend un cœur ou un bouclier
  - `avenger` : Yuumi en colère, livre ouvert
- **Fil et résultats** : garder le badge sur les cartes réglées (« Bob a réussi
  Ange vengeur »), pour que le thème se repère dans l'historique.

À éviter :
- remplacer la couleur de rareté par le rose : on ne saurait plus si la carte
  vaut 10 ou 25 points ;
- filtrer ou regrouper l'offre par thème : l'offre reste trois cartes tirées au
  hasard selon leur rareté.

## Accessibilité

Le badge doit porter le texte « Lien » (ou un `aria-label`), pas seulement une
couleur ou une icône.

## Exemple : savoir si une carte est Lien

```ts
type Card = {
  code: string;
  label: string;
  description: string;
  rarity: 'silver' | 'gold' | 'prismatic' | 'saboteur';
  theme: 'bond' | null;
  points: number;
  secret: boolean;
  needsTarget: boolean;
};

const isBond = (card: Card) => card.theme === 'bond';
```

Prévoir que `theme` puisse un jour prendre d'autres valeurs : afficher sans
badge un thème inconnu plutôt que planter.
