Redaxo Integration for Nextcloud
==================================

<!-- markdown-toc start - Don't edit this section. Run M-x markdown-toc-refresh-toc -->
**Table of Contents**

- [Intro](#intro)
- [Installation](#installation)
- [Single Sign On](#single-sign-on)
- [More Documentation should follow ...](#more-documentation-should-follow-)
- [Screenshots](#screenshots)
  - [Start Page](#start-page)
  - [Admin Settings](#admin-settings)
  - [JQuery Popup](#jquery-popup)

<!-- markdown-toc end -->

# Intro

This is a Nextcloud app which embeds a Redaxo instance into a
Nextcloud server installation. If Redaxo and Nextcloud are
configured to use the same authentication backend, then this will work
with SSO, otherwise the login window of Redaxo will appear in the
embedding iframe.

# Installation

- ~install from the app-store~ (not yet)
- install from a (pre-)release tar-ball by extracting it into your app folder
- clone the git repository in to your app folder and run make
  - `make help` will list all targets
  - `make dev` comiles without minification or other assset-size optimizations
  - `make build` will generate optimized assets
  - there are several build-dependencies like compose, node, tar
    ... just try and install all missing tools ;)

# Single Sign On

If Redaxo and Nextcloud share a common user-base and authentication
scheme then the current user is just silently logged into the
configured Redaxo instance and later the Redaxo contents will just
be presented in an IFrame to the user.

# More Documentation should follow ...

# Screenshots

## Start Page

TODO

## Admin Settings

TODO

## JQuery Popup

TODO
