# CPT Exporter Plugin

Exporte les types de contenu personnalisés.

## Prérequis

- WordPress 6.5+
- PHP 8.1+
- ACF (optionnel, uniquement pour proposer les champs ACF)

## Fonctionnalités

Un fichier = une feature, dans `includes/` :

| Fichier | Feature |
|---|---|
| `cpt-exporter-settings.php` | Page de réglages **Réglages > CPT Exporter** : liste les articles et les types de contenu personnalisés. Cocher un type affiche ses taxonomies, ses champs natifs et ses champs ACF, à cocher à leur tour. Le bouton d'enregistrement stocke la sélection dans l'option `cpt_exporter_settings`. |

## Usage

## Notes techniques

- **Types de contenu proposés.** Les articles, puis les types non natifs déclarés avec `can_export` (même requête qu'Outils > Exporter). Les types internes d'Elementor (`elementor_`, `e-`, `e_`) et d'ACF (`acf-`) sont exclus par préfixe : aucun réglage ne distingue ceux d'Elementor d'un CPT maison, `elementor_library` étant public et doté d'un menu. Limite connue : un CPT maison dont le nom commence par `e-` ou `e_` serait masqué lui aussi.
- **Ce qui est proposé pour chaque type.** Taxonomies : celles qui ont une interface (`show_ui`), ce qui écarte `post_format` et les taxonomies internes de Polylang. Champs natifs : les `supports` qui portent une donnée (`title`, `editor`, `excerpt`, `thumbnail`, `author`, `page-attributes`), pas les comportements (`revisions`, `autosave`, `elementor`…). Champs ACF : ceux des groupes assignés au type, hors champs de mise en page (`tab`, `message`, `accordion`), identifiés par leur clé `field_…` qui survit à un renommage.
- **Enregistrement.** Une seule option, `cpt_exporter_settings`, via l'API Settings (`options.php`, nonce et capacité `manage_options` gérés par WordPress). Le nettoyage ne garde que les valeurs que la page propose au moment de l'enregistrement : un type, une taxonomie ou un champ disparu est retiré à la sauvegarde suivante. Décocher un type conserve ses sous-sélections : elles sont masquées, pas effacées, et réapparaissent si on le recoche. À l'étape suivante, ne lire que les types dont `enabled` vaut `true`.
- **Affichage conditionnel.** Les sous-options d'un type sont masquées tant qu'il n'est pas coché, par une seule règle CSS `:has()` écrite dans la page : ni JavaScript, ni fichier de style. Structure enregistrée :

  ```php
  ['projet' => ['enabled' => true, 'taxonomies' => ['category'], 'fields' => ['excerpt'], 'acf' => ['field_…']]]
  ```

## Tests

## Installation

```bash
cd wp-content/plugins/   # ou web/app/plugins/ en Bedrock
git clone https://github.com/WarLay0/plugin-cpt-exporter.git
wp plugin activate plugin-cpt-exporter
```
