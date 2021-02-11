<?php 

namespace CupNoodles\Postmates;

use System\Classes\BaseExtension;
use System\Traits\SendsMailTemplate;

use App;
use Event;
use ApplicationException;

use Admin\Controllers\Orders;
use Admin\Widgets\Form;
use Admin\Widgets\Toolbar;
use Admin\Models\Location_areas_model;
use Admin\Models\Orders_model;

use System\Classes\BaseController;
use Igniter\Local\Facades\Location;

use CupNoodles\Postmates\Models\PostmatesSettings;
use CupNoodles\Postmates\Classes\PostmatesCoveredArea;

class Extension extends BaseExtension
{
    use SendsMailTemplate;

    public $order_model;
    /**
     * Returns information about this extension.
     *
     * @return array
     */
    public function extensionMeta()
    {
        return [
            'name'        => 'Postmates',
            'author'      => 'CupNoodles',
            'description' => 'Postmates API integration.',
            'icon'        => 'fa fa-shipping-fast',
            'version'     => '1.0.0'
        ];
    }

    /**
     * Register method, called when the extension is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {

        // object replacement 
        BaseController::extend( function($controller) {
            $controller->bindEvent('controller.afterConstructor', function($controller){

            // in 3.0.4-beta.27's tastyigniter-orange theme, if the location slug resolver is set then theme.php calls app('currency')->getDefault() before Currency's been set (i think)
            // unsetting just locationSlugResolver and then setting it back to what the constructor would have set it to allows us to sneak in and change the classtype of CoveredArea
            // this certainly doesn't feel good so any advice or feedback about a better way to do this would be appreciated. 

                $location = App::make('location');
                $location->locationSlugResolver(function(){});
                if(is_array($location->coveredArea()->conditions) && isset($location->coveredArea()->conditions[0])){
                    if($location->coveredArea()->conditions[0]['delivery_service'] == 'postmates' &&
                    get_class($location->coveredArea()) == 'Igniter\Local\Classes\CoveredArea'){
                        if ($areaId = (int)$location->getSession('area')){
                            $area = $location->getModel()->findDeliveryArea($areaId);
                        }
                        if (is_null($area)) {
                            $area = $location->getModel()->searchOrDefaultDeliveryArea(
                                $location->userPosition()->getCoordinates()
                            );
                        }
                        $ca = new PostmatesCoveredArea($area, $location);
                        $location->setCoveredArea($ca);
                    }
                }
                $location->locationSlugResolver(function () {return controller()->param('location');});
            });
        });

        Event::listen('location.area.updated', function($location,$coveredArea){
            $this->updatePostmatesDeliveryCost($location);
        });

        // Put a 'postmates' button for type on delivery areas
        Event::listen('admin.form.extendFields', function (Form $form, $fields) {
            if ($form->model instanceof Location_areas_model) {
                //The following line can trigger E_WARNING if ->config hasn't been initialized yet.
                @$fields['conditions']->config['form']['fields']['delivery_service'] = [
                    'label' => 'lang:cupnoodles.postmates::default.delivery_service',
                    'type' => 'radiotoggle',
                    'default' => 'self_delivery',
                    'options' => [
                        'self_delivery' => 'lang:cupnoodles.postmates::default.self_delivery',
                        'postmates' => 'lang:cupnoodles.postmates::default.postmates'
                    ],
                ];
            }
        });

    
        // create an order page button that calls the actual Postmates Delivery when it's ready (or about to be)
        Event::listen('admin.form.extendFieldsBefore', function (Form $form) {

            if ($form->model instanceof Orders_model) {

                Event::listen('admin.toolbar.extendButtons', function (Toolbar $toolbar) use ($form) {
                    $toolbar->buttons['call_postmates']  = [
                        'label' => 'lang:cupnoodles.postmates::default.call_postmates',
                        'class' => 'btn btn-primary',
                        'data-request' => 'onCallPostmates',
                        'data-request-data' => "_method:'POST', order_id:" . $form->model->order_id . ", refresh:1",
                        'data-request-confirm' => 'lang:cupnoodles.postmates::default.call_postmates_confirmation',
                    ];
                    
                });						
            }
        });

        Orders::extend(function($controller){
            $controller->addDynamicMethod('edit_onCallPostmates', function($action, $order_id) use ($controller) {
                $model = $controller->formFindModelObject($order_id);
                $this->callPostmatesDelivery($model);

                if ($redirect = $controller->makeRedirect('edit', $model)) {
                    return $redirect;
                }

            });
        } );

    }

    public function registerMailTemplates()
    {
        return [
            'cupnoodles.postmates::mail.delivery_requested' => 'Order confirmation email to customer'
        ];
    }



    public function callPostmatesDelivery($order_model){

        
        $address = $order_model->address->toArray();
        $deliver_to = $address['address_1'] . ', '  . $address['city'] . ', '  . $address['state'] . ', ' . $address['postcode'];


        $location_address = $order_model->location->getModel()->getAddress();
        $location_address = $location_address['address_1'] . ', ' . $location_address['city'] . ', ' . $location_address['state'] . ', ' . $location_address['postcode'];

        $post_data = [
        'dropoff_address' => $deliver_to,
        'dropoff_name' => $order_model->first_name . ' ' . $order_model->last_name,
        'dropoff_phone_number'=> $order_model->telephone,
        'manifest' => 'Postmates delivery for ' . $order_model->first_name . ' ' . $order_model->last_name . 'from' . '',
        'manifest_items' => [
            'name' => 'Postmates delivery for ' . $order_model->first_name . ' ' . $order_model->last_name . 'from' . '',
            'quantity' => 1,
            'size' => 'medium'
        ],
        'pickup_address' => $location_address,
        'pickup_name' => $order_model->location->getModel()->location_name,
        'pickup_phone_number' => $order_model->location->getModel()->location_telephone
        //'quote_id' => '' //not yet implemented
        ];

        // get postmates sandbox/production settings
        if( PostmatesSettings::get('testing_mode') ){
            $api_key = PostmatesSettings::get('production_api_key');
        }
        else{
            $api_key = PostmatesSettings::get('sandbox_api_key');
        }
        $b64_api_key = base64_encode($api_key . ':');

        $base_url = 'https://api.postmates.com/';
        $quote_url = $base_url . 'v1/customers/'.PostmatesSettings::get('customer_id').'/deliveries';

        $ch = curl_init($quote_url);
                
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: application/x-www-form-urlencoded", 'Authorization: Basic '. $b64_api_key]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));

        $result_json = curl_exec($ch);
        $result = json_decode($result_json, true);
        
        if($result['kind'] == 'error'){
            throw new ApplicationException($result['message'] ); // could do with some better error handling here, there's potentially a $params array in response that could be helpful to users.
        }
        else{
            $this->tracking_url = $result['tracking_url'];
            $this->order_model = $order_model;
            // add the tracking link into the order status

            $order_model->updateOrderStatus(null, ['comment' => 'Postmates Delivery requested from admin. Tracking url: <a target="_blank" href="' . $result['tracking_url'] . '" >'.$result['tracking_url'].'</a>']);

            // send an email if the setting's on
            if( PostmatesSettings::get('send_email_auto') ){
                $this->mailSend('cupnoodles.postmates::mail.delivery_requested', 'customer');
                $this->mailSend('cupnoodles.postmates::mail.delivery_requested', 'location');
            }
        }

    }

    public function mailGetRecipients($type)
    {
        $recipients = [];
        switch ($type) {
            case 'customer':
                $recipients[] = [$this->order_model->email,  $this->order_model->first_name . ' ' . $this->order_model->last_name];
                break;
            case 'location':
                $recipients[] = [$this->order_model->location->location_email, $this->order_model->location->location_name];
                break;
            case 'admin':
                $recipients[] = [setting('site_email'), setting('site_name')];
                break;
        }

        return $recipients;
    }

    public function mailGetData()
    {
        return [
            'tracking_url' => $this->tracking_url,
            'first_name' => $this->order_model->first_name,
            'last_name' => $this->order_model->last_name,
            'requested_time' => date('H:i')
        ];
    }


    public function updatePostmatesDeliveryCost($location){
        
        // if $location->coveredArea is of the base type but has delivery_service == postmates, replace it with the new PostmatesCoveredAreaClass
        if(is_array($location->coveredArea()->conditions) && isset($location->coveredArea()->conditions[0])){
            if($location->coveredArea()->conditions[0]['delivery_service'] == 'postmates' &&
            get_class($location->coveredArea()) == 'Igniter\Local\Classes\CoveredArea'){
                
                if ($areaId = (int)$location->getSession('area')){
                    $area = $location->getModel()->findDeliveryArea($areaId);
                }

                if (is_null($area)) {
                    $area = $location->getModel()->searchOrDefaultDeliveryArea(
                        $location->userPosition()->getCoordinates()
                    );
                }
                $ca = new PostmatesCoveredArea($area, $location);
                $location->setCoveredArea($ca);


            }
        }
        //echo get_class($location->coveredArea()); die();

    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'Postmates API settings',
                'description' => 'Manage Postmates delivery API settings.',
                'icon' => 'fa fa-shipping-fast',
                'model' => 'CupNoodles\Postmates\Models\PostmatesSettings',
                'permissions' => ['Module.Postmates'],
            ],
        ];
    }

    /**
     * Registers any front-end components implemented in this extension.
     *
     * @return array
     */
    public function registerComponents()
    {

    }

    /**
     * Registers any admin permissions used by this extension.
     *
     * @return array
     */
    public function registerPermissions()
    {

    }

    public function registerNavigation()
    {

    }
}
