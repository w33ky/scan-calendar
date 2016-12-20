<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Calendar;
use AppBundle\Entity\CalList;
use AppBundle\Model\Raster;
use JMS\Serializer\Serializer;
use Libern\QRCodeReader\lib\QrReader;
use Libern\QRCodeReader\QRCodeReader;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\DateTime;

class ListController extends Controller
{
    /**
     * @Route("/api/lists", name="get_lists")
     * @Method("GET")
     */
    public function getListsAction() {
        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:CalList')->findAll();

        if (!$db_list) return new Response('fail', 500);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($db_list, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/list/{id}", name="get_list")
     * @Method("GET")
     */
    public function getListAction($id) {
        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:CalList')->find($id);

        if (!$db_list) return new Response('fail', 500);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($db_list, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/list", name="create_list")
     * @Method("POST")
     */
    public function createListAction(Request $request) {
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
        }

        if (count($parametersAsArray) == 0) return new Response('fail', 500);

        $list = new CalList();
        $list->setTitle($parametersAsArray['title']);

        $em = $this->getDoctrine()->getManager();
        $em->persist($list);
        $em->flush();

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($list, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/list/{id}", name="update_list")
     * @Method("PATCH")
     */
    public function updateList($id, Request $request) {
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
        }

        if (count($parametersAsArray) == 0) return new Response('fail', 500);
        $title = $parametersAsArray['title'];

        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:CalList')->find($id);

        if (!$db_list) return new Response('fail', 500);

        $db_list->setTitle($title);
        $em->flush();

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($db_list, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/list/{id}", name="delete_list")
     * @Method("DELETE")
     */
    public function deleteListAction($id) {
        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:CalList')->find($id);

        if (!$db_list) return new Response('fail', 500);

        $em->remove($db_list);
        $em->flush();

        return new Response('success');
    }

    /**
     * @Route("/api/appointment/{id}", name="list_calendar_rest")
     * @Method("GET")
     */
    public function listRestAction($id)
    {
        $calendar = $this->getDoctrine()->getRepository('AppBundle:Calendar')->find($id);
        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($calendar, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }
}