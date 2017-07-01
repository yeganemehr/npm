<?php
namespace packages\npm;
use \packages\base;
use \packages\base\IO;
use \packages\base\date;
use \packages\base\packages;
class cache extends base\cache{
	private static $storage;
	private static function getStorage(){
		if(!self::$storage){
			self::$storage = new IO\directory\local(packages::package('npm')->getFilePath('storage/private/cache'));
			if(!self::$storage->exists()){
				self::$storage->make(true);
			}
			self::clear();
		}
		return self::$storage;
	}
	public static function set(string $name, $value, int $timeout = 86400){
		if($value instanceof base\IO\file){
			$name = self::name($name);
			$file = self::getStorage()->file($name);
			$value->copyTo($file);
			self::setIndex($name, date::time() + $timeout);
		}else{
			parent::set($name, $value, $timeout);
		}
	}
	public static function get(string $name){
		$file = self::getStorage()->file(self::name($name));
		if($file->exists()){
			return $file;
		}else{
			return parent::get($name);
		}
	}
	public static function delete(string $name){
		$file = self::getStorage()->file(self::name($name));
		if($file->exists()){
			$file->delete();
		}else{
			return parent::delete($name);
		}
	}
	private static function clear(){
		$items = self::readIndex();
		foreach($items as $x => $index){
			if($index[2] < date::time()){
				$item = self::getStorage()->file($index[0]);
				if($item->exists()){
					$item->delete();
				}
			}
			unset($items[$x]);
		}
		self::writeIndex($items);
	}
	private static function name(string $name){
		return md5($name);
	}
	private static function lockIndex(){
		$startAt = date::time();
		$lock = self::getStorage()->file('index.lock');
		while($lock->exists() and date::time() - $startAt < 10);
		$lock->write("");
		return $lock;
	}
	private static function readIndex():array{
		$index = self::getStorage()->file('index');
		if(!$index->exists()){
			return [];
		}
		$keys = [];
		$buffer = $index->open(IO\file\local::readOnly);
		while($line = $buffer->readLine()){
			$line = explode(",", $line, 3);
			$line[1] = intval($line[1]);
			$line[2] = intval($line[2]);
			$keys[] = $line;
		}
		return $keys;
	}
	private static function writeIndex(array & $items){
		$lock = self::lockIndex();
		$index = self::getStorage()->file('index');
		$buffer = $index->open(IO\file\local::writeOnly);
		foreach($items as $item){
			$buffer->write(implode(",", $item)."\n");
		}
		$lock->delete();
	}
	private static function setIndex(string $name, int $expire){
		$items = self::readIndex();
		$keys = array_column($items, 0);
		$index = array_search($name, $keys);
		if($index !== false){
			$items[$index][2] = $expire;
			self::writeIndex($items);
		}else{
			$item = [$name, date::time(), $expire];
			$items[] = $item;
			$lock = self::lockIndex();
			$index = self::getStorage()->file('index');
			$index->append(implode(",", $item)."\n");
			$lock->delete();
		}
	}
	private static function removeIndex(string $name){
		$items = self::readIndex();
		$keys = array_column($items, 0);
		$index = array_search($name, $keys);
		if($index !== false){
			unset($items[$index]);
			self::writeIndex($items);
		}
	}
}