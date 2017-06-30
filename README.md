magento2-Twispay_Payments
======================

The official Twispay Payment Gateway extension for Magento2.

At the time of purchase, after checkout confirmation, the customer will be redirected to the secure Twispay Payment Gateway.

All payments will be processed in a secure PCI DSS compliant environment so you don't have to think about any such compliance requirements in your web shop. 

Install
=======

### Magento Marketplace

The recommended way of installing is through Magento Marketplace, where you can
find [The Official Twispay Payment Gateway Extension][marketplace].

### Manually

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash
    composer config repositories.twispay git https://github.com/Twispay/magento2.git
    composer require twispay/magento2-payments:dev-master
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Twispay_Payments --clear-static-content
    php bin/magento setup:upgrade
    ```
4. Enable and configure Twispay in Magento Admin under Stores/Configuration/Payment Methods/Twispay

Other Notes
===========

A functional description of the extension can be found on the [wiki page][doc]



[twispay]: http://twispay.com/
[marketplace]: https://marketplace.magento.com/twispay-magento2-payments.html
[doc]: https://twis.li/2spt8rx
