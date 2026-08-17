<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

final class CcCompositePlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'CC Composite',
            'ArniePalau',
            'Layered PERSCOM uniforms and locally archived mission field reports.',
            'https://github.com/ArniePalau/cc-composite',
            'cc_composite_admin_layers_index',
        );
    }

    public function getPermissions(): array
    {
        return [
            'admin' => [
                'view',
                'manage',
            ],
            'frontend' => [
                'customize',
            ],
        ];
    }
}
