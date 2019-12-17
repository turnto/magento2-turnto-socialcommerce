##3.1.1 (2019-12-17)
* Fixed bug that caused setup:di:compile to fail 

##3.1.0 (2019-12-17)
* Added checkout comment pinboards ([documentation](https://docs.turnto.com/en/ecommerce-platforms/magento.html#al_UUID-e25540c2-ef56-12c7-f40d-597ebd2bc751_section-idm13153034345814))
* Added visual content pinboards ([documentation](https://docs.turnto.com/en/ecommerce-platforms/magento.html#al_UUID-e25540c2-ef56-12c7-f40d-597ebd2bc751_section-idm13153034343116))
* Added the Magento and TurnTo module version to turnToConfig object ([documentation](https://docs.turnto.com/en/ecommerce-platforms/magento.html#al_UUID-e25540c2-ef56-12c7-f40d-597ebd2bc751_section-idm4650205841459231530342733983))

##3.0.4 (2019-10-16)
* Module now searches for Magento 2 root and appends it to export file paths. This fixes some read/write errors for servers running in a non-standard configuration. 
  
##3.0.3 (2019-10-10)
* Added version to composer.json

##3.0.2 (2019-08-02)
* Fixed historical order feed cron job upload.

##3.0.1 (2019-05-14)
* Added Magento 2.3 Compatibility

##3.0.0 (2019-05-07)
* New version release for compatibility with JS widget v5

## 2.1.3 (2019-04-18)
* Updated README and made new version number for Magento Marketplace Release

## 2.1.2 (2018-10-24)

* Fix issue with catalog export when stock item could not be found

## 2.1.1 (2018-07-02)

* Fix issue with custom configuration default value

## 2.1.0 (2018-06-08)

* Handle special characters not supported by turnto ([#44](https://github.com/turnto/magento2-turnto-socialcommerce/issues/44))

## 2.0.0 (2018-04-13)

* Improve HttpClient logging for debugging errors during retrieval of embedded content ([#38](https://github.com/turnto/magento2-turnto-socialcommerce/issues/38))
* Add support for using child SKU instead of parent SKU option in 2.2.x ([#29](https://github.com/turnto/magento2-turnto-socialcommerce/issues/29))
* Fix order export failing due to sorting on incorrect order column ([#33](https://github.com/turnto/magento2-turnto-socialcommerce/issues/33))
* Fix stock availability status not being set for catalog export ([#39](https://github.com/turnto/magento2-turnto-socialcommerce/issues/39))
* Fix issue where admin configured "Custom Configuration" was not getting output on product detail pages ([#41](https://github.com/turnto/magento2-turnto-socialcommerce/issues/41))
    * Refactored TurnTo javascript config generation to centralized Block
	* Developers can now intercept config generation through Magento plugins to customize functionality

## 1.3.0 (2018-01-19)
* Added [documentation](https://github.com/turnto/magento2-turnto-socialcommerce#rating-import-from-turnto) explaining how reviews are pulled from TurnTo
* Fixed issue where category pages don't display proper review count/rating until manual reindex is performed ([#31](https://github.com/turnto/magento2-turnto-socialcommerce/issues/31)) and ([#23](https://github.com/turnto/magento2-turnto-socialcommerce/issues/23))
* Increase frequency of importing reviews from TurnTo ([#30](https://github.com/turnto/magento2-turnto-socialcommerce/issues/30))
* Fixed DI compilation error on Magento 2.2.x ([#30](https://github.com/turnto/magento2-turnto-socialcommerce/issues/26))

## 1.2.0 (2017-12-12)
* Add Single Sign On functionality to the mobile landing page ([#24](https://github.com/turnto/magento2-turnto-socialcommerce/pull/24))

## 1.1.2 (2017-10-18)
* Add option to include/exclude order items from historical feed based on whether they've been shipped ([#20](https://github.com/turnto/magento2-turnto-socialcommerce/issues/20))
* Misc bug fix ([#21](https://github.com/turnto/magento2-turnto-socialcommerce/issues/21))

## 1.1.1 (2017-10-04)
* Add option have a "from" date when exporting historical orders ([#18](https://github.com/turnto/magento2-turnto-socialcommerce/issues/18))
* Make it possible for a developer to group reviews for similar products by modifying g:item_group_id value in the catalog feed ([#17](https://github.com/turnto/magento2-turnto-socialcommerce/pull/17)) 
* Fix catalog/order feed export issue ([#16](https://github.com/turnto/magento2-turnto-socialcommerce/issues/16))
* Misc bug fixes

## 1.1.0 (2017-09-24)
* Add SSO (Single Sign On) feature ([#14](https://github.com/turnto/magento2-turnto-socialcommerce/issues/14))
* Add support for using child/variant SKUs instead of parent SKUs ([#8](https://github.com/turnto/magento2-turnto-socialcommerce/issues/8))

## 1.0.4 (2017-07-28)

* Add user-supplied turnToConfig JS to all places it is used ([#5](https://github.com/turnto/magento2-turnto-socialcommerce/issues/5))
* Fix errors when attempting to import ratings ([#4](https://github.com/turnto/magento2-turnto-socialcommerce/issues/4))
* Fix error when historical orders are exported with products that have been deleted ([#3](https://github.com/turnto/magento2-turnto-socialcommerce/issues/3))
* Increase CRON history lifetime to provide more robust debugging information

## 1.0.3 (2017-06-29)

* Allow admins to add custom values to the turnToConfig object ([see docs](https://github.com/turnto/magento2-turnto-socialcommerce#custom-configuration))
* Fix issue where reviews were not showing on product listing or search result pages ([#7](https://github.com/turnto/magento2-turnto-socialcommerce/issues/7))

## 1.0.2 (2017-06-13)

* Fix product image url in catalog feed export

## 1.0.1 (2016-08-24)

* Fixed composer install for Magento 2.0. Works on 2.0 and 2.1 now.

## 1.0.0 (2016-08-09)

* Initial Release
