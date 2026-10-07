<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_4(AppinetProductAvailability $module): bool
{
    return $module->registerHook('displayHeader');
}
