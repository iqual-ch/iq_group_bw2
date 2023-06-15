# Integration of GCB Drupal Website with the CRM BW2

This module has two objectives:
* on every user creation / user update request, a listener is triggered.
This sends all the user data to the CRM bw2 where they are saved.
* once a day, a cron job will run to import new users /
updated users from the CRM to Drupal.

This module require the iqual/bw2_api module.

## Installation and basic usage

* Add the module as usual and activate.
* Configure the API under /admin/config/services/bw2-api
* All informations are available on lastpass notice GCB - BW2 API Portal ID
