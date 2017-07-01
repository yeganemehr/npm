<?php
namespace packages\npm;
use \packages\base\log;
use \packages\base\json;
use \packages\base\IO\file;
use \packages\base\IO\directory;
use \packages\base\http\client;
use \packages\base\http\clientException;
use \packages\npm\Package;
use \packages\npm\PackageNotfoundException;
use \packages\npm\Package\Version;
use \packages\npm\Package\VersionNotFoundException;
use \packages\npm\Repository\PackageConfigException;
use \packages\npm\cache;
class API{
	private static $tries = 0;
	private static $enableCache = true;
	private static $installQueue = [];
	public static function getArchiveFile(string $url, file $file){
		$log = log::getInstance();
		if(self::$enableCache){
			$log->debug("looking in cache");
			$cache = cache::get('packages.cache.file.'.$url);
			if($cache){
				$log->reply("Found");
				$log->debug("copy to ", $file->getPath());
				$cache->copyTo($file);
				$log->reply("Success");
				return true;
			}else{
				$log->reply("Notfound");
			}
		}
		for($x = 0;$x < 3;$x++){
			if($content = file_get_contents($url)){
				$file->write($content);
				$log->debug("Save in cache");
				cache::set('packages.cache.file.'.$url, $file);
				return true;
			}
		}
		return false;
	}
	public static function find(string $string):Version{
		$log = log::getInstance();

		$string = trim($string);
		$at = strpos($string, '@', 1);
		if($at === false){
			$at = strlen($string);
		}
		$name = str_replace('/', '%2f', substr($string, 0, $at));
		$code = trim(substr($string, $at + 1));
		if(!$code){
			$code = 'latest';
		}
		
		if(self::$enableCache){
			$log->debug("looking for package in cache");
			$response = cache::get('packages.npm.packages.'.$name);
			if($response){
				$log->reply("Found");
			}else{
				$log->reply("Notfound");
			}
		}
		for($tries = 0;$tries < 3 and !$response;$tries++){
			try{
				if($tries > 0){
					$log->info("try again (".($tries+1)." / 3)");
				}
				$log->info("find {$name} package");
				if(!$response){
					$response = self::sendGetRequest($name);
					
				}
				if(!$response){
					$log->reply()->error("failed");
				}
			}catch(clientException $e){
				if($e->getResponse()->getStatusCode() == 404){
					throw new PackageNotfoundException($name);
				}else{
					throw new $e;
				}
			}
		}
		if(!$response){
			throw new PackageConfigException();
		}
		if(self::$enableCache and $response instanceof \stdClass){
			cache::set('packages.npm.packages.'.$name, $response);
		}
		if($response instanceof Package){
			$package = $response;
		}else{
			$package = Package::fromJSON($response);
			if(self::$enableCache){
				$log->debug("save package in cache");
				cache::set('packages.npm.packages.'.$name, $package);
			}
		}
		
		$log->info("looking for {$name}@{$code} version");
		$version = $package->findVersion($code);
		if(!$version){
			throw new VersionNotFoundException($package, $code);
		}
		return $version;
	}
	private static function sendGetRequest($url){
		$log = log::getInstance();
		$log->debug("http request to https://registry.npmjs.org/{$url}");
		$client = new client([
			'base_uri' => 'https://registry.npmjs.org/'
		]);
		$response = $client->get($url);
		return json\decode($response->getBody(), false);
	}
	public static function getPackageName(string $string):string{
		$string = trim($string);
		$at = strpos($string, '@', 1);
		if($at === false){
			$at = strlen($string);
		}
		return substr($string, 0, $at);
	}
	public static function getVersionName(string $string):string{
		$string = trim($string);
		$at = strpos($string, '@', 1);
		if($at === false){
			$code = $string;
		}else{
			$code = substr($string, $at+1);
		}
			
		$operators = ['>=','<=','==','=','~','^','*', '>', '<'];
		foreach($operators as $operator){
			if(substr($code, 0, strlen($operator)) == $operator){
				$code = substr($code, strlen($operator));
				break;
			}
		}
		return $code ? $code : 'latest';
	}
	public static function addToInstallQueue(Repository $repo, Version $version){
		self::$installQueue[$repo->getDirectory()->getPath()][$version->getPackage()->getName()] = $version;
	}
	public static function RemoveFromInstallQueue(Repository $repo, Version $version){
		unset(self::$installQueue[$repo->getDirectory()->getPath()][$version->getPackage()->getName()]);
	}
	public static function getInstallQueueFor(Repository $repo){
		$path = $repo->getDirectory()->getPath();
		if(!isset(self::$installQueue[$path]) or !is_array(self::$installQueue[$path])){
			return [];
		}
		return array_values(self::$installQueue[$path]);
	}
	public static function installQueue(){
		foreach(self::$installQueue as $path => $packages){
			$repo = new Repository(new directory\local($path));
			foreach($packages as $name => $package){
				unset(self::$installQueue[$path][$name]);
				$package->installTo($repo);
			}
			unset(self::$installQueue[$path]);
		}
	}
}