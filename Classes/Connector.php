<?php

namespace Sitegeist\Flow\AkamaiNetStorage;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\Exception\InvalidArgumentException;

/**
 * An Akamai NetStorage Connector to be used for the AkamaiStorage and AkamaiTarget implementation
 */
class Connector {
    /**
     * The host of the API, e.g. [Domain Prefix]-nsu.akamaihd.net
     *
     * @var string
     */
    protected $host;

    /**
     * The host for providing static content, e.g. your-static.your-domain.de
     *
     * @var string
     */
    protected $staticHost;

    /**
     * The unique CP Code that represents the root directory in the applicable NetStorage Storage Group
     *
     * @var string
     */
    protected $cpCode;

    /**
     * Path with additional sub-directories that the $key is restricted to
     *
     * @var string
     */
    protected $restrictedDirectory;

    /**
     * The directory, that you want to store files in, e.g. "storage" or "target"
     * You need to use different working directories when configuring your storage and target.
     *
     * @var string
     */
    protected $workingDirectory;

    /**
     * The internally-generated Akamai Key. This is the value used when provisioning access to the API.
     *
     * @var string
     */
    protected $key;

    /**
     * The name ("Id") of an Upload Account provisioned to access the target Storage Group. It can be gathered from the Luna Control Center.
     *
     * @var string
     */
    protected $keyName;

    public function __construct($options = array(), $name) {
        # checking the configuration
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'host':
                    $this->host = $value;
                    break;
                case 'staticHost':
                    $this->staticHost = $value;
                    break;
                case 'cpCode':
                    $this->cpCode = $value;
                    break;
                case 'restrictedDirectory':
                    $this->restrictedDirectory = $value;
                    break;
                case 'workingDirectory':
                    $this->workingDirectory = $value;
                    break;
                case 'key':
                    $this->key = $value;
                    break;
                case 'keyName':
                    $this->keyName = $value;
                    break;
                default:
                    if ($value !== null) {
                        throw new InvalidArgumentException(sprintf('An unknown option "%s" was specified in the configuration for akamai %s. Please check your settings.', $key, $name), 1428928229);
                    }
            }
        }
    }

    /**
     * returns the restricted directory, omitting the $host and $cpCode
     *
     * @return string
     */
    public function getRestrictedDirectory() {
        return $this->restrictedDirectory;
    }

    /**
     * returns restricted and working directory, omitting the $host and $cpCode
     *
     * @return string
     */
    public function getFullDirectory() {
        return $this->restrictedDirectory . '/' . $this->workingDirectory;
    }

    /**
     * returns the full path to the $restrictedDirectory
     *
     * @return string
     */
    public function getRestrictedPath() {
        return $this->host . '/' . $this->cpCode . '/' . $this->restrictedDirectory;
    }

    /**
     * returns the full path to the $workingDirectory
     *
     * @return string
     */
    public function getFullPath() {
        return $this->host . '/' . $this->cpCode . '/' . $this->getFullDirectory();
    }

    /**
     * returns the full path to the $workingDirectory for the $staticHost
     *
     * @return string
     */
    public function getFullStaticPath() {
        return $this->staticHost . '/' . $this->getFullDirectory();
    }

    /**
     * @return \Akamai\Open\EdgeGrid\Client
     */
    private function createClient() {
        $signer = new \Akamai\NetStorage\Authentication();
        $signer->setKey($this->key, $this->keyName);

        $handler = new \Akamai\NetStorage\Handler\Authentication();
        $handler->setSigner($signer);

        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push($handler, 'netstorage-handler');

        $client = new \Akamai\Open\EdgeGrid\Client([
            'base_uri' => $this->host,
            'handler' => $stack
        ]);

        return $client;

        /* Example:
            $client->put('/' . $cpCode . '/path/to/file', [
                'headers' => [
                    'X-Akamai-ACS-Action' => 'version=1&action=upload&sha1=' .sha1($fileContents)
                ],
                'body' => $fileContents
            ]);
        */
    }

    /**
     * Provides a client for filesystem abstraction (as described here https://github.com/akamai/NetStorageKit-PHP)
     * to store files on Akamai NetStorage.
     *
     * @return \League\Flysystem\Filesystem
     */
    public function createFilesystem() {
        $client = $this->createClient();
        $adapter = new \Akamai\NetStorage\FileStoreAdapter($client, $this->cpCode);
        $filesystem = new \League\Flysystem\Filesystem($adapter);
        return $filesystem;
    }

    /**
     * @return boolean
     */
    public function testConnection() {
        $this->createFilesystem()->getMetadata($this->getRestrictedDirectory());
        return true;
    }

    /**
     * provides a directory listing
     *
     * @return array A nested array with all files an subdirectories
     */
    public function getContentList() {
        return $this->createFilesystem()->listContents($this->getFullDirectory(), true);
    }

    /**
     * For some strange reason we do not get the correct encoding for chars like ä,ü,ö, ...
     * This is why we decode the paths send by Akamai from utf8 unicode characters, which fixes
     * problems of files not beeing found, although they are present.
     *
     * @param string $path
     * @return string
     */
    public function decodeAkamaiPath($path = '') {
        return implode('/', array_map('utf8_decode', explode('/', $path)));
    }

    /**
     * Collects all paths inside the $workingDirectory
     * Paths are sorted by nesting (deepest path first)
     *
     * @return array
     */
    public function collectAllPaths() {
        $paths = array();
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($this->getContentList()));
        foreach ($it as $key => $value) {
            if ($key === 'path') {
                array_unshift($paths, $this->decodeAkamaiPath($value));
            }
        }

        $paths[] = $this->getFullDirectory();

        return $paths;
    }

    /**
     * Removes all folders and files created by the connector
     * Removes $workingDirectory
     */
    public function removeAllFiles() {
        $paths = $this->collectAllPaths();

        if (!$paths) {
            echo "   nothing to remove\n";
            return;
        }

        foreach ($paths as $currentPath) {
            echo "   removing-> " . $currentPath . "\n";
            // we do not explicitly check if it is a file or a directory, we just try both
            try {
                $this->createFilesystem()->deleteDir($currentPath);
            } catch (\Exception $e) {
            }
            try {
                $encodedPath = implode('/', array_map('rawurlencode', explode('/', $currentPath)));
                $this->createFilesystem()->delete($encodedPath);
            } catch (\Exception $e) {
                echo "exception when deleting a file for path " . $e->getMessage();
            }
        }
    }
}