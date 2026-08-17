<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\MenuBuilder;

use Forumify\Core\Entity\MenuItem;
use Forumify\Core\MenuBuilder\MenuType\UrlMenuType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FieldReportsMenuType extends UrlMenuType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getType(): string
    {
        return 'field_reports';
    }

    public function getPayloadFormType(): ?string
    {
        return null;
    }

    protected function getUrl(MenuItem $item): string
    {
        return $this->urlGenerator->generate('cc_composite_field_reports_index');
    }
}
