# O'Japon

Le projet O'Japon est un site collaboratif, permettant à des voyageurs de partager leur expérience de voyage, et à de futurs voyageurs de préparer leur séjour au Japon.

_O'Japon is a collaborative website, allowing travelers to share their travel experience, and future travelers to prepare their stay in Japan._

## Backend

Ce dépôt ne concerne que le backend du projet, qui est développé à l'aide de WordPress.

_This repository only contains the back end project with WordPress._

## Frontend

Les interactions et la dynamisation des pages sont développées à l'aide du [framework Vue.js](https://vuejs.org/).

_The front-end is developed with the [Vue.js framework](https://vuejs.org/)._

Le dépôt pour le frontend se trouve [ici](https://github.com/AurelieCuignet/o-japon-front).

_See front-end repository [front](https://github.com/AurelieCuignet/o-japon-front)_

## Configuration initiale / _Initial setting_

### Récupérer ce dépôt Github / _Clone this repository_

```bash
git clone git@github.com:O-clock-Yuna/projet-24-voyage-o-japon-back.git
```

---

### Créer le vhost / _Set up vhost_

Se déplacer dans le dossier du dépôt puis récupérer le chemin exact du dossier courant pour le copier et le garder de côté :

_Move in the repository folder and retrieve the exact path of the current folder to copy it and keep it aside:_

```bash
pwd
```

Ouvrir ensuite le dossier des vhosts d'Apache, pour dupliquer un des fichiers de conf existants (par exemple _ocooking.conf_ pour créer _ojapon.conf_). Ouvrir le nouveau fichier avec nano pour l'éditer :

_Then open the vhost Apache folder. Duplicate one of the conf files ( for exemple _ocooking.conf_ to create _ojapon.conf_ )
Open the new file with nano to edit it:_

```bash
cd /etc/apache2/sites-available/
ls
sudo cp ocooking.conf ojapon.conf 

sudo nano ojapon.conf
```

Modifier les infos **(/!\ rajouter _/bedrock/web_ à la fin du chemin)**

_Edit information **(/!\ add /bedrock/web at the end of the path)**_

```apache
<VirtualHost *:80>
	ServerName ojapon.local
	ServerAdmin webmaster@localhost
	DocumentRoot path/to/project/folder/bedrock/web

	# Emplacement logs Apache
	ErrorLog ${APACHE_LOG_DIR}/error.log
	CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

Activer le nouveau site puis redémarrer Apache :

_Activate the new website and reload Apache:_

```bash
sudo a2ensite ojapon.conf

sudo service apache2 reload
```

Modifier les hosts sur le poste :

_Update hosts on your device:_

```bash
sudo sed -i -e '$a127.0.0.1 ojapon.local' /etc/hosts
```

---

### Créer la base de données / _Set up database_

Via Adminer ou phpMyAdmin, avec un nom de BDD et un user identique. Accorder tous les privilèges à l'user.

_Use Adminer or phpMyAdmin with the same name for database and username. Grant all privileges._

---

### Configurer le projet / _Configure project_

Créer le fichier .env à partir du fichier .env.example. Éditer ce fichier .env pour modifier :

* les infos de connexion à la BDD
* la variable WP_HOME='http://ojapon.local'
* les différentes keys avec [Roots.io](https://roots.io/salts.html)

_Create the .env file from the .env.example file. Edit this .env file to change:_

* _Database information_
* _WP_HOME='http://ojapon.local'_
* _Change the keys with [Roots.io](https://roots.io/salts.html)_

Le projet utilise la version 5.8.3 de WordPress, le fichier _composer.lock_ en tient compte. Il suffit donc d'installer les dépendances pour que le projet soit prêt à fonctionner (commande à exécuter dans le dossier bedrock) :

_This project uses Wordpress v. 5.8.3. You have to install dependencies to get the project ready ( command to run in bedrock folder):_

```bash
composer install
```

En cas de besoin, pour forcer l'utilisation de la version 5.8.3 de WordPress, modifier dans le fichier _composer.json_ à la racine du dossier Bedrock la ligne suivante :

_If necessary, to force the use of version 5.8.3 of WordPress, modify in the _composer.json_ file at the root of the Bedrock folder the following line:_

```composer
"roots/wordpress": "5.8.3",
```

Puis faire une mise à jour des dépendances :

_Then update dependencies:_

```bash
composer update
```

---

### Terminer l'installation / _Complete installation_

Aller sur l'adresse locale du projet http://ojapon.local. Cela devrait afficher la page de configuration de WordPress.

_Go to http://ojapon.local. This should display the WordPress configuration page._

Il peut être nécessaire de modifier les droits du dossier web pour installer des plugins ou faire des mises à jour :

_It may be necessary to modify the rights of the web folder to install plugins or make updates:_

```bash 
sudo chgrp -R www-data .
sudo find . -type f -exec chmod 664 {} +
sudo find . -type d -exec chmod 775 {} +
```
