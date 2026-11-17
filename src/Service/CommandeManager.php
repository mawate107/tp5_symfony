<?php

namespace App\Service;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;

class CommandeManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Calcule les montants, décrémente le stock des produits commandés
     * et enregistre la commande (le tout dans une seule transaction).
     */
    public function enregistrer(Commande $commande): void
    {
        $commande->recalculerTotal();

        $this->entityManager->wrapInTransaction(function () use ($commande): void {
            foreach ($commande->getLigneCommandes() as $ligne) {
                $produit = $ligne->getProduit();
                $produit->setStock(($produit->getStock() ?? 0) - $ligne->getQuantite());
            }
            $this->entityManager->persist($commande);
        });
    }

    /** Génère un numéro de commande par défaut, ex. CMD-20261117-A3F9. */
    public static function genererNumero(): string
    {
        return sprintf('CMD-%s-%s', (new \DateTimeImmutable())->format('Ymd'), strtoupper(bin2hex(random_bytes(2))));
    }
}
