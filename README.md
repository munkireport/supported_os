Supported OS Module
==============

Lists the highest supported and shipping OS versions for a machine.

OS support data is updated by module once a week from MunkiReport's supported_os module GitHub page. Can be manually updated from admin page. Module will use data contained within module if unable to access OS support data on GitHub.

Shipping OS versions from [https://mrmacintosh.com/can-i-upgrade-or-downgrade-macos-every-mac-from-2006-2020/](https://mrmacintosh.com/can-i-upgrade-or-downgrade-macos-every-mac-from-2006-2020/)

Config Items
---
By default the module sends an event to the Messages widget when macOS is updated or upgraded. To hide these events, set `SUPPPORTED_OS_SHOW_MACOS_UPDATED` to `FALSE` in MunkiReport's `.env` config.
 
Remarks
---

* The client triggers the server to do a lookup once a week
* Admin page provides ability to process all machines at once


Table Schema
---
* current_os (int) The current OS version
* highest_supported (int) The highest supported OS version
* machine_id (string) Machine model ID
* last_touch (int) When highest supported OS was last processed
* shipping_os (int) The shipping version for the model
