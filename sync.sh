#!/bin/bash

# The purpose of this script is to sync CMS assets and DB bewteen local and
# remote enviroinments. Syncing can occur in two directions (pull and push) and
# the remote enironment can be either staging or production. This results in
# six possible command combinations (we shouldn't push to production):
#
# 1) ./sync.sh pull staging db
# 2) ./sync.sh pull staging assets
# 3) ./sync.sh push staging db
# 4) ./sync.sh push staging assets
# 5) ./sync.sh pull production db
# 6) ./sync.sh pull production assets
#
# This script requires a sibling file named ".env" that contains named
# variabels as follows:
#
# |_ CRAFT_DB_USER (craft CMS variable)
# |_ CRAFT_DB_PASSWORD (craft CMS variable)
# |_ CRAFT_DB_DATABASE (craft CMS variable)
# |_ HOME_PATH
# |_ WEB_DIR
# |_ ASSETS_DIR (craft CMS variables)
# |_ STAG_SSH_HOST
# |_ STAG_USER
# |_ STAG_HOME_PATH
# |_ STAG_DB_HOST
# |_ STAG_DB_USER
# |_ STAG_DB_PASSWORD
# |_ STAG_DB_DATABASE
# |_ PROD_SSH_HOST
# |_ PROD_USER
# |_ PROD_HOME_PATH
# |_ PROD_DB_HOST
# |_ PROD_DB_USER
# |_ PROD_DB_PASSWORD
# |_ PROD_DB_DATABASE

# Import env variables
source .env

# Constants
BACKUP_TMP="db.sql"

# Initialize variables
SSH_HOST=""
REMOTE_DB_HOST=""
REMOTE_DB_DATABASE=""
REMOTE_DB_USER=""
REMOTE_DB_PASSWORD=""
REMOTE_USER=""
REMOTE_HOME_PATH=""
DEPLOY_SCRIPT="$HOME_PATH/beanstalk-post-deployment.sh"

################################################################################
# Error checks
################################################################################

# Check for required arguments
if [[ -z "$1" || ( "$1" != "push" && "$1" != "pull" ) ]]; then
	echo "Please provide a sync direction"

	exit
fi

if [[ -z "$2" || ( "$2" != "staging" && "$2" != "production" ) ]]; then
	echo "Please provide a valid remote environment argument"

	exit
fi

if [[ -z "$3" || ( "$3" != "assets" && "$3" != "db" ) ]]; then
	echo "Please provide a valid resource argument"

	exit
fi

if [[ "$1" == "push" && "$2" == "production" ]]; then
	echo "Let's not push to production mkay"

	#exit
fi

# Set remote environment
if [ "$2" = "staging" ]; then
	SSH_HOST="$STAG_SSH_HOST"
	REMOTE_USER="$STAG_USER"
	REMOTE_HOME_PATH="$STAG_HOME_PATH"
	REMOTE_DB_HOST="$STAG_DB_HOST"
	REMOTE_DB_DATABASE="$STAG_DB_DATABASE"
	REMOTE_DB_USER="$STAG_DB_USER"
	REMOTE_DB_PASSWORD="$STAG_DB_PASSWORD"
elif [ "$2" = "production" ]; then
	SSH_HOST="$PROD_SSH_HOST"
	REMOTE_USER="$PROD_USER"
	REMOTE_HOME_PATH="$PROD_HOME_PATH"
	REMOTE_DB_HOST="$PROD_DB_HOST"
	REMOTE_DB_DATABASE="$PROD_DB_DATABASE"
	REMOTE_DB_USER="$PROD_DB_USER"
	REMOTE_DB_PASSWORD="$PROD_DB_PASSWORD"
fi

################################################################################
# Data sync
################################################################################

# Steps: Ignore owner, group, and permissions on push rsync
# 1) Dump DB
# 2) Sync DB
# 3) Import DB
# 4) Sync assets
# 5) Remove DB backup

if [ "$1" = "push" ]; then
	# push assets
	if [ "$3" = "assets" ]; then
		rsync -azh --no-o --no-g --no-p "$HOME_PATH/$WEB_DIR/$ASSETS_DIR/" "$REMOTE_USER@$SSH_HOST:$REMOTE_HOME_PATH/$WEB_DIR/$ASSETS_DIR"

	# push db
	elif [ "$3" = "db" ]; then
		mysqldump --no-defaults --no-tablespaces -u"$CRAFT_DB_USER" -p"$CRAFT_DB_PASSWORD" "$CRAFT_DB_DATABASE" > "$HOME_PATH/$BACKUP_TMP"
		rsync -azP --no-o --no-g --no-p "$HOME_PATH/$BACKUP_TMP" "$REMOTE_USER@$SSH_HOST:$REMOTE_HOME_PATH"
		ssh "$REMOTE_USER@$SSH_HOST" "mysql -u$REMOTE_DB_USER -p'$REMOTE_DB_PASSWORD' $REMOTE_DB_DATABASE < $REMOTE_HOME_PATH/$BACKUP_TMP"

		# clean db trash
		rm "$HOME_PATH/$BACKUP_TMP"
		ssh "$REMOTE_USER@$SSH_HOST" "rm $REMOTE_HOME_PATH/$BACKUP_TMP"
	fi

elif [ "$1" = "pull" ]; then
	# pull assets
	if [ "$3" = "assets" ]; then
		rsync -azh "$REMOTE_USER@$SSH_HOST:$REMOTE_HOME_PATH/$WEB_DIR/$ASSETS_DIR/" "$HOME_PATH/$WEB_DIR/$ASSETS_DIR"

	# pull db
	elif [ "$3" = "db" ]; then
		ssh "$REMOTE_USER@$SSH_HOST" "mysqldump --no-defaults --no-tablespaces -h$REMOTE_DB_HOST -u$REMOTE_DB_USER -p'$REMOTE_DB_PASSWORD' $REMOTE_DB_DATABASE > $REMOTE_HOME_PATH/$BACKUP_TMP"
		rsync -azP "$REMOTE_USER@$SSH_HOST":"$REMOTE_HOME_PATH/$BACKUP_TMP" "$HOME_PATH/"
		mysql -u"$CRAFT_DB_USER" -p"$CRAFT_DB_PASSWORD" -e "drop database if exists $CRAFT_DB_DATABASE"
		mysql -u"$CRAFT_DB_USER" -p"$CRAFT_DB_PASSWORD" -e "create database $CRAFT_DB_DATABASE"
		mysql -u"$CRAFT_DB_USER" -p"$CRAFT_DB_PASSWORD" "$CRAFT_DB_DATABASE" < "$BACKUP_TMP"

		# clean db trash
		rm "$HOME_PATH/$BACKUP_TMP"
		ssh "$REMOTE_USER@$SSH_HOST" "rm $REMOTE_HOME_PATH/$BACKUP_TMP"

		# composer install and craft up
		if [ -e $DEPLOY_SCRIPT ]; then
			$DEPLOY_SCRIPT
		fi
	fi
fi
