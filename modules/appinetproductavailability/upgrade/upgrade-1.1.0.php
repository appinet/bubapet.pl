<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    return $module instanceof AppinetProductAvailability
        && $module->registerHook('actionGetProductPropertiesBefore');
}
