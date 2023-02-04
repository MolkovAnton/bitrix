<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

use \CustomComponent\Component;

class NewComponent extends Component
{
    public function init() {
        $this->setModules(['sale', 'catalog']);
        echo $this->getView('start', ['test']);
    }
}