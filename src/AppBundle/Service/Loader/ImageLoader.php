<?php

namespace AppBundle\Service\Loader;

use AppBundle\Entity\LDraw\Model;
use AppBundle\Service\Stl\StlRendererService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;

class ImageLoader extends BaseLoader
{
    /** @var FilesystemInterface */
    private $mediaFilesystem;

    /** @var string */
    private $rebrickableDownloadUrl;

    /** @var StlRendererService */
    private $stlRendererService;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger, FilesystemInterface $mediaFilesystem, $rebrickableDownloadUrl, StlRendererService $stlRendererService)
    {
        $this->mediaFilesystem = $mediaFilesystem;
        $this->rebrickableDownloadUrl = $rebrickableDownloadUrl;
        $this->stlRendererService = $stlRendererService;

        parent::__construct($em, $logger);
    }

    /**
     * Download ZIP file with part images from rebrickable and unzip file to filesystem
     *
     * @param integer $color color id used by rebrickable
     */
    public function loadColorFromRebrickable($color)
    {
        $path = $this->rebrickableDownloadUrl."ldraw/parts_{$color}.zip";

        $file = $this->downloadFile($path);
        $zip = new \ZipArchive($file);

        if ($zip->open($file) === true) {
            $this->output->writeln([
                "Extracting ZIP file into {$this->mediaFilesystem->getAdapter()->getPathPrefix()}images/{$color}",
            ]);
            $zip->extractTo($this->mediaFilesystem->getAdapter()->getPathPrefix().'images'.DIRECTORY_SEPARATOR.$color);
            $zip->close();
            $this->output->writeln(['Done!']);
        } else {
            $this->logger->error('Extraction of file failed!');
        }
    }

    /**
     * Load missing images of models
     */
    public function loadMissingModelImages()
    {
        // Get models without image
        $missing = [];
        $models = $this->em->getRepository(Model::class)->findAll();
        foreach ($models as $model) {
            if (!$this->mediaFilesystem->has('images'.DIRECTORY_SEPARATOR.'-1'.DIRECTORY_SEPARATOR.$model->getId().'.png')) {
                $missing[] = $model;
            }
        }
        unset($models);

        // Render images
        $this->output->writeln([
            "Rendering missing images of models",
        ]);
        $this->initProgressBar(count($missing));
        foreach ($missing as $model) {
            $this->progressBar->setMessage($model->getId());

            try {
                $this->loadModelImage($this->mediaFilesystem->getAdapter()->getPathPrefix().$model->getPath());
            } catch (\Exception $e) {
                $this->logger->error('Error rendering model '.$model->getId().' image', $e);
            }
            $this->progressBar->advance();
        }

        $this->progressBar->finish();
    }

    /**
     * Render model and save image into
     *
     * @param $file
     */
    public function loadModelImage($file)
    {
        $this->stlRendererService->render(
            $file,
            $this->mediaFilesystem->getAdapter()->getPathPrefix().'images'.DIRECTORY_SEPARATOR.'-1'.DIRECTORY_SEPARATOR
        );
    }
}
