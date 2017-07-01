<?php
namespace packages\npm;
class PackageNotfoundException extends \Exception{
	protected $package;
	public function __construct(string $name){
		parent::__construct($name.' is notfound');
		$this->package = $name;
	}
	public function getPackage():string{
		return $this->name;
	}
}