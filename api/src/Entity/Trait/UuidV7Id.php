<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Identifiant UUID v7 commun à toutes les entités (issue #22).
 *
 * Généré par `doctrine.uuid_generator`, qui s'appuie sur la UuidFactory de Symfony
 * (version fixée à 7 dans config/packages/uid.yaml). Stocké dans une colonne native `uuid` de PostgreSQL.
 * L'identifiant n'existe qu'après le `persist()`.
 */
trait UuidV7Id
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['common:read'])]
    private ?Uuid $id = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }
}
