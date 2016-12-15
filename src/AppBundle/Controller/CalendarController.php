<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Calendar;
use JMS\Serializer\Serializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\DateTime;


class CalendarController extends Controller
{
    public $_schedule = array(
        'Montag' => array(
            1 => 'BIO',
            2 => 'BIO',
            3 => 'MA',
            4 => 'MA',
            5 => 'DE',
            6 => 'ETH',
            7 => 'ETH'
        ),
        'Dienstag' => array(
            1 => 'MU',
            2 => 'MU',
            3 => 'ENG',
            4 => 'ENG',
            5 => 'GEO',
            6 => 'MA',
            7 => 'FÖ'
        ),
        'Mittwoch' => array(
            1 => 'TC',
            2 => 'TC',
            3 => 'DE',
            4 => 'DE',
            5 => 'GEO',
            6 => 'MU',
            7 => 'ENG'
        ),
        'Donnerstag' => array(
            1 => 'FREI',
            2 => 'FREI',
            3 => 'SP',
            4 => 'GE',
            5 => 'ENG',
            6 => 'MA',
            7 => 'FÖ'
        ),
        'Freitag' => array(
            1 => 'DE',
            2 => 'DE',
            3 => 'KU',
            4 => 'KU',
            5 => 'SP',
            6 => 'SP',
            7 => 'ENG'
        )
    );

    /* QR-Code muss übergeben werden */
    public $_qr_code = '47-2016';

    /* muss hocgeladen und pfad übergeben werden */
    public $_imgPath = 'upload/tempfile';

    /* Appointment wird hier gespeichert */
    public $_appointment = array();

    /**
     * @Route("/debug", name="debug")
     */
    public function debugAction(Request $request) {
        $daylist = Array();
        for ($i = 1; $i < 125; $i += 6) {
            $day = $this->checkDay($i);
            $subject = $this->getSubject($i, $day['weekday']);
            $entry = Array();
            $entry[] = $day;
            $entry[] = $subject;
            $daylist[$i] = $entry;
        }

        return new JsonResponse($daylist);
    }

    /**
     * @Route("/api/list/{id}", name="list_calendar_rest")
     */
    public function listRestAction($id)
    {
        $calendar = $this->getDoctrine()->getRepository('AppBundle:Calendar')->find($id);
        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($calendar, 'json'), 200, ['Content-Type' => "application/json"]);
    }

    /**
     * @Route("/api/upload", name="upload_rest")
     */
    public function uploadAction(Request $request) {
        $imageContent = $request->getContent();
        file_put_contents('upload/tempfile', $imageContent);
        $mimetype = mime_content_type('upload/tempfile');

        if ($mimetype != 'image/jpeg')
            return new Response('wrong file type - jpeg needed', 500);

        $appointment = $this->checkEntry();
        for ($i = 0; $i < count($this->_appointment, 0); $i++) {
            $calendar = new Calendar;

            $calendar->setId($this->_appointment[$i]['snippet']);
            $calendar->setType($this->_appointment[$i]['type']);
            $calendar->setSubject($this->_appointment[$i]['subject']);
            $calendar->setHour($this->_appointment[$i]['col']);
            $date = new\DateTime($this->_appointment[$i]['date']);
            $calendar->setDate($date);

            $em = $this->getDoctrine()->getManager();

            //TODO: check earlier
            $dql = 'SELECT 1 FROM AppBundle\Entity\Calendar calendar WHERE calendar.id = :tl';
            $query = $em->createQuery($dql);
            $query->setParameter('tl', $calendar->getId());
            $res = $query->getResult();

            dump($res);

            if ($res == null) {
                $em->persist($calendar);
                $em->flush();
            }
        }

        return new Response('success', 200);
    }

    /**
     * @Route("/api/list", name="list_all_calendar_rest")
     */
    public function listAllRestAction()
    {
        $calendar = $this->getDoctrine()->getRepository('AppBundle:Calendar')->findAll();
        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($calendar, 'json'), 200, ['Content-Type' => "application/json"]);
    }

    /**
     * @Route("/", name="calendar")
     */
    public function indexAction(Request $request)
    {
        return $this->render('calendar/index.html.twig');
    }

    /**
     * @Route("/list", name="list_calendar")
     */
    public function listAction(Request $request)
    {
        $calendar = $this->getDoctrine()
            ->getRepository('AppBundle:Calendar')
            ->findAll();

        return $this->render('calendar/list.html.twig', array(
            'tasks' => $calendar
        ));
    }

    /**
     * @Route("/view/{id}", name="view_calendar")
     */
    public function viewAction($id)
    {
        $calendar = $this->getDoctrine()
            ->getRepository('AppBundle:Calendar')
            ->find($id);

        return $this->render('calendar/view.html.twig', array(
            'task' => $calendar
        ));
    }

    /**
     * @Route("/delete/{id}", name="delete_calendar")
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $calendar = $em->getRepository('AppBundle:Calendar')->find($id);

        $em->remove($calendar);
        $em->flush();

        $this->addFlash(
            'notice_success',
            'Task Removed'
        );

        return $this->redirectToRoute('list_calendar');
    }

    /**
     * @Route("/read", name="read_calendar")
     */
    public function readAction(Request $request)
    {
        $appointment = $this->checkEntry();

        for ($i = 0; $i < count($this->_appointment, 0); $i++) {
            $calendar = new Calendar;

            $calendar->setId($this->_appointment[$i]['snippet']);
            $calendar->setType($this->_appointment[$i]['type']);
            $calendar->setSubject($this->_appointment[$i]['subject']);
            $calendar->setHour($this->_appointment[$i]['col']);
            $date = new\DateTime($this->_appointment[$i]['date']);
            $calendar->setDate($date);

            $em = $this->getDoctrine()->getManager();

            $em->persist($calendar);
            $em->flush();
        }

        $this->addFlash(
            'notice_success',
            'Task readed successfully'
        );

        return $this->redirectToRoute('list_calendar');
    }

    /******************************************
     ** Berechnung der Helligkeit eines Bereich
     ******************************************/
    private function getLum($coordinates, $step)
    {
        $anzahlMessungen = 0;
        $total_lum = 0;

        $xStart = $coordinates['x1'];
        $xEnd = $coordinates['x2'];
        $yStart = $coordinates['y1'];
        $yEnd = $coordinates['y2'];

        for ($y = $yStart; $y < $yEnd; $y += $step) {
            for ($x = $xStart; $x < $xEnd; $x += $step) {
                $pixColor = imagecolorat($this->_img, $x, $y);

                $r = ($pixColor >> 16) & 0xFF;
                $g = ($pixColor >> 8) & 0xFF;
                $b = $pixColor & 0xFF;

                $lum = sqrt(0.299 * ($r * $r) + 0.587 * ($g * $g) + 0.114 * ($b * $b));

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

        if ($checkLum < $lumMax && $checkLum > $lumMin) {
            $result['type'] = false;
            $result['refLum'] = $checkLum;
        } else {
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
        $this->_img = imagecreatefromjpeg($this->_imgPath);
        $raster = new \AppBundle\Model\Raster($this->_img);

        /* Helligkeit des Referenzbereich LINKE SEITE */
        $coordinates['x1'] = $raster->_border['left'];
        $coordinates['x2'] = $raster->_border['left'] + ($raster->_border['width'] * 0.5);
        $coordinates['y1'] = $raster->_border['top'];
        $coordinates['y2'] = $raster->_border['top'] + ($raster->_border['height'] * 0.1);

        $refLum = $this->getLum($coordinates, 10);

        /* Helligkeit der Subject-Zelle prüfen LINKE SEITE*/
        for ($i = 1; $i < 125; $i += 6) {
            $checkLum = $this->getLum($raster->_raster[$i], 1);
            $result = $this->checkLum($refLum, $checkLum, 8);

            $isApp = $result['type'];
            $refLum = $result['refLum'];

            if ($isApp == true) {
                $day = $this->checkDay($i);

                $pathToSnippet = $day['date'] . '_' . $this->getHourOfDay($i);
                $snippet = $this->snippPic($pathToSnippet, $i, $raster);

                $subject = $this->getSubject($i, $day['weekday']);

                $type = 'none';

                for ($j = 1; $j <= 3; $j++) {
                    $column = $i + $j;
                    $empty = $i + 4;

                    $checkLumType = $this->getLum($raster->_raster[$column], 1);
                    $refLumType = $this->getLum($raster->_raster[$empty], 1);
                    $resultType = $this->checkLum($refLumType, $checkLumType, 13);

                    if ($resultType['type'] == true) {
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
                $isapp['col'] = $this->getHourOfDay($i);
                $isapp['type'] = $type;
                $isapp['color'] = $checkLum;
                $isapp['day'] = $day['weekday'];
                $isapp['snippet'] = $pathToSnippet;
                $isapp['subject'] = $subject;
                $isapp['date'] = $day['date'];
                array_push($this->_appointment, $isapp);
            }
        }

        /* Helligkeit des Referenzbereich RECHTE SEITE */

        $coordinates['x1'] = $raster->_border['left'] + ($raster->_border['width'] * 0.5);
        $coordinates['x2'] = $raster->_border['left'] + $raster->_border['width'] - 1;
        $coordinates['y1'] = $raster->_border['top'];
        $coordinates['y2'] = $raster->_border['top'] + ($raster->_border['height'] * 0.1);

        $refLum = $this->getLum($coordinates, 10);

        /* Helligkeit der Subject-Zelle prüfen RECHTE SEITE*/
        for ($i = 130; $i < 209; $i += 6) {
            $checkLum = $this->getLum($raster->_raster[$i], 1);
            $result = $this->checkLum($refLum, $checkLum, 8);

            $isApp = $result['type'];
            $refLum = $result['refLum'];

            if ($isApp == true) {
                $day = $this->checkDay($i);

                $pathToSnippet = $day['date'] . '_' . $this->getHourOfDay($i, 'right');
                $snippet = $this->snippPic($pathToSnippet, $i, $raster);

                $subject = $this->getSubject($i, $day['weekday']);
                $type = 'none';

                for ($j = 1; $j <= 3; $j++) {
                    $column = $i - $j;
                    $empty = $i - 4;

                    $checkLumType = $this->getLum($raster->_raster[$column], 1);
                    $refLumType = $this->getLum($raster->_raster[$empty], 1);
                    $resultType = $this->checkLum($refLumType, $checkLumType, 13);

                    if ($resultType['type'] == true) {
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
                $isapp['col'] = $this->getHourOfDay($i, 'right');
                $isapp['type'] = $type;
                $isapp['color'] = $checkLum;
                $isapp['day'] = $day['weekday'];
                $isapp['snippet'] = $pathToSnippet;
                $isapp['subject'] = $subject;
                $isapp['date'] = $day['date'];
                array_push($this->_appointment, $isapp);
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

    /**
     * @param $taskCell
     * @return float|int
     */
    private function getHourOfDay($taskCell, $page = 'left') {
        $offset = 1;
        if ($page == 'right') {
            $offset = 4;
        }

        $day_cell = $taskCell % 42;
        $hour = (($day_cell - $offset) / 6) + 1;

        return $hour;
    }

    /******************************************
     ** Errechnet aus übergebener Zelle im Raster
     ** und dem Wochentag das Schulfach aus dem array
     ******************************************/
    private function getSubject($cell, $day)
    {
        $hour = $this->getHourOfDay($cell);
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

        imagecopyresized($bild, $this->_img, 0, 0, $x1, $y1, $width, $height, $width, $height);

        // Bild schreiben
        imagepng($bild, 'images/' . $pathToSnippet . '.png');
    }
}
