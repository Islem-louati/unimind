<?php
namespace App\Entity;

use App\Repository\FavoriRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriRepository::class)]
class Favori
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'favoris')]
#[ORM\JoinColumn(name: "user_id", referencedColumnName: "user_id", nullable: false, onDelete: 'CASCADE')]
private ?User $user = null;


    #[ORM\ManyToOne(targetEntity: SeanceMeditation::class)]
   #[ORM\JoinColumn(name: "seance_id", referencedColumnName: "seance_id", nullable: false, onDelete: 'CASCADE')]
    private ?SeanceMeditation $seance = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Entity(repositoryClass: FavoriRepository::class)]
#[ORM\Table(
    name: "favori",
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: "user_seance_unique", columns: ["user_id", "seance_id"])
    ]
)]


    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ================== GETTERS ==================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getSeance(): ?SeanceMeditation
    {
        return $this->seance;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    // ================== SETTERS ==================

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setSeance(?SeanceMeditation $seance): self
    {
        $this->seance = $seance;
        return $this;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
