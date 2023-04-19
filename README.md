# Pinterest magento Extension

This repo contains code for magento extension of pinterest.

The Pinterest (Alpha) Integration Plugin allows Adobe Commerce Cloud store owners to easily showcase their products to millions of Pinterest users. Merchants can upload their product catalog to Pinterest , making their products visible to potential customers on Pinterest. Product stock and pricing are automatically updated in near real-time, ensuring that the information displayed on Pinterest is always up-to-date.. Conversion tracking provided by the plugin allows merchants to track conversions using both frontend JavaScript and backend API tracking. For example, a clothing store could use the Pinterest Integration Plugin to showcase their latest seasonal collections on Pinterest, helping increase visibility and drive more sales.

## Requirements
 - Magento Opensource/Enterprise Edition Adobe Commerce Cloud store
 - Magento 2.4.4+
 - php 8.1+
 - Pinterest business account


## Extension installation

The extension is available in Adobe marketplace at https://marketplace.magento.com/

To install this extension manually run the following command from the top level magento folder

- `cd app/code`
- `mkdir pinterest/pinterestmagento2extension`
- clone this repo
- `cd pinterest/pinterestmagento2extension`
- `sh install-pinterest-magento-extension.sh`

## Unit tests

To run a unit tests from this module (replace app/code with vendor if you installed from compser)

vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Pinterest/PinterestMagento2Extension/Test/Unit/

## Cron tasks

- To install all cron tasks to system crontab : `bin/magento cron:install`
- To verify system crontab `crontab -l`
- To run the catalog export cron task included in this extension : `bin/magento cron:run --group index`

## composer

Composer is required for this repo. You can find more info at https://devdocs.magento.com/guides/v2.3/extension-dev-guide/build/composer-integration.html

Perform `vendor/composer/composer/bin/composer update` when you make composer change

## code sniffer

From the magento root directory run the following command to see code sniffer issues

`vendor/bin/phpcs --standard=Magento2 --ignore=./app/code/Pinterest/PinterestMagento2Extension/vendor ./app/code/Pinterest/PinterestMagento2Extension/`

To auto fix some issues

`vendor/bin/phpcbf --standard=Magento2 --ignore=./app/code/Pinterest/PinterestMagento2Extension/vendor ./app/code/Pinterest/PinterestMagento2Extension/`

The following page explains the coding standards - https://developer.adobe.com/commerce/php/coding-standards/

## configuration values

The default values are in /etc/config.xml . You can modify the file during development locally to suite your needs. To checkin new changes remove it temporarily from .gitignore and then add it back so that developer env can modify that file.

## Frequently Asked Questions (FAQs)
Q: Can I use the plugin with a personal Pinterest account?
A: No, the plugin is designed to work only with Pinterest business accounts.

Q: How often is the product catalog updated with Pinterest?
A: The catalog is updated daily. Product updates for a limited set of attributes (stock/price) are sent in real-time after the Pins are ingested.

Q: Can I track Pinterest conversions?
A: Yes, the plugin provides a frontend JavaScript tracking code. It also provides backend conversion API tracking for more accurate conversion data.
