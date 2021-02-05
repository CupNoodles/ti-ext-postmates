<?php 

namespace CupNoodles\Postmates;

use System\Classes\BaseExtension;
use Admin\Widgets\Form;

use Admin\Models\Location_areas_model;
use Illuminate\Foundation\AliasLoader;
use CupNoodles\Postmates\Models\PostmatesSettings;
use CupNoodles\Postmates\Classes\PostmatesCoveredArea;
use Event;

use Lang;

use Igniter\Cart\Classes\OrderManager;

//use Igniter\Local\Classes\Location;

use Igniter\Local\Facades\Location;

class Extension extends BaseExtension
{
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
            'description' => 'Front end quotes for delivery through Postmates.',
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
        
        //$langPath = '/var/www/html/extensions/cupnoodles/postmates/language-igniter-local';
        //Lang::addNamespace('igniter.local', $langPath);
        //$langPath = '/var/www/html/extensions/igniter/local/language';
        //Lang::addNamespace('igniter.local', $langPath);
        //@lang('Igniter.local::default.text_condition_postmates_total', 'foo');
        //if(Lang::isLoaded('igniter.local', 'default', 'en') ){
        //    die();
        //}

        //Location::fireSystemEvent('location.area.updated', [$coveredArea]);
        //Lang::addLines(['default.text_condition_postmates_total' => 'foo'], 'en', 'igniter::local');
            

        $location = Location::instance();
        $this->updatePostmatesDeliveryCost($location);
        /*
        print_r($location);
        Event::listen('location.orderType.updated', function($location, $orderType, $oldOrderType){
            $this->updatePostmatesDeliveryCost($location);
        });
        
        Event::listen('location.position.updated', function($location, $position, $oldPosition){
            $this->updatePostmatesDeliveryCost($location);
        });

        Event::listen('location.timeslot.updated', function($location, $slot, $oldSlot){
            $this->updatePostmatesDeliveryCost($location);
        });
        
        Event::listen('location.area.updated', function($location,$coveredArea){
            $this->updatePostmatesDeliveryCost($location);
        });
*/
        
        // Put a 'postmates' button for type on delivery areas
        Event::listen('admin.form.extendFields', function (Form $form, $fields) {
            if ($form->model instanceof Location_areas_model) {
                $fields['conditions']->config['form']['fields']['type']['options']['postmates'] = 'lang:cupnoodles.postmates::default.postmates';
            }
        });


    }

    public function updatePostmatesDeliveryCost($location){
        
        // if $location->coveredArea is of the base type but has type == postmates, replace it with the new PostmatesCoveredAreaClass
        
        if(is_array($location->coveredArea()->conditions) && isset($location->coveredArea()->conditions[0])){
            
            if($location->coveredArea()->conditions[0]['type'] == 'postmates' &&
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