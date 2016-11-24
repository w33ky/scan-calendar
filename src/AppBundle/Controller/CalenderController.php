<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class CalenderController extends Controller
{
    /**
     * @Route("/calender", name="calender")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('calender/index.html.twig');
    }
	
	/**
     * @Route("/read", name="read_calender")
     */
	public function readAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('calender/read.html.twig');
    }
}
