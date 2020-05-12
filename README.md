
NextCloud Delayed Previews
==========================

I have Nextcloud installed on a lightweight single-board computer.
Every time I open a folder with lots of media files it takes long time to display the thumbnails, because
thumbnails are generated on the fly if not pre-generated.
Many preview generation requests are handled in parallel, causing very high system load.
I wrote this app to handle all preview cache missing requests by pushing the generation tasks to Redis list while
simply returning 404 Not Found responses or just a small waiting image. Then all queued tasks are handled by
a cron job sequentially.

Denpendencies
-------------
 - Nextcloud 18+
 - Redis
 - some patience

Usage
-----

Clone this repository into your Nextcloud app folder and then enable it.

Set up a cron job to handle queued preview generation tasks:
```
*/3 * * * * php occ preview:generate-delayed
```

Or if you run Nextcloud in a docker container:
```
*/3 * * * * /usr/bin/docker exec --user www-data {nextcloud_container} php occ preview:generate-delayed
```

Config to display a waiting image when the thumbnail not ready:

```
php occ config:system:set enable_waiting_previews --value=true --type=boolean
```

For container users:

```
docker exec --user www-data {nextcloud_container} php occ config:system:set enable_waiting_previews --value=true --type=boolean
```
