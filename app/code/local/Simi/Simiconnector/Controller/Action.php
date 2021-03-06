<?php

/**
 * Created by PhpStorm.
 * User: Max
 * Date: 5/2/16
 * Time: 4:14 PM
 */
class Simi_Simiconnector_Controller_Action extends Mage_Core_Controller_Front_Action
{

    protected $_data;

    public function preDispatch()
    {
        parent::preDispatch();
        $enable = (int) Mage::getStoreConfig("simiconnector/general/enable");

        if (!$enable) {
            throw new Exception('Connector was disabled!');
        }

        /*
        if (!$this->isHeader()) {
            echo 'Connect error!';
            header("HTTP/1.0 401 Unauthorized");
            //exit();
        }
        */
    }

    protected function _getServer()
    {
        return Mage::getSingleton('simiconnector/server');
    }

    protected function _printData($result)
    {
        $this->getResponse()->clearHeaders()->setHeader(
            'Content-type',
            'application/json'
        );
        $this->setData($result);
        Mage::dispatchEvent($this->getFullActionName(), array('object' => $this, 'data' => $result));
        $this->_data = $this->getData();
        ob_clean();
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode($this->_data)
        );
        //echo Mage::helper('core')->jsonEncode($this->_data);
    }

    protected function isHeader()
    {
        if (strpos(Mage::helper('core/url')->getCurrentUrl(), 'migrate_') !== false)
            return true;
        if (!function_exists('getallheaders')) {
            function getallheaders()
            {
                $head = array();
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $name        = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                        $head[$name] = $value;
                    } else if ($name == "CONTENT_TYPE") {
                        $head["Content-Type"] = $value;
                    } else if ($name == "CONTENT_LENGTH") {
                        $head["Content-Length"] = $value;
                    } else {
                        $head[$name] = $value;
                    }
                }

                return $head;
            }

        }

        $head      = getallheaders();
        // token is key
        $keySecret = md5(Mage::getStoreConfig('simiconnector/general/secret_key'));
        $token     = "";
        foreach ($head as $k => $h) {
            $header_title = strtolower($k);
            if ($header_title == "authorization" || $header_title == "token") {
                $token = $h;
            }
        }

        if (strcmp($token, 'Bearer ' . $keySecret) == 0)
            return true;
        else
            return false;
    }

    public function getData()
    {
        return $this->_data;
    }

    public function setData($data)
    {
        $this->_data = $data;
    }

}
