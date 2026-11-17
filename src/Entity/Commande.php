<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    public const STATUTS = [
        'En attente' => 'En attente',
        'Validée' => 'Validée',
        'Expédiée' => 'Expédiée',
        'Annulée' => 'Annulée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le numéro de commande est obligatoire')]
    private ?string $numero = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(choices: self::STATUTS, message: 'Statut invalide')]
    private ?string $statut = 'En attente';

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $total = '0.00';

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[Assert\NotNull(message: 'Veuillez sélectionner un client')]
    private ?Client $client = null;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'commande', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'Une commande doit contenir au moins un produit')]
    private Collection $ligneCommandes;

    public function __construct()
    {
        $this->ligneCommandes = new ArrayCollection();
        // Date et heure enregistrées automatiquement à la création
        $this->date = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLigneCommandes(): Collection
    {
        return $this->ligneCommandes;
    }

    public function addLigneCommande(LigneCommande $ligneCommande): static
    {
        if (!$this->ligneCommandes->contains($ligneCommande)) {
            $this->ligneCommandes->add($ligneCommande);
            $ligneCommande->setCommande($this);
        }

        return $this;
    }

    public function removeLigneCommande(LigneCommande $ligneCommande): static
    {
        if ($this->ligneCommandes->removeElement($ligneCommande)) {
            if ($ligneCommande->getCommande() === $this) {
                $ligneCommande->setCommande(null);
            }
        }

        return $this;
    }

    /**
     * Recalcule le montant de chaque ligne puis le total de la commande
     * à partir des données métier (prix des produits × quantités).
     */
    public function recalculerTotal(): static
    {
        $totalCentimes = 0;
        foreach ($this->ligneCommandes as $ligne) {
            $ligne->calculerMontant();
            $totalCentimes += LigneCommande::versCentimes($ligne->getMontant());
        }

        $this->total = LigneCommande::depuisCentimes($totalCentimes);

        return $this;
    }

    /**
     * Contrôle métier : le stock de chaque produit doit couvrir la quantité
     * demandée (toutes lignes confondues si un produit apparaît plusieurs fois).
     */
    #[Assert\Callback]
    public function validerStock(ExecutionContextInterface $context): void
    {
        $demandes = [];
        $produits = [];

        foreach ($this->ligneCommandes as $ligne) {
            $produit = $ligne->getProduit();
            $quantite = $ligne->getQuantite();
            if (null === $produit || null === $quantite || $quantite <= 0) {
                continue;
            }
            $cle = spl_object_id($produit);
            $produits[$cle] = $produit;
            $demandes[$cle] = ($demandes[$cle] ?? 0) + $quantite;
        }

        foreach ($demandes as $cle => $quantite) {
            $stock = $produits[$cle]->getStock() ?? 0;
            if ($quantite > $stock) {
                $context->buildViolation('Stock insuffisant pour « {{ produit }} » : {{ demande }} demandé(s), {{ stock }} disponible(s).')
                    ->setParameter('{{ produit }}', (string) $produits[$cle]->getNom())
                    ->setParameter('{{ demande }}', (string) $quantite)
                    ->setParameter('{{ stock }}', (string) $stock)
                    ->atPath('ligneCommandes')
                    ->addViolation();
            }
        }
    }
}
