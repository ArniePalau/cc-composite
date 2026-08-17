<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form;

use ArniePalau\CcComposite\Entity\Campaign;
use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Entity\GalleryImage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GalleryImageType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GalleryImage::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var GalleryImage|null $galleryImage */
        $galleryImage = $options['data'] ?? null;
        $isNew = $galleryImage === null || $galleryImage->getId() === null;

        $builder
            ->add('campaign', EntityType::class, [
                'class' => Campaign::class,
                'choice_label' => 'name',
                'label' => 'Campanya',
                'required' => false,
                'placeholder' => '— Selecciona una campanya —',
                'help' => 'Campanya a la qual pertany aquesta fotografia.',
            ])
            ->add('fieldReport', EntityType::class, [
                'class' => FieldReport::class,
                'choice_label' => static function (FieldReport $report): string {
                    $campaignName = $report->getCampaign() ? '[' . $report->getCampaign()->getName() . '] ' : '';
                    return $campaignName . $report->getMissionName() . ' (' . $report->getStartedAt()->format('d/m/Y') . ')';
                },
                'label' => 'Missió / Informe de combat (opcional)',
                'required' => false,
                'placeholder' => '— Cap missió específica —',
                'help' => 'Si es selecciona, la foto també apareixerà a la fitxa d\'aquesta missió.',
            ])
            ->add('title', TextType::class, [
                'label' => 'Títol o peu de foto',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Assalt a l\'aeròdrom d\'Altis'],
            ])
            ->add('image', FileType::class, [
                'label' => 'Fitxer d\'imatge (JPG, PNG, WEBP)',
                'mapped' => false,
                'required' => $isNew,
                'constraints' => [
                    ...($isNew ? [new Assert\NotBlank(message: 'Seleccioneu una imatge.')] : []),
                    new Assert\Image(
                        maxSize: '25M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'El fitxer ha de ser una imatge vàlida (JPG, PNG, WEBP o GIF).'
                    ),
                ],
                'help' => 'S\'optimitzarà i es convertirà automàticament per a una càrrega ultraràpida.',
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Ordre de visualització',
                'required' => false,
                'data' => $galleryImage?->getPosition() ?? 0,
                'help' => 'Les fotos amb un número més baix es mostren primer.',
            ])
        ;
    }
}
