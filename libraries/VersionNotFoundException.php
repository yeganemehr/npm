<?php
namespace packages\npm\Package;
use \packages\npm\Package;
class VersionNotfoundException extends \Exception{
	protected $package;
	protected $version;
	public function __construct(Package $package, string $name){
		parent::__construct($package->getName().'@'.$name.' is notfound');
		$this->package = $package;
		$this->version = $name;
	}
	public function getPackage():Package{
		return $this->package;
	}
	public function getName():string{
		return $this->name;
	}
}