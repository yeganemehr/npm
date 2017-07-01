<?php
namespace packages\npm\Package\Version;
use \packages\npm\Package\Version;
class InvalidVersionControllerException extends \Exception{
	protected $version;
	protected $controller;
	public function __construct(Version $package, int $controller){
		parent::__construct($version->getPackage()->getName().'@'.$controller.' '.$version->getName().' is notfound');
		$this->version = $version;
		$this->controller = $controller;
	}
	public function getVersion():Version{
		return $this->version;
	}
	public function getPackage():Package{
		return $this->version->getPackage();
	}
	public function getController():int{
		return $this->controller;
	}
}