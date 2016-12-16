<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Calendar;
use JMS\Serializer\Serializer;
use Libern\QRCodeReader\lib\QrReader;
use Libern\QRCodeReader\QRCodeReader;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\DateTime;


class CalendarController extends Controller
{
    //TODO: reomve global vars
    /**
     * @var string Speichert den QR-Code für die Woche
     */
    public $_qr_code = '';

    /**
     * @var string Pfad zur Bild-Datei mit dem Stundenplan
     */
    public $_imgPath = '';

    /**
     * @var array Appointment wird hier gespeichert
     */
    public $_appointment = array();

    /**
     * @Route("/debug", name="debug")
     */
    public function debugAction(Request $request)
    {
        $imageContent = $request->getContent();
        $filename = 'upload/' . uniqid('img_');
        file_put_contents($filename, $imageContent);

        $img = imagecreatefromjpeg($filename);

        $coordinates['x1'] = 0;
        $coordinates['x2'] = imagesx($img);
        $coordinates['y1'] = 0;
        $coordinates['y2'] = imagesy($img);
        $color = $this->getLumNew($img, $coordinates, 1);

        unlink($filename);

        return new JsonResponse($color);
    }

    /**
     * @param $coordinates
     * @param $step
     * @return mixed
     */
    private function getLumNew($img, $coordinates, $step)
    {
        $anzahlMessungen = 0;
        $total_r = 0;
        $min_r = 255;
        $max_r = 0;
        $total_g = 0;
        $min_g = 255;
        $max_g = 0;
        $total_b = 0;
        $min_b = 255;
        $max_b = 0;

        $xStart = $coordinates['x1'];
        $xEnd = $coordinates['x2'];
        $yStart = $coordinates['y1'];
        $yEnd = $coordinates['y2'];

        for ($y = $yStart; $y < $yEnd; $y += $step) {
            for ($x = $xStart; $x < $xEnd; $x += $step) {
                $pixColor = imagecolorat($img, $x, $y);

                //Bitshift durch die Farbwerte
                $r = ($pixColor >> 16) & 0xFF;
                $g = ($pixColor >> 8) & 0xFF;
                $b = $pixColor & 0xFF;

                if ($min_r > $r) $min_r = $r;
                if ($max_r < $r) $max_r = $r;
                if ($min_g > $g) $min_g = $g;
                if ($max_g < $g) $max_g = $g;
                if ($min_b > $b) $min_b = $b;
                if ($max_b < $b) $max_b = $b;

                $total_r += $r;
                $total_g += $g;
                $total_b += $b;

                $anzahlMessungen++;
            }
        }

        $out['r'] = $total_r / $anzahlMessungen;
        $out['min_r'] = $min_r;
        $out['max_r'] = $max_r;
        $out['g'] = $total_g / $anzahlMessungen;
        $out['min_g'] = $min_g;
        $out['max_g'] = $max_g;
        $out['b'] = $total_b / $anzahlMessungen;
        $out['min_b'] = $min_b;
        $out['max_b'] = $max_b;
        $out['lum'] = sqrt(0.299 * ($out['r'] * $out['r']) + 0.587 * ($out['g'] * $out['g']) + 0.114 * ($out['b'] * $out['b']));

        return $out;
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
    public function uploadAction(Request $request)
    {
        $imageContent = $request->getContent();
        $filename = 'upload/' . uniqid('img_');
        $this->_imgPath = $filename;
        file_put_contents($filename, $imageContent);
        $mimetype = mime_content_type($filename);

        if ($mimetype != 'image/jpeg') {
            unlink($filename);
            return new Response('wrong file type - jpeg needed', 500);
        }

        $this->_qr_code = $this->getQrCode($filename);

        $appointment = $this->checkEntry();
        for ($i = 0; $i < count($this->_appointment, 0); $i++) {
            $em = $this->getDoctrine()->getManager();
            $db_calendar = $em->getRepository('AppBundle:Calendar')->find($this->_appointment[$i]['snippet']);

            $date = new\DateTime($this->_appointment[$i]['date']);
            if (!$db_calendar) {
                $calendar = new Calendar;
                $calendar->setId($this->_appointment[$i]['snippet']);
                $calendar->setType($this->_appointment[$i]['type']);
                $calendar->setSubject($this->_appointment[$i]['subject']);
                $calendar->setHour($this->_appointment[$i]['col']);
                $calendar->setDate($date);
                $em->persist($calendar);
            }
            else {
                $db_calendar->setType($this->_appointment[$i]['type']);
                $db_calendar->setSubject($this->_appointment[$i]['subject']);
                $db_calendar->setHour($this->_appointment[$i]['col']);
                $db_calendar->setDate($date);
            }

            $em->flush();
        }
        unlink($filename);

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
     * @Route("/api/delete/{id}", name="delete_calendar")
     */
    public function deleteRestAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $calendar = $em->getRepository('AppBundle:Calendar')->find($id);

        unlink('images/' . $calendar->getId() . '.png');

        $em->remove($calendar);
        $em->flush();

        return new Response();
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

        unlink('images/' . $calendar->getId() . '.png');

        $em->remove($calendar);
        $em->flush();

        $this->addFlash(
            'notice_success',
            'Task Removed'
        );

        return $this->redirectToRoute('list_calendar');
    }

    /**
     * Liest QR Code von Bilddatei aus
     * @param $filename
     * @return mixed
     */
    private function getQrCode($filename) {
        //TODO: cut and resize image for faster upload
        $post_data['file'] = new \CURLFile($filename, mime_content_type($filename));

        $ch = curl_init('http://api.qrserver.com/v1/read-qr-code/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close ($ch);

        $data = json_decode($result, true);
        $text = $data[0]['symbol'][0]['data'];

        return $text;
    }

    /**
     * Berechnung der Helligkeit eines Bereich
     * @param $coordinates
     * @param $step
     * @return float
     */
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

                //Bitshift durch die Farbwerte
                $r = ($pixColor >> 16) & 0xFF;
                $g = ($pixColor >> 8) & 0xFF;
                $b = $pixColor & 0xFF;

                //ITU-R Recommendation 601
                $lum = sqrt(0.299 * ($r * $r) + 0.587 * ($g * $g) + 0.114 * ($b * $b));

                $total_lum += $lum;

                $anzahlMessungen++;
            }
        }

        $avgLum = round($total_lum / $anzahlMessungen, 0);

        return $avgLum;
    }

    /**
     * Vergleich der Helligkeit eines Bereich mit Referenz-Helligkeit
     * @param $refLum
     * @param $checkLum
     * @param $variance
     * @return mixed
     */
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

    /**
     * Erstellt neue Instanz von Raster über das Bild.
     * Danach werden einzelne Zellen auf Helligkeit geprüft
     */
    private function checkEntry()
    {
        $this->_img = imagecreatefromjpeg($this->_imgPath);
        $raster = new \AppBundle\Model\Raster($this->_img);

        //Helligkeit des Referenzbereich LINKE SEITE
        $coordinates['x1'] = $raster->_border['left'];
        $coordinates['x2'] = $raster->_border['left'] + ($raster->_border['width'] * 0.5);
        $coordinates['y1'] = $raster->_border['top'];
        $coordinates['y2'] = $raster->_border['top'] + ($raster->_border['height'] * 0.1);

        $refLum = $this->getLum($coordinates, 10);

        //Helligkeit der Subject-Zelle prüfen LINKE SEITE
        //TODO: überarbeiten ... reagiert nur auf blau
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

        //Helligkeit des Referenzbereich RECHTE SEITE
        $coordinates['x1'] = $raster->_border['left'] + ($raster->_border['width'] * 0.5);
        $coordinates['x2'] = $raster->_border['left'] + $raster->_border['width'] - 1;
        $coordinates['y1'] = $raster->_border['top'];
        $coordinates['y2'] = $raster->_border['top'] + ($raster->_border['height'] * 0.1);

        $refLum = $this->getLum($coordinates, 10);

        //Helligkeit der Subject-Zelle prüfen RECHTE SEITE
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

    /**
     * Errechnet aus übergebener Zelle im Raster, sowie KW das Datum und den Wochentag
     * @param $i
     * @return mixed
     */
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
     * Berechnet aus der Rasterzelle die Unterrichtsstunde des entsprechenden Tages
     * @param $taskCell
     * @param string $page
     * @return float|int
     */
    private function getHourOfDay($taskCell, $page = 'left')
    {
        $offset = 1;
        if ($page == 'right') {
            $offset = 4;
        }

        $day_cell = $taskCell % 42;
        $hour = (($day_cell - $offset) / 6) + 1;

        return $hour;
    }

    /**
     * Errechnet aus übergebener Zelle im Raster und dem Wochentag das Schulfach aus dem _schedule Array
     * @param $cell
     * @param $day
     * @return mixed
     */
    private function getSubject($cell, $day)
    {
        $time_table_json = file_get_contents(__DIR__ . "/../Model/time_table.json");
        $time_table = json_decode($time_table_json, true);
        $hour = $this->getHourOfDay($cell);
        $subject = $time_table[$day][$hour];

        return $subject;
    }

    /**
     * Erstellt ein PNG Bild des Ausschnittes
     * @param $pathToSnippet
     * @param $cell
     * @param $raster
     */
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

    /**
     * Token zum Zugriff auf Restyaboard anfordern
     * Zugnagsdaten in restyaboard.json
     * @return mixed
     */
    private function getRestyaAuth() {
        $restya_config_json = file_get_contents(__DIR__ . "/../Model/restyaboard.json");
        $restya_config = json_decode($restya_config_json, true);

        //get token
        $url = $restya_config['host'] . ':' . $restya_config['port'] . '/api/v1/oauth.json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close ($ch);

        $result = json_decode($result, true);
        $access_token = $result['access_token'];

        $url = $restya_config['host'] . ':' . $restya_config['port'] . '/api/v1/users/login.json?token=' . $access_token;
        $ch = curl_init($url);
        $data['email'] = $restya_config['user'];
        $data['password'] = $restya_config['password'];
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data))
        );

        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);
        $access_token = $result['access_token'];

        return $access_token;
    }
}
