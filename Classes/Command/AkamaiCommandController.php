<?php

namespace Sitegeist\Flow\AkamaiNetStorage\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;

use Sitegeist\Flow\AkamaiNetStorage\AkamaiStorage;
use Sitegeist\Flow\AkamaiNetStorage\AkamaiTarget;
use Sitegeist\Flow\AkamaiNetStorage\Connector;


/**
 * Akamai NetStorage command controller
 *
 * @Flow\Scope("singleton")
 */
class AkamaiCommandController extends CommandController {
    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var ResourceRepository
     */
    protected $resourceRepository;

    function __construct() {
        parent::__construct();
        $this->resourceManager = new ResourceManager;
        $this->resourceRepository = new ResourceRepository;
    }

    /**
     * @param string $collectionName
     */
    public function connectCommand($collectionName) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector) {
            \Neos\Flow\var_dump($storageConnector->testConnection(), 'storage connection is working');
        } else {
            echo "No akamai connector found for storage in collection " . $collectionName . "\n";
        }

        if ($targetConnector) {
            \Neos\Flow\var_dump($targetConnector->testConnection(), 'target connection is working');
        } else {
            echo "No akamai connector found for target in collection " . $collectionName . "\n";
        }
    }

    /**
     *
     * @param string $collectionName
     */
    public function listCommand($collectionName) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector) {
            \Neos\Flow\var_dump($storageConnector->getContentList(), 'storage connector listing');
        } else {
            echo "No akamai connector found for storage in collection " . $collectionName . "\n";
        }

        if ($targetConnector) {
            \Neos\Flow\var_dump($targetConnector->getContentList(), 'target connector listing');
        } else {
            echo "No akamai connector found for target in collection " . $collectionName . "\n";
        }
    }

    /**
     * @param string $collectionName
     */
    public function listPathsCommand($collectionName) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector) {
            \Neos\Flow\var_dump($storageConnector->collectAllPaths(), 'storage connector listing');
        } else {
            echo "No akamai connector found for storage in collection " . $collectionName . "\n";
        }

        if ($targetConnector) {
            \Neos\Flow\var_dump($targetConnector->collectAllPaths(), 'target connector listing');
        } else {
            echo "No akamai connector found for target in collection " . $collectionName . "\n";
        }
    }

    /**
     * @param string $collectionName
     * @return Connector | null
     */
    private function getAkamaiStorageConnectorByCollectionName($collectionName) {
        $collection = $this->resourceManager->getCollection($collectionName);
        $storage = $collection->getStorage();

        if ($storage instanceof AkamaiStorage) {
            return $storage->getConnector();
        } else {
            return null;
        }
    }

    /**
     * @param string $collectionName
     * @return Connector | null
     */
    private function getAkamaiTargetConnectorByCollectionName($collectionName) {
        $collection = $this->resourceManager->getCollection($collectionName);
        $target = $collection->getTarget();

        if ($target instanceof AkamaiTarget) {
            return $target->getConnector();
        } else {
            return null;
        }
    }

    /**
     * Danger!!! removes all folders and files for collection
     *
     * @param string $collectionName
     * @param string $areYouSure
     */
    public function nukeCommand($collectionName, $areYouSure) {
        $storageConnector = $this->getAkamaiStorageConnectorByCollectionName($collectionName);
        $targetConnector = $this->getAkamaiTargetConnectorByCollectionName($collectionName);

        if ($storageConnector && $areYouSure) {
            echo "removing files for storage connector\n";
            $storageConnector->removeAllFiles();
        } else {
            echo "No akamai connector found for storage in collection " . $collectionName . "\n";
        }

        if ($targetConnector && $areYouSure) {
            echo "removing files for target connector\n";
            $targetConnector->removeAllFiles();
        } else {
            echo "No akamai connector found for target in collection " . $collectionName . "\n";
        }
    }
}