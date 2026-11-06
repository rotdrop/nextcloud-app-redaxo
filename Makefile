# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
SRCDIR = .
ABSSRCDIR = $(CURDIR)
DEV_LIB_DIR = $(ABSSRCDIR)/dev-scripts/lib

TS_PHP_SOURCE_DIRS = lib
APP_NOTIFICATION_API = 1

include $(DEV_LIB_DIR)/makefile/app.mk
