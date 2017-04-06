<?php

namespace AppBundle\Service;

use League\Flysystem\File;
use League\Flysystem\Filesystem;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ProcessBuilder;

//TODO enable file overwrite
class LDViewService
{
    /**
     * @var string LDView binary file path
     */
    private $ldview;

    /**
     * @var Filesystem
     */
    private $mediaFilesystem;

    /**
     * @var Filesystem
     */
    private $ldrawLibraryFilesystem;

    /**
     * LDViewService constructor.
     *
     * @param string     $ldview                    Path to LDView OSMesa binary file
     * @param Filesystem $mediaFilesystem           Filesystem for generated web assets
     * @param Filesystem $ldrawLibraryFilesystem    Filesystem with ldraw source files library
     */
    public function __construct($ldview, $mediaFilesystem, $ldrawLibraryFilesystem)
    {
        $this->ldview = $ldview;
        $this->mediaFilesystem = $mediaFilesystem;
        $this->ldrawLibraryFilesystem = $ldrawLibraryFilesystem;
    }

    /**
     * @param Filesystem $ldrawFilesystem
     */
    public function setLdrawFilesystem($ldrawLibraryFilesystem)
    {
        $this->ldrawLibraryFilesystem = $ldrawLibraryFilesystem;
    }

    /**
     * Convert LDraw model from .dat format to .stl by using LDView
     * stores created file to $stlStorage filesystem.
     *
     * @param Filesystem $LDrawDir
     *
     * @return File
     */
    public function datToStl($file)
    {
        if (!$this->mediaFilesystem->has('ldraw'.DIRECTORY_SEPARATOR.'models')) {
            $this->mediaFilesystem->createDir('ldraw'.DIRECTORY_SEPARATOR.'models');
        }

        $newFile = 'ldraw'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.basename($file,'.dat').'.stl';

        if (!file_exists($newFile)) {
            $this->runLDView([
                $file,
                '-LDrawDir='.$this->ldrawLibraryFilesystem->getAdapter()->getPathPrefix(),
                '-ExportFiles=1',
                '-ExportSuffix=.stl',
                '-ExportsDir='.$this->mediaFilesystem->getAdapter()->getPathPrefix().'ldraw'.DIRECTORY_SEPARATOR.'models',
            ]);

            // Check if file created successfully
            if (!$this->mediaFilesystem->has($newFile)) {
                throw new LogicException($newFile.': new file not found'); //TODO
            }
        }

        return $this->mediaFilesystem->get($newFile);
    }

    /**
     * Convert LDraw model from .dat format to .stl by using LDView
     * stores created file to $stlStorage filesystem.
     *
     * @param Filesystem $LDrawDir
     *
     * @return File
     */
    public function datToPng($file)
    {
        if (!$this->mediaFilesystem->has('ldraw'.DIRECTORY_SEPARATOR.'images')) {
            $this->mediaFilesystem->createDir('ldraw'.DIRECTORY_SEPARATOR.'images');
        }

        $newFile = 'ldraw'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.basename($file,'.dat').'.png';

        if (!$this->mediaFilesystem->has($newFile)) {
            $this->runLDView([
                $file,
                '-LDrawDir='.$this->ldrawLibraryFilesystem->getAdapter()->getPathPrefix(),
                '-AutoCrop=1',
                '-SaveAlpha=0',
                '-SnapshotSuffix=.png',
                '-SaveDir='.$this->mediaFilesystem->getAdapter()->getPathPrefix().'ldraw'.DIRECTORY_SEPARATOR.'images',
                '-SaveSnapshots=1',
            ]);

            // Check if file created successfully
            if (!$this->mediaFilesystem->has($newFile)) {
                throw new LogicException($newFile.': new file not found'); //TODO
            }
        }

        return $this->mediaFilesystem->get($newFile);
    }

    /**
     * Call LDView process with $arguments.
     *
     * @param array $arguments
     */
    private function runLDView(array $arguments)
    {
        $builder = new ProcessBuilder();
        $process = $builder
            ->setPrefix($this->ldview)
            ->setArguments($arguments)
            ->getProcess();

        $process->run();

        if (!$process->isSuccessful()) {
            throw new LogicException($process->getOutput()); //TODO
        }
    }
}
