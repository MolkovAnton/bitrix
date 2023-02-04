<?
namespace CustomComponent;

use \Bitrix\Main\Application,
    \Bitrix\Main\Loader;

class Component extends \CBitrixComponent
{
    private $modules = [];
    private $errors = [];

    protected function customInit()
    {
        $this->request = Application::getInstance()->getContext()->getRequest();
        $this->registerModules();
        $this->id = $this->GetName().'_'.substr(md5(rand()), 0, 4);
        
        if (!empty($this->errors)) {
            throw new \Exception(implode('<br>', $this->errors));
        }
    }

    public function executeComponent() 
    {
        try {
            if(method_exists($this, 'init')) {
               $this->init(); 
            }
            $this->customInit();
            $this->customIncludeTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
    
    protected function customIncludeTemplate() {
        echo '<div id="'.$this->id.'">';
        $this->IncludeComponentTemplate();
        echo '</div>';
    }

    protected function registerModules() {
        foreach ($this->modules as $module) {
            if (!Loader::includeModule($module)) {
                $this->errors[] = "Не удается загрузить модуль - $module";
            }
        }
    }
    
    protected function setModules(array $modules) {
        $this->modules = $modules;
    }

    protected function getViewContent(string $path, array $arResult = []) {
        if (file_exists($path)) {
            ob_start();
            include_once $path;
            $result = ob_get_contents();
            ob_end_clean();
            return $result;
        }
    }
    
    protected function getView(string $view, array $arResult = []) {
        return $this->getViewContent($_SERVER['DOCUMENT_ROOT'].$this->GetPath().'/templates/'.$this->getTemplateName()."/view/$view.php", $arResult);
    }
}