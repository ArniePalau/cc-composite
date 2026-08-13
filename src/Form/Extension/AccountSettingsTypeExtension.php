<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form\Extension;

use ArniePalau\CcComposite\Form\AppearanceType;
use ArniePalau\CcComposite\Service\CompositeGenerator;
use ArniePalau\CcComposite\Service\SelectionService;
use Forumify\Core\Entity\User;
use Forumify\Forum\Form\AccountSettingsType;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class AccountSettingsTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly PerscomUserRepository $userRepository,
        private readonly SelectionService $selectionService,
        private readonly CompositeGenerator $generator,
        private readonly Security $security,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [AccountSettingsType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            if (!$this->security->isGranted('cc_composite.frontend.customize')) {
                return;
            }

            $perscomUser = $this->findPerscomUser($event->getData());
            if ($perscomUser === null) {
                return;
            }

            $event->getForm()->add('ccCompositeAppearance', AppearanceType::class, [
                'mapped' => false,
                'label' => 'Soldier uniform and appearance',
                'data' => $this->selectionService->getExplicitLayers($perscomUser),
                'perscom_user' => $perscomUser,
                'inherited_layers' => $this->selectionService->getInheritedLayers($perscomUser),
                'help' => 'The generated image includes your selected layers and current award record.',
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->isValid() || !$form->has('ccCompositeAppearance')) {
                return;
            }

            $perscomUser = $this->findPerscomUser($event->getData());
            if ($perscomUser === null) {
                return;
            }

            $selection = $this->selectionService->save(
                $perscomUser,
                $form->get('ccCompositeAppearance')->getData(),
            );
            $this->generator->generate($perscomUser, $selection);
        });
    }

    private function findPerscomUser(mixed $data): ?PerscomUser
    {
        return $data instanceof User ? $this->userRepository->findOneBy(['user' => $data]) : null;
    }
}
