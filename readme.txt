=== Bulk Create Users ===
Contributors: Offereins
Tags: bulk, create, import, upload, users, csv, buddypress, xprofile, groups, multisite
Requires at least: 4.0, BP 2.3
Tested up to: 4.9.8, BP 3.2
Stable tag: 1.3.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create, import or update multiple users at once

== Description ==

Upload a CSV file, set the corresponding user data fields and other import settings, and create/update your users! 
This plugin runs on both single site and multisite installations, under Admin > Users.

The default import settings contain:
* Map csv columns to data fields
* Update or skip existing users
* Register users for selected sites (multisite)
* Store the password of created users for later use
* Registration email: don't send, send default email, send custom email

This plugin has various filters to extend its use and add support for other user data fields.

This plugin has out of the box support for Buddypress Member Types, XProfile fields and User Groups. Requires at least BuddyPress 2.3.

== Changelog ==

= 1.3.0 =
* Added option to define a data column's separator for importing multiple values

= 1.2.0 =
* Moved to ajaxified import procedure
* Added PHP sessions to enable larger data files
* Added support for setting BuddyPress Member Types by key
* Added Dutch translation

= 1.1.2 =
* Fixed serious bug when creating usernames

= 1.1.1 =
* Fixed custom email From and From Name usage
* Fixed multisite site registration when updating users
* Fixed row count message

= 1.1.0 =
* Added option to include/exclude the first data row
* Added option to send a custom user registration email
* Added support for Buddypress Member Types (since BP 2.2)

= 1.0.1 =
* Fixed collecting updated users
* Added using column titles as user meta keys

= 1.0.0 =
* Initial release
