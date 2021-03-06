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


/**
 * Class TaskController
 * Standard Controller für Task Actions
 * @package AppBundle\Controller
 */
class TaskController extends Controller
{
    /**
     * @Route("/api/task/{id}", name="get_task")
     * @Method("GET")
     */
    public function getTaskAction($id)
    {
        $task = $this->getDoctrine()->getRepository('AppBundle:Task')->find($id);

        if(!$task) return self::jsonError('no task with id ' . $id, 404);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($task, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/upload/{id}", name="upload_to_list")
     * @Method("POST")
     */
    public function uploadToListAction($id, Request $request)
    {
        $sentFile = $request->files->get('file');
        $filename = 'upload/' . uniqid('img_');
        $succes = move_uploaded_file($sentFile->getPathName(), $filename);

        if (!$succes) return self::jsonError('couldn\'t move file', 500);

        $mimetype = mime_content_type($filename);
        $img = imagecreatefromjpeg($filename);

        if ($mimetype != 'image/jpeg') {
            unlink($filename);
            return self::jsonError('wrong file type - jpeg needed', 415);
        }

        $em = $this->getDoctrine()->getManager();


        $list = $em->getRepository('AppBundle:TaskList')->find(intval($id));
        if(!$list) {
            return TaskController::jsonError('no list with id ' . $id, 404);
        }

        $task_entrys = [];
        $tasks = $this->checkEntrys($img, $filename);
        for ($i = 0; $i < count($tasks, 0); $i++) {

            $db_task = $em->getRepository('AppBundle:Task')->find($tasks[$i]['snippet']);

            $date = new\DateTime($tasks[$i]['date']);
            if (!$db_task) {
                $task = new Task;
                $task->setId($tasks[$i]['snippet']);
                $task->setType($tasks[$i]['type']);
                $task->setSubject($tasks[$i]['subject']);
                $task->setHour($tasks[$i]['col']);
                $task->setDate($date);
                $task->setInList($list);
                $em->persist($task);
                $task_entrys[] = $task;
            } else {
                $db_task->setType($tasks[$i]['type']);
                $db_task->setSubject($tasks[$i]['subject']);
                $db_task->setHour($tasks[$i]['col']);
                $db_task->setDate($date);
                $task_entrys[] = $db_task;
            }

            $em->flush();
        }
        unlink($filename);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($task_entrys, 'json'), 201, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/tasks", name="get_tasks")
     * @Method("GET")
     */
    public function getTasksAction()
    {
        $task = $this->getDoctrine()->getRepository('AppBundle:Task')->findAll();

        if (!$task) return self::jsonError('could not load tasks', 500);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($task, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/task/{id}", name="delete_task")
     * @Method("DELETE")
     */
    public function deleteTaskAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $task = $em->getRepository('AppBundle:Task')->find($id);

        if(!$task) return self::jsonError('no task with id ' . $id, 404);
        unlink('images/' . $task->getId() . '.png');

        $em->remove($task);
        $em->flush();

        return new Response(null, 204, ['Access-Control-Allow-Origin' => '*']);
    }

    /**
     * @Route("/api/task/{id}", name="update_task")
     * @Method("PUT")
     */
    public function updateTaskAction($id, Request $request) {
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
        }

        if (count($parametersAsArray) == 0) return self::jsonError('invalid json', 400);

        $em = $this->getDoctrine()->getManager();
        $db_Appointment = $em->getRepository('AppBundle:Task')->find($id);
        if (!$db_Appointment) return self::jsonError('no task with id ' . $id, 404);

        if (array_key_exists('in_list', $parametersAsArray)) {
            $inList = $parametersAsArray['in_list'];

            if (array_key_exists('id', $inList)) {
                $db_list = $em->getRepository('AppBundle:TaskList')->find($inList['id']);
                if (!$db_list) return self::jsonError('no list with id ' . $inList, 404);

                $db_Appointment->setInList($db_list);
            }
        }

        if (array_key_exists('date', $parametersAsArray)) {
            $timestamp = strtotime($parametersAsArray['date']);
            $date = new \DateTime();
            $date->setTimestamp($timestamp);
            $db_Appointment->setDate($date);
        }
        if (array_key_exists('type', $parametersAsArray)) $db_Appointment->setType($parametersAsArray['type']);
        if (array_key_exists('subject', $parametersAsArray)) $db_Appointment->setSubject($parametersAsArray['subject']);
        if (array_key_exists('hour', $parametersAsArray)) $db_Appointment->setHour($parametersAsArray['hour']);

        $em->flush($db_Appointment);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($db_Appointment, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);
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
        $tasks = $this->getDoctrine()
            ->getRepository('AppBundle:Task')
            ->findAll();

        return $this->render('calendar/list.html.twig', array(
            'tasks' => $tasks
        ));
    }

    /**
     * @Route("/view/{id}", name="view_calendar")
     */
    public function viewAction($id)
    {
        $task = $this->getDoctrine()
            ->getRepository('AppBundle:Task')
            ->find($id);

        return $this->render('calendar/view.html.twig', array(
            'task' => $task
        ));
    }

    /**
     * @Route("/delete/{id}", name="delete_calendar")
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $task = $em->getRepository('AppBundle:Task')->find($id);

        unlink('images/' . $task->getId() . '.png');

        $em->remove($task);
        $em->flush();

        $this->addFlash(
            'notice_success',
            'Task Removed'
        );

        return $this->redirectToRoute('list_calendar');
    }

    /**
     * @Route("/api/timetable", name="get_time_table")
     */
    public function getTimeTable() {
        $time_table_json = file_get_contents(__DIR__ . "/../Model/time_table.json");
        $time_table = json_decode($time_table_json, true);

        $serializer = $this->get('jms_serializer');
        return new Response($serializer->serialize($time_table, 'json'), 200, ['Content-Type' => "application/json", 'Access-Control-Allow-Origin' => '*']);

    }

    public static function jsonError($err, $code = 500) {
        $err_array = [];
        $err_array['error'] = $err;
        return new JsonResponse($err_array, $code, ['Access-Control-Allow-Origin' => '*']);
    }

    private function thumbnail_box($img, $box_w, $box_h) {
        //create the image, of the required size
        $new = imagecreatetruecolor($box_w, $box_h);
        if($new === false) {
            //creation failed -- probably not enough memory
            return null;
        }


        //Fill the image with a light grey color
        //(this will be visible in the padding around the image,
        //if the aspect ratios of the image and the thumbnail do not match)
        //Replace this with any color you want, or comment it out for black.
        //I used grey for testing =)
        $fill = imagecolorallocate($new, 255, 255, 255);
        imagefill($new, 0, 0, $fill);

        //compute resize ratio
        $hratio = $box_h / imagesy($img);
        $wratio = $box_w / imagesx($img);
        $ratio = min($hratio, $wratio);

        //if the source is smaller than the thumbnail size,
        //don't resize -- add a margin instead
        //(that is, dont magnify images)
        if($ratio > 1.0)
            $ratio = 1.0;

        //compute sizes
        $sy = floor(imagesy($img) * $ratio);
        $sx = floor(imagesx($img) * $ratio);

        //compute margins
        //Using these margins centers the image in the thumbnail.
        //If you always want the image to the top left,
        //set both of these to 0
        $m_y = floor(($box_h - $sy) / 2);
        $m_x = floor(($box_w - $sx) / 2);

        //Copy the image data, and resample
        //
        //If you want a fast and ugly thumbnail,
        //replace imagecopyresampled with imagecopyresized
        if(!imagecopyresampled($new, $img,
            $m_x, $m_y, //dest x, y (margins)
            0, 0, //src x, y (0,0 means top left)
            $sx, $sy,//dest w, h (resample to this size (computed above)
            imagesx($img), imagesy($img)) //src w, h (the full size of the original)
        ) {
            //copy failed
            imagedestroy($new);
            return null;
        }
        //copy successful
        return $new;
    }

    /**
     * Generiert ein Array, mit dem Vorkommen der Verschiedenen Farb-Werte
     * @param resource $img Bilddatei
     * @param int $scale Skala in der die Farben aufgeteilt werden
     * @param array $coordinates Bildbereich
     * @param int $step Schrittabstand
     * @param int $filter
     * @return array
     */
    private function getColorCounts($img, $scale, $coordinates, $step = 1, $filter = 6)
    {
        $xStart = $coordinates['x1'];
        $xEnd = $coordinates['x2'];
        $yStart = $coordinates['y1'];
        $yEnd = $coordinates['y2'];

        $color_quantity_r = [];
        $color_quantity_g = [];
        $color_quantity_b = [];

        for ($i = 0; $i < $scale; $i++) {
            $color_quantity_r[$i] = 0;
            $color_quantity_g[$i] = 0;
            $color_quantity_b[$i] = 0;
        }

        for ($y = $yStart; $y < $yEnd; $y += $step) {
            for ($x = $xStart; $x < $xEnd; $x += $step) {
                $pixColor = imagecolorat($img, $x, $y);

                //Bitshift durch die Farbwerte
                $r = ($pixColor >> 16) & 0xFF;
                $g = ($pixColor >> 8) & 0xFF;
                $b = $pixColor & 0xFF;

                $index = floor((($scale - 1) / 255) * $r);
                $color_quantity_r[$index] += 1;
                $index = floor((($scale - 1) / 255) * $g);
                $color_quantity_g[$index] += 1;
                $index = floor((($scale - 1) / 255) * $b);
                $color_quantity_b[$index] += 1;
            }
        }

        $r_max = 0;
        $r_max_index = 0;
        $g_max = 0;
        $g_max_index = 0;
        $b_max = 0;
        $b_max_index = 0;

        $range = floor($scale / $filter);

        for ($i = 0; $i < $scale - $range; $i++) {
            $sum_r = 0;
            $sum_g = 0;
            $sum_b = 0;
            for ($j = 0; $j < $range; $j++) {
                $sum_r += $color_quantity_r[$i + $j];
                $sum_g += $color_quantity_g[$i + $j];
                $sum_b += $color_quantity_b[$i + $j];
            }
            if ($sum_r > $r_max) {
                $r_max = $sum_r;
                $r_max_index = $i;
            }
            if ($sum_g > $g_max) {
                $g_max = $sum_g;
                $g_max_index = $i;
            }
            if ($sum_b > $b_max) {
                $b_max = $sum_b;
                $b_max_index = $i;
            }
        }

        for ($j = 0; $j < $range; $j++) {
            $color_quantity_r[$r_max_index + $j] = 0;
            $color_quantity_g[$g_max_index + $j] = 0;
            $color_quantity_b[$b_max_index + $j] = 0;
        }

        $avg_r = 0;
        $avg_g = 0;
        $avg_b = 0;
        for ($i = 0; $i < $scale; $i++) {
            $avg_r += $color_quantity_r[$i];
            $avg_g += $color_quantity_g[$i];
            $avg_b += $color_quantity_b[$i];
        }

        $avg_r = $avg_r / $scale;
        $avg_g = $avg_g / $scale;
        $avg_b = $avg_b / $scale;

        $color_quantity['r'] = $color_quantity_r;
        $color_quantity['g'] = $color_quantity_g;
        $color_quantity['b'] = $color_quantity_b;
        $color_quantity['avg_r'] = $avg_r;
        $color_quantity['avg_g'] = $avg_g;
        $color_quantity['avg_b'] = $avg_b;

        return $color_quantity;
    }

    /**
     * Zieht Linien durch einen bestimmten Bereich und gibt Farb-Statistiken zu diesen zurück.
     * @param resource $img Bilddatei
     * @param array $coordinates Koordinaten Bereich
     * @param int $numLines Anzahl der gezogenen Linien
     * @param int $step Schrittabstand
     * @param int $neighborhood Anzahl der einzubeziehenden Nachbarn
     * @return array Farb-Statistiken
     */
    private function getColorStatsFromLines($img, $coordinates, $numLines = 3, $step = 1, $neighborhood = 0)
    {
        $xStart = $coordinates['x1'];
        $xEnd = $coordinates['x2'];
        $yStart = $coordinates['y1'];
        $yEnd = $coordinates['y2'];

        $height = $yEnd - $yStart;

        $lineSpacing = floor($height / ($numLines + 1));

        $color = Array();

        for ($i = 0; $i < $numLines; $i++) {
            $y = $yStart + ($i + 1) * $lineSpacing;

            $newcoordinates['x1'] = $xStart;
            $newcoordinates['x2'] = $xEnd;
            $newcoordinates['y1'] = $y;
            $newcoordinates['y2'] = $y + $neighborhood * 2 + 1;
            $color[] = $this->getColorStatistic($img, $newcoordinates, $step, $neighborhood);
        }

        return $color;
    }

    /**
     * Farbstatistiken für bestimmten Bereich berechnen
     * @param resource $img Bilddatei
     * @param array $coordinates Koordinaten Bereich
     * @param int $step Schrittabstand
     * @param int $neighborhood Anzahl der einzubeziehenden Nachbarn
     * @return array Farb-Statistiken
     */
    private function getColorStatistic($img, $coordinates, $step = 1, $neighborhood = 0)
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
        $jumps = ['r' => 0, 'g' => 0, 'b' => 0];
        $last = ['r' => 0, 'g' => 0, 'b' => 0];

        $xStart = $coordinates['x1'];
        $xEnd = $coordinates['x2'];
        $yStart = $coordinates['y1'];
        $yEnd = $coordinates['y2'];

        for ($y = $yStart + $neighborhood; $y < $yEnd - $neighborhood; $y += $step) {
            for ($x = $xStart + $neighborhood; $x < $xEnd - $neighborhood; $x += $step) {
                $r = 0;
                $g = 0;
                $b = 0;
                $loopcount = 0;
                for ($ny = $y - $neighborhood; $ny <= $y + $neighborhood; $ny++) {
                    for ($nx = $x - $neighborhood; $nx <= $x + $neighborhood; $nx++) {
                        $pixColor = imagecolorat($img, $nx, $ny);

                        //Bitshift durch die Farbwerte
                        $r += ($pixColor >> 16) & 0xFF;
                        $g += ($pixColor >> 8) & 0xFF;
                        $b += $pixColor & 0xFF;

                        $loopcount++;
                    }
                }
                $r = $r / $loopcount;
                $g = $g / $loopcount;
                $b = $b / $loopcount;

                $threshold = 40;
                if ($last['r'] < $r - $threshold || $last['r'] > $r + $threshold) $jumps['r']++;
                if ($last['g'] < $g - $threshold || $last['g'] > $g + $threshold) $jumps['g']++;
                if ($last['b'] < $b - $threshold || $last['b'] > $b + $threshold) $jumps['b']++;

                $last['r'] = $r;
                $last['g'] = $r;
                $last['b'] = $r;

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
        $out['jumps'] = $jumps;

        return $out;
    }

    /**
     * Liest QR Code von Bilddatei aus
     * @param string $filename Pfad zu Bilddatei
     * @return string QR-Text
     */
    private function getQrCode($filename)
    {
        //TODO: cut and resize image for faster upload
        $post_data['file'] = new \CURLFile($filename, mime_content_type($filename));

        $ch = curl_init('http://api.qrserver.com/v1/read-qr-code/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        $text = $data[0]['symbol'][0]['data'];

        return $text;
    }

    /**
     * Überprüft ob eine Zelle beschrieben ist indem Farbsprünge gesucht werden
     * @param resource $img Bilddatei
     * @param int $index Index im Raster
     * @param Raster $raster Raster
     * @param int $threshold Schwellwert
     * @return int Anzahl der Farbsprünge
     */
    private function checkCell($img, $index, $raster, $threshold = 3)
    {
        $stats = $this->getColorStatsFromLines($img, $raster->_raster[$index], 3, 2, 1);
        $jump['r'] = max($stats[0]['jumps']['r'], $stats[1]['jumps']['r'], $stats[2]['jumps']['r']);
        $jump['g'] = max($stats[0]['jumps']['g'], $stats[1]['jumps']['g'], $stats[2]['jumps']['g']);
        $jump['b'] = max($stats[0]['jumps']['b'], $stats[1]['jumps']['b'], $stats[2]['jumps']['b']);

        $match_count = 0;
        if ($jump['r'] > $threshold) $match_count++;
        if ($jump['g'] > $threshold) $match_count++;
        if ($jump['b'] > $threshold) $match_count++;

        return $match_count;
    }

    /**
     * Erstellt neue Instanz von Raster über das Bild.
     * Danach werden einzelne Zellen auf Helligkeit geprüft
     * @param resource $img Bilddatei
     * @param string $filename Pfad zu Bilddatei
     * @return array Liste an Appointments
     */
    private function checkEntrys($img, $filename)
    {
        $raster = new \AppBundle\Model\Raster($img);

        $qr_text = $this->getQrCode($filename);

        $appointments = [];
        for ($i = 1; $i < 206; $i += 6) {
            $index = $i;
            if ($i > 121) $index += 3;

            $match = $this->checkCell($img, $index, $raster);

            if ($match > 0) {
                $ha_col = $index + 1;
                if ($i > 121) $ha_col = $index - 3;
                $lk_col = $index + 2;
                if ($i > 121) $lk_col = $index - 2;
                $ka_col = $index + 3;
                if ($i > 121) $ka_col = $index - 1;

                $is_ha = $this->checkCell($img, $ha_col, $raster, 2);
                $is_lk = $this->checkCell($img, $lk_col, $raster, 2);
                $is_ka = $this->checkCell($img, $ka_col, $raster, 2);

                $count = 0;
                if ($is_ha) $count++;
                if ($is_lk) $count++;
                if ($is_ka) $count++;

                $type = '';
                if ($count == 0) $type = 'none';
                elseif ($count > 1) $type = 'fail';
                else {
                    if ($is_ha) $type = 'HA';
                    if ($is_lk) $type = 'LK';
                    if ($is_ka) $type = 'KA';
                }

                //backup strategy
                if ($type == 'fail') {
                    $ha_lum = $this->getColorStatistic($img, $raster->_raster[$ha_col]);
                    $ha_lum = $ha_lum['lum'];
                    $lk_lum = $this->getColorStatistic($img, $raster->_raster[$lk_col]);
                    $lk_lum = $lk_lum['lum'];
                    $ka_lum = $this->getColorStatistic($img, $raster->_raster[$ka_col]);
                    $ka_lum = $ka_lum['lum'];
                    if ($ha_lum <= $lk_lum && $ha_lum <= $ka_lum) $type = 'HA';
                    if ($lk_lum <= $ha_lum && $lk_lum <= $ka_lum) $type = 'LK';
                    if ($ka_lum <= $ha_lum && $ka_lum <= $lk_lum) $type = 'KA';
                }

                $page = 'left';
                if ($index > 125) $page = 'right';

                $day = $this->checkDay($index, $qr_text);
                $pathToSnippet = $day['date'] . '_' . $this->getHourOfDay($index, $page);
                $snippet = $this->snippPic($img, $pathToSnippet, $index, $raster);
                $subject = $this->getSubject($index, $day['weekday']);

                $appointment['col'] = $this->getHourOfDay($index, $page);
                $appointment['type'] = $type;
                $appointment['day'] = $day['weekday'];
                $appointment['snippet'] = $pathToSnippet;
                $appointment['subject'] = $subject;
                $appointment['date'] = $day['date'];

                $appointments[] = $appointment;
            }
        }

        return $appointments;
    }

    /**
     * Errechnet aus übergebener Zelle im Raster, sowie KW das Datum und den Wochentag
     * @param int $i Zellennummer
     * @param string $qr_text Text aus dem QR-Code
     * @return array Datum und Wochentag
     */
    private function checkDay($i, $qr_text)
    {
        $parts = explode("-", $qr_text);
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
     * @param int $taskCell Rasterzelle
     * @param string $page 'left' oder 'right'
     * @return int Unterrichtsstunde
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
     * @param int $cell Rasterzelle
     * @param string $day Wochentag
     * @return string Unterrichtsstunde
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
     * @param resource $img Bilddatei
     * @param string $pathToSnippet Pfad zum zu schreibenden Bildausschnitt
     * @param int $cell Rasterzelle
     * @param Raster $raster Raster
     */
    private function snippPic($img, $pathToSnippet, $cell, $raster)
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

        imagecopyresized($bild, $img, 0, 0, $x1, $y1, $width, $height, $width, $height);

        // Bild schreiben
        imagepng($bild, 'images/' . $pathToSnippet . '.png');

        /*$ratio = 150/202;
        $restya_picture = $this->thumbnail_box($bild, $width, floor($width * $ratio));
        imagepng($restya_picture, 'images/' . $pathToSnippet . '_restya' . '.png');*/
    }

    /**
     * Token zum Zugriff auf Restyaboard anfordern
     * Zugnagsdaten in restyaboard.json
     * @return string Access-Token
     */
    private function getRestyaAuth()
    {
        $restya_config_json = file_get_contents(__DIR__ . "/../Model/restyaboard.json");
        $restya_config = json_decode($restya_config_json, true);

        //get token
        $url = $restya_config['host'] . ':' . $restya_config['port'] . '/api/v1/oauth.json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

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
