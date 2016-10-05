<?php
namespace MyImouto;

class Application extends \Rails\Application\Base
{
    private $booruConfig;
    
    public function booruConfig()
    {
        return $this->booruConfig;
    }
    
    protected function init()
    {
        require __DIR__ . '/default_config.php';
        require __DIR__ . '/config.php';
        require __DIR__ . '/../lib/functions.php';
        $this->booruConfig = new LocalConfig();
        $this->booruConfig->server_host = $_SERVER['HTTP_HOST'];
        $this->booruConfig->url_base = 'http://' . $_SERVER['HTTP_HOST'];
        
        $this->I18n()->loadLocale('my-imouto');
    }
    
    protected function initConfig($config)
    {
        $config->assets->enabled = true;
        
        $config->action_view->layout = 'default';
        
        $config->plugins = [
            'Rails\WillPaginate'
        ];
    }
}
