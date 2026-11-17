<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La quantité est obligatoire')]
    #[Assert\Positive(message: 'La quantité doit être strictement positive')]
    private ?int $quantite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixUnitaire = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $montant = '0.00';

    #[ORM\ManyToOne(inversedBy: 'ligneCommandes')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(inversedBy: 'ligneCommandes')]
    #[Assert\NotNull(message: 'Veuillez sélectionner un produit')]
    private ?Produit $produit = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    /**
     * Fige le prix du produit au moment de la commande puis calcule
     * le montant de la ligne (prix unitaire × quantité).
     */
    public function calculerMontant(): static
    {
        if (null === $this->produit || null === $this->quantite) {
            $this->montant = '0.00';

            return $this;
        }

        $this->prixUnitaire = $this->produit->getPrix() ?? '0.00';
        $this->montant = self::depuisCentimes(
            self::versCentimes($this->prixUnitaire) * $this->quantite
        );

        return $this;
    }

    /** Convertit "12.50" en 1250 (calcul en entiers pour éviter les erreurs de flottants). */
    public static function versCentimes(?string $montant): int
    {
        return (int) round(((float) $montant) * 100);
    }

    /** Convertit 1250 en "12.50". */
    public static function depuisCentimes(int $centimes): string
    {
        return number_format($centimes / 100, 2, '.', '');
    }
}
