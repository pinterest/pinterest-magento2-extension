<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Returns customer related data
 */
class CustomerDataHelper
{
    /**
     * @var CustomerSession
     */
    protected $_customerSession;

    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Returns if the user is logged In
     *
     * @return true if user is logged in
     */
    public function isUserLoggedIn()
    {
        return $this->_customerSession->isLoggedIn();
    }

    /**
     * Returns the email id
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->_customerSession->getCustomer()->getEmail();
    }

    /**
     * Returns the first name
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->_customerSession->getCustomer()->getFirstname();
    }

    /**
     * Returns the last name
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->_customerSession->getCustomer()->getLastname();
    }

    /**
     * Returns the date of birth of the logged in user if present
     *
     * The dob of birth returned from the customer object is
     * in the format yyyy-mm-dd and we need to send it in the
     * format yyyymmdd to the conversions API
     *
     * @return string|null
     */
    public function getDateOfBirth()
    {
        $dob = $this->_customerSession->getCustomer()->getDob();
        return $dob ? str_replace("-", "", $dob) : null;
    }

    /**
     * Returns the city of the user from the last order
     *
     * @return string|null
     */
    public function getCity()
    {
        if ($this->_checkoutSession->getLastRealOrder()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getCity()) {
                return str_replace(
                    ' ',
                    '',
                    strtolower($this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getCity())
                );
        }
        return null;
    }

    /**
     * Returns the state of the user from the last order
     *
     * @return string|null
     */
    public function getState()
    {
        if ($this->_checkoutSession->getLastRealOrder()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getRegionCode()) {
                return strtolower($this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getRegionCode());
        }
        return null;
    }

    /**
     * Returns the country of the user from the last order
     *
     * @return string|null
     */
    public function getCountry()
    {
        if ($this->_checkoutSession->getLastRealOrder()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getCountryId()) {
                return $this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getCountryId();
        }
        return null;
    }

    /**
     * Returns the zipcode of the user from the last order
     *
     * @return string|null
     */
    public function getZipCode()
    {
        if ($this->_checkoutSession->getLastRealOrder()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()
            && $this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getPostcode()) {
                return $this->_checkoutSession->getLastRealOrder()->getBillingAddress()->getPostcode();
        }
        return null;
    }

    /**
     * Returns the gender of the logged in user
     *
     * Customer object returns gender with the following map
     * null => 0
     * male => 1
     * female => 2
     * non-specified => 3
     * We convert it into the format expected by the conversions API
     *
     * @return string|null
     */
    public function getGender()
    {
        $gender_code = $this->_customerSession->getCustomer()->getGender();
        switch ($gender_code) {
            case 1:
                return "m";
            case 2:
                return "f";
            case 3:
                return "n";
            default:
                return null;
        }
    }

    /**
     * Returns the sha256 hashed value of the input
     *
     * @param string $value
     * @return string|null
     */
    public function hash($value)
    {
        return hash('sha256', strtolower($value ?? ''));
    }
}
