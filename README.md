# PLUGIN OAI-PMH LODEL 
Ce plugin permet de renseigner les identifiants idref et orcid des auteurs d'un article à partir du formulaire d'édition de celui-ci.
On pourra ensuite afficher publiquement ces identifiants via les modifications proposées ici pour la maquette Nova et Open Edition.

## Prérequis
- Lodel 1.0
## Installation
Dans le répertoire `share/plugins/custom/` de votre installation, dézippez l'archive du plugin ou clonez le dépôt :
```
git clone https://github.com/oliviercrouzet/idplus/.git
```
## Activation
Accédez à l'administration des plugins de votre installation lodel (Administrer/Plugins).
> url =>  `https://votreinstallation/lodeladmin/index.php?do=list&lo=mainplugins`

Vous pouvez alors le « copier » sur chaque site après avoir renseigné les paramètres communs à tous les sites.
Ces paramètres sont éditables via le lien *Configurer*.

  * Si on clique sur *Installer sur tous les sites*, le plugin est non seulement copié mais aussi directement activé au niveau de chaque site, ce qui n'est pas forçément souhaitable.
  * Si, on clique sur *Activer*, le plugin est installé mais pas activé (oui, c'est un rien contre-intuitif !). Sur chaque site, on retrouve le plugin dans le tableau des plugins (Administration/Plugins).

On peut, si nécessaire, modifier les paramètres au niveau du site en cliquant sur *Configurer* . L'astérisque indique que le champ est obligatoire.

## Usage
Une section "Identifiants auteurs" apparait au dessous de l'encadré des fichers annexes.
Lorsque des correspondances sont trouvées avec le nom de l'auteur dans les bases idref et orcid, les mentions idref et orcid sont activées comme lien orange et suivies entre parenthèses du nombre d'occurrences trouvées. Un click sur le lien affiche chaque profil dans un onglet du navigateur.
Lorsque un identifiant est renseigné, il sera ensuite disponible automatiquement pour tous les articles de la revue où la personne figure comme auteur.
On peut compléter les identifiants immédiatement au moment du chargement de l'article en cliquant sur le lien "Continuer" plutôt que "Terminer...".
Notez que les références sont enregistrés dans un fichier texte au format json qu'on pourra utiliser postérieurement pour ajouter une balise hadoc aux fichiers sources tei xml.


## Désinstallation

Pour modifier la **nature** des paramètres (leur caractère obligatoire ou non par exemple) et en général toutes données que l'on trouve dans le fichier du plugin _config.xml_, on est obligé de désinstaller sur tous les sites.  
La désinstallation complète d'un plugin ne peut pas se faire au niveau d'un site particulier via l'interface graphique.  
A priori, on serait donc tenté d'utiliser la fonction présente au niveau de l'administration générale (*Désinstaller sur tous les sites*) mais elle ne semble pas très bien fonctionner.  
De toute manière, il faut encore supprimer, dans la base sql principale, la ligne qui concerne le plugin dans la table _mainplugins_.

Le mieux est de procéder de la manière suivante :

1. Désactiver le plugin d'abord dans l'Administration générale.
2. Si vous avez plusieurs sites à traiter, utilisez plutôt le script [*delete\_idplus\_instances.php*](https://github.com/oliviercrouzet/idplus/tree/outils) qui va effacer l'enregistrement du plugin sur chaque site ainsi que sur l'administration générale.    
      Sinon, via mysql directement > effacer directement la ligne *doicooker* dans la table *plugins* du site puis dans la table *mainplugins* du site principal.
3. Vous pourrez alors modifier le fichier *config.xml* puis recharger la page Plugins de l'Administration générale.

## Crédits
Plugin réalisé d'après la documentation fournie par Jean-François Rivière (merci à lui) :  
https://github.com/OpenEdition/lodel/wiki/Plugins
