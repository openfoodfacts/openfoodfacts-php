# Exemples

🇫🇷 Version française · 🇬🇧 [English version](README.md)

| Exemple | Ce qu'il montre |
|---|---|
| [`00_flat_request`](00_flat_request) | Lecture d'un produit en HTTP brut, **sans le SDK** : URL v3.6, en-tête `User-Agent`, validation du code-barres, enveloppe de réponse v3 et échappement HTML. À lire pour voir ce que le wrapper prend en charge à votre place. |
| [`01-basic_api_usage`](01-basic_api_usage) | La même lecture via `OpenFoodFacts\Api`, avec logger PSR-3, client HTTP et cache PSR-16 injectés au constructeur. |

## Lancer les exemples

`00_flat_request` n'a aucune dépendance :

```sh
php -S 127.0.0.1:8000 -t examples/00_flat_request
# puis ouvrir http://127.0.0.1:8000/index.html
```

`01-basic_api_usage` utilise l'autoloader du dépôt (`composer install` à la racine) :

```sh
php examples/01-basic_api_usage/cached_example.php
```
