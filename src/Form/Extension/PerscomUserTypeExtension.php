<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form\Extension;

use ArniePalau\CcComposite\Form\AppearanceType;
use ArniePalau\CcComposite\Service\SelectionService;
use Forumify\PerscomPlugin\Admin\Form\UserType;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class PerscomUserTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly SelectionService $selectionService)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [UserType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $user = $event->getData();
            if (!$user instanceof PerscomUser || $user->getId() === null) {
                return;
            }

            $event->getForm()->add('ccCompositeAppearance', AppearanceType::class, [
                'mapped' => false,
                'label' => 'Composite uniform and appearance',
                'data' => $this->selectionService->getExplicitLayers($user),
                'perscom_user' => $user,
                'admin_context' => true,
                'inherited_layers' => $this->selectionService->getInheritedLayers($user),
            ]);
        });
    }
}
