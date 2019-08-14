<?php

$this->run("update core_config_data set path='payment/multon_everypay/show_logo',value=if(value,0,1) where path like 'payment/multon_everypay/hide_logo'");
