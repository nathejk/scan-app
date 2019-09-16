<?php
namespace Nathejk\Entity;

/**
 * @Entity
 * @Table(name="scan")
 */
class Scan extends \Nathejk\Entity
{
    public function __construct()
    {
        $this->time = new \DateTime;
    }

    /**
     * @Id @GeneratedValue @Column(type="integer", options={"unsigned":true})
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="QR", inversedBy="scans")
     */
    protected $qr;

    /**
     * @Column(type="string", length=20)
     */
    protected $phone;

    /**
     * @Column(type="datetime")
     */
    protected $time;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    protected $location;
}
