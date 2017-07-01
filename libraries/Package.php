<?php
namespace packages\npm;
use \packages\base\log;
use \packages\base\json;
use \packages\base\http\client;
use \packages\npm\Package\Version;
use \packages\npm\Package\Version\Dependency;
class Package implements \Serializable{
	private $name;
	private $description;
	private $versions = [];
	private $tags = [];
	public function __construct(string $name){
		$this->name = $name;
	}
	public function setDescription(string $description){
		$this->description = $description;
	}
	public function addVersion(Version $version){
		$this->versions[$version->getName()] = $version;
	}
	public function addTag(string $tag, Version $version){
		$this->tags[$tag] = $version;
	}
	public function findVersion(string $string){
		if(isset($this->tags[$string])){
			return $this->tags[$string];
		}
		$string = trim($string);
		if(strpos($string, "||") !== false){
			$orParts = explode("||", $string);
			$versions = [];
			foreach($orParts as $orPart){
				$versions[] = $this->findVersion($orPart);
			}
			if($versions){
				$latest = $versions[0];
				foreach($versions as $version){
					preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $latest->getName(), $matches1);
					preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $version->getName(), $matches2);
					if(isset($matches1[1], $matches2[1])){
						if(isset($matches1[2])){
							$matches1[2] = 0;
						}
						if(isset($matches1[3])){
							$matches1[2] = 0;
						}
						if(isset($matches2[2])){
							$matches1[2] = 0;
						}
						if(isset($matches2[3])){
							$matches1[2] = 0;
						}
						$versionInt1 = intval($matches1[1].$matches1[2].$matches1[3]);
						$versionInt2 = intval($matches2[1].$matches2[2].$matches2[3]);
						if($versionInt2 > $versionInt1){
							$latest = $version;
						}
					}else{
						$latest = $version;
					}
				}
				return $latest;
			}else{
				return null;
			}
		}
		$string = str_replace(" ", "", $string);
		$versionController = Dependency::parse($string);
		$string = API::getVersionName($string);
		$latest = null;
		if($versionController == Dependency::wildcard){
			$latest = $this->tags['latest'];
		}elseif($versionController == Dependency::exact){
			if(isset($this->versions[$string])){
				$latest = $this->versions[$string];
			}
		}elseif(preg_match('/^(\\d+)(\.(\\d+))?(\.(\\d+))?/i', $string, $matches1)){
			if(!isset($matches1[2])){
				$matches1[2] = 0;
			}
			if(!isset($matches1[3])){
				$matches1[3] = 0;
			}
			$versionInt1 = intval($matches1[1].$matches1[2].$matches1[3]);
			foreach($this->versions as $version){
				$if = false;
				if(preg_match('/^(\\d+)(\.(\\d+))?(\.(\\d+))?/i', $version->getName(), $matches2)){					
					if(!isset($matches2[2])){
						$matches2[2] = 0;
					}
					if(!isset($matches2[3])){
						$matches2[3] = 0;
					}
					if($versionController == Dependency::minor){
						$if = ($matches1[1] == $matches2[1]);
					}elseif($versionController == Dependency::bugfix){
						$if = ($matches1[1] == $matches2[1] and $matches1[2] == $matches2[2]);
					}else{
						$versionInt2 = intval($matches2[1].$matches2[2].$matches2[3]);
						if($versionController == Dependency::bigger){
							$if = ($versionInt1 > $versionInt2);
						}elseif($versionController == Dependency::biggerOrEqual){
							$if = ($versionInt1 >= $versionInt2);
						}elseif($versionController == Dependency::smaller){
							$if = ($versionInt1 < $versionInt2);
						}elseif($versionController == Dependency::smallerOrEqual){
							$if = ($versionInt1 <= $versionInt2);
						}
					}
				}
				if($if and ($latest == null or $version->getName() >= $latest->getName())){
					$latest = $version;
				}
			}
		}
		return $latest;
	}
	public function getName():string{
		return $this->name;
	}
	public function getDescription():string{
		return $this->description;
	}
	public function getVersions():array{
		return $this->versions;
	}
	public function getTags():array{
		return $this->tags;
	}
	public function serialize () {
		$result = [
			'name' => $this->name,
			'description' => $this->description,
			'versions' => $this->versions,
			'tags' => []
		];
		foreach($this->tags as $tag => $version){
			$result['tags'][$tag] = $version->getName();
		}
		return serialize($result);
	}
	public function unserialize($serialized) {
		$data = unserialize($serialized);
		$this->name = $data['name'];
		$this->description = $data['description'];
		$this->versions = $data['versions'];
		unset($data['versions']);
		foreach($this->versions as $version){
			$version->setPackage($this);
		}
		foreach($data['tags'] as $tag => $version){
			$this->tags[$tag] = $this->versions[$version];
		}
	}
	public static function fromJSON(\stdClass $data):Package{
		$log = log::getInstance();
		$log->debug("name:", $data->name);
		$package = new Package($data->name);
		$log->debug("description:", $data->description);
		$package->setDescription($data->description);
		$log->debug(count($data->versions), "versions found");
		foreach($data->versions as $versionName => $versionData){
			$version = Version::fromJson($versionData, $package);
			$package->addVersion($version);
			foreach($data->{"dist-tags"} as $tagName => $tagVersionName){
				if($tagVersionName == $versionName){
					$package->addTag($tagName, $version);
				}
			}
		}
		return $package;
	}
}