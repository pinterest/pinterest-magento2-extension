#!/usr/bin/env bash
echo "Updating Magento"
cd /home/cloudpanel/htdocs/magento2.mgt/

echo "Installing component..."
php bin/magento setup:upgrade

echo "Compiling app..."
php bin/magento setup:di:compile

echo "Cleaning the cache..."
php bin/magento cache:clean

echo "Deploying..."
php bin/magento setup:static-content:Deploy -f

echo "Providing correct access"
chmod -R 777 var/ generated/ pub/

echo "Completed"