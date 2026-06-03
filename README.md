## Emplifi Ratings & Reviews module for Magento 2

**(Formerly TurnTo Social Commerce)**

Use this Magento 2 extension to connect to [Emplifi Ratings & Reviews](https://emplifi.io/product/online-customer-reviews). Compatible with Magento Open Source and Adobe Commerce, versions 2.3–2.4.

The Emplifi Ratings & Reviews extension integrates Adobe Commerce and
Magento Open Source with Emplifi's Ratings & Reviews user-generated content platform. This
extension enables merchants to:

- Display customer reviews and ratings for products
- Display visual reviews and user-generated image galleries
- Capture user comments during checkout
- Provide CMS widgets for pinboards and landing pages
- Syndicate user-generated content through Emplifi widgets
- Configure widget behavior from the Adobe Commerce Admin
- Synchronize product and order data with Emplifi services

## Overview

Emplifi is a premier provider of customer content solutions to top merchants and brands. With a unique suite of four innovative products that work beautifully together - Ratings & Reviews, Community Q&A, Visual Reviews, and Checkout Comments – Emplifi produces more content of more different types, delivering greater conversion lift, better SEO, and deeper merchandising insights.

Emplifi clients enjoy a world-class Ratings & Reviews platform, a Q&A system designed to maximize customer engagement and help shoppers find fast answers from a variety of sources, a highly engaging source of real-time customer content with Checkout Comments, and a permissions-cleared source of customer photos and videos through Visual Reviews. Emplifi offers integrations with major eCommerce platforms and is built for the new world, reimagined for mobile, visual content, and messaging.

## Installation Instructions

### Install using Composer (recommended)

Follow the Adobe documentation to [Install an extension](https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/tutorials/extensions.html?lang=en)

Composer Package [turnto/social-commerce](https://packagist.org/packages/turnto/social-commerce)

1. Run these commands in your root Magento installation directory for composer install:

    ```
    composer require turnto/social-commerce
    bin/magento module:enable TurnTo_SocialCommerce --clear-static-content
    bin/magento setup:upgrade
    bin/magento setup:di:compile
    bin/magento cache:clean
    ```

2. Configure the module to connect to your Emplifi account. Please see the [Configuration](#configuration) section below.

### Install by copying files

1. Create a `code/TurnTo/SocialCommerce` directory in the `app` directory of your Magento installation.
2. Download the latest "Source code" from this page: [https://github.com/turnto/magento2-turnto-socialcommerce/releases](https://github.com/turnto/magento2-turnto-socialcommerce/releases)
3. Extract the file and copy the contents of the TurnTo_SocialCommerce-*** directory into the `app/code/TurnTo/SocialCommerce` directory.
4. Run the following commands from your root Magento installation directory:

    ```
    bin/magento module:enable TurnTo_SocialCommerce --clear-static-content
    bin/magento setup:upgrade
    bin/magento setup:di:compile
    bin/magento cache:clean
    ```

5. Configure the module to connect to your Emplifi account. Please see the [Configuration](#configuration) section below.

## Configuration

After installing the extension, configure it in the Admin:

**Stores > Configuration > Emplifi Ratings & Reviews > Configuration**

### Enable the extension
To enable the extension, you need to set **Enable Social Commerce** to **Yes** and enter your Emplifi **Site Key** and **Auth
Key** under **General Settings**. These credentials are required for the extension to connect to Emplifi services.

You can retrieve your Site Key and Auth Key from the [Emplifi portal](https://emplifi.io/login).

* **Use child SKU**
    * **No:** Configurable parent SKUs are used, and the catalog feed exports visible catalog products.
    * **Yes:** Child/simple SKUs are used where applicable. For the catalog feed, simple child products may be exported so variant SKUs are included, with parent data used for grouping/URLs where needed.

Enable the following features as desired:
* **Enable Reviews** – Enables the display of product reviews and ratings.
* **Enable Q&A** – Enables the display of Question & Answer content.
* **Enable PDP Gallery Row** – Enables the display of Gallery Row on product detail pages.

### Teaser
**Teaser Type** is used to select how the teaser is added to product detail pages. 
* **Use Teaser Widget** – Using the Teaser Widget is recommended as this provides the latest features and can be fully managed and customized through your Emplifi account.
* **Use Local Teaser Code** – This is a legacy implementation and should only be used for sites already using this implementation.

### Checkout Comments
When enabled, the extension will add the required code to capture user comments during checkout and display the comments.
This feature requires enabling JavaScript to capture order data in real-time.
* **Enable JavaScript Order Feed** – Enable the JavaScript that captures order data in real-time (required for comment capture).
* **Enable Checkout Comment Capture** – Enable the comment capture form on the checkout success page.
* **Enable Comments Display** – Enable the comments display on the product details page.
* **Enable Top Comments Display** – Enable the top comments display on the product details page.
* **Enable Comments Pinboard Teaser** – Enable the comments pinboard teaser when the widget is added to a page.
* **Customer Name Fallback** – Select which address to retrieve the customer's name from if the customer does not have an account.

### Product Feed Export to Emplifi

If the **Enable Automated Feed Submission** configuration option is set to **Yes**, Magento will export a feed of all products to Emplifi nightly. This feed will include links to product images. In order for this link to be accurate, you will need to do the following:

1. Login to the backend.
2. Go to **Stores > Configuration > General > Web**
3. Do the following (if you have multiple store views, follow these steps for each "Store View" scope):
    1. For the **Base URLS > Base URL for User Media Files** field, ensure a value is entered. If the field is blank, enter this value: `{{unsecure_base_url}}media/`
    2. For the **Base URLS (Secure) > Secure Base URL for User Media Files** field, ensure a value is entered. If the field is blank, enter this value: `{{secure_base_url}}media/`
       Here is a screenshot of an example configuration:

#### Product Detail Pages

Reviews displayed on product detail pages are loaded directly from Emplifi. The method used to retrieve and display review content is determined by the **Setup Type** configuration setting.

#### Product Listing Pages

Review ratings displayed on category, search, and other product listing pages are sourced from the Magento product attributes **Review Count** and **Rating**. These
attributes are updated by the Import Ratings CRON job that retrieves a rating feed from Emplifi and updates the corresponding product attributes.

The rating feed is generated by Emplifi three times daily at approximately:

* 3:00 AM EST/EDT
* 11:00 AM EST/EDT
* 6:00 PM EST/EDT

After a review is approved in Emplifi, changes will not appear on the product listing page until the next feed is generated and processed by the scheduled CRON job.


### Historical Orders Feed Export to Emplifi

This feed exports order data for any order that has been modified in the previous two days will be sent to Emplifi nightly. Emplifi uses this order feed data to populate owner pools for Q&A and to schedule review solicitation emails based optionally on ship date instead of order date. In the feed file, the ship date (DELIVERYDATE) column is the creation date of the shipment that contains that specific order item.

* **Enable Automated Feed Submission** – Enable the order export run nightly by the cron.
* **Enable Cancelled Order Feed Submission** – Enable the canceled order export run nightly by the cron.
* **Exclude Items without a Delivery Date**
    * **No:** All order items whose orders have been modified in the previous two days will be sent during each nightly CRON job (which means many order items will have a blank value for "DELIVERYDATE").
    * **Yes:** Only the order items who have been shipped will get sent (which means all order items will have a "DELIVERYDATE" value).
* **Delivery Date on Full Shipment**
    * **No:** Delivery Date will be populated normally.
    * **Yes:** Delivery Date will only be populated if all the items in the order have shipped

### Average Rating Feed Import from Emplifi

This feature synchronizes product review data from Emplifi to displayed throughout the storefront by populating the product rating attributes.

* **Enable Automated Feed Import** – Enable the average rating import run nightly by the cron.
* **Aggregate Related Review Count**
    * **No:** The Review Count attribute will be populated with the number of reviews, NOT including related reviews.
    * **Yes:** The Review Count attribute will be populated based on the number of reviews and related reviews.

### Custom Configuration

Note: This is an advanced configuration option and should only be configured by a developer.

If you need to customize the `turnToConfig` JS object which used by the extension to display content on the frontend, you can do so by adding a JS object to the **Stores > Configuration > Emplifi Ratings & Reviews > Configuration > Custom Configuration** field. You must enter a valid JS object into this field, as the contents of the JS object that you enter will get __merged__ with the contents of the existing `turnToConfig` object. Here is an example of a valid value that could be added to the **Custom Configuration** field:

```
{
    reviewTitleInstruction: 'Summary for your review',
    eventHandlers: {
         reviewSubmit: function(evt) {
            // your code here
         }
    }
}
```

These are the following locations where the `turnToConfig` object gets output on the frontend:

* Product detail pages (reviews, Q&A, checkout comment display widget, visual gallery widget)
* Checkout comments on the checkout confirmation page
* Pinboard widget

When you add values to this configuration field, it is critical that you enter a valid JS object and that you test to ensure you didn't cause any JS errors.

### Landing Page Widget

To add a widget for your email landing page, follow these steps:
1. Navigate to **Content > Pages** and create or edit the desired page.
2. Edit the page content using Page Builder.
3. Add an **HTML Code** element where the widget should appear.
4. Open the HTML editor and click **Insert Widget...**.
5. Select "TurnTo SpeedFlex – Landing Page" as the **Widget Type**.
6. Click **Insert Widget**, then save the page.

### Pinboard Widgets

The Emplifi extension provides three pinboard widget types:

* Comments Pinboard
* Comment Pinboard Teaser
* Visual Content Pinboard

To add a pinboard widget to a CMS page or CMS block:

1. Navigate to **Content > Pages or Content > Blocks** and create or edit the desired content.
2. Edit the content using Page Builder.
3. Add an HTML Code content type where the widget should appear. 
4. Open the HTML editor and click **Insert Widget...**.
5. Select "TurnTo SpeedFlex – Pinboard" as the Widget Type.
6. Choose the desired Content Type:
   * Comments Pinboard
   * Comment Pinboard Teaser
   * Visual Content Pinboard
7. Configure any additional widget options.
8. Click **Insert Widget**, then save the page or block.

## CRON Schedule

This extension depends on the Magento CRON to synchronize data. Reference the [Adobe Documentation](https://experienceleague.adobe.com/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs.html?lang=en) for details on
how to configure the Magento CRON. The exports are scheduled to run nightly, but the cron times can be modified if the
default times cause a conflict. The Rating Import is scheduled to run after the file creation times in Emplifi. It is
not recommended to move these times earlier as the updated files may not be created.

## Troubleshooting

### Logging
If you experience issues with the extension, review the extension logs in `var/log/turnto.log`.

### Product Feed Export

#### View the product feed file
On the server, open the feed file written under `var/turnto/`. If a catalog is split into multiple batches, the file is overwritten, so only the last batch sent will be available.

### Rating Synchronization

If review counts or ratings are not updating as expected, verify that the rating feed is available from Emplifi. The feed can be accessed using the following URL format:

```text
https://export.turnto.com/<SITE_KEY>/<AUTHORIZATION_KEY>/turnto-skuaveragerating.xml
```

Replace `<SITE_KEY>` and `<AUTHORIZATION_KEY>` with values from your Emplifi account.

## License

This project is licensed under the [Open Software License (OSL 3.0)](https://opensource.org/licenses/osl-3.0.php) – Please see [LICENSE.txt](LICENSE.txt) for the full text of the OSL 3.0 license.
