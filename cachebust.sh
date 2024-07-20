#!/bin/bash

# Import env variables
source .env

################################################################################
# Commands
################################################################################

# If scripts or styles have changed, update cachebusting variables
SCRIPTS_HASH=$(sha256sum "$HOME_PATH/$WEB_DIR/$SCRIPTS_FILE" | cut -f 1 -d " ")
STYLES_HASH=$(sha256sum "$HOME_PATH/$WEB_DIR/$STYLES_FILE" | cut -f 1 -d " ")

if [[ "$SCRIPTS_HASH" != "$SCRIPTS_CACHE_HASH" ]]; then
	sed -i "s/^\(SCRIPTS_CACHE_HASH=\"\)\(.*\)\"$/\1$SCRIPTS_HASH\"/" .env
fi

if [[ "$STYLES_HASH" != "$STYLES_CACHE_HASH" ]]; then
	sed -i "s/^\(STYLES_CACHE_HASH=\"\)\(.*\)\"$/\1$STYLES_HASH\"/" .env
fi
