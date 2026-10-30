#! /bin/bash

LANG=${1:-de}

APPDIR=$(realpath $(dirname $0)/..)
APP=$(basename $APPDIR)

CLOUDDIR=$(realpath "${APPDIR}/../..")
CLOUDTOOL="php ${CLOUDDIR}/tools/translationtool/translations/translationtool/translationtool.phar"

cd "$APPDIR" || exit 1

TEMPLATE="${APPDIR}/translationfiles/templates/${APP}.pot"
TRANSLATION="${APPDIR}/translationfiles/${LANG}/${APP}.po"

${CLOUDTOOL} create-pot-files
sed -i 's|'$APPDIR'/||g' "${TEMPLATE}"
msgmerge -vU --previous --backup=numbered "$TRANSLATION" "$TEMPLATE"
${CLOUDTOOL} convert-po-files
