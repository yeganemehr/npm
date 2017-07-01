<?php
namespace packages\npm;
use \packages\base\json;
use \packages\npm\API;
use \packages\base\IO\directory;
use \packages\base\IO\NotFoundException;
use \packages\npm\Package\Version;
use \packages\npm\Repository\PackageConfigException;
class Repository{
	protected $directory;
	protected $npmdir;
	protected $installQueue = [];
	public function __construct(directory $directory){
		if(!$directory->exists()){
			throw new NotFoundException($directory);
		}
		$this->directory = $directory;
		$this->npmdir = $this->directory->directory('node_modules');
	}
	public function getInstalledPackages(){
		if(!$this->npmdir->exists()){
			return [];
		}
		$directories = $this->npmdir->directories(false);
		$installedPackages = [];
		foreach($directories as $directory){
			if($directory->basename[0] != '.'){
				try{
					$installedPackages[] = Version::fromDirectory($directory);
				}catch(PackageConfigException $e){

				}
				
			}
		}
		return $installedPackages;
	}
	public function isInstalled($package, $acceptFormal = false){
		if(!$package instanceof Package and !$package instanceof Version and !is_string($package)){
			throw new \TypeError("Arguement 1 must be instance of string or ".Package::class." or ".Version::class);
		}
		if(!$this->npmdir->exists()){
			return false;
		}
		$packageName = '';
		$versionName = '';
		if($package instanceof Package){
			$packageName = $package->getName();
		}elseif($package instanceof Version){
			$packageName = $package->getPackage()->getName();
			$versionName = $package->getName();
		}elseif(is_string($package)){
			$packageName = API::getPackageName($package);
			if(strpos($package, '@', 1) !== false){
				$versionName = API::getPackageName($package);
			}
		}
		$packageDir = $this->npmdir->directory($packageName);
		if($packageDir->exists()){
			$packageJson = $packageDir->file('package.json');
			if($packageJson->exists()){
				if(!$acceptFormal and $packageDir->file('.formal')->exists()){
					return false;
				}
				$json = json\decode($packageJson->read(), false);
				if($json->name == $packageName){
					if($versionName){
						if($json->version == $versionName){
							return Version::fromJSON($json);
						}
						return false;
					}
					return Version::fromJSON($json);
				}
			}
		}
		return false;
	}
	public function install($package){
		if(is_string($package)){
			$this->install(API::find($package));
		}elseif($package instanceof Version){
			if(!$this->isInstalled($package)){
				if(!$this->npmdir->exists()){
					$this->npmdir->make();
				}
				$package->installTo($this);
				API::installQueue();
			}
		}
	}
	public function addToInstallQueue(Version $version){
		$version->formalInstallTo($this);
		API::addToInstallQueue($this, $version);
	}
	public function getDirectory():directory{
		return $this->directory;
	}
	public function getModulesDirectory():directory{
		return $this->npmdir;
	}
}