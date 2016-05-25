<?php

/**
 * 
 */
class Simi_Simiconnector_Model_Api_Customers extends Simi_Simiconnector_Model_Api_Abstract {

    protected $_DEFAULT_ORDER = 'entity_id';
    protected $_RETURN_MESSAGE;

    public function setBuilderQuery() {
        $data = $this->getData();
        if ($data['resourceid']) {
            switch ($data['resourceid']) {
                case 'forgetpassword':
                    Mage::getModel('simiconnector/customer')->forgetPassword($data);
                    $email = $data['params']['email'];
                    $this->builderQuery = Mage::getSingleton('customer/session')->getCustomer();
                    $this->_RETURN_MESSAGE = Mage::helper('customer')->__('If there is an account associated with %s you will receive an email with a link to reset your password.', Mage::helper('customer')->htmlEscape($email));
                    break;
                case 'profile':
                    $this->builderQuery = Mage::getSingleton('customer/session')->getCustomer();
                    break;
                case 'login':
                    if (Mage::getModel('simiconnector/customer')->login($data))
                        $this->builderQuery = Mage::getSingleton('customer/session')->getCustomer();
                    else
                        throw new Exception($this->_helper->__('Login Failed'), 4);
                    break;
                case 'logout':
                    if (Mage::getModel('simiconnector/customer')->logout($data))
                        $this->builderQuery = Mage::getSingleton('customer/session')->getCustomer();
                    else
                        throw new Exception($this->_helper->__('Logout Failed'), 4);
                    break;
                default:
                    $this->builderQuery = Mage::getModel('customer/customer')->load($data['resourceid']);
                    break;
            }
        } else {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $currentCustomerId = Mage::getSingleton('customer/session')->getId();
            }
            $this->builderQuery = Mage::getModel('customer/customer')->getCollection()
                    ->addFieldToFilter('entity_id', $currentCustomerId);
        }
    }

    /*
     * Register
     */

    public function store() {
        $data = $this->getData();
        $customer = Mage::getModel('simiconnector/customer')->register($data);
        $this->builderQuery = $customer;
        $this->_RETURN_MESSAGE = Mage::helper('customer')->__("Thank you for registering with " . Mage::app()->getStore()->getName() . " store");
        return $this->show();
    }

    /*
     * Update Profile
     */

    public function update() {
        $data = $this->getData();
        $customer = Mage::getModel('simiconnector/customer')->updateProfile($data);
        $this->builderQuery = $customer;
        $this->_RETURN_MESSAGE = Mage::helper('customer')->__('The account information has been saved.');
        return $this->show();
    }

    /*
     * Add Message
     */

    public function getDetail($info) {
        if ($this->_RETURN_MESSAGE) {
            $resultArray = parent::getDetail($info);
            $resultArray['message'] = array($this->_RETURN_MESSAGE);
            return $resultArray;
        }
        return parent::getDetail($info);
    }

}
