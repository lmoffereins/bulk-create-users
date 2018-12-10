# Bulk Create Users #

Create, import or update multiple users at once

## Description ##

> This WordPress plugin requires at least [WordPress](https://wordpress.org) 4.0.

Upload a CSV file, set the corresponding user data fields and other import settings, and create/update your users.
This plugin runs on both single site and multisite installations, under Admin > Users.

The default import settings contain:
* Map csv columns to data fields
* Update or skip existing users
* Register users for selected sites (multisite)
* Store the password of created users for later use
* Registration email: don't send, send default email, send custom email

This plugin has various filters to extend its use and add support for other user data fields.

This plugin has out of the box support for Buddypress Member Types, XProfile fields and User Groups. Requires at least [BuddyPress](https://buddypress.org) 2.3.

## Installation ##

If you download Bulk Create Users manually, make sure it is uploaded to "/wp-content/plugins/bulk-create-users/".

Activate Bulk Create Users in the "Plugins" admin panel using the "Activate" link. If you're using WordPress Multisite, you can choose to activate Bulk Create Users network wide for full integration with all of your sites.

## Updates ##

This plugin is not hosted in the official WordPress repository. Instead, updating is supported through use of the [GitHub Updater](https://github.com/afragen/github-updater/) plugin by @afragen and friends.

## Contributing ##

You can contribute to the development of this plugin by [opening a new issue](https://github.com/lmoffereins/bulk-create-users/issues/) to report a bug or request a feature in the plugin's GitHub repository.
