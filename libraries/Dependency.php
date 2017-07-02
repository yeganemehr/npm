<?php
namespace packages\npm\Package\Version;
use \packages\base\log;
use \packages\npm\API;
use \packages\npm\Package;
use \packages\npm\Package\Version;
class Dependency{
	const wildcard = 1;
	const minor = 2;
	const bugfix = 3;
	const exact = 4;
	const bigger = 5;
	const biggerOrEqual = 6;
	const smaller = 7;
	const smallerOrEqual = 8;
	private $versionController;
	private $version;
	public function __construct($version, int $versionController){
		if(!is_string($version) and !$version instanceof Version){
			throw new TypeError("\$version argument must be insteadof string or ".Version::class);
		}
		$this->version = $version;
		$this->setVersionController($versionController);
	}
	public function setVersionController(int $controller){
		if(!in_array($controller, [self::wildcard, self::minor, self::bugfix, self::exact, self::bigger, self::biggerOrEqual, self::smaller, self::smallerOrEqual])){
			throw new InvalidVersionControllerException($this->getVersion(), $controller);
		}
		$this->versionController = $controller;
	}
	public function getPackageName():string{
		if(is_string($this->version)){
			$string = str_replace(' ', '', $this->version);
			$at = strpos($string, '@', 1);
			if($at === false){
				$at = strlen($string);
			}
			return substr($string, 0, $at);
		}
		return $this->getPackage()->getName();
	}
	public function getVersionName():string{
		if(is_string($this->version)){
			$at = strpos($this->version, '@', 1);
			if($at === false){
				$at = strlen($this->version);
			}
			$code = trim(substr($this->version, $at+1));
			$operators = ['>=','<=','==','=','~','^','*'];
			foreach($operators as $operator){
				if(substr($code, 0, strlen($operator)) == $operator){
					$code = trim(substr($code, strlen($operator)));
					break;
				}
			}
			return $code ? $code : 'latest';
		}
		return $this->getVersion()->getName();
	}
	public function getVersionController():int{
		return $this->versionController;
	}
	public function getVersion():Version{
		if(is_string($this->version)){
			$log = log::getInstance();
			$log->debug("looking for", $this->version);
			$this->version = API::find($this->version);
		}
		return $this->version;
	}
	public function getPackage():Package{
		return $this->getVersion()->getPackage();
	}
	public function isCompatible(Version $version):bool{
		if($this->versionController == self::wildcard){
			return true;
		}elseif($this->versionController == self::exact){
			return $this->getVersionName() == $version->getName();
		}else{
			if(
				preg_match('/^(\\d+)(\.(\\d+))?(\.(\\d+))?/i', $this->getVersionName(), $matches1) and
				preg_match('/^(\\d+)(\.(\\d+))?(\.(\\d+))?/i', $version->getName(), $matches2)
			){
				if(!isset($matches1[2])){
					$matches1[2] = 0;
				}
				if(!isset($matches1[3])){
					$matches1[3] = 0;
				}
				if(!isset($matches2[2])){
					$matches2[2] = 0;
				}
				if(!isset($matches2[3])){
					$matches2[3] = 0;
				}
				if($this->versionController == self::minor){
					return ($matches1[1] == $matches2[1]);
				}elseif($this->versionController == self::bugfix){
					return ($matches1[1] == $matches2[1] and $matches1[2] == $matches2[2]);
				}else{
					$versionInt1 = intval($matches1[1].$matches1[2].$matches1[3]);
					$versionInt2 = intval($matches2[1].$matches2[2].$matches2[3]);
					if($this->versionController == self::bigger){
						return ($versionInt1 > $versionInt2);
					}elseif($this->versionController == self::biggerOrEqual){
						return ($versionInt1 >= $versionInt2);
					}elseif($this->versionController == self::smaller){
						return ($versionInt1 < $versionInt2);
					}elseif($this->versionController == self::smallerOrEqual){
						return ($versionInt1 <= $versionInt2);
					}
				}
			}
		}
		return false;
	}
	public static function parse(string $string):int{
		$lastAt = strrpos($string, '@');
		if($lastAt !== false){
			$string = trim(substr($string, $lastAt + 1));
		}
		$operators = [
			'>=' => self::biggerOrEqual,
			'<=' => self::smallerOrEqual,
			'<=' => self::smallerOrEqual,
			'==' => self::exact,
			'=' => self::exact,
			'>' => self::bigger,
			'<' => self::smaller,
			'~' => self::bugfix,
			'^' => self::minor,
			'*' => self::wildcard
		];
		foreach($operators as $op => $controller){
			if(substr($string, 0, strlen($op)) == $op){
				return $controller;
			}
		}
		return self::minor;
	}
	public function __toString(){
		return $this->getPackageName().'@'.$this->__toStringVersion();
	}
	public function __toStringVersion(){
		$string = '';
		switch($this->versionController){
			case(self::wildcard):$string .= '';break;
			case(self::minor):$string .= '^';break;
			case(self::bugfix):$string .= '~';break;
			case(self::exact):$string .= '=';break;
			case(self::bigger):$string .= '>';break;
			case(self::smaller):$string .= '<';break;
			case(self::smallerOrEqual):$string .= '<=';break;
			case(self::biggerOrEqual):$string .= '>=';break;
		}
		$string .= $this->getVersionName();
		return $string;
	}
}
