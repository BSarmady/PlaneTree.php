<?php

require_once '../src/bootstrap.php';

(new service_handler())->execute();

debug_Autoload_Stack();

exit();
