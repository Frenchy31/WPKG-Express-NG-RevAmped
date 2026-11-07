# Agent WPKG pour postes nomades

Met à jour les postes en arrière-plan, sans action de l'utilisateur, dès qu'ils reviennent sur le réseau de l'entreprise.

## Fonctionnement

L'agent est une tâche planifiée `\WPKG\Agent`, exécutée en `SYSTEM`, sans fenêtre et sans droits pour l'utilisateur. Elle se déclenche :

- à la connexion réseau (événement `NetworkProfile` 10000), 30 secondes après ;
- au démarrage, 2 minutes après ;
- toutes les 30 minutes.

À chaque déclenchement :

1. Si `wpkg.js` tourne déjà, l'agent s'arrête.
2. Si une synchronisation a réussi depuis moins de 60 minutes, l'agent s'arrête.
3. L'agent interroge `hosts.xml`. Sans vrai flux WPKG en réponse, il s'arrête : poste hors du réseau, portail captif d'hôtel, serveur en panne.
4. Il lance `wpkg.js /synchronize /quiet /nonotify /noreboot`.

Une synchronisation en échec n'est pas comptée : le déclenchement suivant recommence.

Le poste ne redémarre jamais de lui-même. Un paquet qui exige un redémarrage attend le prochain redémarrage de l'utilisateur.

## Prérequis de production

**HTTPS obligatoire.** L'agent exécute en `SYSTEM` les commandes reçues du serveur, sur n'importe quel réseau rejoint par le portable. Sur un réseau hostile, un faux serveur HTTP obtiendrait donc l'exécution de code avec les droits maximaux sur le poste. `install.ps1` refuse HTTP, sauf avec `-AllowInsecureHttp`, à réserver aux réseaux de test. Le certificat doit être reconnu par le poste.

**Accès aux installeurs.** Les commandes des paquets s'exécutent en `SYSTEM`, sans le lecteur réseau ni les identifiants de l'utilisateur. Un paquet qui lit `\\serveur\partage` doit y avoir accès avec le compte machine, ou télécharger son installeur en HTTPS.

**Flux protégés non gérés.** Une URL avec identifiants est refusée, pour ne pas stocker de mot de passe sur le poste.

## Déploiement

Le plus simple est de passer par WPKG :

1. Copier `install.ps1`, `run.ps1`, `uninstall.ps1` et `WpkgAgent.psm1` dans `agent/`, à côté de `wpkg.js` sur le partage.
2. Adapter `AGENT_SOURCE` et `WPKG_SERVER` dans `package.xml`, puis l'importer depuis `/admin`.
3. Ajouter le paquet `wpkg-agent` au profil des portables.

Le poste installe l'agent à sa prochaine exécution de WPKG, par exemple lancée une dernière fois par l'utilisateur en administrateur.

Installation manuelle, en administrateur :

```powershell
.\install.ps1 -ServerUrl https://wpkg.example.org
```

Options : `-MinIntervalMinutes` (60), `-RepeatMinutes` (30), `-WpkgScript` (`wpkg.js` copié sur le poste).

## Sécurité du poste

`SYSTEM` exécute le contenu de `%ProgramData%\WpkgAgent` : un utilisateur capable d'y écrire deviendrait administrateur. Ce dossier est donc réservé à `SYSTEM` et aux administrateurs, et placé directement sous `%ProgramData%`.

Un utilisateur standard peut créer ce dossier avant la première installation. `install.ps1` vérifie donc le dossier, qu'il existe déjà ou non : ce doit être un vrai dossier, et non une jonction, dont le propriétaire est `SYSTEM` ou les administrateurs, sans héritage, et dont les droits ne visent que ces deux comptes. Sinon, l'installation échoue sans rien copier. Chercher qui a créé le dossier, le supprimer, puis relancer l'installation.

`uninstall.ps1` applique la même vérification et refuse de supprimer un dossier qui ne la passe pas. Le paquet lance la désinstallation depuis le partage, jamais depuis le poste.

## Exploitation

| Fichier | Contenu |
|---|---|
| `logs\agent.log` | Décision de chaque déclenchement et code de retour de `wpkg.js` |
| `logs\wpkg-<poste>.log` | Journal de `wpkg.js` |
| `last-success.txt` | Date de la dernière synchronisation réussie |

Lancer une synchronisation : `schtasks /run /tn \WPKG\Agent`.

Désinstaller : `powershell -File %ProgramData%\WpkgAgent\uninstall.ps1`, ou retirer le paquet du profil.

## Tests

```shell
docker run --rm -v "$PWD/wpkg-agent:/agent:ro,z" mcr.microsoft.com/powershell:latest pwsh -NoProfile -File /agent/tests/run.ps1
```

Ces tests couvrent la logique, sous PowerShell 7. Windows PowerShell 5.1 se comporte différemment : toute modification doit aussi passer sur la VM (`wpkg-test/README.md`).
