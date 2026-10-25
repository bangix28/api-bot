<?php

namespace App\Controller\Admin;

use App\Entity\LanEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
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
        // RiotAccount n'a pas de __toString : le Riot ID sert de libellé.
        yield AssociationField::new('participants', 'Participants')
            ->setFormTypeOption('choice_label', 'riotId')
            ->setFormTypeOption('by_reference', false);
    }
}
