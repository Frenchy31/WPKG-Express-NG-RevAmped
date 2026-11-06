# Rapport de tests

Campagne du 2026-11-06 : portage de WPKG Express NG de PHP 5.6 / CakePHP 1.3 vers PHP 8.5 / CakePHP 5.4.

## 1. Synthèse

| Domaine | Résultat |
|---|---|
| PHPUnit | 89 tests, 294 assertions, aucun échec, aucune dépréciation |
| Tests unitaires de `wpkg.js` | 47 tests : 46 réussis, 1 `todo` documentant un bug de la version 1.3.1 |
| PSR-12 | 0 erreur, 20 avertissements de longueur de ligne dans les tests |
| Flux XML par rapport à l'application d'origine | Identiques, hors écarts volontaires (section 4) |
| Migration MySQL 5.5 vers 26.10 | Répétée sur données de test, deux cas d'encodage |
| Bout en bout sur Windows 10 | Installation, mise à niveau, désinstallation validées |
| Scénarios de panne | 8 scénarios, un seul destructeur (section 8) |
| Base de production | **Non testée** |

Environnement : PHP 8.5.12, CakePHP 5.4.4, PHPUnit 13.4.6, MySQL 26.10.0 (`mysql:latest`), Apache 2.4.69, `wpkg.js` 1.3.1, Windows 10 22H2 (build 19045) sous QEMU.

Les deux jobs de `.gitlab-ci.yml` ont été rejoués localement depuis un clone neuf du dépôt, avec les commandes extraites du fichier : `php` (image `php:8.5.12-cli`, service `mysql:latest`) et `wpkg-js` (image `node:22-alpine`) réussissent.

## 2. Tests automatisés PHP

Exécution :

```shell
docker compose --profile tools run --rm tools vendor/bin/phpunit
```

| Classe | Tests | Couverture |
|---|---:|---|
| `Feed\GoldenFeedTest` | 23 | Flux comparés aux captures d'origine, échappement, commentaires, validation XSD |
| `Controller\PackagesControllerTest` | 16 | Paquets, actions, codes de sortie, arbre des vérifications, échappement HTML |
| `Import\WpkgImporterTest` | 9 | Aller-retour export/import, namespace, révisions, dépendances, annulation |
| `Controller\HostsControllerTest` | 8 | Hôtes, regex, ordre, profils additionnels, suppression |
| `Controller\AuthControllerTest` | 6 | Accès anonyme, connexion, CSRF |
| `Feed\FeedComparatorTest` | 6 | Détection des liens perdus, révisions, ordre, XML invalide |
| `Controller\ProfilesControllerTest` | 5 | Profils, protection du profil principal, associations |
| `Controller\VariablesAndHomeControllerTest` | 5 | Variables, tableau de bord, recherche, administration |
| `Middleware\HostHeaderMiddlewareTest` | 4 | Noms d'hôte autorisés et refusés |
| `Command\CompareFeedsCommandTest` | 3 | Contrôle de bascule |
| `Controller\FeedsControllerTest` | 3 | URL des flux, type de contenu, protection Basic |
| `Command\HashPasswordCommandTest` | 1 | Hash Argon2id |

Vérification des tests : une mutation de la `revision` dans `PackagesFeed` provoque 4 échecs, ce qui confirme que la comparaison détecte un écart réel.

## 3. Tests unitaires de `wpkg.js`

Exécution, sous Node.js 20 ou plus, sans dépendance :

```shell
cd wpkg-client && npm test
```

Le client est un script JScript pour Windows Script Host. `tests/harness.js` charge le fichier livré, non modifié, dans une VM Node.js avec des bouchons de `WScript`, `ActiveXObject`, du registre, des fichiers et de l'environnement. Seul l'appel de `main()` au chargement est retiré.

| Fichier | Tests | Couverture |
|---|---:|---|
| `checks.test.js` | 18 | Vérifications `logical`, `file`, `registry`, `host`, types inconnus |
| `packages.test.js` | 12 | Attributs des paquets, tri par priorité, listes, dates |
| `hosts.test.js` | 7 | Correspondance d'hôtes, regex, ordre du flux, `applyMultiple` |
| `removal.test.js` | 6 | Décision de désinstallation |
| `versions.test.js` | 4 | Comparaison de versions |

Vérification des tests : inverser le test `!found` de `getPackagesRemoved()` provoque 6 échecs. Le fichier a été restauré ensuite, somme de contrôle vérifiée.

Comportements établis par ces tests :

- `atleast` sans valeur lève `requires a value`, ce qui justifie l'obligation côté application.
- Un profil valide sans paquets marque tout pour suppression.
- Renommer un paquet revient à le supprimer.
- La première entrée du flux gagne, même si une entrée suivante porte le nom exact du poste.

**Bug réel dans `wpkg.js` 1.3.1 :** `trim()` n'enlève les espaces que d'un seul côté, car son expression régulière n'a pas le drapeau `g`. Le test correspondant est marqué `todo`. Le fichier n'a pas été modifié.

## 4. Comparaison avec l'application d'origine

Une pile PHP 5.6 / MySQL 5.5 figée (`legacy/`) a servi de référence. Un jeu de données conçu pour couvrir toutes les branches des gabarits a été capturé en trois configurations : par défaut, sans mise en forme, avec éléments désactivés. Il contient 11 paquets, 29 vérifications, les six types de vérification et les pièges d'échappement (`&`, `<`, `>`, guillemets, apostrophes, `--`, accents).

La comparaison porte sur ce que voit un client : ordre des éléments et valeurs des attributs. Les écarts acceptés sont listés dans `FeedComparator`.

| Vérification | Résultat |
|---|---|
| `hosts.xml`, HTTP réel, ancienne et nouvelle application | Identique, 10 éléments |
| `profiles.xml` | Identique, 15 éléments |
| `packages.xml` | Identique, 69 éléments, le fichier d'origine étant mal formé |
| Vues unitaires `/{ressource}/view/{id}.xml` | Identiques |
| Validation par les XSD de `wpkg-js` 1.4.0 | Conforme, racine namespacée |
| Export, import sur base vide, nouvel export | Identique |
| Reconstruction complète de la pile de référence | Captures identiques octet par octet |

Défauts de l'application d'origine confirmés à l'exécution :

| Défaut | Constat |
|---|---|
| `&` brut dans une commande | `xmllint` : `xmlParseEntityRef: no name` |
| `value` perdu sur `atleast` et `atmost` | Attribut absent du flux, alors que `wpkg.js:1752` l'exige |
| « Protect XML » sans effet | `protectxml=1`, requête anonyme servie en 200 |
| Élément désactivé servi vide | Document réduit à l'en-tête XML |

## 5. Recette HTTP sur Apache

| Vérification | Résultat |
|---|---|
| Migrations sur MySQL 26.10 | Réussies via PDO, `caching_sha2_password` compris |
| Mauvais mot de passe | 200, message d'erreur, pas de session |
| POST sans jeton CSRF | 403 |
| Connexion valide | 302 vers `/` |
| Pages principales après connexion | 200 |
| Flux protégé sans identifiants | 401, `WWW-Authenticate` présent |
| Flux protégé, mauvais mot de passe | 401 |
| Flux protégé, identifiants valides | 200 |
| `Host` autorisé | 200 |
| `Host: evil.example` | 400 |
| Envoi de fichiers par `/admin` | Import de 2 paquets, 1 profil, 1 hôte |

## 6. Migration de données

Dump depuis MySQL 5.5, restauration sur MySQL 26.10, migration `ConvertToUtf8mb4`, puis `bin/cake compare_feeds` contre les flux d'origine.

| Cas | Export | Résultat |
|---|---|---|
| Connexion `utf8`, tables latin1 | `--default-character-set=utf8` | Identique |
| Connexion latin1 sans clé `encoding` | `--default-character-set=utf8` | **Rejeté** : `Éditeur été` devient `Ã‰diteur Ã©tÃ©`, 6 différences détectées |
| Connexion latin1 sans clé `encoding` | `--default-character-set=latin1 --skip-set-charset` | Identique |

## 7. Bout en bout sur Windows

Client réel `cscript wpkg.js` sur la VM `wpkg-test-client`, flux servis par l'application en HTTP. Données envoyées par le formulaire `/admin`, modifications faites par l'interface web.

| Scénario | Résultat |
|---|---|
| `/synchronize /dryrun /debug` | 2 hôtes, 1 profil, 2 paquets lus, profil appliqué, rien d'exécuté |
| Installation | Fichier témoin écrit par une commande contenant `&`, 7-Zip réel téléchargé et installé |
| Vérification `logical atleast` | Évaluée sans erreur |
| Second passage | Aucune action |
| Hausse de `revision` et action `upgrade` ajoutées dans l'interface | Action exécutée, révision mise à jour |
| Retrait de 7-Zip du profil dans l'interface | Paquet désinstallé, `7z.exe` absent |
| Remise du paquet dans le profil | Réinstallé |
| Restauration après perte des liens | Les deux paquets réinstallés |
| Dry-run avec `wpkg.js` 1.4.0 | 2 hôtes, 1 profil, 2 paquets lus, aucune suppression |
| Flux protégé, `http://USER:PASS@...` dans `config.xml` | 2 hôtes, 1 profil, 2 paquets lus |

Exécution réelle de `wpkg.js` sur la VM, en dry-run, sur des paquets déjà installés : les trois flux sont lus en HTTP, le profil est appliqué et rien n'est à supprimer.

![wpkg.js lit les flux de l'application](docs/screenshots/10-wpkg-dryrun.png)

## 8. Scénarios de panne

Deux paquets installés, `quitonerror=false`, `wpkg.js` 1.3.1.

| Scénario | HTTP | Réaction du client | Paquets |
|---|---|---|---|
| `packages.xml` en échec | 500 | `Found 0 package entries`, 0 à supprimer | Conservés |
| `profiles.xml` en échec | 500 | `No profiles found. Aborting` | Conservés |
| `hosts.xml` en échec | 500 | `No hosts found. Aborting` | Conservés |
| MySQL arrêté | 500 | `No hosts found. Aborting` | Conservés |
| `<wpkg/>` vide | 200 | 0 hôte, exécution en échec | Conservés |
| `<profiles/>` vide | 200 | 0 profil, exécution en échec | Conservés |
| Flux protégé sans identifiants | 401 | `Access is denied` (80070005) | Conservés |
| **Profil valide sans ses paquets** | 200 | `Number of packages to remove: 2` | **Désinstallés** |

Le dernier scénario justifie le contrôle `bin/cake compare_feeds` avant toute bascule.

## 9. Contrôle visuel

Pages ouvertes dans Edge sur la VM, version finale, avec le jeu de données E2E.

| Page | Capture |
|---|---|
| Connexion | ![Connexion](docs/screenshots/01-login.png) |
| Tableau de bord | ![Tableau de bord](docs/screenshots/02-summary.png) |
| Liste des paquets | ![Liste des paquets](docs/screenshots/03-packages.png) |
| Détail d'un paquet, arbre des vérifications | ![Détail d'un paquet](docs/screenshots/04-package.png) |
| Édition d'un paquet | ![Édition d'un paquet](docs/screenshots/05-package-edit.png) |
| Ajout d'une vérification `atleast` | ![Ajout d'une vérification](docs/screenshots/06-check-form.png) |
| Liste des hôtes | ![Liste des hôtes](docs/screenshots/07-hosts.png) |
| Paquets d'un profil | ![Paquets d'un profil](docs/screenshots/08-profile-packages.png) |
| Administration | ![Administration](docs/screenshots/09-admin.png) |

## 10. Anomalies trouvées pendant la campagne

Dans le code porté, toutes corrigées :

| Anomalie | Détectée par |
|---|---|
| `enabledList()` lié à l'alias `Profiles`, en erreur via `MainProfiles` | PHPUnit |
| Erreur d'unicité des codes de sortie sur un champ sans saisie | PHPUnit |
| Appels de behaviors dépréciés depuis CakePHP 5.3 | PHPUnit |
| Type de vérification perdu après une validation échouée | PHPUnit |
| `atleast` et `atmost` acceptés sans valeur | Contrôle visuel |
| Parent d'une vérification logique réinitialisé à la modification | Relecture |
| `HostHeaderMiddleware` refusant tout nom autre que celui de `APP_FULL_BASE_URL` | Relecture, avant déploiement |
| Enfants affichés avant leur parent dans l'arbre | Contrôle visuel |
| Liste `Parent` vide au lieu de `Root Node` | Contrôle visuel |
| Séparateurs de pagination isolés sur une seule page | Contrôle visuel |
| Cases à cocher mal alignées | Contrôle visuel |

Dans l'environnement de test :

| Anomalie | Résolution |
|---|---|
| CakePHP 1.3 en erreur fatale sans `date.timezone` | Option ajoutée dans l'image de référence |
| Volumes refusés par SELinux | Option `:z` sur les montages |
| `log_file_path` du `config.xml` de test mal échappé (`Z:wpkglogs`) | Non corrigé, repli sur le journal local du client |

## 11. Limites

- La base de production n'a pas été testée.
- HTTPS et `WPKG_FORCE_SSL` n'ont pas été éprouvés de bout en bout.
- `wpkg.js` 1.4.0 n'a été exécuté qu'en dry-run.
- Toutes les pages n'ont pas été contrôlées visuellement.
- Les codes de retour de `cscript` n'ont pas été exploités : le constat repose sur les journaux et l'état du poste.
- Les scénarios Windows ont été exécutés à la main sur une VM, ils ne sont pas automatisés.
