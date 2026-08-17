<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form;

use ArniePalau\CcComposite\Entity\Campaign;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', options: ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('image', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Campaign cover image',
                'help' => 'JPEG, PNG or WebP, up to 10 MB. Wide landscape images work best.',
                'constraints' => [new Assert\Image(maxSize: '10M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Campaign::class]);
    }
}
