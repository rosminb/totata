#!/usr/bin/env bash
set -ex

SCRIPTPATH="$( cd "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"

php $SCRIPTPATH/export_data.php
eval `php $SCRIPTPATH/recommender_command.php`
php $SCRIPTPATH/import_recommendations.php