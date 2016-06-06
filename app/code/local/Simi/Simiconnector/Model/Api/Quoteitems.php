<?php

class Simi_Simiconnector_Model_Api_Quoteitems extends Simi_Simiconnector_Model_Api_Abstract {

    protected $_DEFAULT_ORDER = 'item_id';
    protected $_RETURN_MESSAGE;

    protected function _getCart() {
        return Mage::getSingleton('checkout/cart');
    }

    protected function _getQuote() {
        return $this->_getCart()->getQuote();
    }

    public function setBuilderQuery() {
        $data = $this->getData();
        if ($data['resourceid']) {
            if ($data['resourceid'] == 'setcoupon') {
                $this->setCoupon($data);
            }
        }
        $this->updateBuilderQuery();
    }

    public function updateBuilderQuery() {
        $quote = $this->_getQuote();
        $this->builderQuery = $quote->getItemsCollection()->addFieldToFilter('parent_item_id', array('null' => true));
    }

    public function update() {
        $data = $this->getData();
        $quote = $this->_getQuote();
        $parameters = (array) $data['contents'];
        $this->updateQuote($parameters);
        return $this->index();
    }

    public function updateQuote($parameters) {
        $cartData = array();
        $quote = $this->_getQuote();
        foreach ($parameters as $index => $qty) {
            $cartData[$index] = array('qty' => $qty);
        }
        if (count($cartData)) {
            $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
            );
            $removedItems = array();
            foreach ($cartData as $index => $data) {
                if (isset($data['qty'])) {
                    $cartData[$index]['qty'] = $filter->filter(trim($data['qty']));
                    if ($data['qty'] == 0) {
                        $removedItems[] = $index;
                    }
                }
            }
            $this->builderQuery->addFieldToFilter('item_id', array('neq' => $removedItems));
            $cart = $this->_getCart();
            if (!$cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                $cart->getQuote()->setCustomerId(null);
            }

            if (version_compare(Mage::getVersion(), '1.4.2.0', '>=') === true) {
                $cartData = $cart->suggestItemsQty($cartData);
            }
            $cart->updateItems($cartData)
                    ->save();
            Mage::getSingleton('checkout/session')->setCartWasUpdated(true);
        }
    }

    public function setCoupon($data) {
        if (!isset($data['params']['coupon_code']))
            return;
        $couponCode = $data['params']['coupon_code'];

        $this->_getCart()->getQuote()->getShippingAddress()->setCollectShippingRates(true);
        $this->_getCart()->getQuote()->setCouponCode(strlen($couponCode) ? $couponCode : '')
                ->collectTotals()
                ->save();
        $total = $this->_getCart()->getQuote()->getTotals();
        $return['discount'] = 0;
        if ($total['discount'] && $total['discount']->getValue()) {
            $return['discount'] = abs($total['discount']->getValue());
        }
        if (strlen($couponCode)) {
            if ($couponCode == $this->_getCart()->getQuote()->getCouponCode() && $return['discount'] != 0) {
                $this->_RETURN_MESSAGE = Mage::helper('simiconnector')->__('Coupon code "%s" was applied.', Mage::helper('core')->htmlEscape($couponCode));
            } else {
                $this->_RETURN_MESSAGE = Mage::helper('simiconnector')->__('Coupon code "%s" is not valid.', Mage::helper('core')->htmlEscape($couponCode));
            }
        } else {
            $this->_RETURN_MESSAGE = Mage::helper('simiconnector')->__('Coupon code was canceled.', Mage::helper('core')->htmlEscape($couponCode));
        }
    }

    public function show() {
        return $this->index();
    }

    public function index() {
        $collection = $this->builderQuery;
        $this->filter();
        $data = $this->getData();
        $parameters = $data['params'];
        $page = 1;
        if (isset($parameters[self::PAGE]) && $parameters[self::PAGE]) {
            $page = $parameters[self::PAGE];
        }

        $limit = self::DEFAULT_LIMIT;
        if (isset($parameters[self::LIMIT]) && $parameters[self::LIMIT]) {
            $limit = $parameters[self::LIMIT];
        }

        $offset = $limit * ($page - 1);
        if (isset($parameters[self::OFFSET]) && $parameters[self::OFFSET]) {
            $offset = $parameters[self::OFFSET];
        }
        $collection->setPageSize($offset + $limit);

        $all_ids = array();
        $info = array();
        $total = $collection->getSize();

        if ($offset > $total)
            throw new Exception($this->_helper->__('Invalid method.'), 4);

        $fields = array();
        if (isset($parameters['fields']) && $parameters['fields']) {
            $fields = explode(',', $parameters['fields']);
        }

        $check_limit = 0;
        $check_offset = 0;

        /*
         * Add options and image
         */
        foreach ($collection as $entity) {
            if (++$check_offset <= $offset) {
                continue;
            }
            if (++$check_limit > $limit)
                break;

            $options = array();
            if (version_compare(Mage::getVersion(), '1.5.0.0', '>=') === true) {
                $helper = Mage::helper('catalog/product_configuration');
                if ($entity->getProductType() == "simple") {
                    $options = Mage::helper('simiconnector/checkout')->convertOptionsCart($helper->getCustomOptions($entity));
                } elseif ($entity->getProductType() == "configurable") {
                    $options = Mage::helper('simiconnector/checkout')->convertOptionsCart($helper->getConfigurableOptions($entity));
                } elseif ($entity->getProductType() == "bundle") {
                    $options = Mage::helper('simiconnector/checkout')->getOptions($entity);
                }
            } else {
                if ($entity->getProductType() != "bundle") {
                    $options = Mage::helper('simiconnector/checkout')->getUsedProductOption($entity);
                } else {
                    $options = Mage::helper('simiconnector/checkout')->getOptions($entity);
                }
            }

            $pro_price = $entity->getCalculationPrice();
            if (Mage::helper('tax')->displayCartPriceInclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                $pro_price = Mage::helper('checkout')->getSubtotalInclTax($entity);
            }

            $quoteitem = $entity->toArray($fields);
            $quoteitem['option'] = $options;
            $quoteitem['image'] = Mage::helper('simiconnector/products')->getImageProduct($entity->getProduct(), null, $parameters['image_width'], $parameters['image_height']);
            $info[] = $quoteitem;
            $all_ids[] = $entity->getId();
        }
        return $this->getList($info, $all_ids, $total, $limit, $offset);
    }

    public function getList($info, $all_ids, $total, $page_size, $from) {
        $result = parent::getList($info, $all_ids, $total, $page_size, $from);
        $result['total'] = Mage::helper('simiconnector/total')->getTotal();
        if ($this->_RETURN_MESSAGE) {
            $result['message'] = array($this->_RETURN_MESSAGE);
        }
        return $result;
    }

}
