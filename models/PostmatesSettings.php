<?php

namespace CupNoodles\Postmates\Models;

use Model;

/**
 * @method static instance()
 */
class PostmatesSettings extends Model
{
    public $implement = ['System\Actions\SettingsModel'];

    // A unique code
    public $settingsCode = 'cupnoodles_postmates_settings';

    // Reference to field configuration
    public $settingsFieldsConfig = 'postmatessettings';

    //
    //
    //
}
