<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Commande;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // La date/heure est enregistrée automatiquement et le total est calculé :
        // aucun de ces deux champs n'est saisi dans le formulaire.
        $builder
            ->add('numero', TextType::class, ['label' => 'Numéro'])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => Commande::STATUTS,
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'label' => 'Client',
                'placeholder' => '— Choisir un client —',
                'choice_label' => static fn (Client $c): string => sprintf('%s %s', $c->getPrenom(), $c->getNom()),
            ])
            ->add('ligneCommandes', CollectionType::class, [
                'label' => 'Produits commandés',
                'entry_type' => LigneCommandeType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
        ]);
    }
}
