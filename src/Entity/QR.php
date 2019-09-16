<?php
namespace Nathejk\Entity;

/**
 * @Entity
 * @Table(name="qr")
 */
class QR extends \Nathejk\Entity
{
    /**
     * @Id @GeneratedValue @Column(type="integer", options={"unsigned":true})
     */
    protected $id;

    /**
     * @Column(type="integer", nullable=true, options={"unsigned":true})
     */
    protected $number;

    /**
     * @Column(type="string", length=20)
     */
    protected $secret;

    /**
     * @Column(type="datetime", nullable=true)
     */
    protected $mapCreateTime;

    /**
     * @Column(type="string", length=20, nullable=true)
     */
    protected $mapCreateByPhone;

    /**
     * @OneToMany(targetEntity="Scan", mappedBy="qr")
     */
    protected $scans;
}
