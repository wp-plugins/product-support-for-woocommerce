=== Product Support for Woocommerce ===
Contributors: themology
Donate link: http://themology.net/donate
Tags: comments, spam
Requires at least: 3.0.1
Tested up to: 4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin gives you the ability to provide easy and painless product support via BuddyPress or bbPress.

== Description ==

When you create or edit a product you're given the option to associate the product with a discussion group (in BuddyPress) or forum (in bbPress). This plugin can automatically create a new group or forum and can even create the first discussion topic (based on plugin settings). When a user buys a product they will automatically be granted access to the necessary support forum on order completion.


= Key Features =

* Automatically create new groups or forums.
* Associate products with any existing groups or forums.
* Automatically add users to correct groups on completed purchase (BuddyPress only).
* Automatically create first post in each new forum.
* Doesn't interfere with existing bbPress and BuddyPress functionality.
* You can manually create Groups or forums and add users like you always have.


= Some possible Use Cases: =

* Support Packages for service-based clients
* Supporting digital or physical goods
* Building paid community sites with BuddyPress


Note: This plugin requires either bbPress or BuddyPress to function properly.

== Installation ==


1. Upload the plugin to your ‘plugins’ directory via FTP or within WordPress via Plugins > Add New > Upload.
2. Activate it on the Plugins page in WordPress.
3. You will also need to install either bbPress or BuddyPress (with User Groups and Discussion Forums enabled)


= Configuration =

Under WooCommerce > Settings > Integration > Product Support you can configure the default topic settings.

On this page, you will be able to set the default topic title, as well as the default topic content to use when creating the initial thread for each product forum. Both of the fields will be able to leverage the the name of the product that the forum is being created for, by using the %product_title% placeholder text. We have provided some default text for you out of the box, but you can customize as much as you want.


= Usage =

When creating or editing a product, you will find a new metabox labeled Product Support in the right-hand sidebar. Within this metabox you can optionally enable support for the product and select an existing group/forum or create a new one.

If you are choosing to use BuddyPress Groups for support, and choose the “Create new group” option, the group will be made upon product publish, and take its name from the name given to the product. When a user purchases any product(s) that has support enabled they will automatically be added to all associated groups.

If you are choosing to use bbPress, and choose new group/forum you can also optionally create the first discussion topic (based on the plugin settings in WooCommerce > Settings > Integration > Product Support). This first discussion topic will be made sticky and also locked so that it always appears at the top and is not open to discussion by users.

bbPress integration users will automatically gain access to ALL support forums (as this is the intended behavior of bbPress).


== Screenshots ==

1. 
2.