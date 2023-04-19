<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Customer\Model\Session;

/**
 * Returns customer related data
 */
class CustomerDataHelper
{
    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @param Session $customerSession
     */
    public function __construct(
        Session $customerSession
    ) {
        $this->_customerSession = $customerSession;
    }

    /**
     * Returns if the user is logged In
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
