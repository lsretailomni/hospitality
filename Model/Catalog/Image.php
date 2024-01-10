<?php

namespace Ls\Hospitality\Model\Catalog;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class Image
{
    const NAME = 'file';

    /**
     * media sub folder
     * @var string
     */
    public $subDir = '[namespace]/[module]/[entity]';

    /**
     * url builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    public $urlBuilder;

    /**
     * @var \Magento\Framework\Filesystem
     */
    public $fileSystem;

    /**
     * @param UrlInterface $urlBuilder
     * @param Filesystem $fileSystem
     */
    public function __construct(
        UrlInterface $urlBuilder,
        Filesystem $fileSystem
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->fileSystem = $fileSystem;
    }
    /**
     * get images base url
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]).$this->subDir.'/image';
    }

    /**
     * get base image dir
     *
     * @return string
     * @throws FileSystemException
     */
    public function getBaseDir()
    {
        return $this->fileSystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath($this->subDir.'/image');
    }
}
