# openfoodfacts-php — package PHP officiel pour Open Food Facts

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-dark.png?refresh_github_cache=1">
  <source media="(prefers-color-scheme: light)" srcset="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-light.png?refresh_github_cache=1">
  <img height="48" src="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-light.svg" alt="Open Food Facts">
</picture>

🇫🇷 Version française · 🇬🇧 [English version](README.md)

Wrapper PHP officiel pour [Open Food Facts](https://openfoodfacts.org/), la base de données ouverte sur les produits alimentaires.

La branche de développement migre la lecture, l’écriture et l’upload d’images produit vers l'**API Open Food Facts v3.6** (schéma produit 1004) et corrige plusieurs bugs de robustesse de la version v0.4.0, qui utilisait l'API v0 legacy.

📖 **Documentation complète : [doc/home.fr.md](doc/home.fr.md)** — ce README présente la migration depuis v0.4.0 et les principales évolutions du SDK.

La migration v3 concerne les produits et leurs images. `Api::search()` utilise encore `cgi/search.pl` ; `getByFacets()` et les accesseurs de facettes utilisent les URL `.json` du site. Il n’existe pas de recherche v3 : voir la [matrice officielle des API](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/). Le client `SearchApi` expose séparément Search-a-licious.

## Installation

Avec Composer :

```bash
composer require openfoodfacts/openfoodfacts-php
```

Cette documentation décrit les évolutions de la branche de développement vers l'API v3.6. La commande ci-dessus installe la dernière version stable publiée sur Packagist, qui peut ne pas encore inclure ces évolutions. Le nom du package reste `openfoodfacts/openfoodfacts-php` et les namespaces PHP restent `OpenFoodFacts\`.

## Usage rapide

```php
// Le user agent (1er argument) est obligatoire — décrivez votre application
$api = new OpenFoodFacts\Api('MonApp - Web - 1.0 - https://example.org', 'food', 'fr');

// Lecture via l'API v3.6 : filtrez les champs (recommandé) et localisez la réponse
$product = $api->getProduct('3057640385148', ['product_name', 'nutriscore_grade'], 'fr');
echo $product->product_name;

// Écriture structurée (PATCH v3) et upload d'image (POST v3) — credentials requis
$api->authentification('utilisateur', 'mot-de-passe');
$api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic', 'categories_tags' => ['en:waters']]);
$api->uploadImage('3057640385148', 'front', '/chemin/image.jpg', 'fr');
```

## Migration depuis v0.4.0

### 1. Migration vers l'API v3.6

Réf. : [changelog API et schéma produit](https://openfoodfacts.github.io/openfoodfacts-server/api/ref-api-and-product-schema-change-log/)

| Fonction | v0.4.0 | Branche de développement (API v3.6) |
|---|---|---|
| Lecture produit | `GET /api/v0/product/{code}` | `GET /api/v3.6/product/{code}` (schéma produit 1004 épinglé) |
| Paramètres de lecture | aucun | `fields`, `lc`, `cc`, `tags_lc` (optionnels, rétrocompatibles) |
| Écriture produit | `cgi/product_jqm2.pl` (formulaire legacy) | nouvelle méthode `updateProduct()` : `PATCH /api/v3.6/product/{code}` en JSON structuré (champs par langue, tags, packagings, sélection d'images) |
| Upload d'image | `cgi/product_image_upload.pl` (multipart, food uniquement) | `POST /api/v3.6/product/{code}/images` (base64 + structure `selected` par champ/langue, tous les flavors) |
| Enveloppe de réponse | `status` 0/1 | enveloppe v3 (`status`, `result`, `errors[]`, `warnings[]`) avec messages d'erreur lisibles extraits de `errors[]` |
| Écriture partiellement rejetée | non détectable | `success_with_errors` → `ProductUpdateException` (l'enveloppe complète reste accessible via `getResponse()`) ; `success_with_warnings` journalisé via le logger |
| Produit d'un autre type (cross-flavor) | non géré | paramètre `product_type` de `getProduct()` (ex. `'all'`) : le serveur redirige vers le bon flavor et le document retourné correspond à ce flavor (ex. `BeautyDocument`) |
| Redirections | `strict = false` (un PATCH/POST redirigé en 301/302 serait rétrogradé en GET sans corps) | mode `strict` : la méthode et le corps sont préservés sur les redirections |

Détails d'implémentation :

- `Api::API_VERSION = '3.6'` (constante publique) : toutes les routes produit sont versionnées, la structure de réponse reste stable même quand le serveur évolue.
- `addNewProduct()` (cgi legacy) est conservée mais **dépréciée** au profit de `updateProduct()`.
- `Document::__get` renvoie `null` pour un champ absent (au lieu d'un warning PHP) : indispensable avec la v3.6, qui supprime les champs `*_hierarchy` et `*_lc` au profit de `tags_sources`, et depuis la v3.1 renomme `ecoscore_*` en `environmental_score_*`.

### 2. Corrections de bugs de v0.4.0

- **Les réponses POST ne sont plus mises en cache** : dans v0.4.0, un second envoi identique (`addNewProduct`, `uploadImage`) dans la durée de vie du cache ne faisait jamais la requête — une écriture silencieusement perdue. La clé de cache entrait de plus en collision pour tous les uploads (ressource non sérialisable par `json_encode`).
- **TTL sur le cache de lecture (1 h)** : v0.4.0 cachait les fiches produit sans expiration.
- **Décodage JSON strict** (`JSON_THROW_ON_ERROR`) et **vérification du statut HTTP** : une page HTML d'erreur du serveur provoquait un `TypeError` dans v0.4.0.
- **Validation du code-barres** (chiffres uniquement) et encodage URL : plus d'injection possible de segments d'URL via `getProduct()`.
- `404` → `ProductNotFoundException`, credentials manquants → `MissingCredentialsException`, code-barres invalide → `InvalidBarcodeException` (étend `InvalidParameterException`, valeur rejetée accessible via `getBarcode()`).
- **`activeTestMode()` distingue le htaccess du staging des identifiants du contributeur** : `off`/`off` est la protection htaccess (HTTP Basic) du serveur de test `.net`, pas un compte contributeur. Le SDK l'envoie dans l'en-tête `Authorization`, tandis que les identifiants du contributeur — fournis via `authentification()` — partent dans le corps des écritures v3 (`user_id`/`password`). La version v0.4.0 confondait les deux : `activeTestMode()` écrasait les identifiants du contributeur avec `off`/`off`, faisant échouer toute écriture sur le staging.

### 3. Documentation et exemples réparés

- Les exemples du README de v0.4.0 ne compilaient plus (le constructeur exige `$userAgent` en premier argument depuis la 0.4.0) ; `cached_example.php` passait en plus un `int` à `getProduct(string)`.
- Le badge Travis CI mort a été retiré.

### 4. Tests

- Nouvelle suite unitaire `tests/Unit/OpenFoodFacts/ApiV3Test.php` (sur `MockHandler` Guzzle) : URL versionnée, enveloppe v3, 404, erreurs lisibles, corps PATCH, payload base64 — sans dépendre de l'API live.
- Tests d'intégration mis en cohérence (la restriction « upload food uniquement » n'existe plus en v3).

## Compatibilité

- PHP **8.1 → 8.5** (aucune syntaxe au-delà de 8.1 ; testé notamment sous 8.3).
- Dépendances inchangées : Guzzle 7, PSR-3, PSR-16.
- API publique rétrocompatible, avec les changements suivants : `uploadImage()` limite les fichiers à 10 Mio avant encodage, retourne désormais l'enveloppe v3 et fonctionne pour tous les flavors ; les codes-barres non numériques sont rejetés ; `activeTestMode()` ne pose plus `off`/`off` que comme htaccess du `.net` — pour écrire sur le staging, fournissez un compte contributeur via `authentification()`.
- Les nouvelles exceptions (`InvalidParameterException`, `UnknownException`, `ProductUpdateException`) étendent `BadRequestException` : un `catch (BadRequestException)` écrit avec v0.4.0 continue de les attraper.

## Licence et réutilisation

Le code est sous licence [MIT](LICENSE).

- Si vous utilisez ce SDK, vous pouvez proposer une PR pour documenter votre application et sa réutilisation des données dans ce dépôt.
- Respectez la licence [OdBL](https://opendatacommons.org/licenses/odbl/summary/) des données Open Food Facts : mentionnez la source, évitez d'y combiner des données non libres que vous ne pouvez pas légalement publier en données ouvertes et contribuez en retour les produits que vous ajoutez avec ce SDK.
- Contactez l'équipe à [reuse@openfoodfacts.org](mailto:reuse@openfoodfacts.org).
- Vous pouvez aussi [présenter votre réutilisation](https://forms.gle/hwaeqBfs8ywwhbTg8) à la communauté et proposer que votre application soit mise en avant. Cette démarche est facultative.

## Auteurs

- [Roberto Moreno](https://rampmaster.org/) — développeur du wrapper.
- [Colin Benoit](https://github.com/Benoit382) — développeur PHP.
- Les [contributeurs du projet](https://github.com/openfoodfacts/openfoodfacts-php/graphs/contributors) et la communauté Open Food Facts.
