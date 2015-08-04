<?php
class Gabi77_Mondialrelay_Model_Carrier_Pointsrelais extends MondialRelay_Pointsrelais_Model_Carrier_Pointsrelais
{
	protected $_code = 'pointsrelais';

	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
	{
		try{
			
			$result = Mage::getModel('shipping/rate_result');

        if (!$this->getConfigData('active')) {
            return $result;
        }

        $shipping_free_cart_price = null;
        if ($this->getConfigData('free_active')) {
            	$shipping_free_cart_price = $this->getConfigData('free_price');
        }

        $request->setConditionName($this->getConfigData('condition_name') ? $this->getConfigData('condition_name') : $this->_default_condition_name);

        if($this->getConfigData('package_weight')){
        	$request->_data['package_weight'] = $request->_data['package_weight']+($this->getConfigData('package_weight')/1000);
        }

        $quote = Mage::getModel('sales/quote');
        if (isset($data['cart'])){
        	foreach ($data['cart'] as $id => $itemqty)
        	{
        		$item = Mage::getModel('sales/quote_item')->load($id);
        		if (!$quote->getId()){
        			$quote->load($item->getQuoteId());
        		}
        		$item->setQuote($quote);
        		$product = $item->getProduct();
        		if ($product->getTypeId()!='configurable'){
        			$process['products'][] = array("product"=>$product, "qtycart"=>$itemqty);
        		}
        	}
        } else {
        	$cart = Mage::getModel('checkout/cart')->getQuote();
        	foreach ($cart->getAllItems() as $item) {
        		$product = Mage::getModel('catalog/product')->load($item->getProduct()->getId());
        		
        		if ($product->getTypeId()!='configurable') {
        			$process['products'][] = array("product"=>$product, "qtycart"=>$item->getQty());
        		}
        	}
        }
        if (isset($process['products'])){
        	foreach ($process['products'] as $product) {
        		$rates = $this->getRate($request ,$product['product'], $product['qtycart']);
        		//$productShippingPrice = $rate['price'];
        		//$productn = $product['product'];
        		/*if ($productShippingPrice > $maxProductShippingPrice) {
        			$maxProductShippingPrice = $productShippingPrice;
        			if ($product['qty']>1){
        				$maxProductShippingPrice *= $coeff;
        			}
        		}*/
        	}
        }
   
		$cartTmp = $request->_data['package_value_with_discount'];
		$weghtTmp = $request->_data['package_weight'];
        foreach($rates as $rate)
        {
            if (!empty($rate) && $rate['price'] >= 0) 
            {
            	
/*---------------------------------------- Liste des points relais -----------------------------------------*/

				// On met en place les paramètres de la requète
				$params = array(
							   'Enseigne'     => $this->getConfigData('enseigne'),
							   'Pays'         => $request->_data['dest_country_id'],
							   'CP'           => $request->_data['dest_postcode'],
				);
				
				//On crée le code de sécurité
				$code = implode("",$params);
				$code .= $this->getConfigData('cle');
				
				//On le rajoute aux paramètres
				$params["Security"] = strtoupper(md5($code));
				
				// On se connecte
				$client = new SoapClient("http://www.mondialrelay.fr/WebService/Web_Services.asmx?WSDL");
				
				// Et on effectue la requète
				$points_relais = $client->WSI2_RecherchePointRelais($params)->WSI2_RecherchePointRelaisResult;
				
				$i = 0;
				// On cr�e une m�thode de livraison par point relais
				foreach( $points_relais as $point_relais ) {
					if ( is_object($point_relais) && trim($point_relais->Num) != '' ) {
						$i++;
						$method = Mage::getModel('shipping/rate_result_method');

						$method->setCarrier('pointsrelais');
						$method->setCarrierTitle($this->getConfigData('title'));
						
						$methodTitle = $point_relais->LgAdr1 . ' - ' . $point_relais->Ville  . ' <a href="#" onclick="PointsRelais.showInfo(\'' . $point_relais->Num . '\'); return false;">Détails</a> - <span style="display:none;" id="pays">' . $request->_data['dest_country_id'] . '</span>';
						$method->setMethod($point_relais->Num);
						$method->setMethodTitle($methodTitle);
		
						if($shipping_free_cart_price != null && ($cartTmp > $shipping_free_cart_price && $weghtTmp > 0.101)){
							$price = $rate['price'] = 0;
							$rate['cost']  = 0;
							$method->setPrice($price);
							$method->setCost($rate['cost']);
					   }else{
					   		$price = $rate['price'];
						   	$method->setPrice($this->getFinalPriceWithHandlingFee($price));
							$method->setCost($rate['cost']);
					   }
						$result->append($method);
					}
				}
				

				if (!$i){
					$method = Mage::getModel('shipping/rate_result_method');
					$method->setCarrier('pointsrelais');
					$method->setCarrierTitle('');
					$method->setMethod('000');
					$method->setMethodTitle("Livraison");
					$method->setPrice(0);
					$method->setCost(0);
					$result->append($method);
				}
            }            
        }
				//echo "<pre>" .  var_dump($result) .'</pre>';

        return $result;
		}catch(exception $e)
		{
			return 0;
		}
	}
	
	/**
	 * GetRate
	 * 
	 * @param Mage_Shipping_Model_Rate_Request $request
	 * @return array
	 */
	
	public function getRate(Mage_Shipping_Model_Rate_Request $request, $product = null, $qtycart = null)
	{
		$result = Mage::getResourceModel('pointsrelais/carrier_pointsrelais')->getRate($request);
		
		$destinationCountryCode = $result[0]['dest_country_id'];
		$destinationRegionCode = $result[0]['dest_region_id'];
		$destinationPostCode = $result[0]['dest_zip'];
		$finalPrice = 0;
		$maxProductShippingPrice = 0;
		$coeff = floatval($this->getConfigData('coeff'));
		$pricerelay = $result[0]["price"];
        if($coeff<=0) $coeff = 1;
		switch($destinationCountryCode) {
			case 'FR':
				 
				if($destinationRegionCode == '2B' || $destinationRegionCode == '2A') {
					$finalPrice = $product->getData($this->getConfigData('corse_attribute'), false);
				} elseif(substr($destinationPostCode,0,2) == '20') {
					$finalPrice = $product->getData($this->getConfigData('corse_attribute'), false);
				} else {
					$finalPrice = $product->getData($this->getConfigData(strtolower($destinationCountryCode).'_attribute'), false);
				}
				//$finalPrice = $product->getData($this->getConfigData(strtolower($destinationCountryCode).'_attribute'), false);
				break;
			case 'BE':
			case 'CH':
			case 'IT':
			case 'NL':
				$finalPrice = $product->getData($this->getConfigData(strtolower($destinationCountryCode).'_attribute'), false);
				break;
			case 'GP':
			case 'MQ':
			case 'GF':
			case 'YT':
			case 'RE':
			case 'MF':
			case 'BL':
			case 'PM':
				$finalPrice = $product->getData($this->getConfigData('om1_attribute'), false);
				break;
			default:
				$finalPrice = $product->getData($this->getConfigData('default_attribute'), false);
				break;
		}
		$finalPrice = doubleval($finalPrice);
		if($finalPrice != null) {
			$productShippingPrice = $finalPrice;
			if ($productShippingPrice > $maxProductShippingPrice && $pricerelay) {
				$maxProductShippingPrice = $productShippingPrice;
				if ($qtycart>1){
					$maxProductShippingPrice *= $coeff;
				}
			}
			
			$pricemax = $maxProductShippingPrice - (($maxProductShippingPrice * 10) / 100);
			if($pricerelay > $pricemax) {
				
			} else {
				$result[0]["price"] = $maxProductShippingPrice - (($maxProductShippingPrice * 10) / 100);
			}
		}
		//Zend_Debug::dump($result);
		return $result;
	}	

	public function getCode($type, $code='')
    {
        $codes = array(

            'condition_name'=>array(
                'package_weight' => Mage::helper('shipping')->__('Weight vs. Destination'),
                'package_value'  => Mage::helper('shipping')->__('Price vs. Destination'),
                'package_qty'    => Mage::helper('shipping')->__('# of Items vs. Destination'),
            ),

            'condition_name_short'=>array(
                'package_weight' => Mage::helper('shipping')->__('Poids'),
                'package_value'  => Mage::helper('shipping')->__('Valeur du panier'),
                'package_qty'    => Mage::helper('shipping')->__('Nombre d\'articles'),
            ),

        );

        if (!isset($codes[$type])) {
            throw Mage::exception('Mage_Shipping', Mage::helper('shipping')->__('Invalid Tablerate Rate code type: %s', $type));
        }

        if (''===$code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            throw Mage::exception('Mage_Shipping', Mage::helper('shipping')->__('Invalid Tablerate Rate code for type %s: %s', $type, $code));
        }

        return $codes[$type][$code];
    }

    public function getAllowedMethods()
    {
        return array('pointsrelais'=>$this->getConfigData('name'));
    }

	public function isTrackingAvailable()
	{
		return true;
	}
	
	public function getTrackingInfo($tracking_number)
	{
		$tracking_result = $this->getTracking($tracking_number);
		
		if ($tracking_result instanceof Mage_Shipping_Model_Tracking_Result)
		{
			if ($trackings = $tracking_result->getAllTrackings())
			{
				return $trackings[0];
			}
		}
		elseif (is_string($tracking_result) && !empty($tracking_result))
		{
			return $tracking_result;
		}
		
		return false;
	}
	
	protected function getTracking($tracking_number)
	{
		$key = '<' . $this->getConfigData('marque_url') .'>' . $tracking_number . '<' . $this->getConfigData('cle_url') . '>';
		$key = md5($key);
		
		$tracking_url = 'http://www.mondialrelay.fr/lg_fr/espaces/url/popup_exp_details.aspx?cmrq=' . strtoupper($this->getConfigData('marque_url')) .'&nexp=' . strtoupper($tracking_number) . '&crc=' . strtoupper($key) ;

		$tracking_result = Mage::getModel('shipping/tracking_result');

		$tracking_status = Mage::getModel('shipping/tracking_result_status');
		$tracking_status->setCarrier($this->_code)
						->setCarrierTitle($this->getConfigData('title'))
						->setTracking($tracking_number)
						->setUrl($tracking_url);
		$tracking_result->append($tracking_status);

		return $tracking_result;
	}

}

/**
 * Class Magento_Product
 */
	
class Magento_Product2 implements OS_ProductMR {
	private $parent_cart_item;
	private $cart_item;
	private $cart_product;
	private $loaded_product;
	private $quantity;

	public function Magento_Product2($cart_item, $parent_cart_item)
	{
		$this->cart_item = $cart_item;
		$this->cart_product = $cart_item->getProduct();
		$this->parent_cart_item = $parent_cart_item;
		$this->quantity = isset($parent_cart_item) ? $parent_cart_item->getQty() : $cart_item->getQty();
	}

	public function getOption($option_name, $get_by_id)
	{
		$value = null;
		$product = $this->cart_product;
		foreach ($product->getOptions() as $option)
		{
			if ($option->getTitle()==$option_name) {
				$custom_option = $product->getCustomOption('option_'.$option->getId());
				if ($custom_option) {
					$value = $custom_option->getValue();
					if ($option->getType()=='drop_down' && !$get_by_id) {
						$option_value = $option->getValueById($value);
						if ($option_value) $value = $option_value->getTitle();
					}
				}
				break;
			}
		}
		return $value;
	}

	public function getAttribute($attribute_name, $get_by_id)
	{
		if (!isset($this->loaded_product)) $this->loaded_product = Mage::getModel('catalog/product')->load($this->cart_product->getId());

		$value = null;
		$product = $this->loaded_product;
		$attribute = $product->getResource()->getAttribute($attribute_name);
		if ($attribute) {
			$input_type = $attribute->getFrontend()->getInputType();
			switch ($input_type)
			{
				case 'select' :
					$value = $get_by_id ? $product->getData($attribute_name) : $product->getAttributeText($attribute_name);
					break;
				default :
					$value = $product->getData($attribute_name);
					break;
			}
		}
		return $value;
	}

	public function getQuantity()
	{
		return $this->quantity;
	}

	public function getName()
	{
		return $this->cart_product->getName();
	}

	public function getSku()
	{
		return $this->cart_product->getSku();
	}
}

/**
 * Interface Os_Product 
 * 
 * @author Gabriel Janez
 *
 */

interface OS_ProductMR {
	public function getOption($option, $get_by_id);
	public function getAttribute($attribute, $get_by_id);
	public function getName();
	public function getSku();
	public function getQuantity();
}