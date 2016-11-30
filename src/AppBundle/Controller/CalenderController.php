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
	
	/* muss hocgeladen und pfad übergeben werden */
	public $_imgPath = '/../../../web/images/test2.jpg';
	
	/* Appointment wird hier gespeichert */
	public $_appointment = array();
	
	
    /**
     * @Route("/", name="calender")
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
		$appointment = $this->checkEntry();
		
		for($i=0; $i < count($this->_appointment, 0); $i++)
		{
			$calender = new Calender;
			
			$calender->setTaskLink($this->_appointment[$i]['snippet']);
			$calender->setType($this->_appointment[$i]['type']);
			$calender->setSubject($this->_appointment[$i]['subject']);
			$calender->setHour($this->_appointment[$i]['col']);
			$date = new\DateTime($this->_appointment[$i]['date']);
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
    }
	/******************************************
	** Berechnung der Helligkeit eines Bereich
	******************************************/
	private function getLum($coordinates, $step)
	{
		$anzahlMessungen = 0;
		$total_lum = 0;
		
		$xStart 	= $coordinates['x1'];
		$xEnd 		= $coordinates['x2'];
		$yStart 	= $coordinates['y1'];
		$yEnd 		= $coordinates['y2'];
		
		for($y = $yStart; $y < $yEnd; $y+=$step){
			for($x = $xStart; $x < $xEnd; $x+=$step) {
				$pixColor = imagecolorat($this->_img,$x,$y); 
				
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
	
	/******************************************
	** Vergleich der Helligkeit eines Bereich 
	** mit Referenz-Helligkeit
	******************************************/
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
	
	
	/******************************************
	** Hauptfunktion 
	** erstellt neue Instanz von Raster über das Bild
	** danach werden einzelne Zellen auf 
	** Helligkeit geprüft
	******************************************/
	private function checkEntry()
	{
		$this->_img = imagecreatefromjpeg(__DIR__.$this->_imgPath);
		$raster = new Raster($this->_img);
		
		/* Helligkeit des Referenzbereich LINKE SEITE */
		$coordinates['x1'] = $raster->_border['left'];
		$coordinates['x2'] = $raster->_border['left'] + ($raster->_border['width'] * 0.5);
		$coordinates['y1'] = $raster->_border['top'];
		$coordinates['y2'] = $raster->_border['top'] + ($raster->_border['height'] * 0.1);
		
		$refLum = $this->getLum($coordinates, 10);
		
		/* Helligkeit der Subject-Zelle prüfen LINKE SEITE*/
		for($i = 1; $i < 125; $i+=6)
		{
			$checkLum = $this->getLum($raster->_raster[$i], 1);
			$result = $this->checkLum($refLum, $checkLum, 8);
			
			$isApp = $result['type'];
			$refLum = $result['refLum'];
			
			if($isApp == true)
			{
				$day = $this->checkDay($i);
				
				$pathToSnippet = $day['date'].'_'.$i;
				$snippet = $this->snippPic($pathToSnippet, $i, $raster);
				
				$subject = $this->getSubject($i, $day['weekday']);
				
				$type = 'none';

				for($j = 1; $j <= 3; $j++)
				{
					$column = $i + $j;
					$empty = $i + 4;
					
					$checkLumType = $this->getLum($raster->_raster[$column], 1);
					$refLumType = $this->getLum($raster->_raster[$empty], 1);
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
				array_push( $this->_appointment, $isapp );
			}
		}
		
		/* Helligkeit des Referenzbereich RECHTE SEITE */
		
		$coordinates['x1'] = $raster->_border['left'] + ($raster->_border['width'] * 0.5);
		$coordinates['x2'] = $raster->_border['left'] + $raster->_border['width'] - 1;
		$coordinates['y1'] = $raster->_border['top'];
		$coordinates['y2'] = $raster->_border['top'] + ($raster->_border['height'] * 0.1);
		
		$refLum = $this->getLum($coordinates, 10);
		
		/* Helligkeit der Subject-Zelle prüfen RECHTE SEITE*/
		for($i = 130; $i < 209; $i+=6)
		{
			$checkLum = $this->getLum($raster->_raster[$i], 1);
			$result = $this->checkLum($refLum, $checkLum, 8);
			
			$isApp = $result['type'];
			$refLum = $result['refLum'];
			
			if($isApp == true)
			{
				$day = $this->checkDay($i);
				
				$pathToSnippet = $day['date'].'_'.$i;
				$snippet = $this->snippPic($pathToSnippet, $i, $raster);
				
				$subject = $this->getSubject($i, $day['weekday']);
				$type = 'none';
				
				for($j = 1; $j <= 3; $j++)
				{
					$column = $i - $j;
					$empty = $i - 4;
					
					$checkLumType = $this->getLum($raster->_raster[$column], 1);
					$refLumType = $this->getLum($raster->_raster[$empty], 1);
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
				array_push( $this->_appointment, $isapp );
			}
		}
	}

	/******************************************
	** Errechnet aus übergebener Zelle im Raster
	** sowie KW das Datum und den Wochentag
	******************************************/
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
	
	/******************************************
	** Errechnet aus übergebener Zelle im Raster
	** und dem Wochentag das Schulfach aus dem array
	******************************************/
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
	
	/******************************************
	** Erstellt ein PNG Bild des Ausschnittes
	******************************************/
	private function snippPic($pathToSnippet, $cell, $raster)
	{
		$x1 = $raster->_raster[$cell]['x1'];
		$y1 = $raster->_raster[$cell]['y1'];
		$width = $raster->_raster[$cell]['x2'] - $raster->_raster[$cell]['x1'];
		$height = $raster->_raster[$cell]['y2'] - $raster->_raster[$cell]['y1'];
		
		$bild = imagecreatetruecolor($width, $height);
		// Hintergrundtransparent
		$transparent = imagecolorallocate($bild, 0, 0, 0);
		imagecolortransparent($bild, $transparent);
		// Farben festlegen
		$farbe1 = imagecolorallocate($bild, 255, 0, 0);

		imagecopyresized ( $bild, $this->_img, 0, 0, $x1, $y1, $width, $height, $width, $height ); 
		
		// Ausgabe des Bildes
		header("Content-type: image/png");
		imagepng($bild, __DIR__.'/../../../web/images/'.$pathToSnippet.'.png');
	}
}


class Raster 
{
	/* Speichern des Raster */
	public $_raster = array();
	
	/* Speichern der Border-Variable - gibt an wo der Kalender beginnt und endet */
	public $_border = array();
	
	public function __construct($img)
	{
		$this->_border['top'] = $this->getBorder($img, 'top');
		$this->_border['bottom'] = $this->getBorder($img, 'bottom');
		$this->_border['left'] = $this->getBorder($img, 'left');
		$this->_border['right'] = $this->getBorder($img, 'right');
		
		$this->_border['width'] = $this->_border['right'] - $this->_border['left'];
		$this->_border['height'] = $this->_border['bottom'] - $this->_border['top'];
		
		$test = $this->getRaster('left');
		$test = $this->getRaster('right');
		
		/****************************************************
		** Raster kann gezeichnete werden
		** $zeichnen = $this->drawRaster($this->_border, $this->_raster);
		****************************************************/
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
	
	private function getRaster($page)
	{
		$col = $this->getCol();
		$rowHeight = $this->getRow();
		
		if($page == 'left')
		{
			$rowStart = 0;
			$rowEnd = 21;
			$colStart = 0;
			$colEnd = 6;
			$startLeft = $this->_border['left'];
		}
		else
		{
			$rowStart = 0;
			$rowEnd = 14;
			$colStart = 6;
			$colEnd = 12;
			$startLeft = $this->_border['left'];
			for($i = 0; $i < 6; $i++)
			{
				$startLeft += $col[$i];
			}
		}
		
		for($i = $rowStart; $i < $rowEnd; $i++)
		{
			$x2 = $startLeft; 
			$y1 = $this->_border['top'] + ($i * $rowHeight);
			$y2 = $y1 + $rowHeight;
			
			for($j = $colStart; $j < $colEnd; $j++)
			{
				$x1 = $x2;
				$x2 = $x1 + $col[$j];
				$y1 = $this->_border['top'] + ($i * $rowHeight);
				$y2 = $y1 + $rowHeight;
				
				$koordinaten = array(
						'col'		=>	$j + 1,
						'row'		=>	$i + 1,
						'x1'		=>	$x1,
						'x2'		=>	$x2,
						'y1'		=>	$y1,
						'y2'		=>	$y2
				);
				
				array_push( $this->_raster, $koordinaten );
			}
		}
	}
	
	private function getRow()
	{

		/* Höhe jeder Spalte berechnen */
		$rowHeight	= floor($this->_border['height'] / 21);
		
		return $rowHeight;		
	}
	
	private function getCol()
	{
		/* Breite der Spalten in Pixel berechnen */
		$colSubject = floor($this->_border['width'] / 2 * 0.1);
		$colTask 	= floor($this->_border['width'] / 2 * 0.6);
		$colHA		= floor($this->_border['width'] / 2 * 0.07);
		$colLK		= floor($this->_border['width'] / 2 * 0.07);
		$colKA		= floor($this->_border['width'] / 2 * 0.06);
		$colEmpty	= floor($this->_border['width'] / 2 * 0.1);
	
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
	
	/*
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
	*/
	
}
