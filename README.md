## Pinterest magento Extension

This repo contains the code for the Pinterest Magento extension.

The Pinterest (Alpha) integration plugin allows Adobe Commerce Cloud store owners to easily showcase their products to millions of Pinterest users. Merchants can upload their product catalog to Pinterest, making their products visible to potential customers on Pinterest. Product stock and pricing are automatically updated in near real-time, ensuring that the information displayed on Pinterest is always up-to-date. Conversion tracking provided by the plugin allows merchants to track conversions using both frontend JavaScript and backend API tracking. For example, a clothing store could use the Pinterest integration plugin to showcase their latest seasonal collections on Pinterest, helping increase visibility and drive more sales.

## Requirements
 - Magento Opensource/Enterprise Edition Adobe Commerce Cloud store
 - Magento 2.4.4+
 - php 8.1+
 - Pinterest business account


## Extension installation

The extension is available in Adobe marketplace at https://marketplace.magento.com/

To install this extension, manually run the following command from the top level magento folder

- `cd app/code`
- `mkdir pinterest/pinterestmagento2extension`
- clone this repo
- `cd pinterest/pinterestmagento2extension`
- `sh install-pinterest-magento-extension.sh`

## Unit tests

To run a unit tests from this module (replace app/code with vendor if you installed from composer)

`vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Pinterest/PinterestMagento2Extension/Test/Unit/`

## Composer

Composer is required for this repo. You can find more info at https://devdocs.magento.com/guides/v2.3/extension-dev-guide/build/composer-integration.html

Perform `composer update` when you make the composer change

## Cron tasks

- To install all cron tasks to system crontab : `bin/magento cron:install`
- To verify system crontab `crontab -l`
- To run the catalog export cron task included in this extension : `bin/magento cron:run --group index`

## Code sniffer

From the magento root directory run the following command to see code sniffer issues

`vendor/bin/phpcs --standard=Magento2 --ignore=./app/code/Pinterest/PinterestMagento2Extension/vendor ./app/code/Pinterest/PinterestMagento2Extension/`

To auto fix some issues

`vendor/bin/phpcbf --standard=Magento2 --ignore=./app/code/Pinterest/PinterestMagento2Extension/vendor ./app/code/Pinterest/PinterestMagento2Extension/`

The following page explains the coding standards - https://developer.adobe.com/commerce/php/coding-standards/

## Configuration values

The default values are in `/etc/config.xml` . You can modify the file during development locally to suit your needs. To check new changes, remove it temporarily from .gitignore and then add it back so that developer env can modify that file.

## Translations

To translate new strings run the below command from magento root instalation

`bin/magento i18n:collect-phrases --output="app/code/Pinterest/PinterestMagento2Extension/i18n/en_US.csv" app/code/Pinterest/PinterestMagento2Extension/`

https://devdocs.magento.com/guides/v2.3/config-guide/cli/config-cli-subcommands-i18n.html#config-cli-subcommands-xlate-dict

To deploy a new language you can follow the following format

`php bin/magento setup:static-content:deploy es_MX -f`

To get the list of locales use the following command

`php bin/magento info:language:list`

## Frequently Asked Questions (FAQs)
Q: Can I use the plugin with a personal Pinterest account?
A: No, the plugin is designed to work only with Pinterest business accounts.

Q: How often is the product catalog updated with Pinterest?
A: The catalog is updated daily. Product updates for a limited set of attributes (stock/price) are sent in real-time after the Pins are ingested.

Q: Can I track Pinterest conversions?
A: Yes, the plugin provides a frontend JavaScript tracking code. It also provides backend conversion API tracking for more accurate conversion data.
