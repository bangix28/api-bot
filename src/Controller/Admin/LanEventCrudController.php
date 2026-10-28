<?php

namespace App\Controller\Admin;

use App\Domain\LanChallenge\LanQueue;
use App\Entity\LanEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class LanEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LanEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('LAN')
            ->setEntityLabelInPlural('LAN')
            ->setDefaultSort(['startsAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield DateTimeField::new('startsAt', 'Début');
        yield DateTimeField::new('endsAt', 'Fin')
            ->setHelp('Seules les parties jouées entre le début et la fin comptent pour les défis.');
        yield ChoiceField::new('acceptedQueueIds', 'Files')
            ->setChoices(array_combine(
                array_map(static fn(LanQueue $queue) => $queue->label(), LanQueue::cases()),
                array_map(static fn(LanQueue $queue) => $queue->value, LanQueue::cases()),
            ))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges()
            ->setHelp('Seules les parties de ces files comptent pour les défis. Les classées demandent un agent capable de lire le client LoL.');
        // RiotAccount n'a pas de __toString : le Riot ID sert de libellé.
        yield AssociationField::new('participants', 'Participants')
            ->setFormTypeOption('choice_label', 'riotId')
            ->setFormTypeOption('by_reference', false);
    }
}
