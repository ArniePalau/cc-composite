<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form;

use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AppearanceType extends AbstractType
{
    public function __construct(
        private readonly CompositeLayerRepository $layerRepository,
        private readonly Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var PerscomUser|null $user */
        $user = $options['perscom_user'];
        $adminContext = $options['admin_context'];
        $canOverride = $adminContext && $this->security->isGranted('cc_composite.admin.manage');

        foreach (LayerCategory::cases() as $category) {
            $choices = [$options['empty_label'] => ''];
            $choiceAttributes = [];

            foreach ($this->layerRepository->findForCategory($category) as $layer) {
                $allowed = $user === null || $layer->isAllowedFor($user);
                if (!$allowed && !$adminContext) {
                    continue;
                }

                $label = $layer->getFilename() . ($allowed ? '' : ' (locked)');
                $choices[$label] = $layer->getFilename();
                if (!$allowed && !$canOverride) {
                    $choiceAttributes[$label] = ['disabled' => 'disabled'];
                }
            }

            $builder->add($category->value, ChoiceType::class, [
                'choices' => $choices,
                'choice_attr' => $choiceAttributes,
                'required' => false,
                'label' => $category->label(),
            ]);
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['inherited_layers'] = (object) $options['inherited_layers'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'perscom_user' => null,
            'admin_context' => false,
            'inherited_layers' => [],
            'empty_label' => 'Inherit default',
        ]);
        $resolver->setAllowedTypes('perscom_user', ['null', PerscomUser::class]);
        $resolver->setAllowedTypes('admin_context', 'bool');
        $resolver->setAllowedTypes('inherited_layers', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'cc_composite_appearance';
    }
}
