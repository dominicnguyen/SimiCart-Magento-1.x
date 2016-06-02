<?php

/**
 * Created by PhpStorm.
 * User: Max
 * Date: 5/28/16
 * Time: 4:40 PM
 */
class Simi_Simiconnector_Helper_Products extends Mage_Core_Helper_Abstract
{
    protected $_layer = array();
    protected $builderQuery;
    protected $_data = array();

    public function setData($data){
        $this->_data = $data;
    }

    public function getData(){
        return $this->_data;
    }

    public function getLayers(){
        return $this->_layer;
    }

    /**
     * @return product collection.
     *
     */
    public function getBuilderQuery(){
        return $this->builderQuery;
    }

    public function getProduct($product_id){
        return Mage::getModel('catalog/product')->load($product_id);
    }

    /**
     *
     */
    public function setCategoryProducts($category){
        $category = Mage::getModel('catalog/category')->load($category);
        if($category->getData('include_in_menu') == 0){
            $this->builderQuery = $category->getProductCollection();
            $this->setAttributeProducts();
        }else{
            //has Layers
            $this->setLayers(0, $category);
        }
        return $this;
    }

    /**
     * @param int $is_search
     * @param int $category
     * set Layer and collection on Products
     */
    public function setLayers($is_search=0, $category=0){
        $data = $this->getData();
        $controller = $data['controller'];
        $parameters = $data['params'];
        if(isset($parameters[Simi_Simiconnector_Model_Api_Abstract::FILTER])) {
            $filter = $parameters[Simi_Simiconnector_Model_Api_Abstract::FILTER];
            if($is_search == 1){

                $controller->getRequest()->setParam('q', (string)$filter['q']);
            }
            if(isset($filter['layer'])){
                $filter_layer = $filter['layer'];
                $params = array();
                foreach($filter_layer as $key=>$value){
                    $params[(string) $key] = (string) $value;
                }
                $controller->getRequest()->setParams($params);
            }
        }
        $layout = $controller->getLayout();
        if($is_search == 0){
            $block = $layout->createBlock('catalog/layer_view');
            //setCurrentCate
            $block->getLayer()->setCurrentCategory($category);
            $layers = $this->getItemsShopBy($block);
            $this->_layer = $layers;
            //update collection
            $this->builderQuery = $block->getLayer()->getProductCollection();
            $this->setAttributeProducts();
        }else{
            $query = Mage::helper('catalogsearch')->getQuery();
            $query->setStoreId(Mage::app()->getStore()->getId());
            if ($query->getQueryText() != '') {
                if (Mage::helper('catalogsearch')->isMinQueryLength()) {
                    $query->setId(0)
                        ->setIsActive(1)
                        ->setIsProcessed(1);
                }
                else {
                    if ($query->getId()) {
                        $query->setPopularity($query->getPopularity()+1);
                    }
                    else {
                        $query->setPopularity(1);
                    }

                    if ($query->getRedirect()){
                        $query->save();
                    }
                    else {
                        $query->prepare();
                    }
                }

                Mage::helper('catalogsearch')->checkNotes();

                $block = $layout->createBlock('catalogsearch/layer');
                $layers = $this->getItemsShopBy($block);
                $this->_layer = $layers;
                //update collection
                $this->builderQuery = $block->getLayer()->getProductCollection();
                $this->setAttributeProducts(1);

                if (!Mage::helper('catalogsearch')->isMinQueryLength()) {
                    $query->save();
                }
            }
        }
    }

    protected function setAttributeProducts($is_search=0){
        $storeId = Mage::app()->getStore()->getId();
        $this->builderQuery->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes());
        $this->builderQuery->setStoreId($storeId);
        $this->builderQuery->addFinalPrice();
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($this->builderQuery);
        if($is_search == 0){
            Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->builderQuery);
        }else{
            Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($this->builderQuery);
        }
        $this->builderQuery->addUrlRewrite(0);
    }

     protected  function getItemsShopBy($block){
        $_children = $block->getChild();
        $refineArray = array();
        foreach ($_children as $index => $_child) {
            if ($index == 'layer_state') {
                // $itemArray = array();
                foreach ($_child->getActiveFilters() as $item) {
                    $itemValues = array();
                    $itemValues = $item->getValue();
                    if(is_array($itemValues)){
                        $itemValues = implode('-', $itemValues);
                    }

                    if($item->getFilter()->getRequestVar() != null){
                        $refineArray['layer_state'][] = array(
                            'attribute' => $item->getFilter()->getRequestVar(),
                            'title' => $item->getName(),
                            'label' => (string) strip_tags($item->getLabel()), //filter request var and correlative name
                            'value' => $itemValues,
                        ); //value of each option
                    }
                }
                // $refineArray[] = $itemArray;
            }else{
                $items = $_child->getItems();
                $itemArray = array();
                foreach ($items as $index => $item) {
                    $filter = array();
                    if ($index == 0) {
                        foreach ($items as $index => $item){
                            $filter[] = array(
                                'value' => $item->getValue(), //value of each option
                                'label' => strip_tags($item->getLabel()),
                            );
                        }

                        if($item->getFilter()->getRequestVar() != null) {
                            $refineArray['layer_filter'][] = array(
                                'attribute' => $item->getFilter()->getRequestVar(),
                                'title' => $item->getName(), //filter request var and correlative name
                                'filter' => $filter,
                            );
                        }
                    }
                }
            }
        }
        return $refineArray;
    }

    public function getImageProduct($product, $file = null, $width, $height)
    {
        if (!is_null($width) && !is_null($height)) {
            if ($file) {
                return Mage::helper('catalog/image')->init($product, 'thumbnail', $file)->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize($width, $height)->__toString();
            }
            return Mage::helper('catalog/image')->init($product, 'small_image')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize($width, $height)->__toString();
        }
        if ($file) {
            return Mage::helper('catalog/image')->init($product, 'thumbnail', $file)->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(600, 600)->__toString();
        }
        return Mage::helper('catalog/image')->init($product, 'small_image')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(600, 600)->__toString();
    }
}