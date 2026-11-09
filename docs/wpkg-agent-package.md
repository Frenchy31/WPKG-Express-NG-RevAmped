# Déploiement de l'agent d'arrière-plan avec WPKG

Cette page explique comment installer l'agent d'arrière-plan de `wpkg-agent/` sur les portables avec WPKG lui-même, grâce au package `wpkg-agent`. Elle s'adresse aux administrateurs qui maintiennent le partage WPKG et WPKG Express NG. Le fonctionnement de l'agent est décrit dans `wpkg-agent/README.md`.

## Pré-requis

- Les portables joignent WPKG Express NG en HTTPS, avec un certificat qu'ils reconnaissent. `install.ps1` refuse HTTP seul, sauf avec l'option `-AllowInsecureHttp`, réservée aux réseaux de test.
- WPKG s'exécute avec les droits d'administrateur sur le poste : sinon, `install.ps1` s'arrête.
- Le compte qui exécute WPKG peut lire le dossier `agent` du partage.

## 1. Copier l'agent vers le partage

Copier `install.ps1`, `run.ps1`, `uninstall.ps1` et `WpkgAgent.psm1` dans un dossier `agent`, à côté de `wpkg.js` sur le partage. Avec la pile locale :

```shell
mkdir -p samba-conf/shared/wpkg/agent && cp wpkg-agent/*.ps1 wpkg-agent/*.psm1 samba-conf/shared/wpkg/agent/
```

`install.ps1` copie aussi sur le poste le `wpkg.js` du dossier parent. L'agent exécute cette copie, et non celle du partage.

## 2. Importer le package

1. Dans `wpkg-agent/package.xml`, renseigner les deux variables :

   | Variable | Valeur |
   |---|---|
   | `AGENT_SOURCE` | Chemin UNC du dossier `agent`, par exemple `\\server\wpkg\agent` |
   | `WPKG_SERVER` | Adresse HTTPS de WPKG Express NG, par exemple `https://wpkg.example.org` |

2. Ouvrir **Administration**, sélectionner le fichier dans le champ **Packages** de la section **Importer**, puis cliquer sur **Importer**. Le rapport liste le package importé.
3. Ouvrir le profile des portables, cliquer sur **Modifier** à côté de **Packages associés**, puis ajouter `wpkg-agent`.

## 3. Vérifier sur un portable

Le portable installe l'agent à sa prochaine exécution de WPKG :

- `%ProgramData%\WpkgAgent` contient `run.ps1`, `wpkg.js` et `config.xml`.
- `schtasks /query /tn \WPKG\Agent` affiche la tâche planifiée.
- `schtasks /run /tn \WPKG\Agent` lance une synchronisation. Son résultat se trouve dans `%ProgramData%\WpkgAgent\logs\agent.log`.

## Mettre à jour l'agent

WPKG considère le package comme installé dès que `%ProgramData%\WpkgAgent\run.ps1` existe : de nouveaux fichiers sur le partage ne suffisent pas à atteindre les portables. Pour déployer une nouvelle version de l'agent, ou un nouveau `wpkg.js` :

1. Copier les nouveaux fichiers dans le dossier `agent`, et `wpkg.js` à côté.
2. Augmenter la révision du package `wpkg-agent`, sur sa page de modification ou en important `package.xml` avec une `revision` supérieure. Un import avec une révision identique ou inférieure est ignoré.

À leur prochaine exécution, les portables lancent la commande `upgrade`, qui relance `install.ps1`.

## Supprimer l'agent

Retirer `wpkg-agent` du profile. À sa prochaine exécution, WPKG lance `uninstall.ps1` depuis le partage, jamais depuis le poste.
