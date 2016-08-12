# Overview

This repository contains a Magento 2 extension that connects Magento 2 with TurnTo's Social Commerce service. Compatible with Magento Community and Enterprise, versions 2.0.x - 2.1.0.

<!--# Extension User Guide

Find installation and configuration instructions here: TODO: Add link to guide-->

# Installation Instructions

## Install using Composer (recommended)

1. Run these commands in your root Magento installation directory (this extension is registered on Packagist):

    ```
    composer require turnto/social-commerce:dev-master
    bin/magento module:enable --clear-static-content TurnTo_SocialCommerce
    bin/magento setup:upgrade
    bin/magento cache:flush
    ```

2. If you are deploying the extension to a production environment, follow the [devdocs.magento.com deployment instructions](http://devdocs.magento.com/guides/v2.1/config-guide/prod/prod_deploy.html#deploy-prod)

## Install by copying files

1. Create an `app/code/TurnTo/SocialCommerce` directory in your Magento installation.
2. Download the latest "Source code" from this page: [https://github.com/turnto/magento2-turnto-socialcommerce/releases](https://github.com/turnto/magento2-turnto-socialcommerce/releases)
3. Extract the file and copy the contents of the TurnTo_SocialCommerce-*** directory into the `app/code/TurnTo/SocialCommerce` directory.
4. Run following commands from your root Magento installation directory:

    ```
    bin/magento module:enable --clear-static-content TurnTo_SocialCommerce
    bin/magento setup:upgrade
    bin/magento cache:flush
    ```

5. If you are deploying the extension to a production environment, follow the [devdocs.magento.com deployment instructions](http://devdocs.magento.com/guides/v2.1/config-guide/prod/prod_deploy.html#deploy-prod)

# About TurnTo Social Commerce

TurnTo is the fastest-growing provider of customer content solutions to top merchants and brands. With a unique suite of 4 innovative products that work beautifully together - Ratings & Reviews, Community Q&A, Visual Reviews, and Checkout Comments - TurnTo produces more content of more different types, delivering greater conversion lift, better SEO, and deeper merchandising insights. 

TurnTo clients enjoy a world-class Ratings & Reviews platform, a Q&A system designed to maximize customer engagement and help shoppers find fast answers from a variety of sources, a highly engaging source of real-time customer content with Checkout Comments, and a permissions-cleared source of customer photos and videos through Visual Reviews. TurnTo offers integrations with major eCommerce platforms and is built for the new world, reimagined for mobile, visual content, and messaging. ECommerce leaders like Saks Fifth Avenue, Newegg, Sur La Table, and GNC, and brands like Cole Haan, Jockey, and Clarins rely on TurnTo for their customer-voice programs.

# License

This project is licensed under the Open Software License 3.0 (OSL-3.0). See included LICENSE file for full text of OSL-3.0
