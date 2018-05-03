<?php
namespace CodeDetector\Detector;

class CoverageData implements \IteratorAggregate
{
    const STORAGE_KEY_PREFIX = 'CodeDetector';

    private $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function createFromStorage(StorageInterface $storage)
    {
        return new self($storage->getAll(self::STORAGE_KEY_PREFIX));
    }

    public static function createFromXDebug(array $xdebugCoverageData, $ignoreRootDir, $id = null)
    {
        $ignoreRootDir = substr($ignoreRootDir, -1) == '/' ? $ignoreRootDir : $ignoreRootDir . '/';
        $data = array();

        foreach ($xdebugCoverageData as $file => $lines) {
            $key = self::convertStorageKey($file, $ignoreRootDir);
            foreach ($lines as $line => $execute) {
                if ($execute == Driver::LINE_EXECUTED) {
                    $data[$key][$line][] = $id;
                }
            }
        }

        return new self($data);
    }

    private static function convertStorageKey($path, $ignore_root_dir)
    {
        $hash = hash_file('md5', $path);
        $path = str_replace($ignore_root_dir, '', $path);

        return self::STORAGE_KEY_PREFIX . ":{$path}:{$hash}";
    }

    public function getData()
    {
        return $this->data;
    }

    public function merge(CoverageData $that)
    {
        foreach ($that->getData() as $file => $lines) {
            if (!isset($this->data[$file])) {
                $this->data[$file] = array();
            }

            foreach ($lines as $line => $ids) {
                $this_ids = isset($this->data[$file][$line]) ? $this->data[$file][$line] : array();
                $this->data[$file][$line] = array_unique(array_merge($this_ids, $ids));
            }
        }
    }

    public function save(StorageInterface $storage)
    {
        foreach ($this->getData() as $file => $lines) {
            $storage->set($file, $lines);
        }
    }

    public function getPHP_CodeCoverageData($ignoreRootDir)
    {
        $result = array();
        foreach ($this->getData() as $file => $lines) {
            $result[$this->convertRealFilePath($file, $ignoreRootDir)] = $lines;
        }

        return $result;
    }

    private function convertRealFilePath($storageKey, $ignoreRootDir)
    {
        $ignoreRootDir = substr($ignoreRootDir, -1) == '/' ? $ignoreRootDir : $ignoreRootDir . '/';
        if (preg_match('/^' . self::STORAGE_KEY_PREFIX . ':([^:]+):.*$/', $storageKey, $matches)) {
            return realpath($ignoreRootDir . $matches[1]);
        }

        return null;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }
}