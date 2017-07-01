<?php
namespace packages\npm\Package\Version;
use \packages\npm\Package\Version;
use \packages\npm\Repository;
class InvalidVersionControllerException extends \Exception{
	protected $version;
	protected $repository;
	public function __construct(Version $version, Repository $repository){
		parent::__construct($version->getPackage()->getName().' is alreay installed in '.$repository->getDirectory->getPath());
		$this->version = $version;
		$this->repository = $repository;
	}
	public function getVersion():Version{
		return $this->version;
	}
	public function getPackage():Package{
		return $this->version->getPackage();
	}
	public function getRepository():Repository{
		return $this->repository;
	}
}