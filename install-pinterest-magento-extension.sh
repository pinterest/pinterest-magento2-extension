#!/usr/bin/env bash
echo "Starting Pinterest Magento Extension installation"

echo "Enabling extension..."
php bin/magento module:enable Pinterest_PinterestBusinessConnectPlugin

echo "Deploying static files..."
php bin/magento setup:static-content:deploy

echo "Installing component..."
php bin/magento setup:upgrade

echo "Compiling app..."
php bin/magento setup:di:compile

echo "Cleaning the cache..."
php bin/magento cache:clean

echo "Install all cron tasks"
php bin/magento cron:install
php bin/magento cron:run --group index

echo "Installation finished"