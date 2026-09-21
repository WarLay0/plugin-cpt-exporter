# CPT Exporter Plugin

Exporte les types de contenu personnalisés.

## Prérequis

- WordPress 6.5+
- PHP 8.1+, avec l'extension `zip` pour l'export XLSX
- ACF (optionnel, uniquement pour proposer les champs ACF)

## Fonctionnalités

Un fichier = une feature, dans `includes/` :

| Fichier | Feature |
|---|---|
| `cpt-exporter-settings.php` | Page de réglages **Réglages > CPT Exporter** : liste les articles et les types de contenu personnalisés. Cocher un type affiche la liste de ses champs natifs, taxonomies et champs ACF, à cocher et à glisser dans l'ordre voulu des colonnes. Le bouton d'enregistrement stocke la sélection et l'ordre dans l'option `cpt_exporter_settings`. |
| `cpt-exporter-order.php` | Liste unique et ordonnée des éléments d'un type, et glisser-déposer de la page de réglages (souris ou flèches du clavier). |
| `cpt-exporter-button.php` | Sur la liste d'un type dont au moins un champ est coché, ajoute un menu déroulant **Export** (CSV, XLSX) dans la barre d'outils, pour les utilisateurs ayant la capacité `export`. |
| `cpt-exporter-export.php` | Traite les liens du menu : vérifications, colonnes dans l'ordre de la page de réglages, lignes des contenus publiés, conversion des valeurs en texte. |
| `cpt-exporter-csv.php` | Écrit le CSV : UTF-8 avec BOM, point-virgule, fins de ligne CRLF, protection contre l'injection de formules. |
| `cpt-exporter-xlsx.php` | Écrit le XLSX avec `ZipArchive`, sans librairie : en-tête en gras et figé, nombres typés. |

Traductions dans `languages/` : français (`fr_FR`) et modèle `.pot` pour les autres langues.

## Usage

1. **Réglages > CPT Exporter** : cocher un type, puis les champs natifs, taxonomies et champs ACF à exporter, et les glisser dans l'ordre des colonnes voulu par la poignée (ou, poignée sélectionnée au clavier, avec ↑ et ↓). Enregistrer.
2. Sur la liste de ce type, **Export > CSV** ou **Export > XLSX** télécharge `{type}-{AAAA-MM-JJ}.csv` ou `.xlsx`.

Une ligne par contenu publié, numérotée à partir de 1 dans la première colonne (`#`), puis une colonne par champ coché, dans l'ordre de la liste de la page de réglages.

## Notes techniques

- **Types de contenu proposés.** Les articles, puis les types non natifs déclarés avec `can_export` (même requête qu'Outils > Exporter). Les types internes d'Elementor (`elementor_`, `e-`, `e_`) et d'ACF (`acf-`) sont exclus par préfixe : aucun réglage ne distingue ceux d'Elementor d'un CPT maison, `elementor_library` étant public et doté d'un menu. Limite connue : un CPT maison dont le nom commence par `e-` ou `e_` serait masqué lui aussi.
- **Ce qui est proposé pour chaque type.** Champs natifs : les `supports` qui portent une donnée (`title`, `editor`, `excerpt`, `thumbnail`, `author`, `page-attributes`), pas les comportements (`revisions`, `autosave`, `elementor`…). Taxonomies : celles qui ont une interface (`show_ui`), ce qui écarte `post_format` et les taxonomies internes de Polylang. Champs ACF : ceux des groupes assignés au type, hors champs de mise en page (`tab`, `message`, `accordion`), identifiés par leur clé `field_…` qui survit à un renommage. Ils sont nommés, sur la page comme dans l'en-tête des exports, par le libellé défini dans ACF (« Champ de test »), ou par le nom du champ (`champ_de_test`) si le libellé est vide.
- **Enregistrement.** Une seule option, `cpt_exporter_settings`, via l'API Settings (`options.php`, nonce et capacité `manage_options` gérés par WordPress). Le nettoyage ne garde que les valeurs que la page propose au moment de l'enregistrement : un type, une taxonomie ou un champ disparu est retiré à la sauvegarde suivante. Décocher un type conserve ses sous-sélections et leur ordre : ils sont masqués, pas effacés, et réapparaissent si on le recoche. L'export ne lit que les types dont `enabled` vaut `true`.
- **Ordre.** `order` liste toutes les lignes du type, cochées ou non, sous la forme `groupe:clé` (`fields:title`, `taxonomies:category`, `acf:field_…`). Chaque ligne porte un champ caché dans le formulaire : l'ordre enregistré est simplement celui du DOM au moment de l'envoi, sans JavaScript de sérialisation. À l'enregistrement, les doublons et les lignes inconnues sont retirés. À la lecture, une ligne absente de `order` (nouveau champ ACF, réglages antérieurs à cette option) se place à la fin, dans l'ordre par défaut : champs natifs, taxonomies, champs ACF.
- **Glisser-déposer.** jQuery UI Sortable, fourni par WordPress, chargé uniquement sur la page de réglages avec un script en ligne. La poignée est un `<button>` pour être atteignable au clavier : il faut vider l'option `cancel` de Sortable, qui refuse sinon de démarrer un glisser depuis un bouton. Au clavier, ↑ et ↓ déplacent la ligne de la poignée ciblée, qui garde le focus. Un clic souris ne donne pas le focus à la poignée (Sortable annule le `mousedown`) : le parcours clavier passe par Tab.
- **Affichage conditionnel.** Les sous-options d'un type sont masquées tant qu'il n'est pas coché, par une seule règle CSS `:has()` écrite dans la page : ni JavaScript, ni fichier de style. Structure enregistrée :

  ```php
  ['projet' => ['enabled' => true, 'fields' => ['excerpt'], 'taxonomies' => ['category'], 'acf' => ['field_…'], 'order' => ['acf:field_…', 'fields:excerpt', 'taxonomies:category']]]
  ```
- **Bouton d'export.** Inséré par `manage_posts_extra_tablenav`, le seul point d'accroche PHP de la liste : WordPress écrit le bouton « Ajouter » du titre en dur. Barre du haut uniquement, à droite de « Filtrer », et affiché même quand la liste est vide. Masqué tant qu'aucun champ n'est coché pour le type. Le conteneur n'a pas la classe `actions`, que WordPress masque sous 782 px. Menu natif `<details>` sans JavaScript, positionné en absolu par une règle CSS écrite dans la page ; il se referme en recliquant sur « Export », pas au clic extérieur. Chaque format est un lien vers `admin-post.php?action=cpt_exporter_export` signé par un nonce.
- **Traductions.** Chaînes sources en anglais, domaine `plugin-cpt-exporter`, chargées depuis `languages/` par `load_plugin_textdomain()` sur `init` : hors wordpress.org, WordPress ne cherche sinon que dans `wp-content/languages`. La langue suivie est celle de l'utilisateur, y compris pour les en-têtes des fichiers exportés (« Titre », « Contenu », « Ordre »…). Libellés venant d'ailleurs (types, taxonomies, champs ACF) : tels que déclarés. Trois fichiers : `plugin-cpt-exporter.pot` (modèle), `plugin-cpt-exporter-fr_FR.po` (source éditable), `plugin-cpt-exporter-fr_FR.l10n.php` (format compilé lu par WordPress 6.5+). Pas de `.mo` : WordPress lit le `.l10n.php` en priorité, un `.mo` régénéré par Poedit serait donc ignoré tant que le `.l10n.php` n'est pas recompilé. Après une modification du `.po`, et depuis le dossier du plugin :

  ```bash
  wp i18n make-php languages
  ```

  Après l'ajout ou la modification de chaînes dans le code :

  ```bash
  wp i18n make-pot . languages/plugin-cpt-exporter.pot --domain=plugin-cpt-exporter --exclude=languages --headers='{"Report-Msgid-Bugs-To":"https://github.com/WarLay0/plugin-cpt-exporter/issues"}'
  wp i18n update-po languages/plugin-cpt-exporter.pot
  wp i18n make-php languages
  ```
- **Sécurité de l'export.** Nonce invalide ou expiré : 403. Utilisateur sans la capacité `export` : 403. Format inconnu, type non coché ou sans champ coché : 400 avec un message qui renvoie vers les réglages.
- **Lignes.** Contenus publiés uniquement, quels que soient les filtres de la liste, du plus récent au plus ancien. L'ID départage les dates identiques. Lus par lots de 200, en vidant le cache mémoire entre deux lots quand le cache objet le permet, pour garder une mémoire stable sur les gros volumes.
- **Colonnes.** D'abord `#`, le numéro de ligne à partir de 1, continu d'un lot à l'autre ; ce n'est pas l'ID WordPress. Il est ajouté aux lignes, pas aux colonnes de réglage : le bouton reste masqué tant qu'aucun champ n'est coché. Ensuite, les lignes cochées dans l'ordre de la liste de la page de réglages, groupes mélangés (un champ ACF peut précéder le titre). `page-attributes` donne deux colonnes : **Parent** (titre du parent) et **Order** (`menu_order`).
- **Valeurs.** Titre, contenu, extrait et termes passent en texte brut : shortcodes, balises HTML et commentaires de blocs retirés, entités décodées, lignes rognées, au plus une ligne vide d'affilée. Image mise en avant : URL de la taille `full`. Auteur : nom affiché. Termes : noms séparés par des virgules. Champs ACF : valeur formatée par ACF, puis contenus, termes et utilisateurs par leur nom, images, fichiers et liens par leur URL, choix « valeur et libellé » par le libellé, listes jointes par des virgules, groupes et lignes de répéteur en JSON, booléens en `1`/`0`.
- **CSV.** BOM UTF-8 pour les accents dans Excel, point-virgule pour qu'Excel en français ouvre directement en colonnes, guillemets RFC 4180, CRLF. Les caractères de contrôle invisibles sont retirés, tabulations et retours à la ligne conservés. Injection de formules (OWASP) : une cellule commençant par `=`, `+`, `-`, `@`, une tabulation ou un retour chariot est préfixée d'une apostrophe, visible à l'ouverture (y compris pour un nombre négatif).
- **XLSX.** Paquet Office Open XML minimal écrit à la main : `[Content_Types].xml`, relations, classeur, styles et une feuille. La feuille est écrite ligne à ligne dans un fichier temporaire, zippée, envoyée puis supprimée. Textes en chaînes en ligne (`inlineStr`), jamais interprétés comme formules, donc sans apostrophe. Nombres typés seulement s'ils sont « propres » : pas de zéro initial (téléphones, codes postaux), pas de zéro décimal final, au plus 15 chiffres (précision d'Excel). Octets UTF-8 invalides remplacés par U+FFFD, caractères interdits en XML 1.0 retirés, cellules plafonnées à 32 767 caractères (limite d'Excel). Nom de feuille : libellé du type, sans `[ ] : * ? / \`, 31 caractères au plus, sans apostrophe en bordure, « Export » par défaut. En-tête en gras et figé, colonnes larges de 30.

## Tests

Vérification locale (WP 7.1 / PHP 8.5.10 / ACF 6.8.10), sur des contenus temporaires piégeux (accents, emoji, guillemets, point-virgule, caractère de contrôle, blocs et shortcode, formules, brouillons) et 205 contenus générés à la même date :

- fonctions de conversion et d'écriture, cas limites compris (colonnes `ZZ`/`AAA`, UTF-8 invalide, 40 000 caractères, nom de feuille) ;
- fichiers relus avec openpyxl : archive et XML valides, en-tête gras et figé, ordre des colonnes et des lignes, brouillons exclus, aucun doublon entre les lots, nombres et textes typés, mêmes lignes en CSV et en XLSX ;
- rendu des deux XLSX par Quick Look (macOS) ;
- téléchargements réels depuis la liste : en-têtes HTTP, BOM, signature ZIP, présence du menu selon les réglages, refus en cas de nonce invalide ou absent, de format inconnu et de type non coché ou inexistant ;
- colonnes Parent et Order de `page-attributes` ;
- numérotation `#` : en-tête, suite continue de 1 à 208 au-delà d'un lot de 200, cellules numériques en XLSX ;
- ordre des colonnes : liste unifiée (ordre par défaut, ordre enregistré, lignes inconnues ignorées, nouvelles lignes en fin), nettoyage à l'enregistrement (doublons, lignes inconnues, second passage identique), compatibilité avec des réglages sans `order`, position des deux colonnes de `page-attributes` ; dans le navigateur, glisser à la souris, ↑/↓ au clavier avec focus conservé, ordre et cases conservés après enregistrement et rechargement, en-tête du CSV dans l'ordre glissé ;
- traduction et libellés ACF : `.po` validé par `msgfmt -c` (21 messages), chargement depuis `languages/`, chaînes toutes traduites, `Déplacer %s`, typographie française, libellé ACF utilisé et repli sur le nom si le libellé est vide ou fait d'espaces, en-têtes d'export en français ; dans l'admin avec un profil en français, page de réglages, bouton « Exporter », en-tête du CSV et message d'erreur traduits, sans avertissement de chargement trop précoce dans `debug.log`.

Dernier passage : 68/68 sur `feat/cpt-exporter-export`, puis 7/7 sur la numérotation, puis 16/16 sur l'ordre des colonnes, puis 14/14 sur la traduction et les libellés ACF (`feat/cpt-exporter-order`).

## Installation

```bash
cd wp-content/plugins/   # ou web/app/plugins/ en Bedrock
git clone https://github.com/WarLay0/plugin-cpt-exporter.git
wp plugin activate plugin-cpt-exporter
```
