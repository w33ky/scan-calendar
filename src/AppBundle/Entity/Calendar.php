<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Calender
 * @package AppBundle\Entity
 * @ORM\Table(name="calendar")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CalenderRepository")
 */
class Calendar
{
    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=255)
     * @ORM\Id
     */
    private $id;

    /**
     * @var \DateTime
     * @ORM\Column(name="date", type="date")
     */
    private $date;

    /**
     * @var string
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * @var string
     * @ORM\Column(name="subject", type="string", length=255)
     */
    private $subject;

    /**
     * @var int
     * @ORM\Column(name="hour", type="integer")
     */
    private $hour;

    /**
     * @var CalList
     * @ORM\ManyToOne(targetEntity="CalList")
     */
    private $inList;

    /**
     * Set date
     * @param \DateTime $date
     * @return Calendar
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     * @return \DateTime 
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set taskLink
     * @param string $id
     * @return Calendar
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get taskLink
     * @return string 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type
     * @param string $type
     * @return Calendar
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set subject
     * @param string $subject
     * @return Calendar
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get subject
     * @return string 
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set hour
     * @param integer $hour
     * @return Calendar
     */
    public function setHour($hour)
    {
        $this->hour = $hour;

        return $this;
    }

    /**
     * Get hour
     * @return integer 
     */
    public function getHour()
    {
        return $this->hour;
    }

    /**
     * Set inList
     * @param \AppBundle\Entity\CalList $inList
     * @return Calendar
     */
    public function setInList(\AppBundle\Entity\CalList $inList = null)
    {
        $this->inList = $inList;

        return $this;
    }

    /**
     * Get inList
     * @return \AppBundle\Entity\CalList
     */
    public function getInList()
    {
        return $this->inList;
    }
}
