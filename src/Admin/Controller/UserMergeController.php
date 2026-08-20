<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Service\UserMergeService;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('user-merge', name: 'user_merge')]
final class UserMergeController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(
        Request $request,
        UserRepository $userRepository,
        UserMergeService $mergeService
    ): Response {
        $this->denyAccessUnlessGranted('forumify.admin.users.manage');

        $users = $userRepository->findBy([], ['displayName' => 'ASC', 'username' => 'ASC']);
        $ghostUsers = $mergeService->findPendingGhostUsers();

        $preselectSourceId = $request->query->getInt('source', 0);
        $preselectTargetId = $request->query->getInt('target', 0);

        return $this->render('@CcCompositePlugin/admin/users/merge.html.twig', [
            'users' => $users,
            'ghostUsers' => $ghostUsers,
            'preselectSourceId' => $preselectSourceId,
            'preselectTargetId' => $preselectTargetId,
        ]);
    }

    #[Route('/preview', name: '_preview', methods: ['GET'])]
    public function preview(Request $request, UserMergeService $mergeService): JsonResponse
    {
        $this->denyAccessUnlessGranted('forumify.admin.users.manage');

        $sourceId = $request->query->getInt('source', 0);
        $targetId = $request->query->getInt('target', 0);

        $preview = $mergeService->getPreview($sourceId, $targetId);

        return new JsonResponse($preview);
    }

    #[Route('/execute', name: '_execute', methods: ['POST'])]
    public function execute(
        Request $request,
        UserRepository $userRepository,
        UserMergeService $mergeService
    ): Response {
        $this->denyAccessUnlessGranted('forumify.admin.users.manage');

        if (!$this->isCsrfTokenValid('cc-composite-user-merge', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invàlid.');
        }

        $sourceId = (int) $request->request->get('source_user_id', 0);
        $targetId = (int) $request->request->get('target_user_id', 0);

        $sourceUser = $userRepository->find($sourceId);
        $targetUser = $userRepository->find($targetId);

        if (!$sourceUser instanceof User || !$targetUser instanceof User) {
            $this->addFlash('error', 'Heu de seleccionar un usuari d\'origen i un usuari de destí vàlids.');
            return $this->redirectToRoute('cc_composite_admin_user_merge_index');
        }

        if ($sourceUser->getId() === $targetUser->getId()) {
            $this->addFlash('error', 'L\'usuari d\'origen i el de destí no poden ser el mateix.');
            return $this->redirectToRoute('cc_composite_admin_user_merge_index');
        }

        try {
            $result = $mergeService->merge($sourceUser, $targetUser);

            $this->addFlash('success', sprintf(
                'S\'ha unificat l\'usuari "%s" a "%s" amb èxit! S\'han traspassat %d temes, %d missatges/comentaris i %d enllaços de combat.%s',
                $sourceUser->getDisplayName() ?: $sourceUser->getUsername(),
                $targetUser->getDisplayName() ?: $targetUser->getUsername(),
                $result['topics'],
                $result['comments'],
                $result['playerLinks'],
                $result['perscomLinked'] ? ' S\'ha vinculat també la fitxa de soldat de PERSCOM.' : ''
            ));
        } catch (Throwable $e) {
            $this->addFlash('error', 'Error en unificar els usuaris: ' . $e->getMessage());
        }

        return $this->redirectToRoute('cc_composite_admin_user_merge_index');
    }
}
