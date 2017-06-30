<?php
namespace Twispay\Payments\Api\Data;

/**
 * Interface OrderPaymentResponseInterface
 * @package Twispay\Payments\Api\Data
 */
interface OrderPaymentResponseInterface
{

    /**
     * Returns the siteId provided by Twispay
     *
     * @return int
     */
    public function getSiteId();

    /**
     * The customer identifier, must not start with a number
     * @return string
     */
    public function getIdentifier();

    /**
     * The customer's first name
     *
     * @return string|null
     */
    public function getFirstName();

    /**
     * The customer's last name
     *
     * @return string|null
     */
    public function getLastName();

    /**
     * The customer's email
     *
     * @return string|null
     */
    public function getEmail();

    /**
     * The customer's country
     *
     * @return string|null
     */
    public function getCountry();

    /**
     * The customer's state
     *
     * @return string|null
     */
    public function getState();

    /**
     * The customer's city
     *
     * @return string|null
     */
    public function getCity();

    /**
     * The customer's address zip code
     *
     * @return string|null
     */
    public function getZipCode();

    /**
     * The customer's address
     *
     * @return string|null
     */
    public function getAddress();

    /**
     * The customer's phone
     *
     * @return string|null
     */
    public function getPhone();

    /**
     * Gets the Amount for the payment.
     *
     * @return float Amount.
     */
    public function getAmount();

    /**
     * Return ISO 4217 three letter code for currency
     *
     * @return string
     */
    public function getCurrency();

    /**
     * The description of the transaction.
     * 77056 characters long; mandatory if item is not defined
     *
     * @return string|null
     */
    public function getDescription();

    /**
     * Return one of: “purchase”, “recurring”
     *
     * @return string
     */
    public function getOrderType();

    /**
     * Gets the OrderId for the payment.
     *
     * @return int|null OrderId.
     */
    public function getOrderId();

    /**
     * Gets the indexed array of string values – contains name of the products.
     *
     * @return array|null
     */
    public function getItem();

    /**
     * Returns an indexed array of float values corresponding to the price of each
     * item in the order
     *
     * @return array|null
     */
    public function getUnitPrice();

    /**
     * Returns an indexed array of integer values representing the number of unit of each
     * item in the order
     *
     * @return array|null
     */
    public function getUnits();

    /**
     * Returns an indexed array of float values representing the total price of each unit type
     *
     * @return array|null
     */
    public function getSubTotal();

    /**
     * The type of transaction to perform: AUTH or AUTH+Capture
     *
     * @return string
     */
    public function getCardTransactionMode();

    /**
     * The URL to return to after a successful or failed transaction
     *
     * @return string|null
     */
    public function getBackUrl();
    /**
     * Verification hash for the payment details
     *
     * @return string
     */
    public function getChecksum();
}
