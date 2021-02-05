<?php

/**
 * Model configuration options for settings model.
 */

return [
    'form' => [
        'toolbar' => [
            'buttons' => [
                'save' => ['label' => 'lang:admin::lang.button_save', 'class' => 'btn btn-primary', 'data-request' => 'onSave'],
                'saveClose' => [
                    'label' => 'lang:admin::lang.button_save_close',
                    'class' => 'btn btn-default',
                    'data-request' => 'onSave',
                    'data-request-data' => 'close:1',
                ],
            ],
        ],
        'fields' => [
            'enable_postmates' => [
                'label' => 'lang:cupnoodles.postmates::default.enable_postmates',
                'type' => 'switch',
                'span' => 'left',
                'default' => FALSE,
            ],
            'testing_mode' => [
                'label' => 'lang:cupnoodles.postmates::default.testing_mode',
                'type' => 'switch',
                'span' => 'left',
                'default' => FALSE,
                'on' => 'lang:cupnoodles.postmates::default.label_production',
                'off' => 'lang:cupnoodles.postmates::default.label_sandbox',
            ],
            'customer_id' => [
                'label' => 'lang:cupnoodles.postmates::default.customer_id',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'sandbox_api_key' => [
                'label' => 'lang:cupnoodles.postmates::default.sandbox_api_key',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'production_api_key' => [
                'label' => 'lang:cupnoodles.postmates::default.production_api_key',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
        ],
        'rules' => [
            ['enable_postmates', 'igniter.cart::default.enable_postmates', 'required|integer'],
        ],
    ],
];
