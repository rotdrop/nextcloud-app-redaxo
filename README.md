ocapp-embeddedredaxo
====================

OwnCloud "app" which embeds an existing Redaxo instance into
OwnCloud. Intended for SSO (but that needs further Redaxo hacks not
published yet). Inspired by RoundCube-app, but does not store any
paswords :)

This is an "old fashioned" OC-app which does not yet use the newer
controller etc. stuff. In fact, this is a "clone" of my dokuwikiembed
app which does a similar thing with Dokuwiki.

After installation the admin has to set the URL to the Redaxo
instance, which should reside on the same server (iframe cross-domain
restrictions). Without SSO any user has to configure its Redaxo
credentials in the user-settings page.

Theory of operation:

- on login in Owncloud a login-hook runs which authenticated with
  Redaxo and obtains its session cookie. This cookie is then echoed
  back (with a modified path) to the users web-browser.

- in order to do so without SSO, the app encrypts the Redaxo
  credentials with a public key. On login, the corresponding private
  key is unlocked with the users password and the Redaxo credentials
  are decrypted

- after successful login into Redaxo the Redaxo session is kept open
  by periodically pinging it with a page fetch (this causes traffic,
  but primarily on the server side to the same server or a server on
  the same host)

- the ping interval should be adjusted to be somewhat smaller than the
  session life-time of Redaxo. This can be configured on the admin
  page.