<?php

namespace CupNoodles\Postmates\Classes;

use Igniter\Local\Classes\CoveredArea;
use Admin\Models\Location_areas_model;
use Igniter\Flame\Location\Contracts\AreaInterface;

use CupNoodles\Postmates\Models\PostmatesSettings;

use Igniter\Flame\Database\Model;
/**
 * @method getLocationId()
 * @method getKey()
 * @method checkBoundary(\Igniter\Flame\Geolite\Contracts\CoordinatesInterface $userPosition)
 */
class PostmatesCoveredArea extends CoveredArea{

    protected $model;
    protected $location;
    
    public function __construct(AreaInterface $model, $location)
    {
        $this->model = $model;
        $this->location = $location;
    }

    public function deliveryAmount($cartTotal)
    {
        
        $delivery_cost_estimate = 0;
        $user_position = $this->location->getSession('position');
        if($user_position){
            $delivery_cost_estimate = $this->curl_postmates_delivery_quote($user_position, $cartTotal);
            
            if($delivery_cost_estimate >= 0 ){
                // amount condition value is added as a surcharge
                $delivery_cost_estimate += $this->getConditionValue('amount', $cartTotal);
                session(['postmates_delivery_quote' => $delivery_cost_estimate]);

                return $delivery_cost_estimate;
            }
        }
        
        return -1;
        
    }

    public function curl_postmates_delivery_quote($user_position, $cartTotal){
        
        // get customer address string
        $user_position->format();
        // Postmates specifically requests comma-separated address formatting, so remove any potential commas in existing address fields
        $street_address = str_replace(',', ' ', $user_position->getStreetNumber() . ' ' . $user_position->getStreetName());
        $city = str_replace(',', ' ', $user_position->getSubLocality());
        $state = str_replace(',', ' ', $user_position->getLocality());
        $postcode = str_replace(',', ' ', $user_position->getPostalCode());
        $user_address = $street_address . ', ' . $city . ', ' . $state . ', ' . $postcode;

        // get location address string
        $location_address = $this->location->getModel()->getAddress();
        $location_address = $location_address['address_1'] . ', ' . $location_address['city'] . ', ' . $location_address['state'] . ', ' . $location_address['postcode'];

        // get postmates pickup time
        $time = date("Y-m-d\TH:i:sP", strtotime($this->location->orderDateTime()));

        // get postmates sandbox/production settings
        if( PostmatesSettings::get('testing_mode') ){
            $api_key = PostmatesSettings::get('production_api_key');
        }
        else{
            $api_key = PostmatesSettings::get('sandbox_api_key');
        }
        $b64_api_key = base64_encode($api_key . ':');

        $base_url = 'https://api.postmates.com/';
        $quote_url = $base_url . 'v1/customers/'.PostmatesSettings::get('customer_id').'/delivery_quotes';
        
        $post_data = [
            'dropoff_address' => $user_address,
            'pickup_address' => $location_address,
            'pickup_deadline_dt' => $time
        ];

        $ch = curl_init($quote_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic '. $b64_api_key]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);



        $result_json = curl_exec($ch);
        $result = json_decode($result_json, true);
        

        if(isset($result['fee'])){
            return $result['fee'] / 100;
        }
        else{
            // 
            return -1;
        }
    }


}