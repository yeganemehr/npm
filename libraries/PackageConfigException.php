<?php
namespace packages\npm\Repository;
use \packages\base\io\file;
class PackageConfigException extends \Exception{
	protected $fileConfig;
	public function __construct(file $file){
		parent::__construct($file->getPath().' is notfound');
		$this->fileConfig = $file;
	}
	public function getConfigFile():file{
		return $this->fileConfig;
	}
}