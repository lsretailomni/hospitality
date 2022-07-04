# LS Ecommerce - Hospitality (1.17.0)

## Compatibility:
1. Magento Commerce/Enterprise 2.3.5 - current version
2. LS Central 16.x - current version
3. LS Omni 4.14.x - current version
4. [ LS eCommerce - Base package](https://github.com/lsretailomni/lsmag-two) - v1.17.0 or onwards 

## Installation:

1. Navigate to your magento2 installation directory and run `composer require "lsretail/hospitality"` .
2. Run `composer install` to install all the dependencies it needs.
3. To enable all our modules, run command from command line, `php bin/magento module:enable Ls_Hospitality`.
4. Once done, you will see the list of our modules by running `php bin/magento module:status` which means our module is now good to go.
5. Run `php bin/magento setup:upgrade ` and  `php bin/magento setup:di:compile` from root directory to update magento2 database with the schema and generate interceptor files.
6. Once done, you will see the list of our modules in enabled section by running `php bin/magento module:status`.
7. Configure the connection with LS Central by navigating to LS Retail -> Configuration from Magento Admin panel, enter the base url of the Omni server and choose the store and Hierarchy code to replicate data. Make sure to do all the configurations which are required on the Omni server for ecommerce i-e disabling security token for authentication.
8. If you are unable to find the fields for base url, store or Hierarchy is the LS Retail configuration, then make sure the scope of system configuration in the top left corner is set to "website",
9. If your server is setup for cron, then you will see all the new crons created in the `cron_schedule` table and status of all the replication data by navigating to LS Retail -> Cron Listing from the Admin Panel.
10. To Trigger the cron manually from admin panel, navigate to LS Retail -> Cron Listing from the left menu and click on the cron which needs to be run.
11. To check the status of data replicated from LS Central, navigate to any Replication job from `LS Retail -> Replication Status` and there we can see the list of all data along with status with `Processed` or `Not Processed` in the grid.

## Support
All LS Retail active partners can use [ LS Partner & Customer Portal](https://portal.lsretail.com/ "LS Retail Partner & Customer Portal") to submit the technical support request.

- Partner Portal: https://portal.lsretail.com/
- https://www.lsretail.com/contact-us
- support@lsretail.com
