<?php
/**
 * Created by PhpStorm.
 * User: w33ky
 * Date: 15.12.16
 * Time: 11:49
 */
namespace AppBundle\Model;

class Raster
{
    /**
     * @var array Speichert das Raster
     */
    public $_raster = array();

    /**
     * @var array Speichern der Border-Variable - gibt an wo der Kalender beginnt und endet
     */
    public $_border = array();

    /**
     * Raster constructor.
     * @param $img
     */
    public function __construct($img)
    {
        $this->_border['top'] = $this->getBorder($img, 'top');
        $this->_border['bottom'] = $this->getBorder($img, 'bottom');
        $this->_border['left'] = $this->getBorder($img, 'left');
        $this->_border['right'] = $this->getBorder($img, 'right');

        $this->_border['width'] = $this->_border['right'] - $this->_border['left'];
        $this->_border['height'] = $this->_border['bottom'] - $this->_border['top'];

        $this->getRaster('left');
        $this->getRaster('right');
    }

    /**
     * Berechnet die Ränder des Scans und legt diese in _boder ab
     * @param $img
     * @param $side
     * @return float|int
     */
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

        for ($i = 1; $i < $end; $i += $step) {
            $pixel = $start + ($i * $count);
            $center = $middle;

            if ($side == 'left' or $side == 'right') {
                $help = $center;
                $center = $pixel;
                $pixel = $help;
            }

            if ($i == 1) {
                $colorComp = imagecolorat($img, $center, $pixel);
            } else {
                $colorAct = imagecolorat($img, $center, $pixel);
                $colorMin = $colorComp - $variance;
                $colorMax = $colorComp + $variance;

                if ($colorAct < $colorMax && $colorAct > $colorMin) {
                    $colorComp = $colorAct;
                } else {
                    if ($side == 'right' or $side == 'left') {
                        $border = $center;
                    } else {
                        $border = $pixel;
                    }
                    break;
                }
            }
        }

        return $border;
    }

    /**
     * Erzeugt das Raster und speichert es in _raster
     * @param $page
     */
    private function getRaster($page)
    {
        $col = $this->getCol();
        $rowHeight = $this->getRow();

        if ($page == 'left') {
            $rowStart = 0;
            $rowEnd = 21;
            $colStart = 0;
            $colEnd = 6;
            $startLeft = $this->_border['left'];
        } else {
            $rowStart = 0;
            $rowEnd = 14;
            $colStart = 6;
            $colEnd = 12;
            $startLeft = $this->_border['left'];
            for ($i = 0; $i < 6; $i++) {
                $startLeft += $col[$i];
            }
        }

        for ($i = $rowStart; $i < $rowEnd; $i++) {
            $x2 = $startLeft;
            $y1 = $this->_border['top'] + ($i * $rowHeight);
            $y2 = $y1 + $rowHeight;

            for ($j = $colStart; $j < $colEnd; $j++) {
                $x1 = $x2;
                $x2 = $x1 + $col[$j];
                $y1 = $this->_border['top'] + ($i * $rowHeight);
                $y2 = $y1 + $rowHeight;

                $koordinaten = array(
                    'col' => $j + 1,
                    'row' => $i + 1,
                    'x1' => $x1,
                    'x2' => $x2,
                    'y1' => $y1,
                    'y2' => $y2
                );

                array_push($this->_raster, $koordinaten);
            }
        }
    }

    /**
     * Berechnet die Höhe jeder Spalte
     * @return float
     */
    private function getRow()
    {
        $rowHeight = floor($this->_border['height'] / 21);
        return $rowHeight;
    }

    /**
     * Berechnet die Breite der einzelnen Spalten
     * @return array
     */
    private function getCol()
    {
        $colSubject = floor($this->_border['width'] / 2 * 0.1);
        $colTask = floor($this->_border['width'] / 2 * 0.6);
        $colHA = floor($this->_border['width'] / 2 * 0.07);
        $colLK = floor($this->_border['width'] / 2 * 0.07);
        $colKA = floor($this->_border['width'] / 2 * 0.06);
        $colEmpty = floor($this->_border['width'] / 2 * 0.1);

        $col = array(
            0 => $colSubject,
            1 => $colTask,
            2 => $colHA,
            3 => $colLK,
            4 => $colKA,
            5 => $colEmpty,
            6 => $colEmpty,
            7 => $colHA,
            8 => $colLK,
            9 => $colKA,
            10 => $colTask,
            11 => $colSubject
        );

        return $col;
    }

    /**
     * Zeichnet das Raster in ein Bild im Ordner web/images/raster_neu.png
     * @param $border
     * @param $raster
     */
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
        imagepng($bild, 'images/raster_neu.png');
        //imagedestory($bild);
    }
}