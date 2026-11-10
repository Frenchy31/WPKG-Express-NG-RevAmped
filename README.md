# WPKG Express NG

Interface web de [WPKG](https://wpkg.org), l'outil de déploiement de logiciels sur postes Windows.
L'application gère les hôtes, profils et paquets, et sert `hosts.xml`, `profiles.xml` et `packages.xml` au client `wpkg.js`.

Pile : PHP 8.5, CakePHP 5.4, MySQL, Apache, Docker Compose. Le code est dans `web/`.

## Démarrage

`./install.sh` installe tout depuis un clone neuf : `.env`, fichiers d'environnement des composants, mot de passe administrateur, images, conteneurs et tables. Il vérifie d'abord que Docker est accessible et que le port HTTP (`WPKG_HTTP_PORT`, 80 par défaut), les ports 139 et 445 si `samba` est actif, le sous-réseau `172.19.0.0/16` et les noms de conteneurs des composants actifs sont libres. Étapes manuelles équivalentes :

1. Créer `.env` à partir de `.env.example`, puis un fichier `services/<composant>/<composant>.env` à partir de chaque `services/<composant>/<composant>.env.example` des composants actifs.
2. Générer le hash du mot de passe administrateur, puis le coller dans `services/wpkg/wpkg.env` entre apostrophes :
   ```shell
   docker compose --profile tools run --rm --no-deps tools composer install --no-scripts
   docker compose --profile tools run --rm --no-deps tools bin/cake hash_password 'mot-de-passe'
   ```
3. Démarrer la pile et créer les tables :
   ```shell
   docker compose up -d --build wpkg mysql samba
   docker compose exec wpkg bin/cake migrations migrate
   ```
4. Ouvrir <http://localhost/> (ou le port choisi dans `WPKG_HTTP_PORT`).

L'utilisateur Unix doit appartenir au groupe `docker`. Sur Fedora, les volumes liés portent l'option `:z` à cause de SELinux.

## Services

Chaque service est un composant : un dossier de `services/` qui porte son fichier Compose, sa configuration et son modèle de variables `<composant>.env.example`.

| Composant | Service | Rôle | Accès |
|---|---|---|---|
| `wpkg` | `wpkg` | Application web | Port `WPKG_HTTP_PORT` (80 par défaut) |
| `mysql` | `mysql` | Base de données | Réseau interne |
| `samba` | `samba` | Partage `\\172.19.0.4\WPKG` | Ports 139 et 445 |
| `tools` | `tools` | Composer, migrations, PHPUnit (profil `tools`) | Sans objet |
| `windows-vm` | `wpkg-test-client` | VM Windows de test, désactivée par défaut | Ports 8006 et 3389 |

`legacy/` contient l'application PHP 5.6 d'origine et sa pile figée. Elle sert uniquement à capturer les flux de référence : ne jamais la déployer.

## Configuration

La liste des composants actifs est la variable `COMPOSE_FILE` de `.env` : le `compose.yaml` racine, puis un fichier `services/<composant>/compose.yaml` par composant actif. Pour désactiver `mysql`, `samba` ou `tools`, retirer son fichier de la liste ; pour activer la VM Windows, ajouter `services/windows-vm/compose.yaml`. Aucun fichier versionné n'est modifié. Relancer ensuite `./install.sh`, qui crée les fichiers d'environnement manquants, ou `docker compose up -d`. Un composant absent n'existe pas pour Compose.

Les modifications propres au poste, par exemple un proxy Traefik, vont dans un fichier `compose.local.yaml`, ignoré par git, ajouté à la main en fin de `COMPOSE_FILE`. Les modifications locales d'un ancien `compose.yaml` sont à reporter dans ce fichier.

Les valeurs de chaque service sont dans `services/<composant>/<composant>.env`, copie du modèle `.env.example` voisin et ignorée par git. Elles contiennent pour l'instant des valeurs de développement : les changer avant tout déploiement partagé. Les chemins de `compose.yaml` et des composants sont relatifs à la racine du dépôt.

La configuration de l'application se fait par variables d'environnement (`services/wpkg/wpkg.env`), décrites dans `web/config/app.php`. Points d'attention :

- `APP_FULL_BASE_URL` est obligatoire en production.
- Chaque nom utilisé par les postes dans `hosts_path` doit figurer dans `WPKG_ALLOWED_HOSTS`, sinon leurs requêtes sont refusées en 400.
- Les réglages s'affichent dans `/admin` mais ne s'y modifient pas.

Les postes lisent les flux via `config.xml` :

```xml
<param name='hosts_path' value='http://SERVEUR/hosts.xml' />
<param name='profiles_path' value='http://SERVEUR/profiles.xml' />
<param name='packages_path' value='http://SERVEUR/packages.xml' />
```

Si `WPKG_XML_PROTECT=true`, les URL prennent la forme `http://USER:PASS@SERVEUR/hosts.xml`.

## Rapports des postes

La page `/reports` affiche la dernière exécution de `wpkg.js` sur chaque poste. Elle lit, dans le dossier `WPKG_REPORTS_PATH`, les deux fichiers que chaque poste y copie à chaque exécution :

| Fichier | Contenu |
|---|---|
| `<POSTE>.log` | journal de l'exécution |
| `<POSTE>.xml` | base locale des paquets (`wpkg.xml`) |

Un package exécuté en dernier sur les postes réalise la copie : sa définition complète, les pré-requis côté poste et côté serveur sont décrits dans [Copie des rapports des postes vers le partage](docs/client-log-package.md). Exemple de commandes d'installation, avec `settings_file_path` à `C:\admin` :

```
%COMSPEC% /C if exist c:\admin\wpkg.xml copy c:\admin\wpkg.xml %SOFTWARE%\..\logs\%COMPUTERNAME%.xml /Y
%COMSPEC% /C if exist C:\admin\wpkg-%COMPUTERNAME%.log copy /Y C:\admin\wpkg-%COMPUTERNAME%.log %SOFTWARE%\..\logs\%COMPUTERNAME%.log
```

Le dossier `logs` du partage est monté en lecture seule dans le conteneur `wpkg`. En local, il s'agit de `services/samba/share/wpkg/logs`.

Les postes sont regroupés sous la première entrée d'hôte active qui les reconnaît, avec la règle de `wpkg.js`. Couleurs :

| Statut | Signification |
|---|---|
| Failed (rouge) | au moins une entrée `ERROR` dans le journal |
| Warning (orange) | au moins une entrée `WARNING`, aucune `ERROR` |
| Success (vert) | ni `ERROR` ni `WARNING` |
| Unknown (gris) | journal absent ou illisible |

Un rapport de plus de 24 h porte le badge « Stale » : le poste n'a pas tourné depuis. Un clic sur le nom du poste affiche son journal complet.

## Tests

```shell
docker compose --profile tools run --rm --no-deps tools vendor/bin/phpunit
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/web:/opt/project:z" -w /opt/project jakzal/phpqa:latest /tools/phpcs
cd wpkg-client && npm test
```

La CI GitLab exécute PHPUnit et les tests de `wpkg.js`. Les tests de `wpkg.js` tournent sous Node.js, sans dépendance, avec des bouchons du Windows Script Host.

Les tests de bout en bout sur la VM Windows sont décrits dans `services/windows-vm/README.md`. Les résultats sont dans `TESTS.md`.

## Migration depuis l'application d'origine

**Attention :** un profil valide privé de ses paquets désinstalle ces paquets sur tous les postes concernés. `bin/cake compare_feeds` doit réussir avant qu'un poste ne lise la nouvelle application.

## Postes nomades

`wpkg-agent/` met à jour les portables en arrière-plan dès leur retour sur le réseau, sans droits pour l'utilisateur. Voir `wpkg-agent/README.md`, notamment l'obligation de HTTPS. Son installation par WPKG est décrite dans [Déploiement de l'agent d'arrière-plan avec WPKG](docs/wpkg-agent-package.md).

## Licence et auteurs

WPKG Express NG est un logiciel libre sous licence GPL-3.0-or-later, voir `web/LICENSE`. Il est fourni sans aucune garantie.

| Œuvre | Droits d'auteur |
|---|---|
| WPKG Express NG, reprise et modifications de 2026 | © 2026 Aubin Puyoou |
| wpkgExpress-ng | © 2013 [Probesys](https://www.probesys.com) |
| wpkgExpress | © 2009 Brian White |
| Feuille de style cloudyDay.css | © 2005 Pat Heard (fullahead.org) |

Modifié en 2026 par Aubin Puyoou :

- portage de PHP 5 et CakePHP 1.x vers PHP 8.5 et CakePHP 5.4 ;
- interface modernisée, thèmes clair et sombre, traduction française ;
- page des rapports des postes et tableau de bord du parc ;
- pile Docker, script d'installation, tests PHPUnit et tests de `wpkg.js`.

Le client `wpkg-client/` est une copie de [WPKG](https://wpkg.org), sous sa propre licence.
