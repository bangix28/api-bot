<?php

namespace App\Controller\Admin;

use App\Entity\RankedRaceEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RankedRaceEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RankedRaceEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Événement Ranked Race')
            ->setEntityLabelInPlural('Événements Ranked Race')
            ->setDefaultSort(['startDate' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield DateField::new('startDate', 'Début');
        yield DateField::new('endDate', 'Fin');
        // EasyAdmin 5 détecte l'enumType Doctrine : select SOLO/FLEX au formulaire,
        // nom du case affiché à l'index — pas besoin de setChoices().
        yield ChoiceField::new('queueType', 'File')->renderAsBadges();
        yield IntegerField::new('minGamesToQualify', 'Parties min. (winrate)')
            ->setHelp('Seuil de qualification du classement winrate — ex. 5 pour une semaine, 15 pour un mois.');
    }
}
