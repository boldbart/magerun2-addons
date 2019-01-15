<?php

namespace Elgentos\Magento\Command\Media\Images;

use N98\Magento\Command\AbstractMagentoCommand;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\App\ResourceConnection;

class AbstractCommand extends AbstractMagentoCommand
{
    /**
     * @var \Magento\Framework\Filesystem
     */
    private $filesystem;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;

    /**
     * @var int
     */
    protected $_currentStep = 1;

    /**
     * @var int
     */
    protected $_totalSteps = 1;


    /**
     * @param Filesystem $filesystem
     * @param ResourceConnection $resource
     */
    public function inject(
        Filesystem $filesystem,
        ResourceConnection $resource
    ) {
        $this->filesystem = $filesystem;
        $this->resource = $resource;
    }

    /**
     * Set total steps
     *
     * @param int $steps
     * @return $this
     */
    protected function _setTotalSteps($steps)
    {
        return $this->_totalSteps = $steps;
        return $this;
    }

    /**
     * Get total steps
     *
     * @return int
     */
    protected function _getTotalSteps()
    {
        return $this->_totalSteps;
    }

    /**
     * Get current step
     *
     * @return int
     */
    protected function _getCurrentStep()
    {
        return $this->_currentStep;
    }

    /**
     * Advance to next step
     *
     * @return $this
     */
    protected function _advanceNextStep()
    {
        $this->_currentStep++;

        return $this;
    }


    /**
     * Get media base
     *
     * @return string
     */
    protected function _getMediaBase()
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        return $mediaDirectory->getAbsolutePath();
    }


    /**
     * Get media files on disc
     *
     * @param string $mediaBaseDir
     * @return array
     */
    protected function _getMediaFiles($mediaBaseDir)
    {
        // Get all files without cache
        return array_filter(
            glob($mediaBaseDir . 'catalog' . DIRECTORY_SEPARATOR  . 'product' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '**'),
            function($file) use ($mediaBaseDir) {
                if (is_dir($file)) {
                    // Skip directories
                    return false;
                } elseif (strpos($file, DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR) !== false) {
                    // Skip cache directory
                    return false;
                } elseif (strpos($file, DIRECTORY_SEPARATOR . 'placeholder' . DIRECTORY_SEPARATOR) !== false) {
                    // Skip placeholder directory
                    return false;
                } elseif (strpos($file, DIRECTORY_SEPARATOR . 'watermark' . DIRECTORY_SEPARATOR) !== false) {
                    // Skip watermark directory
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * Get file hashes
     *
     * @param array $files
     * @param null|\Closure $callback
     * @return array
     */
    protected function _getMediaFileHashes(array &$files, $callback = null)
    {
        return array_map(function($file) use ($callback) {

            $size = filesize($file);
            $md5sum = md5_file($file);

            $hash = $md5sum . ':' . $size;

            $data = [
                'file' => $file,
                'hash' => $hash,
                'md5sum' => $md5sum,
                'size' => $size
            ];

            $callback && call_user_func($callback, $data);

            return $data;
        }, $files);
    }

    /**
     * Get product image values from catalog_product_entity_varchar table
     *
     * @return array value => [value_id...]
     * @throws \Zend_Db_Statement_Exception
     */
    protected function _getProductImageValues()
    {
        $connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $varcharTable = $connection->getTableName('catalog_product_entity_varchar');
        $eavTable = $connection->getTableName('eav_attribute');

        $select = $connection->select()
            ->from(['v' => $varcharTable], ['value_id', 'value'])
            ->join(['a' => $eavTable], 'v.attribute_id = a.attribute_id', [])
            ->where('a.frontend_input = ?', 'gallery')
            ->orWhere('a.frontend_input = ?', 'media_image');

        $values = [];
        $result = $connection->query($select);

        while ($row = $result->fetch()) {
            if ('no_selection' == $row['value']) {
                continue;
            }

            if (!isset($values[$row['value']])) {
                $values[$row['value']] = [];
            }
            $values[$row['value']][] = $row['value_id'];
        }

        return $values;
    }

    /**
     * Get product image gallery from catalog_product_entity_media_gallery table
     *
     * @return array value => [value_id...]
     * @throws \Zend_Db_Statement_Exception
     */
    protected function _getProductImageGallery()
    {
        $connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);

        $galleryTable = $connection->getTableName('catalog_product_entity_media_gallery');

        $select = $connection->select()
            ->from(['v' => $galleryTable], ['value_id', 'value']);

        $values = [];
        $result = $connection->query($select);
        while ($row = $result->fetch()) {
            if (!isset($values[$row['value']])) {
                $values[$row['value']] = [];
            }
            $values[$row['value']][] = $row['value_id'];
        }

        return $values;
    }

}
