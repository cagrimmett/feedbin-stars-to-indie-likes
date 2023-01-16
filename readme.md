# Feedbin Stars to Indie Likes

Takes starred posts from [Feedbin](https://feedbin.com/), my RSS feed syncing engine of choice, and turns them into Indie Likes.

You probably don't have the same setup as me (you might use a different reader or use a different plugin or post type for IndieWeb Likes), but perhaps you can reuse some of this code to fit your purposes.

## Description

This plugin fetches your starred posts from Feedbin and creates new Indie Likes posts for them on your WordPress site. The plugin sets up a cron job to fetch new starred posts from Feedbin every hour.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/feedbin-stars-to-indie-likes` directory, or download a zip of this repo and install the plugin through the WordPress plugins screen.
2. Activate the plugin.
3. Use the Settings->Feedbin Stars to Indie Likes screen to configure the plugin.
4. Make sure the Indieblocks plugin is installed and activated

## Requirements

This plugin requires the [IndieBlocks](https://wordpress.org/plugins/indieblocks/) plugin by Jan Boddez to be installed and activated. It is what I prefer to power my website's Likes.

## Configuration

1. Go to the plugin settings page at Tools->Feedbin Stars to Indie Likes
2. Enter your Feedbin username and password. [Feedbin's API](https://github.com/feedbin/feedbin-api/) uses basic auth, not API keys.
3. Select the author you want to attribute the likes to from the dropdown menu of existing authors
4. Enter the date you want to start fetching new starred posts after in the format `YYYY-MM-DDTHH:MM:SS`. This is there because I already had a lot of starred posts but didn't want to import them all. 
5. Click 'Save Changes'

## Changelog

### 0.0.2
* Fixed bug with cron and plugin identification, 2022-12-29

### 0.0.1
* Initial release, 2022-12-27
