<?php

namespace App\Tests\Entity;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CommandeTest extends TestCase
{
    private function produit(string $nom, string $prix, int $stock = 100): Produit
    {
        return (new Produit())->setNom($nom)->setPrix($prix)->setStock($stock);
    }

    private function ligne(Produit $produit, ?int $quantite): LigneCommande
    {
        return (new LigneCommande())->setProduit($produit)->setQuantite($quantite);
    }

    private function commande(LigneCommande ...$lignes): Commande
    {
        $commande = (new Commande())
            ->setNumero('CMD-TEST')
            ->setStatut('En attente')
            ->setClient(new Client());
        foreach ($lignes as $ligne) {
            $commande->addLigneCommande($ligne);
        }

        return $commande;
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testMontantDUneLigne(): void
    {
        $ligne = $this->ligne($this->produit('Stylo', '10.50'), 3);
        $ligne->calculerMontant();

        $this->assertSame('10.50', $ligne->getPrixUnitaire());
        $this->assertSame('31.50', $ligne->getMontant());
    }

    public function testTotalAvecPlusieursLignes(): void
    {
        $commande = $this->commande(
            $this->ligne($this->produit('Cahier', '10.50'), 2),   // 21.00
            $this->ligne($this->produit('Crayon', '4.99'), 3),    // 14.97
            $this->ligne($this->produit('Règle', '1.20'), 5),     // 6.00
        );
        $commande->recalculerTotal();

        $this->assertSame('41.97', $commande->getTotal());
    }

    public function testTotalCommandeVideEstZero(): void
    {
        $commande = $this->commande();
        $commande->recalculerTotal();

        $this->assertSame('0.00', $commande->getTotal());
    }

    public function testTotalSeRecalculeApresSuppressionDUneLigne(): void
    {
        $ligneASupprimer = $this->ligne($this->produit('Gomme', '2.00'), 4);
        $commande = $this->commande($this->ligne($this->produit('Stylo', '3.00'), 2), $ligneASupprimer);
        $commande->recalculerTotal();
        $this->assertSame('14.00', $commande->getTotal());

        $commande->removeLigneCommande($ligneASupprimer);
        $commande->recalculerTotal();
        $this->assertSame('6.00', $commande->getTotal());
    }

    public function testDateEnregistreeAutomatiquement(): void
    {
        $avant = new \DateTimeImmutable('-1 second');
        $commande = new Commande();

        $this->assertNotNull($commande->getDate());
        $this->assertGreaterThanOrEqual($avant, $commande->getDate());
    }

    public function testCommandeValide(): void
    {
        $commande = $this->commande($this->ligne($this->produit('Stylo', '3.00', 10), 2));

        $this->assertCount(0, $this->validator()->validate($commande));
    }

    public function testQuantiteNulleRefusee(): void
    {
        $commande = $this->commande($this->ligne($this->produit('Stylo', '3.00'), 0));

        $this->assertGreaterThan(0, count($this->validator()->validate($commande)));
    }

    public function testQuantiteNegativeRefusee(): void
    {
        $commande = $this->commande($this->ligne($this->produit('Stylo', '3.00'), -2));

        $this->assertGreaterThan(0, count($this->validator()->validate($commande)));
    }

    public function testCommandeSansLigneRefusee(): void
    {
        $this->assertGreaterThan(0, count($this->validator()->validate($this->commande())));
    }

    public function testStockInsuffisantRefuse(): void
    {
        $commande = $this->commande($this->ligne($this->produit('Stylo', '3.00', 5), 6));

        $violations = $this->validator()->validate($commande);
        $this->assertGreaterThan(0, count($violations));
        $this->assertStringContainsString('Stock insuffisant', (string) $violations);
    }

    public function testStockCumuleSurPlusieursLignesDuMemeProduit(): void
    {
        $stylo = $this->produit('Stylo', '3.00', 5);
        $commande = $this->commande($this->ligne($stylo, 3), $this->ligne($stylo, 3)); // 6 > 5

        $this->assertStringContainsString('Stock insuffisant', (string) $this->validator()->validate($commande));
    }

    public function testStockJusteSuffisantAccepte(): void
    {
        $commande = $this->commande($this->ligne($this->produit('Stylo', '3.00', 5), 5));

        $this->assertCount(0, $this->validator()->validate($commande));
    }
}
