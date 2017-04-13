<?php

namespace AppBundle\Controller;

use AppBundle\Api\Exception\ApiException;
use AppBundle\Api\Exception\EmptyResponseException;
use AppBundle\Entity\LDraw\Model;
use AppBundle\Entity\Rebrickable\Color;
use AppBundle\Entity\Rebrickable\Inventory_Part;
use AppBundle\Entity\Rebrickable\Inventory_Set;
use AppBundle\Entity\Rebrickable\Part;
use AppBundle\Entity\Rebrickable\Set;
use AppBundle\Entity\Rebrickable\Theme;
use AppBundle\Form\Filter\Set\SetFilterType;
use AppBundle\Form\FilterSetType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/sets")
 */
class SetController extends Controller
{
    /**
     * @Route("/", name="set_index")
     */
    public function indexAction(Request $request)
    {
        $form = $this->get('form.factory')->create(SetFilterType::class);

        $filterBuilder = $this->get('repository.rebrickable.set')
            ->createQueryBuilder('s');

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $filterBuilder);
        }

        $paginator = $this->get('knp_paginator');
        $sets = $paginator->paginate(
            $filterBuilder->getQuery(),
            $request->query->getInt('page', 1)/*page number*/,
            $request->query->getInt('limit', 30)/*limit per page*/
        );

        return $this->render('set/index.html.twig', [
            'sets' => $sets,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{number}", name="set_detail")
     */
    public function detailAction(Request $request, $number)
    {
        $rebrickableSet = null;
        $bricksetSet = null;
        try {
            if(($rebrickableSet = $this->getDoctrine()->getManager()->getRepository(Set::class)->find($number)) == null) {
                $this->addFlash('warning', 'Set not found in Rebrickable database');
            };

            $bricksetSet = $this->get('api.manager.brickset')->getSetByNumber($number);
            dump($bricksetSet);
        } catch (EmptyResponseException $e) {
            $this->addFlash('warning', 'Set not found in Brickset database');
        } catch (ApiException $e) {
            $this->addFlash('error', $e->getService());
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        if(!$rebrickableSet && !$bricksetSet) {
            return $this->render('error/error.html.twig');
        }

        return $this->render('set/detail.html.twig', [
            'rbset' => $rebrickableSet,
            'brset' => $bricksetSet,
        ]);
    }

    /**
     * @Route("/{number}/download", name="set_download")
     */
    public function downloadZipAction(Request $request, $number) {
        $em = $this->getDoctrine()->getManager();

        $inventoryParts = $em->getRepository(Inventory_Part::class)->findAllRegularBySetNumber($number);

        $zip = new \ZipArchive();
        $zipName = 'set_'.$number.'.zip';
        $zip->open($zipName,  \ZipArchive::CREATE);
        /** @var Inventory_Part $part */
        foreach ($inventoryParts as $part) {
            $filename = $part->getPart()->getNumber().'_('.$part->getColor()->getName().'_'.$part->getQuantity().'x).stl';

            try {
                if($part->getPart()->getModel()) {
                    $zip->addFromString($filename, $this->get('oneup_flysystem.media_filesystem')->read($part->getPart()->getModel()->getPath()));
                }
            } catch (\Exception $e) {
                dump($e);
            }
        }
        $zip->close();

        $response = new Response(file_get_contents($zipName));
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $zipName . '"');
        $response->headers->set('Content-length', filesize($zipName));

        return $response;
    }

}
