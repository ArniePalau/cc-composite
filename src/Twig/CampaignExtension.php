<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Twig;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CampaignExtension extends AbstractExtension
{
    public function __construct(private readonly FieldReportRepository $fieldReportRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cc_recent_campaign_reports', $this->recentCampaignReports(...)),
        ];
    }

    /** @return list<FieldReport> */
    public function recentCampaignReports(int $limit = 3): array
    {
        return $this->fieldReportRepository->findRecentCampaignReports($limit);
    }
}
