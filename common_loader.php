<?php

spl_autoload_register("commonLoader");

function commonLoader($name)
{
    $common_dir = dirname(__FILE__);
    $name = strtolower($name);

    if("claven" === $name) { return true; }

    require_once $common_dir . "/{$name}/{$name}.class.php";

    return true;
}


