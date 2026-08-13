<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\MenuBuilder;

use Forumify\Admin\MenuBuilder\AdminMenuBuilderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompositeMenuBuilder implements AdminMenuBuilderInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(Menu $menu): void
    {
        $composite = new Menu('CC Composite', [
            'icon' => 'ph ph-images-square',
            'permission' => 'cc_composite.admin.view',
        ], [
            new MenuItem('Layers', $this->urlGenerator->generate('cc_composite_admin_layers_index'), [
                'icon' => 'ph ph-stack',
                'permission' => 'cc_composite.admin.manage',
            ]),
            new MenuItem('Defaults', $this->urlGenerator->generate('cc_composite_admin_defaults_edit'), [
                'icon' => 'ph ph-sliders-horizontal',
                'permission' => 'cc_composite.admin.manage',
            ]),
            new MenuItem('Award layout', $this->urlGenerator->generate('cc_composite_admin_awards_index'), [
                'icon' => 'ph ph-medal',
                'permission' => 'cc_composite.admin.manage',
            ]),
        ]);

        $menu->addItem($composite);
    }
}
