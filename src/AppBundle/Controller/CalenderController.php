<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Calender;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\DateTime;


class CalenderController extends Controller
{
	public $_schedule = array(
		'Montag'	=>	array(
						1	=>	'BIO',
						2	=>	'BIO',
						3	=>	'MA',
						4	=>	'MA',
						5	=>	'DE',
						6	=>	'ETH',
						7	=>	'ETH'
		),
		'Dienstag'	=>	array(
						1	=>	'MU',
						2	=>	'MU',
						3	=>	'ENG',
						4	=>	'ENG',
						5	=>	'GEO',
						6	=>	'MA',
						7	=>	'FÖ'
		),
		'Mittwoch'	=>	array(
						1	=>	'TC',
						2	=>	'TC',
						3	=>	'DE',
						4	=>	'DE',
						5	=>	'GEO',
						6	=>	'MU',
						7	=>	'ENG'
		),
		'Donnerstag'	=>	array(
						1	=>	'FREI',
						2	=>	'FREI',
						3	=>	'SP',
						4	=>	'GE',
						5	=>	'ENG',
						6	=>	'MA',
						7	=>	'FÖ'
		),
		'Freitag'	=>	array(
						1	=>	'DE',
						2	=>	'DE',
						3	=>	'KU',
						4	=>	'KU',
						5	=>	'SP',
						6	=>	'SP',
						7	=>	'ENG'
		)
	);
	
	/* QR-Code muss übergeben werden */
	public $_qr_code = '47-2016';
	
	
    /**
     * @Route("/calender", name="calender")
     */
    public function indexAction(Request $request)
    {
        return $this->render('calender/index.html.twig');
    }
	
	/**
     * @Route("/list", name="list_calender")
     */
    public function listAction(Request $request)
    {
		$calender = $this->getDoctrine()
			->getRepository('AppBundle:Calender')
			->findAll();
			
        return $this->render('calender/list.html.twig', array(
			'tasks' 	=>	$calender
		));
    }
	
	/**
     * @Route("/view/{id}", name="view_calender")
     */
    public function viewAction($id)
    {
		$calender = $this->getDoctrine()
			->getRepository('AppBundle:Calender')
			->find($id);
		
        return $this->render('calender/view.html.twig', array(
			'task' 	=>	$calender
		));
    }
	
	/**
     * @Route("/delete/{id}", name="delete_calender")
     */
    public function deleteAction($id)
    {
		$em = $this->getDoctrine()->getManager();
		$calender = $em->getRepository('AppBundle:Calender')->find($id);
		
		$em->remove($calender);
		$em->flush();
		
		$this->addFlash(
			'notice_success', 
			'Task Removed'
		);
		
		return $this->redirectToRoute('list_calender');
    }
	
	/**
     * @Route("/read", name="read_calender")
     */
	public function readAction(Request $request)
    {
		$img = imagecreatefromjpeg(__DIR__.'/../../../app/Resources/public/images/test2.jpg');
		
		$border['top'] = $this->getBorder($img, 'top');
		$border['bottom'] = $this->getBorder($img, 'bottom');
		$border['left'] = $this->getBorder($img, 'left');
		$border['right'] = $this->getBorder($img, 'right');
		
		$border['width'] = $border['right'] - $border['left'];
		$border['height'] = $border['bottom'] - $border['top'];
		
		$raster = $this->getTable($border);
		$zeichnen = $this->drawRaster($border, $raster);
		
		$appointment = $this->checkEntry($img, $border, $raster);
		
		for($i=0; $i < count($appointment, 0); $i++)
		{
			$calender = new Calender;
			
			$calender->setTaskLink($appointment[$i]['snippet']);
			$calender->setType($appointment[$i]['type']);
			$calender->setSubject($appointment[$i]['subject']);
			$calender->setHour($appointment[$i]['col']);
			$date = new\DateTime($appointment[$i]['date']);
			$calender->setDate($date);
			
			$em = $this->getDoctrine()->getManager();
		
			$em->persist($calender);
			$em->flush();
		}
		
		$this->addFlash(
			'notice_success', 
			'Task readed successfully'
		);
		
		return $this->redirectToRoute('list_calender');
		
		/*
        return $this->render('calender/read.html.twig', array(

			'border'		=> $border,
			'appointment' 	=> $appointment

		));
		*/
    }
	
	private function getLum($coordinates, $step, $img)
	{
		$anzahlMessungen = 0;
		$total_lum = 0;
		
		$xStart 	= $coordinates['x1'];
		$xEnd 		= $coordinates['x2'];
		$yStart 	= $coordinates['y1'];
		$yEnd 		= $coordinates['y2'];
		
		for($y = $yStart; $y < $yEnd; $y+=$step){
			for($x = $xStart; $x < $xEnd; $x+=$step) {
				$pixColor = imagecolorat($img,$x,$y); 
				
				$r = ($pixColor >> 16) & 0xFF;
				$g = ($pixColor >> 8) & 0xFF;
				$b = $pixColor & 0xFF;
				
				$lum = sqrt( 0.299*($r*$r) + 0.587*($g*$g) + 0.114*($b*$b) );
				
				$total_lum += $lum;
				
				$anzahlMessungen++;
			}
		}
		
		$avgLum = round($total_lum / $anzahlMessungen, 0);
		
		return $avgLum;
	}
	
	private function getRow($border)
	{

		/* Höhe jeder Spalte berechnen */
		$rowHeight	= floor($border['height'] / 21);
		
		return $rowHeight;		
	}
	
	private function getCol($border)
	{
		/* Breite der Spalten in Pixel berechnen */
		$colSubject = floor($border['width'] / 2 * 0.1);
		$colTask 	= floor($border['width'] / 2 * 0.6);
		$colHA		= floor($border['width'] / 2 * 0.07);
		$colLK		= floor($border['width'] / 2 * 0.07);
		$colKA		= floor($border['width'] / 2 * 0.06);
		$colEmpty	= floor($border['width'] / 2 * 0.1);
	
		$col = array(
				0 =>	$colSubject,
				1 =>	$colTask,
				2 =>	$colHA,
				3 =>	$colLK,
				4 =>	$colKA,
				5 =>	$colEmpty,
				6 =>	$colEmpty,
				7 =>	$colHA,
				8 =>	$colLK,
				9 =>	$colKA,
				10 =>	$colTask,
				11 =>	$colSubject
		);
		
		return $col;
	}
	
	private function getTable($border)
	{
		/* Koordinaten jeder einzelnen Zelle speichern */
		$raster = array();
		
		$raster = $this->getRaster($border, 'left', $raster);
		$raster = $this->getRaster($border, 'right', $raster);
		
		return $raster;
	}
	
	private function getRaster($border, $page, $raster)
	{
		$col = $this->getCol($border);
		$rowHeight = $this->getRow($border);
		
		if($page == 'left')
		{
			$rowStart = 0;
			$rowEnd = 21;
			$colStart = 0;
			$colEnd = 6;
			$startLeft = $border['left'];
		}
		else
		{
			$rowStart = 0;
			$rowEnd = 14;
			$colStart = 6;
			$colEnd = 12;
			$startLeft = $border['left'];
			for($i = 0; $i < 6; $i++)
			{
				$startLeft += $col[$i];
			}
		}
		
		for($i = $rowStart; $i < $rowEnd; $i++)
		{
			$x2 = $startLeft; 
			$y1 = $border['top'] + ($i * $rowHeight);
			$y2 = $y1 + $rowHeight;
			
			for($j = $colStart; $j < $colEnd; $j++)
			{
				$x1 = $x2;
				$x2 = $x1 + $col[$j];
				$y1 = $border['top'] + ($i * $rowHeight);
				$y2 = $y1 + $rowHeight;
				
				$koordinaten = array(
						'col'		=>	$j + 1,
						'row'		=>	$i + 1,
						'x1'		=>	$x1,
						'x2'		=>	$x2,
						'y1'		=>	$y1,
						'y2'		=>	$y2
				);
				
				array_push( $raster, $koordinaten );
			}
		}
		
		return $raster;
	}
	
	private function drawRaster($border, $raster)
	{
		$width = $border['width'] + $border['left'];
		$height = $border['height'] + $border['top'];
		
		$bild = imagecreatetruecolor($width, $height);
		// Hintergrundtransparent
		$transparent = imagecolorallocate($bild, 0, 0, 0);
		imagecolortransparent($bild, $transparent);
		// Farben festlegen
		$farbe1 = imagecolorallocate($bild, 255, 0, 0);

		for($i = 0; $i < count($raster, 0); $i++)
		{
			imagerectangle ($bild, $raster[$i]['x1'], $raster[$i]['y1'], $raster[$i]['x2'], $raster[$i]['y2'], $farbe1);
		}
		
		// Ausgabe des Bildes
		header("Content-type: image/png");
		imagepng($bild, __DIR__.'/../../../web/images/raster_neu.png');
		//imagedestory($bild);
	}
	
	private function getBorder($img, $side)
	{
		$width = imagesx($img);
		$height = imagesy($img);
		
		switch ($side) {
			case 'top':
				$middle = ceil($width / 4);
				$start = 0;
				$end = $height;
				$count = 1;
				
				break;
				
			case 'bottom':
				$middle = ceil($width / 4);
				$start = $height;
				$end = $height;
				$count = -1;
				
				break;
				
			case 'left':
				$middle = ceil($height / 2);
				$start = 0;
				$end = $width;
				$count = 1;
				
				break;
				
			case 'right':
				$middle = ceil($height / 2);
				$start = $width;
				$end = $width;
				$count = -1;
				
				break;
		}
		
		$step = 1;	
		$variance = 1000000;
		
		for($i = 1; $i < $end; $i+=$step)
		{
			$pixel = $start + ($i * $count);
			$center = $middle;
			
			if($side == 'left' or $side == 'right')
			{
				$help = $center;
				$center = $pixel;
				$pixel = $help;
			}
			
			if($i == 1)
			{
				$colorComp = imagecolorat($img,$center,$pixel);
			} 
			else
			{
				$colorAct = imagecolorat($img,$center,$pixel);
				$colorMin = $colorComp - $variance;
				$colorMax = $colorComp + $variance;
				
				if($colorAct < $colorMax && $colorAct > $colorMin)
				{
					$colorComp = $colorAct;
				}
				else
				{
					if($side == 'right' or $side == 'left')
					{
						$border = $center;
					}
					else
					{
						$border = $pixel;
					}
					break;
				}
			}
		}
		
		return $border;
	}
	
	private function checkLum($refLum, $checkLum, $variance)
	{
		$lumMin = $refLum - $variance;
		$lumMax = $refLum + $variance;
		
		if($checkLum < $lumMax && $checkLum > $lumMin)
		{
			$result['type'] = false;
			$result['refLum'] = $checkLum;
		}
		else
		{
			$result['type'] = true;
			$result['refLum'] = $refLum;
		}
		
		return $result;
			
	}
	
	private function checkEntry($img, $border, $raster)
	{
		
		$appointment = array();
		
		/* Helligkeit des Referenzbereich LINKE SEITE */
		$coordinates['x1'] = $border['left'];
		$coordinates['x2'] = $border['left'] + ($border['width'] * 0.5);
		$coordinates['y1'] = $border['top'];
		$coordinates['y2'] = $border['top'] + ($border['height'] * 0.1);
		
		$refLum = $this->getLum($coordinates, 10, $img);
		
		/* Helligkeit der Subject-Zelle prüfen LINKE SEITE*/
		for($i = 1; $i < 125; $i+=6)
		{
			$checkLum = $this->getLum($raster[$i], 1, $img);
			$result = $this->checkLum($refLum, $checkLum, 8);
			
			$isApp = $result['type'];
			$refLum = $result['refLum'];
			
			if($isApp == true)
			{
				$day = $this->checkDay($i);
				
				$pathToSnippet = $day['weekday'].'_'.$i;
				$snippet = $this->snippPic($pathToSnippet, $img, $i, $raster, $border);
				
				$subject = $this->getSubject($i, $day['weekday']);
				
				$type = 'none';

				for($j = 1; $j <= 3; $j++)
				{
					$column = $i + $j;
					$empty = $i + 4;
					
					$checkLumType = $this->getLum($raster[$column], 1, $img);
					$refLumType = $this->getLum($raster[$empty], 1, $img);
					$resultType = $this->checkLum($refLumType, $checkLumType, 13);
					
					if($resultType['type'] == true)
					{
						switch ($j) {
							case 1:
								$type = 'HA';
								break;
							case 2:
								$type = 'LK';
								break;
							case 3:
								$type = 'KA';
								break;
						}
					}
				}
				$isapp['col'] = $i;
				$isapp['type'] = $type;
				$isapp['color'] = $checkLum;
				$isapp['day'] = $day['weekday'];
				$isapp['snippet'] = $pathToSnippet;
				$isapp['subject'] = $subject;
				$isapp['date'] = $day['date'];
				array_push( $appointment, $isapp );
			}
		}
		
		/* Helligkeit des Referenzbereich RECHTE SEITE */
		
		$coordinates['x1'] = $border['left'] + ($border['width'] * 0.5);
		$coordinates['x2'] = $border['left'] + $border['width'] - 1;
		$coordinates['y1'] = $border['top'];
		$coordinates['y2'] = $border['top'] + ($border['height'] * 0.1);
		
		$refLum = $this->getLum($coordinates, 10, $img);
		
		/* Helligkeit der Subject-Zelle prüfen RECHTE SEITE*/
		for($i = 130; $i < 209; $i+=6)
		{
			$checkLum = $this->getLum($raster[$i], 1, $img);
			$result = $this->checkLum($refLum, $checkLum, 8);
			
			$isApp = $result['type'];
			$refLum = $result['refLum'];
			
			if($isApp == true)
			{
				$day = $this->checkDay($i);
				
				$pathToSnippet = $day['weekday'].'_'.$i;
				$snippet = $this->snippPic($pathToSnippet, $img, $i, $raster, $border);
				
				$subject = $this->getSubject($i, $day['weekday']);
				$type = 'none';
				
				for($j = 1; $j <= 3; $j++)
				{
					$column = $i - $j;
					$empty = $i - 4;
					
					$checkLumType = $this->getLum($raster[$column], 1, $img);
					$refLumType = $this->getLum($raster[$empty], 1, $img);
					$resultType = $this->checkLum($refLumType, $checkLumType, 13);
					
					if($resultType['type'] == true)
					{
						switch ($j) {
							case 1:
								$type = 'HA';
								break;
							case 2:
								$type = 'LK';
								break;
							case 3:
								$type = 'KA';
								break;
						}
					}
				}
				$isapp['col'] = $i;
				$isapp['type'] = $type;
				$isapp['color'] = $checkLum;
				$isapp['day'] = $day['weekday'];
				$isapp['snippet'] = $pathToSnippet;
				$isapp['subject'] = $subject;
				$isapp['date'] = $day['date'];
				array_push( $appointment, $isapp );
			}
		}

		return $appointment;
	}
	
	private function checkDay($i)
	{
		$parts = explode("-", $this->_qr_code); 
		switch ($i) {
			case($i < 42):
				$day['weekday'] = 'Montag';
				$day['date'] = date("d-m-Y", strtotime("{$parts['1']}-W{$parts['0']}")); 
				break;
			case($i < 84):
				$day['weekday'] = 'Dienstag';
				$day['date'] = date("d-m-Y", strtotime("{$parts['1']}-W{$parts['0']}-2")); 
				break;
			case($i < 126):
				$day['weekday'] = 'Mittwoch';
				$day['date'] = date("d-m-Y", strtotime("{$parts['1']}-W{$parts['0']}-3")); 
				break;
			case($i < 168):
				$day['weekday'] = 'Donnerstag';
				$day['date'] = date("d-m-Y", strtotime("{$parts['1']}-W{$parts['0']}-4")); 
				break;
			case($i < 210):
				$day['weekday'] = 'Freitag';
				$day['date'] = date("d-m-Y", strtotime("{$parts['1']}-W{$parts['0']}-5")); 
				break;
		}
		
		return $day;
		
	}
	

	private function getSubject($cell, $day)
	{
		if($cell <= 6)
		{
			$hour = 1;
		}
		else
		{
			$row = ceil($cell / 6);
			
			if($row <= 7)
			{
				$hour = $row + 1;
			}
			else
			{
				$hour = $row % 7;
			}
		}
		
		$subject = $this->_schedule[$day][$hour];
		
		return $subject;
	}
	
	private function snippPic($pathToSnippet, $img, $cell, $raster, $border)
	{
		$x1 = $raster[$cell]['x1'];
		$y1 = $raster[$cell]['y1'];
		$width = $raster[$cell]['x2'] - $raster[$cell]['x1'];
		$height = $raster[$cell]['y2'] - $raster[$cell]['y1'];
		
		$bild = imagecreatetruecolor($width, $height);
		// Hintergrundtransparent
		$transparent = imagecolorallocate($bild, 0, 0, 0);
		imagecolortransparent($bild, $transparent);
		// Farben festlegen
		$farbe1 = imagecolorallocate($bild, 255, 0, 0);

		imagecopyresized ( $bild, $img, 0, 0, $x1, $y1, $width, $height, $width, $height ); 
		
		// Ausgabe des Bildes
		header("Content-type: image/png");
		imagepng($bild, __DIR__.'/../../../web/images/'.$pathToSnippet.'.png');
	}
}
