<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Service\CommandeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commande')]
final class CommandeController extends AbstractController
{
    #[Route('', name: 'app_commande_index', methods: ['GET'])]
    public function index(CommandeRepository $commandeRepository): Response
    {
        return $this->render('commande/index.html.twig', [
            'commandes' => $commandeRepository->findBy([], ['date' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_commande_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CommandeManager $commandeManager, ClientRepository $clientRepository): Response
    {
        $commande = new Commande();
        $commande->setNumero(CommandeManager::genererNumero());

        // Pré-sélection du client quand on arrive depuis sa fiche (?client=ID)
        $clientId = $request->query->getInt('client');
        if ($clientId > 0 && ($client = $clientRepository->find($clientId)) instanceof Client) {
            $commande->setClient($client);
        }

        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commandeManager->enregistrer($commande);
            $this->addFlash('success', 'Commande enregistrée.');

            return $this->redirectToRoute('app_commande_show', ['id' => $commande->getId()]);
        }

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_commande_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }
}
