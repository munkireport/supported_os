Supported OS Module
==============

Lists the highest supported and shipping OS versions for a machine.

OS support data is updated by module once a week from MunkiReport's supported_os module GitHub page. Can be manually updated from admin page. Module will use data contained within module if unable to access OS support data on GitHub.

Shipping OS versions from [https://mrmacintosh.com/can-i-upgrade-or-downgrade-macos-every-mac-from-2006-2020/](https://mrmacintosh.com/can-i-upgrade-or-downgrade-macos-every-mac-from-2006-2020/)
 
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
* model_support_cache (text) Column is only use by one row to store the JSON cache file
