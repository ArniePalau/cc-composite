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
                    return $campaignName . $report->getMissionName() . ' (' . $report->getStartedAt()->format('d/m/Y H:i') . ' · ' . $report->getCode() . ')';
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
                'help' => $isNew ? 'Si pugeu múltiples fotos, s\'aplicarà com a títol base numerat (ex: Assalt #1, Assalt #2).' : null,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Ordre de visualització',
                'required' => false,
                'data' => $galleryImage?->getPosition() ?? 0,
                'help' => 'Les fotos amb un número més baix es mostren primer.',
            ]);

        if ($isNew) {
            $builder->add('images', FileType::class, [
                'label' => 'Fitxers d\'imatge (podeu seleccionar fins a 15 fotos)',
                'mapped' => false,
                'multiple' => true,
                'required' => true,
                'attr' => [
                    'multiple' => 'multiple',
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Seleccioneu almenys una imatge.'),
                    new Assert\Count(
                        min: 1,
                        max: 15,
                        minMessage: 'Seleccioneu almenys una fotografia.',
                        maxMessage: 'Podeu pujar un màxim de 15 fotografies a la vegada.'
                    ),
                    new Assert\All([
                        new Assert\Image(
                            maxSize: '25M',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                            mimeTypesMessage: 'Tots els fitxers han de ser imatges vàlides (JPG, PNG, WEBP o GIF).'
                        ),
                    ]),
                ],
                'help' => 'Podeu seleccionar entre 1 i 15 imatges simultàniament (JPG, PNG, WEBP). S\'optimitzaran automàticament.',
            ]);
        } else {
            $builder->add('image', FileType::class, [
                'label' => 'Substituir fitxer d\'imatge (opcional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Image(
                        maxSize: '25M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'El fitxer ha de ser una imatge vàlida (JPG, PNG, WEBP o GIF).'
                    ),
                ],
                'help' => 'Deixeu-ho en blanc si voleu mantenir la fotografia existent.',
            ]);
        }
    }
}
