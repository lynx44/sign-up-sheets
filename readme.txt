=== Sign-up Sheets ===
Contributors: dlsstudios
Tags: sign up, signup, volunteer, PTO, timeslot, photographer, Non-profit, club, sign-up, signup sheet, sign-up sheet, sign up sheet
Requires at least: 3.3
Tested up to: 4.3
Stable tag: 1.0.6
License: GPLv2 or later

An online sign-up sheet manager where your users/volunteers can sign up for tasks


== Description ==

This plugin lets you quickly and easily setup sign-up sheets on your WordPress site.

For many more features, check out Sign-up Sheets Pro here:
[http://www.dlssoftwarestudios.com/sign-up-sheets-wordpress-plugin/](http://www.dlssoftwarestudios.com/sign-up-sheets-wordpress-plugin/)

Sign-up Sheets can be used for many purposes:

* Volunteer sign-ups
* Timeslot sign-ups
* Personell and resource coordination
* And many more...

The basic version of Sign-Up Sheets includes the following features:

* Unlimited number of sign-up sheets and unlimited number of sign-up slots
* Administrator can clear sign-up slots if needed
* Ability to copy a sheet
* Export all sign-up information to CSV

Sign-up sheets is being used on 1000s of WordPress websites and for dozens of functions including:

* Non-profits volunteer opportunities
* Schools and PTO volunteer opportunities
* Church volunteer opportunities
* Club volunteer opportunities
* Photographer timeslot sign-ups
* Meeting room timeslot sign-ups
* And many more...


== Installation ==

1. Download the plugin and extract the files
2. Copy the `sign-up-sheets` directory and all its files to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Create a new blank page and add the shortcode [sign_up_sheet]


== Frequently Asked Questions ==

= How do I create a Sign-up Sheet page on my site? =
You can do this by creating any page or post and adding the shortcode `[sign_up_sheet]` to the content.  Then, go to the "Sign-up Sheets" section of your WP admin and create a new sheet.

= Can I change the "from" address on the confirmation email? =
Yes, in `Settings > Sign-up Sheets` you can specify any email you want.  It defaults to the email address set in `Settings > General`.

= How can I suggest an idea for the plugin? =
Send us an email through our website at http://www.dlssoftwarestudios.com/contact-us/  We appreciate any and all feedback, but can't guarantee it will make it into the next version.  If you are in need a modification immediately, we are available for hire.  Please contact us at the link above and we can provide a quote.

= How do I display sheets from only 1 specific category (Pro version only) =
To filter by category, you can include the category id # in the shortcode to determine which category will display on that page.   As an example, the following shortcode would show all sheets associated with category #5... `[sign_up_sheet category_id="5"]`


== Screenshots ==

1. Admin Sign-up Sheets Listing
2. Admin Sign-up Sheet Details
3. Admin Edit Sign-up Sheet
4. Frontend Example


== Upgrade Notice ==

= 1.0.10 =
* Fixed bug where trashed sheets were showing up on Export All
* Fixed fatal error if activating Pro before deactivating the free version

= 1.0.9 =
* Fixed bug where trashed sheets with no date specified would display on frontend
* fixed bug where trashed individual sheet pages were available on the frontend
* Removed debug statement that was causing issues in certain browsers

= 1.0.8 =
* Fixed security bug on export
* Fixed sheet edit screen to prevent the quantity of available tasks from being decreased below the number of current signups
* Fixed sheets disappearing before the end of day on date of event
* Fixed bug that disallowed leaving the date field blank

= 1.0.7 =
* Corrected export CSV headers

= 1.0.6 =
* Security fix for sign-up form

= 1.0.5 =
* Fixed task sorting
* Added additional error detail on adding a signup

= 1.0.4 =
* Fixed export CSV bug when WordPress is installed in a subfolder
* Added option for detailed debug messages

= 1.0.3 =
* Fixed compatibility bug with WordPress v3.5 prepare statement
* Cleaned markup for standards compliance and missing closing tags

= 1.0.2 =
* Fixed bug with `[sign_up_sheet]` shortcode sometimes messing up headers in certain themes

= 1.0.1 =
* Fixed bug with sites using query strings on sign-up sheet page


== Changelog ==

= 1.0.10 =
* Fixed bug where trashed sheets were showing up on Export All
* Fixed fatal error if activating Pro before deactivating the free version

= 1.0.9 =
* Fixed bug where trashed sheets with no date specified would display on frontend
* Fixed bug where trashed individual sheet pages were available on the frontend
* Removed debug statement that was causing issues in certain browsers

= 1.0.8 =
* Fixed security bug on export
* Fixed sheet edit screen to prevent the quantity of available tasks from being decreased below the number of current signups

= 1.0.7 =
* Corrected export CSV headers

= 1.0.6 =
* Security fix for sign-up form

= 1.0.5 =
* Fixed task sorting
* Added additional error detail on adding a signup

= 1.0.4 =
* Fixed export CSV bug when WordPress is installed in a subfolder
* Added option for detailed debug messages

= 1.0.3 =
* Fixed compatibility bug with WordPress v3.5 prepare statement
* Cleaned markup for standards compliance and missing closing tags

= 1.0.2 =
* Fixed bug with `[sign_up_sheet]` shortcode sometimes messing up headers in certain themes

= 1.0.1 =
* Fixed bug with sites using query strings on sign-up sheet page

= 1.0 =
* Initial public version