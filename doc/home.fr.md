# Documentation — openfoodfacts-php (API v3.6)

🇫🇷 Version française · 🇬🇧 [English version](home.md)

Wrapper PHP pour les API [Open Food Facts](https://world.openfoodfacts.org/), [Open Beauty Facts](https://world.openbeautyfacts.org/), [Open Pet Food Facts](https://world.openpetfoodfacts.org/) et [Open Products Facts](https://world.openproductsfacts.org/).

La branche de développement utilise l'**API v3.6** (schéma produit 1004) pour la lecture, l'écriture et l'upload d'images produit. La migration depuis v0.4.0 est décrite dans le [README](../README.fr.md).

## Sommaire

- [Installation](#installation)
- [Le client `Api`](#le-client-api)
  - [Constructeur](#constructeur)
  - [Lire un produit — `getProduct()`](#lire-un-produit--getproduct)
  - [Écrire un produit — `updateProduct()`](#écrire-un-produit--updateproduct)
  - [Uploader une image — `uploadImage()`](#uploader-une-image--uploadimage)
  - [Facettes — `getBrands()`, `getCategories()`, …](#facettes--getbrands-getcategories-)
  - [Recherche legacy — `search()` et `getByFacets()`](#recherche-legacy--search-et-getbyfacets)
  - [Export de données — `downloadData()`](#export-de-données--downloaddata)
  - [Serveur de test — `activeTestMode()`](#serveur-de-test--activetestmode)
- [Le client `SearchApi` (Search-a-licious)](#le-client-searchapi-search-a-licious)
- [Les modèles](#les-modèles)
- [Les exceptions](#les-exceptions)
- [Cache, logs et client HTTP](#cache-logs-et-client-http)
- [Lancer les tests](#lancer-les-tests)

## Installation

Avec Composer :

```bash
composer require openfoodfacts/openfoodfacts-php
```

Cette documentation décrit les évolutions de la branche de développement vers l'API v3.6. La commande ci-dessus installe la dernière version stable publiée sur Packagist, qui peut ne pas encore inclure ces évolutions. Le nom du package reste `openfoodfacts/openfoodfacts-php` et les namespaces PHP restent `OpenFoodFacts\`.

Prérequis : PHP ≥ 8.1 (testé jusqu'à 8.5), extension `json`.

## Le client `Api`

### Constructeur

```php
$api = new OpenFoodFacts\Api(
    userAgent:      'MonApp - Web - 1.0 - https://example.org', // obligatoire
    currentAPI:     'food',   // 'food' | 'beauty' | 'pet' | 'product'
    geography:      'fr',     // 'world', code pays ('fr', 'de'…) ou 'fr-en'
    logger:         null,     // PSR-3 LoggerInterface (défaut : NullLogger)
    clientInterface: null,    // Guzzle ClientInterface (défaut : new Client())
    cacheInterface: null      // PSR-16 CacheInterface (défaut : pas de cache)
);
```

- **`userAgent`** (obligatoire) : identifie votre application auprès des serveurs OFF. Le SDK envoie l'en-tête `User-Agent: SDK PHP - {userAgent}`. Indiquez nom, version et un contact.
- **`currentAPI`** : la base de produits interrogée. Elle détermine le type de `Document` retourné par défaut (`FoodDocument`, `BeautyDocument`, `PetDocument`, `ProductDocument`) ; après une redirection vers un autre flavor, `getProduct()` utilise le flavor de destination.
- **`geography`** : sous-domaine appelé (`https://{geography}.openfoodfacts.org`) — filtre pays et langue d'interface.

La constante publique `Api::API_VERSION` (actuellement `'3.6'`) indique la version d'API — donc le schéma produit — demandée par toutes les routes produit.

### Lire un produit — `getProduct()`

```php
public function getProduct(
    string  $barcode,            // chiffres uniquement
    ?array  $fields = null,      // liste des champs à retourner (null = tous)
    ?string $lc = null,          // langue de localisation des champs
    ?string $cc = null,          // code pays
    ?string $tagsLc = null,      // langue des tags de taxonomie
    ?string $productType = null  // 'food'|'beauty'|'petfood'|'product'|'all'
): Document
```

```php
$product = $api->getProduct('3057640385148', ['product_name', 'nutriscore_grade'], 'fr');
echo $product->product_name;          // accès magique aux champs
$all = $product->getData();           // tableau complet (trié récursivement)
isset($product->brands);              // teste la présence d'un champ
```

Points importants :

- **Filtrez avec `$fields`** : une fiche complète dépasse souvent 100 Ko. Valeurs spéciales : `all`, `none`, `raw`, et les champs générés à demander explicitement comme `knowledge_panels` ou `attribute_groups`.
- **Champ absent → `null`** (pas de warning PHP). Le schéma évolue : depuis la v3.6, les champs `*_hierarchy` / `*_lc` des champs taxonomisés sont remplacés par `tags_sources` ; depuis la v3.1, `ecoscore_*` s'appelle `environmental_score_*`.
- **`$productType: 'all'`** : si le produit appartient à un autre flavor (ex. un cosmétique scanné via l'API food), le serveur redirige vers le bon serveur et la redirection est suivie automatiquement. Le document retourné correspond au flavor de destination (`BeautyDocument` pour un cosmétique), y compris lors d'une lecture depuis le cache. Sans ce paramètre, le produit est signalé introuvable.
- Produit inexistant → `ProductNotFoundException`.
- Les lectures sont mises en cache 1 h si un cache PSR-16 est fourni.

### Écrire un produit — `updateProduct()`

API WRITE structurée v3 (`PATCH /api/v3.6/product/{code}`). Crée le produit s'il n'existe pas. Nécessite un compte contributeur :

```php
$api->authentification('mon_user', 'mon_mot_de_passe');

$result = $api->updateProduct('3057640385148', [
    'product_name_fr' => 'Eau de Volvic',
    'categories_tags' => ['en:waters'],
    'packagings_add'  => [ /* composants d'emballage structurés */ ],
]);
// $result = enveloppe v3 : status, result, errors, warnings, product
```

Champs actuellement supportés par l'API v3 en écriture : champs par langue (`product_name_xx`, `ingredients_text_xx`…), champs de tags (`categories_tags`, `labels_tags`…), emballages (`packagings`, `packagings_add`, `packagings_complete`) et sélection d'images uploadées.

Gestion des statuts de l'enveloppe :

| `status` | Comportement |
|---|---|
| `success` | retourne l'enveloppe |
| `success_with_warnings` | retourne l'enveloppe, warnings journalisés via le logger |
| `success_with_errors` | lève `ProductUpdateException` (écriture **partiellement** appliquée — l'enveloppe reste accessible via `getResponse()`) |
| `failure` | lève `ProductUpdateException` |

La méthode legacy `addNewProduct(array $postData)` (cgi `product_jqm2.pl`) existe toujours mais est **dépréciée**.

### Uploader une image — `uploadImage()`

`POST /api/v3.6/product/{code}/images` — l'image part encodée en base64, et peut être **sélectionnée** dans la foulée pour un champ et une langue :

```php
$api->authentification('mon_user', 'mon_mot_de_passe');

// upload + sélection comme image "front" en français
$result = $api->uploadImage('3057640385148', 'front', '/chemin/photo.jpg', 'fr');

// upload brut, sans sélection
$result = $api->uploadImage('3057640385148', '', '/chemin/photo.jpg');
```

Champs de sélection valides : `front`, `ingredients`, `nutrition`, `packaging`. Formats : JPEG, PNG, GIF, HEIC. Limite du SDK : 10 Mio avant encodage (`Api::MAX_IMAGE_SIZE`). Les fichiers trop volumineux, vides ou non réguliers sont rejetés localement ; la lecture est bornée avant l’encodage base64. Fonctionne pour tous les flavors.

### Facettes — `getBrands()`, `getCategories()`, …

Des accesseurs magiques listent les valeurs d'une facette avec leur nombre de produits, sous forme de `Collection` :

```php
$brands = $api->getBrands();
echo $brands->searchCount();
foreach ($brands as $doc) { echo $doc->name; }
```

Facettes disponibles : `additives`, `allergens`, `brands`, `categories`, `countries`, `contributors`, `code`, `entry_dates`, `ingredients`, `label`, `languages`, `nutrition_grade`, `packaging`, `packaging_codes`, `purchase_places`, `photographer`, `informer`, `states`, `stores`, `traces` — soit `getAdditives()`, `getAllergens()`, etc. Une facette inconnue lève `BadRequestException`.

### Recherche legacy — `search()` et `getByFacets()`

```php
$collection = $api->search('volvic', page: 1, pageSize: 20);
$collection = $api->getByFacets(['brand' => 'volvic', 'country' => 'france'], page: 1);
```

Ces méthodes reposent sur l'ancien moteur (`cgi/search.pl` et les URL de facettes du site). Pour la recherche plein texte, **préférez [`SearchApi`](#le-client-searchapi-search-a-licious)**.

### Export de données — `downloadData()`

Télécharge un dump complet de la base vers un fichier local : `$api->downloadData('/tmp/dump.tar.gz', 'mongodb')` (types : `mongodb`, `csv`, `rdf`). Attention : plusieurs dizaines de Go pour le dump MongoDB.

### Serveur de test — `activeTestMode()`

```php
$api->activeTestMode();                        // food/world → https://world.openfoodfacts.net
$api->authentification('user_staging', 'pw');  // compte contributeur du staging, pour écrire
```

Le flavor et la géographie choisis au constructeur sont conservés (ex. `beauty`, `fr` → `https://fr.openbeautyfacts.net`).

`activeTestMode()` configure la protection htaccess (`off`/`off`, envoyée en HTTP Basic) du serveur de staging `.net`. C'est **distinct** du compte contributeur : pour les écritures, fournissez un compte via `authentification()`. Les données du staging sont réinitialisées régulièrement — utilisez-le pour tester `updateProduct()`/`uploadImage()` sans polluer la production.

## Le client `SearchApi` (Search-a-licious)

Client du moteur de recherche moderne (`https://search.openfoodfacts.org`), indépendant du client `Api` :

```php
$search = new OpenFoodFacts\SearchApi('MonApp - Web - 1.0 - https://example.org');

// recherche plein texte + syntaxe Lucene
$result = $search->search('categories_tags:"en:beverages" strawberry', langs: ['fr', 'en'], pageSize: 20);
echo $result->count;                       // nombre total
foreach ($result->listDocuments as $doc) { /* SearchDocument */ }

// document par identifiant (code-barres)
$doc = $search->getDocument('3057640385148');

// autocomplétion sur les taxonomies
$auto = $search->autocomplete('choco', ['category'], lang: 'fr', size: 10);
foreach ($auto->options as $option) { echo $option->text; }
```

- `search(?string $query, ?array $langs, ?int $pageSize, ?int $page, ?array $fields, ?string $sortBy, ?string $indexId): SearchResult` — `$query` supporte la [syntaxe Lucene](https://lucene.apache.org/core/3_6_0/queryparsersyntax.html) ; sans `$query`, un `$sortBy` est requis.
- `getDocument(string $identifier, ?string $indexId): SearchDocument` — introuvable → `ProductNotFoundException`.
- `autocomplete(string $query, array $taxonomyNames, ?string $lang, ?int $size, ?int $fuzziness, ?string $indexId): AutocompleteResult`.

## Les modèles

- **`Document`** (et ses spécialisations `FoodDocument`, `BeautyDocument`, `PetDocument`, `ProductDocument`, `SearchDocument`) : conteneur en lecture seule des données d'un produit. Accès magique (`$doc->product_name`), test de présence (`isset($doc->…)`), export trié (`$doc->getData()`). Champ absent → `null`.
- **`Collection`** : liste paginée de `Document`, itérable (`foreach`). Méthodes : `pageCount()` (éléments de la page), `searchCount()` (total de la recherche), `getPage()`, `getPageSize()`, `getSkip()`.
- **`Model\SearchResult`** : résultat de `SearchApi::search()` — propriétés publiques `count`, `isCountExact`, `page`, `pageSize`, `pageCount`, `listDocuments`, `aggregations`, `warning`, `took`, `timedOut`.
- **`Model\AutocompleteResult`** / **`Model\AutocompleteOption`** : options d'autocomplétion (`id`, `text`, `taxonomy_name`).

## Les exceptions

Toutes sous `OpenFoodFacts\Exception`, avec une base commune `ApiException` (qui étend `\Exception`). `UnknownException` conserve son parent `BadRequestException` pour préserver les anciens `catch` ; ce parent historique couvre aussi les erreurs réseau et les réponses inattendues. Les exceptions du transport Guzzle de `SearchApi` et celles d’un cache injecté peuvent encore remonter directement :

```text
ApiException
├── BadRequestException            erreur générique de requête / réponse API
│   ├── InvalidParameterException  paramètre invalide
│   │   ├── InvalidBarcodeException  code-barres vide ou contenant autre chose que des chiffres ASCII
│   │   └── MissingCredentialsException  écriture sans authentification()
│   ├── ProductUpdateException     écriture refusée ou partiellement appliquée
│   │                              → getResponse() = enveloppe v3 complète
│   └── UnknownException           réponse inattendue (non-JSON, statut inconnu)
├── NotFoundException              ressource introuvable
│   └── ProductNotFoundException   produit introuvable (getProduct, SearchApi::getDocument)
└── ValidationException            422 du moteur de recherche (SearchApi)
```

`getProduct()`, `updateProduct()` et `uploadImage()` rejettent les codes-barres invalides avant toute requête HTTP. `InvalidBarcodeException::getBarcode()` retourne la valeur rejetée. Les zéros initiaux sont conservés ; les espaces et séparateurs ne sont pas supprimés. Les anciens `catch (InvalidParameterException)` restent compatibles.

Un `catch (BadRequestException $e)` attrape donc toutes les erreurs du client `Api` sauf `ProductNotFoundException`, à traiter séparément :

```php
use OpenFoodFacts\Exception\InvalidBarcodeException;

try {
    $product = $api->getProduct($barcode);
} catch (InvalidBarcodeException $e) {
    // demander un code-barres composé uniquement de chiffres
} catch (ProductNotFoundException) {
    // produit inconnu
} catch (BadRequestException $e) {
    // erreur réseau, réponse invalide, paramètre incorrect…
}
```

## Cache, logs et client HTTP

Les trois dépendances sont injectables au constructeur (voir [`examples/01-basic_api_usage/cached_example.php`](../examples/01-basic_api_usage/cached_example.php)) :

- **Cache PSR-16** : les lectures (`getProduct`, facettes) sont cachées 1 h. Les écritures ne sont jamais cachées. Les clés incluent l’URL complète : flavor, géographie et environnement `.org`/`.net` sont séparés ; en v3, les paramètres de lecture sont également inclus.
- **Logger PSR-3** : chaque requête est tracée en `info` ; les échecs réseau et les `success_with_warnings` en `warning`.
- **Client Guzzle** : injectez le vôtre pour régler timeouts, proxy ou middlewares. Le SDK impose son `User-Agent`, désactive les erreurs HTTP Guzzle sur les routes v3 (statuts gérés par le SDK) et force des redirections **strictes** (un `PATCH`/`POST` redirigé conserve méthode et corps).

## Lancer les tests

```bash
composer install
vendor/bin/phpunit --testsuite "Unit test"      # unitaires (MockHandler, sans réseau)
vendor/bin/phpunit --testsuite "Integration test"  # frappe l'API réelle
vendor/bin/phpstan --memory-limit=512M           # analyse statique (niveau 8)
```

La CI GitHub Actions rejoue l'ensemble sur PHP 8.1 → 8.5.
