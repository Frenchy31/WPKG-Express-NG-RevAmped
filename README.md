# WPKG Express NG

Interface web de [WPKG](https://wpkg.org), l'outil de déploiement de logiciels sur postes Windows.
L'application gère les hôtes, profils et paquets, et sert `hosts.xml`, `profiles.xml` et `packages.xml` au client `wpkg.js`.

Pile : PHP 8.5, CakePHP 5.4, MySQL, Apache, Docker Compose. Le code est dans `web/`.

## Démarrage

1. Créer `.env` à partir de `.env.example`.
2. Générer le hash du mot de passe administrateur, puis le coller dans `.env` entre apostrophes :
   ```shell
   docker compose --profile tools run --rm tools composer install --no-scripts
   docker compose --profile tools run --rm tools bin/cake hash_password 'mot-de-passe'
   ```
3. Démarrer la pile et créer les tables :
   ```shell
   docker compose up -d --build mysql samba wpkg
   docker compose exec wpkg bin/cake migrations migrate
   ```
4. Ouvrir <http://localhost/>.

L'utilisateur Unix doit appartenir au groupe `docker`. Sur Fedora, les volumes liés portent l'option `:z` à cause de SELinux.

## Services

| Service | Rôle | Accès |
|---|---|---|
| `wpkg` | Application web | Port 80 |
| `mysql` | Base de données | Réseau interne |
| `samba` | Partage `\\172.19.0.4\WPKG` | Ports 139 et 445 |
| `tools` | Composer, migrations, PHPUnit (profil `tools`) | Sans objet |
| `wpkg-test-client` | VM Windows de test | Ports 8006 et 3389 |

`legacy/` contient l'application PHP 5.6 d'origine et sa pile figée. Elle sert uniquement à capturer les flux de référence : ne jamais la déployer.

## Configuration

La configuration se fait par variables d'environnement, décrites dans `web/config/app.php`. Points d'attention :

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

## Tests

```shell
docker compose --profile tools run --rm tools vendor/bin/phpunit
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/web:/opt/project:z" -w /opt/project jakzal/phpqa:latest /tools/phpcs
cd wpkg-client && npm test
```

La CI GitLab exécute PHPUnit et les tests de `wpkg.js`. Les tests de `wpkg.js` tournent sous Node.js, sans dépendance, avec des bouchons du Windows Script Host.

Les tests de bout en bout sur la VM Windows sont décrits dans `wpkg-test/README.md`. Les résultats sont dans `TESTS.md`.

## Migration depuis l'application d'origine

**Attention :** un profil valide privé de ses paquets désinstalle ces paquets sur tous les postes concernés. `bin/cake compare_feeds` doit réussir avant qu'un poste ne lise la nouvelle application.

## Licence

GPL-3.0, voir `web/LICENSE`. Basé sur wpkgExpress-ng (Probesys) et wpkgExpress (Brian White).
