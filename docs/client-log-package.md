# Copie des rapports des postes vers le partage

Cette page explique comment chaque poste envoie au serveur le résultat de sa dernière exécution de WPKG, afin que la page **Reports** et le tableau de bord de WPKG Express NG puissent l'afficher. Elle s'adresse aux administrateurs qui maintiennent la configuration WPKG et le serveur.

## Principe

`wpkg.js` conserve deux fichiers sur le poste :

| Fichier | Contenu | Défini par |
|---|---|---|
| `wpkg.xml` | Base locale : packages installés, propriétés du host, résultats des vérifications | `settings_file_path` et `settings_file_name` dans `config.xml` |
| `wpkg-[HOSTNAME].log` | Log de l'exécution | `log_file_path` et `logfilePattern` dans `config.xml` |

Un package nommé `WPKG_Client_Log`, exécuté en dernier sur chaque poste, copie les deux fichiers vers le dossier `logs` du partage WPKG :

| Sur le poste | Sur le partage |
|---|---|
| `C:\admin\wpkg.xml` | `logs\%COMPUTERNAME%.xml` |
| `C:\admin\wpkg-%COMPUTERNAME%.log` | `logs\%COMPUTERNAME%.log` |

WPKG Express NG lit ce dossier. Chaque exécution écrase les fichiers de la précédente : le serveur ne connaît que la dernière exécution de chaque poste.

## Pré-requis

- Dans `config.xml`, les deux fichiers sont écrits dans `C:\admin` :

  ```xml
  <param name='settings_file_path' value='C:\admin' />
  <param name='log_file_path' value='C:\admin' />
  ```

  Sans ces paramètres, `wpkg.js` utilise ses valeurs par défaut : le dossier système (`%SystemRoot%\system32`) pour `wpkg.xml` et `%TEMP%` pour le log. Adapter les commandes ci-dessous en cas d'autres chemins.
- La variable `SOFTWARE` pointe vers le dossier `software` du partage, par exemple dans le profile de base. Le dossier `logs` se trouve à côté : `%SOFTWARE%\..\logs`.
- Le compte qui exécute WPKG sur les postes doit pouvoir écrire dans le dossier `logs` du partage.

## Définition du package

```xml
<package id="WPKG_Client_Log" name="WPKG_Client_Log" revision="1" priority="0" reboot="false" execute="always">
    <install cmd='%COMSPEC% /C if exist C:\admin\wpkg.xml copy /Y C:\admin\wpkg.xml %SOFTWARE%\..\logs\%COMPUTERNAME%.xml' />
    <install cmd='%COMSPEC% /C if exist C:\admin\wpkg-%COMPUTERNAME%.log copy /Y C:\admin\wpkg-%COMPUTERNAME%.log %SOFTWARE%\..\logs\%COMPUTERNAME%.log' />
</package>
```

- `execute="always"` : le package s'exécute à chaque exécution de `wpkg.js`, même s'il est déjà installé.
- `priority="0"` : `wpkg.js` traite les packages de la priorité la plus haute à la plus basse. Donner à ce package la priorité la plus basse de la configuration, afin qu'il s'exécute après tous les autres et que le log copié couvre toute l'exécution.
- `if exist` : un poste qui n'a pas encore écrit l'un des fichiers ne fait pas échouer le package.

Dans WPKG Express NG :

1. Ouvrir **Packages**, puis **Add**. Saisir `WPKG_Client_Log` comme nom et ID, la révision `1`, la priorité `0`, execute `Always`.
2. Sur la page du package, ajouter les deux commandes ci-dessus comme **Package Actions** de type `Install`.
3. Ajouter le package à un profile que tous les postes reçoivent, par exemple le profile de base dont dépendent les autres profiles.

## Côté serveur

1. Monter le dossier `logs` du partage, en lecture seule, dans le conteneur `wpkg`. La stack locale le fait dans `services/samba/compose.yaml` :

   ```yaml
   volumes:
     - ./services/samba/share/wpkg/logs:/var/lib/wpkg/reports:ro,z
   ```

2. Définir la variable d'environnement `WPKG_REPORTS_PATH` avec le chemin dans le conteneur : `/var/lib/wpkg/reports`. Lorsqu'elle est vide, la page **Reports** l'indique. La page **Admin** affiche sa valeur actuelle.
3. Ouvrir **Reports** : chaque poste apparaît sous la première entrée de host activée qui lui correspond, avec son statut.

## Ce que WPKG Express NG lit

| Donnée | Source |
|---|---|
| Date d'exécution | Première ligne du log, sinon la date de modification des fichiers |
| Statut | Entrées `ERROR` et `WARNING` du log |
| Système, architecture, adresses IP, domaine | Attributs de `wpkg.xml` |
| Packages installés | Éléments `package` de `wpkg.xml` |

- Un poste avec un seul des deux fichiers apparaît quand même, avec les données disponibles.
- Un log qui n'est pas en UTF-8 valide est converti depuis Windows-1252.
- Les noms de fichier doivent correspondre à `^[A-Za-z0-9._-]+$`. Tout autre fichier est ignoré.
- Un rapport de plus de 24 heures reçoit le badge **Stale**.

## Limites

- Le log est copié pendant que `wpkg.js` s'exécute encore : il n'a pas de marqueur de fin, et les entrées écrites après la copie sont absentes.
- `wpkg.xml` reflète l'état au moment de la copie.
- Aucun historique : chaque exécution remplace les fichiers de la précédente.
