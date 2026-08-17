<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\Campaign;
use ArniePalau\CcComposite\Form\CampaignType;
use ArniePalau\CcComposite\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('campaigns', name: 'campaigns')]
final class CampaignController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET', 'POST'])]
    public function index(Request $request, CampaignRepository $repository, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        $campaign = new Campaign();
        $form = $this->createForm(CampaignType::class, $campaign);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $campaign->setSlug($this->uniqueSlug($campaign->getName(), $repository, $slugger));
            $entityManager->persist($campaign);
            $entityManager->flush();
            $this->addFlash('success', 'Campaign created.');

            return $this->redirectToRoute('cc_composite_admin_campaigns_index');
        }

        return $this->render('@CcCompositePlugin/admin/campaigns/index.html.twig', [
            'campaigns' => $repository->findBy([], ['name' => 'ASC']),
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Campaign $campaign, Request $request, CampaignRepository $repository, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        $form = $this->createForm(CampaignType::class, $campaign);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $campaign->setSlug($this->uniqueSlug($campaign->getName(), $repository, $slugger, $campaign));
            $entityManager->flush();
            $this->addFlash('success', 'Campaign updated.');

            return $this->redirectToRoute('cc_composite_admin_campaigns_index');
        }

        return $this->render('@CcCompositePlugin/admin/campaigns/edit.html.twig', ['campaign' => $campaign, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['POST'])]
    public function delete(Campaign $campaign, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-campaign-delete-' . $campaign->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $entityManager->remove($campaign);
        $entityManager->flush();
        $this->addFlash('success', 'Campaign deleted. Assigned reports were kept and unassigned.');

        return $this->redirectToRoute('cc_composite_admin_campaigns_index');
    }

    private function uniqueSlug(string $name, CampaignRepository $repository, SluggerInterface $slugger, ?Campaign $current = null): string
    {
        $base = strtolower((string) $slugger->slug($name));
        $base = $base !== '' ? $base : 'campaign';
        $slug = $base;
        $suffix = 2;
        while (($existing = $repository->findOneBy(['slug' => $slug])) !== null && $existing !== $current) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
