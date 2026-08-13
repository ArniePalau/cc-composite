<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form;

use ArniePalau\CcComposite\Entity\CompositeLayer;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Rank;
use Forumify\PerscomPlugin\Perscom\Entity\Unit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CompositeLayerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('allowedRanks', EntityType::class, [
                'class' => Rank::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
                'by_reference' => false,
                'help' => 'Leave every restriction empty to allow everyone. Restrictions use OR logic.',
            ])
            ->add('allowedUnits', EntityType::class, [
                'class' => Unit::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
                'by_reference' => false,
            ])
            ->add('allowedUsers', EntityType::class, [
                'class' => PerscomUser::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompositeLayer::class]);
    }
}
