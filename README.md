# WPKG Express NG

Interface web de [WPKG](https://wpkg.org), l'outil de déploiement de logiciels sur postes Windows.
L'application gère les hôtes, profils et paquets, et sert `hosts.xml`, `profiles.xml` et `packages.xml` au client `wpkg.js`.

Pile : PHP 8.5, CakePHP 5.4, MySQL, Apache, Docker Compose. Le code est dans `web/`.

## Démarrage

`./install.sh` installe tout depuis un clone neuf : `.env`, fichiers d'environnement des composants, mots de passe, images, conteneurs et tables. Il ne modifie aucun fichier avant d'avoir collecté et vérifié toutes les réponses : Docker accessible, fuseau horaire connu de PHP, ports libres (HTTP, 139 et 445 si `samba` est actif, 8006 et 3389 pour la VM), sous-réseau `172.19.0.0/16`, noms de conteneurs, et pour la VM Windows `/dev/kvm` et l'espace disque.

Avec un terminal, le script pose ces questions et propose en défaut la valeur déjà enregistrée :

| Question | Variable d'environnement | Défaut |
|---|---|---|
| Nom du serveur (donne `APP_FULL_BASE_URL`) | `WPKG_SERVER_NAME` | `hostname -f` |
| Noms et adresses utilisés par les postes (`WPKG_ALLOWED_HOSTS`) | `WPKG_ALLOWED_HOSTS` | nom du serveur, nom court, première adresse de l'hôte ; `wpkg` et `172.19.0.5` sont toujours ajoutés |
| Port HTTP | `WPKG_HTTP_PORT` | 80 |
| Fuseau horaire (`APP_DEFAULT_TIMEZONE`), refusé s'il est inconnu de PHP | `WPKG_TIMEZONE` | celui de l'hôte (`UTC` sur une installation existante) |
| Langue de l'interface (`APP_DEFAULT_LOCALE`) | `WPKG_LOCALE` | `en_US` (ou `fr_FR`) |
| Activer `samba` | `WPKG_ENABLE_SAMBA` | `yes` |
| Activer `windows-vm` (exige `samba`) | `WPKG_ENABLE_WINDOWS_VM` | `no` |
| Mot de passe de `admin` | `WPKG_ADMIN_PASSWORD` | généré |
| Mot de passe du compte Samba `administrateur` | `WPKG_SAMBA_PASSWORD` | généré |
| Mot de passe de la VM Windows | `WPKG_VM_PASSWORD` | généré |
| Protéger les flux XML par mot de passe | `WPKG_XML_PROTECT` (`true`/`false`) | `false` |
| Mot de passe des flux (utilisateur `wpkg`) | `WPKG_XML_PASSWORD` | généré |

Sans terminal, chaque question prend la valeur de sa variable d'environnement, sinon son défaut. Exemple d'installation non interactive :

```shell
WPKG_SERVER_NAME=wpkg.example.org WPKG_TIMEZONE=Europe/Paris WPKG_LOCALE=fr_FR \
  WPKG_ADMIN_PASSWORD='mot de passe' ./install.sh </dev/null
```

Mots de passe :

- `SECURITY_SALT` et les mots de passe MySQL `root` et `wpkg` sont générés à la première installation, jamais affichés ni régénérés.
- Les mots de passe Samba et de la VM sont demandés deux fois. Laissés vides (ou sans terminal et sans variable), ils sont générés et affichés une seule fois : les noter. Ils ne contiennent que `[A-Za-z0-9]`, et une valeur saisie avec un autre caractère est refusée.
- Un mot de passe déjà enregistré est conservé et n'est plus demandé, sauf si sa variable est donnée.
- Aucun mot de passe ne passe sur une ligne de commande. Les fichiers `.env` et `services/*/*.env` ont les droits `600`.
- Avec `WPKG_XML_PROTECT=true`, le hash est écrit dans `WPKG_XML_PASSWORD_HASH`. **`wpkg-agent` refuse les flux protégés** (voir `wpkg-agent/README.md`).

Désactiver un composant arrête et supprime son conteneur, sans toucher à ses données (volume, dossier `storage/`). Activée, la VM Windows est démarrée sans attendre la fin de l'installation de Windows ; le script affiche l'adresse de sa console, `http://<serveur>:8006`.

Sur une installation antérieure, le script déplace les hashes de `.env` vers `services/wpkg/wpkg.env`, reprend les valeurs de développement qui ont initialisé les données (`your_root_password` et `your_password` pour MySQL, `wpkgtest` pour Samba, `changeme-test` pour la VM), garde le fuseau `UTC` sauf choix explicite, remplace l'ancien sel de développement (les sessions ouvertes sont fermées) et liste à la fin les valeurs de développement encore utilisées : à changer avant tout déploiement partagé. Les datetime de la base n'ont pas de fuseau : changer `APP_DEFAULT_TIMEZONE` décale l'affichage des dates déjà enregistrées.

Étapes manuelles équivalentes, pour un composant à la main :

1. Créer `.env` à partir de `.env.example`, puis un fichier `services/<composant>/<composant>.env` à partir de chaque `services/<composant>/<composant>.env.example` des composants actifs, et renseigner les valeurs vides.
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

Pour une démonstration, `DemoSeed` remplit une base vide avec un petit parc : deux salles, des portables, des logiciels courants, leurs profils et leurs entrées d'hôte. Il s'arrête si la base contient déjà des paquets, des profils ou des hôtes.

```shell
docker compose --profile tools run --rm --no-deps tools bin/cake seeds run DemoSeed
```

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

Les valeurs de chaque service sont dans `services/<composant>/<composant>.env`, copie du modèle `.env.example` voisin, remplie par `./install.sh` et ignorée par git. Les modèles ne contiennent ni mot de passe, ni sel, ni nom de serveur. Les chemins de `compose.yaml` et des composants sont relatifs à la racine du dépôt.

Pour changer une valeur : modifier le fichier `services/<composant>/<composant>.env`, puis lancer `docker compose up -d`, qui recrée le conteneur concerné ; ou relancer `./install.sh`, qui repose les questions avec les valeurs enregistrées en défaut. Un nouveau mot de passe MySQL ne s'applique pas à un volume `mysql_data` existant. Le mot de passe du compte Samba de la VM n'est lu qu'au premier démarrage de Windows : après un changement, refaire `net use` dans la VM. `RAM_SIZE`, `CPU_CORES` et `DISK_SIZE` de `services/windows-vm/windows-vm.env` se règlent avant le premier démarrage de la VM.

Réglages PHP, dans `services/wpkg/wpkg.env` : `PHP_MEMORY_LIMIT` (256M), `PHP_UPLOAD_MAX_FILESIZE` (16M) et `PHP_POST_MAX_SIZE` (16M). `php.ini` les lit au démarrage du conteneur : `docker compose up -d` suffit, sans reconstruire l'image. `date.timezone` suit `APP_DEFAULT_TIMEZONE`.

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
