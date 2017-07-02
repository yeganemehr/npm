<?php
namespace packages\npm\Package;
use \packages\npm\API;
use \packages\base\log;
use \packages\base\json;
use \packages\base\http\client;
use \packages\base\io\directory;
use \packages\npm\Package;
use \packages\npm\Repository;
use \packages\npm\Repository\PackageConfigException;
use \packages\npm\Package\Version\Dependency;
use \packages\npm\Package\Version\AlreadyInstalledException;
class Version implements \Serializable{
	protected $pacakge;
	protected $name;
	protected $dependencies = [];
	protected $engines = [];
	protected $dist;
	public function __construct(Package $package, string $name){
		$this->setPackage($package);
		$this->name = $name;
	}
	public function addDependency(Dependency $dependency){
		$this->dependencies[] = $dependency;
	}
	public function addEngine(string $engine){
		$this->engines[] = $engine;	
	}
	public function setDist(string $tarball, string $shasum){
		$this->dist = array(
			'tarball' => $tarball,
			'shasum' => $shasum
		);
	}
	public function getName():string{
		return $this->name;
	}
	public function setPackage(Package $package){
		$this->package = $package;
	}
	public function getPackage():Package{
		return $this->package;
	}
	private function download(directory $directory){
		$log = log::getInstance();
		$tarball = $directory->file(basename($this->dist['tarball']));
		$log->debug("save as ", $tarball->basename);
		$try = 1;
		$sha1Check = false;
		do{
			$log->debug("try ".$try." / 3");
			API::getArchiveFile($this->dist['tarball'], $tarball);
			$try++;
			$log->debug("check SHA1");
			$sha1Check = ($tarball->sha1() == $this->dist['shasum']);
			if($sha1Check){
				$log->reply("Success");
			}else{
				$log->reply()->error("Failed");
			}
		}while(!$sha1Check and $try < 4);
		if(!$sha1Check){
			return null;
		}
		return $tarball;
	}
	private function extract($gzball, directory $directory){
		$log = log::getInstance();
		$log->debug("extract ", $gzball->basename, "to", $directory->getPath());
		$try = 1;
		$check = false;
		do{
			$log->debug("try ".$try." / 3");
			try{
				$zp = gzopen($gzball->getPath(), "r");
				if(!$zp){
					throw new \Exception("cannot open ".$gzball->getPath());
				}
				$tarball = $directory->file('tarball.tar');
				$buffer = $tarball->open('w');
				while (!gzeof($zp)) {
					$buffer->write(gzread($zp, 1024));
				}
				gzclose($zp);
				$buffer->close();
				$p = new \PharData($tarball->getPath());
				$p->extractTo($directory->getPath(), null, true);
				$tarball->delete();
				$log->reply("success");
				$directories = $directory->directories(false);
				if(count($directories) == 0){
					throw new \Exception("downloaded tarball is broken");
				}
				
				$check = true;
			}catch(\Exception $e){
				$check = $e;
			}
			$try++;
		}while($check !== true and $try < 4);
		if($check !== true){
			throw $check;
		}
	}
	public function formalInstallTo(Repository $repo){
		$log = log::getInstance();
		$directory = $repo->getModulesDirectory()->directory($this->package->getName());
		if($directory->exists()){
			$directory->delete();
		}
		$directory->make(true);
		$packageConfig = array(
			'name' => $this->package->getName(),
			'version' => $this->getName(),
			'dependencies' => []
		);
		foreach($this->dependencies as $dependency){
			$packageConfig['dependencies'][$dependency->getPackageName()] = $dependency->__toStringVersion();
		}
		$directory->file('package.json')->write(json\encode($packageConfig, json\PRETTY | json\FORCE_OBJECT));
		$directory->file('.formal')->write('');
		unset($packageConfig);
		$localRepo = new Repository($directory);
		foreach($this->dependencies as $dependency){
			$parentRepository = $repo;
			$found = false;
			do{
				$log->debug("looking for installed version of", $dependency->getPackageName());
				$installed = $parentRepository->isInstalled($dependency->getPackageName(), true);
				if($installed){
					$log->reply("found");
					$found = true;
					$log->debug("check for compatiblely (", $dependency->getVersionName(), "needed,", $installed->getName(), "is installed)");
					if($dependency->isCompatible($installed)){
						$log->reply("is compatible");
					}else{
						$log->reply("is not compatible");
						$log->debug("install to", $directory->getPath());
						$localRepo->addToInstallQueue($dependency->getVersion());
					}
					break;
				}else{
					$parentRepoDir = $parentRepository->getDirectory()->getDirectory();
					$log->debug("check parent directory for existence repository (", $parentRepoDir->getPath(), ")");
					if($parentRepoDir->basename == 'node_modules'){
						$parentRepoDir = $parentRepoDir->getDirectory();
						if($parentRepoDir->file('package.json')->exists()){
							$log->reply("valid repository");
							$log->debug("change repository to", $parentRepoDir->getPath());
							$parentRepository = new Repository($parentRepoDir);
						}else{
							$log->reply()->error("is not a valid repository");
							break;
						}
					}else{
						$log->reply()->error("is not a valid repository");
						break;
					}
				}
			}while(!$found);

			if(!$found){
				$log->debug("notfound in repositories");
				$log->debug("install", $dependency->getPackageName(), "to", $parentRepository->getDirectory()->getPath());
				$parentRepository->addToInstallQueue($dependency->getVersion());
			}
		}
	}
	public function installTo(Repository $repo){
		$log = log::getInstance();
		$directory = $repo->getModulesDirectory()->directory($this->package->getName());
		$formalFlag = $directory->file('.formal');
		if(!$formalFlag->exists()){
			$this->formalInstallTo($repo);
		}
		
		$packageConfig = array(
			'name' => $this->package->getName(),
			'version' => $this->getName(),
			'dependencies' => []
		);
		$log->info("download tarball {$this->dist['tarball']}");
		$tarball = $this->download($directory);
		$log->reply("success");
		$log->info("extract files");
		$this->extract($tarball, $directory);
		$log->reply("success");
		$packageDir = $directory->directory('package');
		if(!$packageDir->exists()){
		    $directories = $directory->directories(false);
		    $packageDir = $directories[0];
		    unset($directories);
		}
		$log->debug("move packages file");
		foreach($packageDir->directories(false) as $item){
			$item->move($item->getDirectory()->getDirectory());
		}
		foreach($packageDir->files(false) as $item){
			$item->move($item->getDirectory()->getDirectory()->file($item->basename));
		}
		$log->reply("done");
		$log->debug("remove unused files");
		$tarball->delete();
		$packageDir->delete();
		$log->reply("done");
		$log->reply("looking for bin files");
		$json = json\decode($directory->file('package.json')->read());
		if(isset($json['bin'])){
			$binDir = $repo->getModulesDirectory()->directory('.bin');
			$log->debug("looking for .bin directory");
			if(!$binDir->exists()){
				$log->reply("notfound");
				$log->debug("create it");
				$binDir->make();
			}else{
				$log->reply("found");
			}
			if(is_string($json['bin'])){
				$command = $this->getPackage()->getName();
				$file = $directory->file($json['bin']);
				$log->debug("link", $command, "to", $file->getPath());
				if(symlink($file->getRealPath(), $binDir->file($command)->getPath())){
					$log->reply("Success");	
					$log->debug("add permission to execute");
					if(chmod($binDir->file($command)->getPath(), 777)){
						$log->reply("Success");	
					}else{
						$log->reply()->error("Failed");
					}
				}else{
					$log->reply()->error("Failed");
				}
			}elseif(is_array($json['bin'])){
				foreach($json['bin'] as $command => $file){
					$log->debug("link", $command, "to", $file);
					$file = $directory->file($file);
					if(symlink($file->getRealPath(), $binDir->file($command)->getPath())){
						$log->debug("add permission to execute");
						if(chmod( $binDir->file($command)->getPath(), 777)){
							$log->reply("Success");	
						}else{
							$log->reply()->error("Failed");
						}
					}else{
						$log->reply()->error("Failed");
					}
				}
			}
		}
		$formalFlag->delete();
	}
	public function serialize ( ) {
		$result = [
			'name' => $this->name,
			'engines' => $this->engines,
			'dist' => $this->dist,
			'dependencies' => []
		];
		foreach($this->dependencies as $dependency){
			$result['dependencies'][] = $dependency->__toString();
		}
		return serialize($result);
	}
	public function unserialize($serialized) {
		$data = unserialize($serialized);
		$this->name = $data['name'];
		$this->engines = $data['engines'];
		$this->dist = $data['dist'];
		foreach($data['dependencies'] as $dependency){
			$this->dependencies[] = new Dependency($dependency, Dependency::parse($dependency));
		}
	}
	public static function fromJSON(\stdClass $data, Package $package = null):Version{
		$log = log::getInstance();
		$packageProvided = ($package != null);
		if(!$packageProvided){
			$log->debug("no package is provided, so we create new instance of it");
			$package = new Package($data->name);
			if(isset($data->description)){
				$package->setDescription($data->description);
			}
		}
		$log->debug("version code:", $data->version);
		$version = new static($package, $data->version);
		if(isset($data->dist)){
			$version->setDist($data->dist->tarball, $data->dist->shasum);
		}
		if(isset($data->dependencies) and !empty((array)$data->dependencies)){
			$log->debug(count($data->dependencies), "dependencies found");
			foreach($data->dependencies as $dependencyName => $dependencyVersion){
				$dependency = new Dependency($dependencyName.'@'.$dependencyVersion, Dependency::parse($dependencyVersion));
				$version->addDependency($dependency);
			}
		}
		return $version;
	}
	public static function fromDirectory(directory $dir):Version{
		$packageJson = $dir->file('package.json');
		if(!$packageJson->exists()){
			throw new PackageConfigException($packageJson);
		}
		$json = json\decode($packageJson->read(), false);
		if(!$json){
			throw new PackageConfigException($packageJson);
		}
		return self::fromJson($json);
	}
}
