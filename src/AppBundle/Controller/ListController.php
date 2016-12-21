<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
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
        $db_list = $em->getRepository('AppBundle:TaskList')->findAll();

        if (!$db_list) return TaskController::jsonError('could not load lists', 500);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($db_list, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/list/{id}", name="get_list")
     * @Method("GET")
     */
    public function getListAction($id) {
        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:TaskList')->find($id);

        if (!$db_list) return TaskController::jsonError('no list with id ' . $id, 404);

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

        if (count($parametersAsArray) == 0) return TaskController::jsonError('invalid json', 400);

        $list = new TaskList();
        $list->setTitle($parametersAsArray['title']);

        $em = $this->getDoctrine()->getManager();
        $em->persist($list);
        $em->flush();

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($list, 'json'), 201, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/list/{id}", name="update_list")
     * @Method("PUT")
     */
    public function updateListAction($id, Request $request) {
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
        }

        if (count($parametersAsArray) == 0) return TaskController::jsonError('invalid json', 400);
        $title = $parametersAsArray['title'];

        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:TaskList')->find($id);

        if (!$db_list) return TaskController::jsonError('no list with id ' . $id, 404);

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
        $db_list = $em->getRepository('AppBundle:TaskList')->find($id);

        if (!$db_list) return TaskController::jsonError('no list with id ' . $id, 404);

        $em->remove($db_list);
        $em->flush();

        return new Response(null, 204, ['Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/listcontent/{id}", name="get_list_content")
     * @Method("GET")
     */
    public function listContentAction($id) {
        $em = $this->getDoctrine()->getManager();
        $db_list = $em->getRepository('AppBundle:TaskList')->find($id);

        if (!$db_list) return TaskController::jsonError('no list with id ' . $id, 404);

        $query = $em->createQuery('SELECT c FROM AppBundle:Task c WHERE c.inList = :id')->setParameter('id', $id);
        $appointments = $query->getResult();

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($appointments, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }
}